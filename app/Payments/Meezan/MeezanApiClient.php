<?php

declare(strict_types=1);

namespace App\Payments\Meezan;

use App\Core\Logger;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Http\HttpClientInterface;
use App\Payments\Http\HttpResult;

/**
 * Thin transport for Meezan Bank's hosted payment API (register.do /
 * getOrderStatus.do).
 *
 * The documented legacy API transmits credentials as GET parameters. Those
 * URLs are therefore never logged or surfaced in exceptions: every failure
 * recorded here is described by endpoint name and outcome only.
 */
final class MeezanApiClient
{
    public function __construct(
        private HttpClientInterface $http,
        private Logger $logger,
        private array $config
    ) {
    }

    private function baseUrl(): string
    {
        $env = $this->config['environment'] ?? 'sandbox';
        $url = $env === 'live'
            ? (string) ($this->config['live_url'] ?? '')
            : (string) ($this->config['sandbox_url'] ?? '');

        $url = rtrim($url, '/');
        if ($url === '') {
            throw new PaymentGatewayException(
                'Meezan API base URL is not configured for the ' . $env . ' environment.',
                'meezan',
                'not_configured'
            );
        }

        return $url;
    }

    private function credentials(): array
    {
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new PaymentGatewayException(
                'Meezan merchant credentials are not configured.',
                'meezan',
                'not_configured'
            );
        }

        return ['userName' => $username, 'password' => $password];
    }

    private function timeout(): int
    {
        return max(5, (int) ($this->config['timeout'] ?? 30));
    }

    /**
     * Register an order. Returns the decoded provider response.
     *
     * @param array<string,string|int> $fields
     * @return array<string,mixed>
     */
    public function register(array $fields): array
    {
        return $this->call('register.do', $fields);
    }

    /**
     * @return array<string,mixed>
     */
    public function orderStatus(string $providerOrderId): array
    {
        return $this->call('getOrderStatus.do', ['orderId' => $providerOrderId]);
    }

    /**
     * Refund endpoint. Availability depends on the merchant agreement, so this
     * is only called when refunds are explicitly enabled in settings.
     *
     * @param array<string,string|int> $fields
     * @return array<string,mixed>
     */
    public function refund(array $fields): array
    {
        return $this->call('refund.do', $fields);
    }

    /**
     * @param array<string,string|int> $fields
     * @return array<string,mixed>
     */
    private function call(string $endpoint, array $fields = []): array
    {
        $url = $this->baseUrl() . '/' . $endpoint;
        $query = array_merge($this->credentials(), $fields);

        $result = $this->http->get($url, $query, [
            'timeout' => $this->timeout(),
            'connect_timeout' => min(10, $this->timeout()),
        ]);

        $this->logger->payment('Meezan API call completed', [
            'endpoint' => $endpoint,
            'outcome'  => $result->describe(),
            'status'   => $result->statusCode,
        ]);

        return $this->decode($endpoint, $result);
    }

    /** @return array<string,mixed> */
    private function decode(string $endpoint, HttpResult $result): array
    {
        if ($result->failed()) {
            throw new PaymentGatewayException(
                'Meezan request failed: ' . ($result->error ?? 'transport error'),
                'meezan',
                'transport_error'
            );
        }

        if ($result->statusCode >= 500) {
            throw new PaymentGatewayException(
                'Meezan returned a server error (HTTP ' . $result->statusCode . ').',
                'meezan',
                'provider_error',
                $result->statusCode
            );
        }

        if (!$result->isJson()) {
            throw new PaymentGatewayException(
                'Meezan returned a response that was not valid JSON.',
                'meezan',
                'invalid_json',
                $result->statusCode
            );
        }

        $decoded = $result->json();
        if ($decoded === []) {
            throw new PaymentGatewayException(
                'Meezan returned an empty JSON object.',
                'meezan',
                'empty_response',
                $result->statusCode
            );
        }

        return $decoded;
    }

    public function describe(): string
    {
        $env = (string) ($this->config['environment'] ?? 'sandbox');
        $host = \App\Core\Str::host($this->config[$env === 'live' ? 'live_url' : 'sandbox_url'] ?? '');
        return sprintf('Meezan (%s, %s)', $env, $host ?? 'unset');
    }
}
