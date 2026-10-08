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
            throw new PaymentGatewayException(
                'Etisalat returned a response that was not valid JSON.',
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
        if (isset($response['Error']) && is_array($response['Error'])) {
            $error = $response['Error'];
            return [
                'block'   => [],
                'code'    => isset($error['Response']) ? (string) $error['Response'] : null,
                'message' => isset($error['Description']) ? (string) $error['Description']
                    : (isset($error['Message']) ? (string) $error['Message'] : 'Provider reported an error.'),
            ];
        }

        // Case-insensitive lookup of the operation block.
        foreach ($response as $key => $value) {
            if (strcasecmp((string) $key, $operation) === 0 && is_array($value)) {
                $code = $value['Response'] ?? $value['response'] ?? $value['Status'] ?? $value['status'] ?? null;
                $message = $value['Description'] ?? $value['description'] ?? $value['Message'] ?? $value['message'] ?? null;
                return [
                    'block'   => $value,
                    'code'    => $code === null ? null : (string) $code,
                    'message' => $message === null ? null : (string) $message,
                ];
            }
        }

        return ['block' => [], 'code' => null, 'message' => 'Provider response did not contain a ' . $operation . ' block.'];
    }

    public function describe(): string
    {
        $env = (string) ($this->config['environment'] ?? 'sandbox');
        $host = \App\Core\Str::host($this->config[$env === 'live' ? 'live_url' : 'sandbox_url'] ?? '');
        return sprintf('Etisalat EPG (%s, %s)', $env, $host ?? 'unset');
    }
}
