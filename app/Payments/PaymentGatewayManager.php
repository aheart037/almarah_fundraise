<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Database;
use App\Core\Logger;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\Etisalat\EtisalatApiClient;
use App\Payments\Etisalat\EtisalatGateway;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Http\CurlHttpClient;
use App\Payments\Http\HttpClientInterface;
use App\Payments\Meezan\MeezanApiClient;
use App\Payments\Meezan\MeezanGateway;
use App\Services\SettingsService;

/**
 * Resolves gateway implementations and persists their registry rows.
 *
 * Callers (controllers, donation service) depend only on this manager and the
 * gateway contract — no provider-specific code exists outside the gateway
 * classes.
 */
final class PaymentGatewayManager
{
    /** @var array<string,PaymentGatewayInterface> */
    private array $resolved = [];

    private ?HttpClientInterface $httpClient = null;

    public function __construct(
        private SettingsService $settings,
        private Database $db,
        private Logger $logger,
        private ?PaymentStatusMapper $statusMapper = null,
        private string $baseUrl = '',
        ?HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient;
        $this->statusMapper = $statusMapper ?? new PaymentStatusMapper();
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : (string) \App\Core\Config::get('app.url', ''), '/');

        // The gateway is given an absolute return URL. On a folder install
        // (https://host/donate) that folder must be in it, or the bank would
        // send the donor to a page that does not exist.
        $basePath = \App\Core\BasePath::get();
        if ($basePath !== '' && !str_ends_with($this->baseUrl, $basePath)) {
            $this->baseUrl .= $basePath;
        }
    }

    /** Test seam: inject a fake transport so gateway logic can be exercised offline. */
    public function setHttpClient(HttpClientInterface $client): void
    {
        $this->httpClient = $client;
        $this->resolved = [];
    }

    private function http(): HttpClientInterface
    {
        if ($this->httpClient instanceof HttpClientInterface) {
            return $this->httpClient;
        }

        $timeout = (int) (\App\Core\Config::get('payments.gateways.meezan.timeout', 30));
        $this->httpClient = new CurlHttpClient($this->logger, $timeout, 10);

        return $this->httpClient;
    }

    public function statusMapper(): PaymentStatusMapper
    {
        return $this->statusMapper;
    }

    /** @return array<int,string> */
    public function availableCodes(): array
    {
        return ['meezan', 'etisalat'];
    }

    public function gateway(string $code): PaymentGatewayInterface
    {
        $code = strtolower(trim($code));

        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $config = $this->settings->gatewayConfig($code);
        if ($config === []) {
            throw new PaymentGatewayException("Unknown payment gateway [{$code}].", $code, 'unknown_gateway');
        }

        $gateway = match ($code) {
            'meezan'   => new MeezanGateway(
                new MeezanApiClient($this->http(), $this->logger, $config),
                $this->statusMapper,
                $this->logger,
                $config,
                $this->baseUrl
            ),
            'etisalat' => new EtisalatGateway(
                new EtisalatApiClient($this->http(), $this->logger, $config),
                $this->statusMapper,
                $this->logger,
                $config,
                $this->baseUrl
            ),
            default    => throw new PaymentGatewayException("Gateway [{$code}] has no implementation.", $code, 'unsupported'),
        };

        $this->resolved[$code] = $gateway;
        return $gateway;
    }

    /**
     * Gateways a donor may currently choose: enabled *and* fully configured.
     *
     * @return array<string,PaymentGatewayInterface>
     */
    public function enabledGateways(): array
    {
        $out = [];
        foreach ($this->availableCodes() as $code) {
            try {
                $gateway = $this->gateway($code);
                if ($gateway->isEnabled() && $gateway->isConfigured()) {
                    $out[$code] = $gateway;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Gateway unavailable', ['gateway' => $code, 'reason' => $e->getMessage()]);
            }
        }
        return $out;
    }

    /** True when at least one gateway can take a payment right now. */
    public function hasUsableGateway(): bool
    {
        return $this->enabledGateways() !== [];
    }

    /** Database id for a gateway code, creating the registry row when missing. */
    public function gatewayId(string $code): int
    {
        $code = strtolower($code);

        $id = $this->db->scalar('SELECT id FROM gateways WHERE code = :c', ['c' => $code]);
        if ($id !== null) {
            return (int) $id;
        }

        $label = match ($code) {
            'meezan'   => 'Meezan Bank',
            'etisalat' => 'Etisalat / UBL EPG',
            default    => ucfirst($code),
        };

        return $this->db->insert('gateways', [
            'code'       => $code,
            'name'       => $label,
            'enabled'    => 1,
            'sort_order' => 0,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function registry(): array
    {
        return $this->db->select('SELECT * FROM gateways ORDER BY sort_order, id');
    }

    /**
     * Health snapshot for the admin dashboard. Never returns credentials —
     * only whether they are present.
     *
     * @return array<string,array<string,mixed>>
     */
    public function health(): array
    {
        $out = [];

        foreach ($this->availableCodes() as $code) {
            $config = $this->settings->gatewayConfig($code);

            $out[$code] = [
                'code'        => $code,
                'label'       => (string) ($config['label'] ?? ucfirst($code)),
                'enabled'     => (bool) ($config['enabled'] ?? false),
                'environment' => (string) ($config['environment'] ?? 'sandbox'),
                'configured'  => (string) ($config['username'] ?? '') !== ''
                    && (string) ($config['password'] ?? '') !== ''
                    && (string) ($config['sandbox_url'] ?? '') !== '',
                'has_username'=> (string) ($config['username'] ?? '') !== '',
                'has_password'=> (string) ($config['password'] ?? '') !== '',
                'currency'    => (string) ($config['currency_name'] ?? $config['currency'] ?? 'PKR'),
                'supports_refunds' => (bool) ($config['refund_enabled'] ?? $code === 'etisalat'),
                'host'        => \App\Core\Str::host((string) ($config[($config['environment'] ?? 'sandbox') === 'live' ? 'live_url' : 'sandbox_url'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Presentation metadata for the donate page.
     *
     * @return array<int,array{code:string,label:string,description:string}>
     */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->enabledGateways() as $code => $gateway) {
            $config = $this->settings->gatewayConfig($code);
            $choices[] = [
                'code'        => $code,
                'label'       => $gateway->label(),
                'description' => (string) ($config['description'] ?? ''),
            ];
        }
        return $choices;
    }
}
