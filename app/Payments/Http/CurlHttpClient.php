<?php

declare(strict_types=1);

namespace App\Payments\Http;

use App\Core\Logger;

/**
 * cURL transport with TLS verification always enabled, redirects disabled
 * (so a gateway can never bounce us somewhere unexpected) and bounded
 * timeouts.
 *
 * Request URLs are never logged verbatim because Meezan's legacy API transmits
 * credentials as query parameters; only method, host and outcome are recorded.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(
        private Logger $logger,
        private int $defaultTimeout = 30,
        private int $connectTimeout = 10
    ) {
    }

    public function get(string $url, array $query = [], array $options = []): HttpResult
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $this->request('GET', $url, null, $options);
    }

    public function postJson(string $url, array $payload, array $options = []): HttpResult
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return $this->request('POST', $url, $body === false ? '{}' : $body, $options + [
            'content_type' => 'application/json',
        ]);
    }

    public function postForm(string $url, array $fields, array $options = []): HttpResult
    {
        return $this->request('POST', $url, http_build_query($fields), $options + [
            'content_type' => 'application/x-www-form-urlencoded',
        ]);
    }

    /** @param array<string,mixed> $options */
    private function request(string $method, string $url, ?string $body, array $options): HttpResult
    {
        $ch = curl_init();

        $timeout = (int) ($options['timeout'] ?? $this->defaultTimeout);
        $connectTimeout = (int) ($options['connect_timeout'] ?? $this->connectTimeout);

        $headers = array_merge(
            ['Accept: application/json'],
            (array) ($options['headers'] ?? [])
        );

        if (isset($options['content_type'])) {
            $headers[] = 'Content-Type: ' . $options['content_type'];
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => max(1, $connectTimeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'AlmarahFoundation/1.0',
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTPS,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        if ($raw === false || $errno !== 0) {
            // Only host + path are logged — never the query string.
            $this->logger->warning('Gateway HTTP request failed', [
                'method' => $method,
                'host'   => $host,
                'path'   => $path,
                'errno'  => $errno,
                'error'  => $error,
            ]);

            return new HttpResult(0, '', [], $this->normaliseError($errno, $error));
        }

        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $responseBody = substr((string) $raw, $headerSize);

        return new HttpResult(
            $statusCode,
            $responseBody,
            $this->parseHeaders((string) $rawHeaders),
            null,
            null
        );
    }

    /** @return array<string,string> */
    private function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'HTTP/')) {
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        return $headers;
    }

    private function normaliseError(int $errno, string $error): string
    {
        $map = [
            CURLE_OPERATION_TIMEDOUT     => 'Request timed out',
            CURLE_COULDNT_CONNECT        => 'Could not connect to the payment provider',
            CURLE_SSL_CACERT             => 'TLS certificate verification failed',
            CURLE_SSL_CONNECT_ERROR      => 'TLS connection failed',
            CURLE_COULDNT_RESOLVE_HOST   => 'Could not resolve the payment provider host',
            CURLE_PEER_FAILED_VERIFICATION => 'TLS peer verification failed',
        ];

        return $map[$errno] ?? ('Transport error: ' . mb_substr($error, 0, 120));
    }
}
