<?php

declare(strict_types=1);

namespace App\Payments\Etisalat;

use App\Core\Logger;
use App\Core\Str;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Http\HttpClientInterface;
use App\Payments\Http\HttpResult;

/**
 * Transport for the Etisalat / UBL EPG REST integration.
 *
 * The bank-provided WooCommerce plugin sends a JSON operation envelope to the
 * gateway host on port 2443 (no /epg/rest path), with
 * Content-Type: application/json and Accept: text/xml-standard-api. Credentials
 * are sent only in the JSON body. TLS peer and hostname verification are kept
 * enabled; request bodies and credentials are never logged.
 */
final class EtisalatApiClient
{
    public const OP_REGISTRATION = 'Registration';
    public const OP_FINALIZATION = 'Finalization';
    public const OP_REFUND       = 'Refund';

    private const DEFAULT_PORT = 2443;
    private const DEFAULT_ACCEPT = 'text/xml-standard-api';

    public function __construct(
        private HttpClientInterface $http,
        private Logger $logger,
        private array $config
    ) {
    }

    /**
     * Return the actual API origin used by the bank plugin: HTTPS host:port,
     * with no REST path. The old application's /epg/rest default is normalized
     * so existing installations do not need to rewrite their saved setting.
     */
    public function baseUrl(): string
    {
        $environment = (string) ($this->config['environment'] ?? 'sandbox');
        $configured = $environment === 'live'
            ? (string) ($this->config['live_url'] ?? '')
            : (string) ($this->config['sandbox_url'] ?? '');
        $configured = trim($configured);
        $parts = $configured !== '' ? parse_url($configured) : false;

        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new PaymentGatewayException(
                'Etisalat EPG must be configured with an HTTPS gateway host and optional port.',
                'etisalat',
                'invalid_endpoint'
            );
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if (strcasecmp($path, '/epg/rest') === 0) {
            // Compatibility with the incorrect path used by the earlier PHP
            // implementation; the supplied WooCommerce client posts to root.
            $path = '';
        }
        if ($path !== '' && $path !== '/') {
            throw new PaymentGatewayException(
                'Etisalat EPG expects its API URL to be the gateway host, without an API path.',
                'etisalat',
                'invalid_endpoint_path'
            );
        }

        // Preserve an explicit port from a saved URL; host-only configuration
        // uses the separate merchant port setting (2443 by default).
        $port = isset($parts['port'])
            ? (int) $parts['port']
            : (int) ($this->config['port'] ?? self::DEFAULT_PORT);
        if ($port < 1 || $port > 65535) {
            throw new PaymentGatewayException(
                'Etisalat EPG API port must be between 1 and 65535.',
                'etisalat',
                'invalid_endpoint_port'
            );
        }

        $host = (string) $parts['host'];
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        return 'https://' . $host . ':' . $port;
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
        $accept = trim((string) ($this->config['accept_header'] ?? self::DEFAULT_ACCEPT));
        if (!in_array($accept, ['text/xml-standard-api', 'application/json'], true)) {
            throw new PaymentGatewayException(
                'Etisalat Accept header must be text/xml-standard-api or application/json.',
                'etisalat',
                'invalid_accept_header'
            );
        }

        $tls12 = defined('CURL_SSLVERSION_TLSv1_2')
            ? (int) constant('CURL_SSLVERSION_TLSv1_2')
            : 6; // cURL's documented numeric value for TLS 1.2.

        $result = $this->http->postJson($url, $payload, [
            'timeout'         => $this->timeout(),
            'connect_timeout' => min(10, $this->timeout()),
            'tls_version'     => $tls12,
            'forbid_reuse'    => true,
            'headers'         => [
                'Accept: ' . $accept,
            ],
        ]);

        $host = Str::host($url) ?? 'unset';
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

        if ($result->statusCode === 404) {
            throw new PaymentGatewayException(
                'Etisalat returned HTTP 404. Check the configured gateway host and API port; the bank integration uses the host root, not /epg/rest.',
                'etisalat',
                'endpoint_not_found',
                $result->statusCode
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

        if (!$result->successful()) {
            throw new PaymentGatewayException(
                'The bank verification endpoint returned HTTP ' . $result->statusCode . '.',
                'etisalat', 'http_error', $result->statusCode
            );
        }

        if (!$result->isJson()) {
            throw new PaymentGatewayException(
                'Etisalat returned a non-JSON response for ' . $operation . ' (HTTP ' . $result->statusCode . ').',
                'etisalat',
                'invalid_json',
                $result->statusCode
            );
        }

        $decoded = $result->json();
        if ($decoded === []) {
            throw new PaymentGatewayException(
                'Etisalat returned an empty JSON response.',
                'etisalat',
                'empty_response',
                $result->statusCode
            );
        }

        return $decoded;
    }

    /**
     * Extract the Transaction object and its documented response fields.
     * The EPG response normally wraps fields in Transaction; a root-level
     * Transaction response is tolerated for older merchant configurations.
     *
     * @param array<string,mixed> $response
     * @return array{block:array<string,mixed>, code:?string, message:?string}
     */
    public function extract(string $operation, array $response): array
    {
        foreach ($response as $key => $value) {
            if (strcasecmp((string) $key, 'Error') === 0 && is_array($value)) {
                return $this->extractBlock($value);
            }
        }

        $block = null;
        foreach ($response as $key => $value) {
            if (strcasecmp((string) $key, 'Transaction') === 0 && is_array($value)) {
                $block = $value;
                break;
            }
        }

        // Some versions return the Transaction fields at the root. Accept only
        // the documented ResponseCode field; do not guess from a generic
        // "status" or "response" field.
        if ($block === null && $this->hasCaseInsensitiveKey($response, 'ResponseCode')) {
            $block = $response;
        }

        if ($block === null) {
            return [
                'block' => [],
                'code' => null,
                'message' => 'Etisalat response did not contain a Transaction or ResponseCode.',
            ];
        }

        return $this->extractBlock($block);
    }

    /** @param array<string,mixed> $block @return array{block:array<string,mixed>, code:?string, message:?string} */
    private function extractBlock(array $block): array
    {
        $fields = array_change_key_case($block, CASE_LOWER);
        $rawCode = $fields['responsecode'] ?? null;
        $code = null;
        if (is_scalar($rawCode)) {
            $candidate = trim((string) $rawCode);
            if ($candidate !== '' && ctype_digit($candidate)) {
                $code = $candidate;
            }
        }

        $rawMessage = $fields['responsedescription'] ?? $fields['responseclassdescription'] ?? null;
        $message = is_scalar($rawMessage) ? trim((string) $rawMessage) : null;
        if ($message === '') {
            $message = null;
        }

        if ($code === null && $message === null) {
            $message = 'Etisalat response did not contain a numeric ResponseCode.';
        }

        return [
            'block'   => $block,
            'code'    => $code,
            'message' => $message,
        ];
    }

    /** @param array<string,mixed> $array */
    private function hasCaseInsensitiveKey(array $array, string $needle): bool
    {
        foreach (array_keys($array) as $key) {
            if (strcasecmp((string) $key, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    public function describe(): string
    {
        $environment = (string) ($this->config['environment'] ?? 'sandbox');
        $urlKey = $environment === 'live' ? 'live_url' : 'sandbox_url';
        $host = Str::host((string) ($this->config[$urlKey] ?? ''));

        return sprintf('Etisalat EPG (%s, %s)', $environment, $host ?? 'unset');
    }
}
