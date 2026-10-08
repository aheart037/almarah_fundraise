<?php

declare(strict_types=1);

namespace App\Payments\Etisalat;

use App\Core\Logger;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Http\HttpClientInterface;
use App\Payments\Http\HttpResult;

/**
 * UBL EPG REST transport (Etisalat).
 *
 * All operations authenticate with UserName/Password inside the JSON body, as
 * specified by the EPG REST guide. There is no provider HMAC/signature field
 * for these operations, so payloads are never logged and the body is never
 * echoed into an exception.
 */
final class EtisalatApiClient
{
    public const OP_REGISTRATION  = 'Registration';
    public const OP_FINALIZATION  = 'Finalization';
    public const OP_REFUND        = 'Refund';

    public function __construct(
        private HttpClientInterface $http,
        private Logger $logger,
        private array $config
    ) {
    }

    public function baseUrl(): string
    {
        $env = $this->config['environment'] ?? 'sandbox';
        $url = $env === 'live'
            ? (string) ($this->config['live_url'] ?? '')
            : (string) ($this->config['sandbox_url'] ?? '');

        $url = rtrim($url, '/');
        if ($url === '') {
            throw new PaymentGatewayException(
                'Etisalat EPG base URL is not configured for the ' . $env . ' environment.',
                'etisalat',
                'not_configured'
            );
        }

        // Appendix B's REST endpoint is /epg/rest. Older saved settings and
        // .env files may contain only the host; preserve an explicit REST or
        // reverse-proxy path, but complete a host-only URL automatically.
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            $url .= '/epg/rest';
        }

        return $url;
    }

    private function timeout(): int
    {
        return max(5, (int) ($this->config['timeout'] ?? 30));
    }

    /**
     * Send an operation envelope, e.g. ['Registration' => [...]].
     *
     * @param array<string,array<string,string>> $payload
     * @return array<string,mixed> decoded provider response
     */
    public function send(string $operation, array $payload): array
    {
        $url = $this->baseUrl();

        $result = $this->http->postJson($url, $payload, [
            'timeout'         => $this->timeout(),
            'connect_timeout' => min(10, $this->timeout()),
            'headers'         => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $host = \App\Core\Str::host($url) ?? 'unset';

        $this->logger->payment('Etisalat API call completed', [
            'operation' => $operation,
            'host'      => $host,
            'outcome'   => $result->describe(),
            'status'    => $result->statusCode,
        ]);

        return $this->decode($operation, $result);
    }

    /** @return array<string,mixed> */
    private function decode(string $operation, HttpResult $result): array
    {
        if ($result->failed()) {
            throw new PaymentGatewayException(
                'Etisalat request failed: ' . ($result->error ?? 'transport error'),
                'etisalat',
                'transport_error'
            );
        }

        if ($result->statusCode >= 500) {
            throw new PaymentGatewayException(
                'Etisalat returned a server error (HTTP ' . $result->statusCode . ').',
                'etisalat',
                'provider_error',
                $result->statusCode
            );
        }

        if (!$result->isJson()) {
            if ($result->statusCode === 404) {
                throw new PaymentGatewayException(
                    'Etisalat returned HTTP 404. Check that the selected sandbox/live REST URL points to the UBL EPG /epg/rest endpoint.',
                    'etisalat',
                    'endpoint_not_found',
                    $result->statusCode
                );
            }

            throw new PaymentGatewayException(
                'Etisalat returned a non-JSON response (HTTP ' . $result->statusCode . ').',
                'etisalat',
                'invalid_json',
                $result->statusCode
            );
        }

        $decoded = $result->json();
        if ($decoded === []) {
            throw new PaymentGatewayException(
                'Etisalat returned an empty JSON object.',
                'etisalat',
                'empty_response',
                $result->statusCode
            );
        }

        return $decoded;
    }

    /**
     * Extract the operation block and its response code from a provider reply.
     *
     * @param array<string,mixed> $response
     * @return array{block:array<string,mixed>, code:?string, message:?string}
     */
    public function extract(string $operation, array $response): array
    {
        foreach ($response as $key => $value) {
            if (strcasecmp((string) $key, 'Error') === 0 && is_array($value)) {
                $error = array_change_key_case($value, CASE_LOWER);
                $code = $error['responsecode'] ?? $error['response'] ?? $error['status'] ?? null;
                $message = $error['responsedescription'] ?? $error['description'] ?? $error['message'] ?? null;

                return [
                    'block'   => [],
                    'code'    => is_scalar($code) ? (string) $code : null,
                    'message' => is_scalar($message) ? (string) $message : 'Provider reported an error.',
                ];
            }
        }

        // The EPG guide wraps Registration, Finalization and Refund responses
        // in a shared Transaction object (not in the request operation name).
        // Continue accepting operation-specific blocks for older EPG versions.
        $block = null;
        foreach ($response as $key => $value) {
            if (strcasecmp((string) $key, $operation) === 0 && is_array($value)) {
                $block = $value;
                break;
            }
        }
        if ($block === null) {
            foreach ($response as $key => $value) {
                if (strcasecmp((string) $key, 'Transaction') === 0 && is_array($value)) {
                    $block = $value;
                    break;
                }
            }
        }

        if ($block === null && (isset($response['ResponseCode']) || isset($response['Response']))) {
            $block = $response;
        }
        if ($block === null) {
            return ['block' => [], 'code' => null, 'message' => 'Provider response did not contain a Transaction block.'];
        }

        $fields = array_change_key_case($block, CASE_LOWER);
        $code = $fields['responsecode'] ?? $fields['response'] ?? $fields['status'] ?? null;
        $message = $fields['responsedescription'] ?? $fields['description'] ?? $fields['message'] ?? null;

        return [
            'block'   => $block,
            'code'    => is_scalar($code) ? (string) $code : null,
            'message' => is_scalar($message) ? (string) $message : null,
        ];
    }

    public function describe(): string
    {
        $env = (string) ($this->config['environment'] ?? 'sandbox');
        $host = \App\Core\Str::host($this->config[$env === 'live' ? 'live_url' : 'sandbox_url'] ?? '');
        return sprintf('Etisalat EPG (%s, %s)', $env, $host ?? 'unset');
    }
}
