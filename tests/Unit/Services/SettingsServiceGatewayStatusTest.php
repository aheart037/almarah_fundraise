<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class SettingsServiceGatewayStatusTest extends TestCase
{
    public function testEtisalatStatusListsMissingMerchantFieldsWithoutExposingSecrets(): void
    {
        $service = $this->service([
            'enabled'     => true,
            'environment' => 'sandbox',
            'customer'    => '',
            'username'    => 'merchant-user',
            'password'    => 'merchant-password',
            'sandbox_url' => 'https://sandbox.example.test',
            'live_url'    => 'https://live.example.test',
        ]);

        $status = $service->gatewayStatus()['etisalat'];

        self::assertTrue($status['enabled']);
        self::assertFalse($status['configured']);
        self::assertSame(['Customer ID'], $status['missing']);
        self::assertArrayNotHasKey('password', $status);
    }

    public function testConfiguredEtisalatCanRemainDisabledUntilPaymentsAreEnabled(): void
    {
        $service = $this->service([
            'enabled'     => false,
            'environment' => 'live',
            'customer'    => 'merchant-customer',
            'username'    => 'merchant-user',
            'password'    => 'merchant-password',
            'sandbox_url' => 'https://sandbox.example.test',
            'live_url'    => 'https://live.example.test',
        ]);

        $status = $service->gatewayStatus()['etisalat'];

        self::assertFalse($status['enabled']);
        self::assertTrue($status['configured']);
        self::assertSame('live', $status['environment']);
        self::assertSame([], $status['missing']);
    }

    /** @param array<string,mixed> $etisalat */
    private function service(array $etisalat): SettingsService
    {
        $defaults = [
            'gateways' => [
                'etisalat' => $etisalat + [
                    'label'       => 'Etisalat / UBL EPG',
                    'description' => '',
                    'port'        => 2443,
                    'currency'    => 'PKR',
                    'transaction_hint' => 'CPT:Y;VCC:Y;',
                    'timeout'     => 30,
                ],
            ],
        ];

        return new SettingsService(
            new Database(['driver' => 'sqlite', 'database' => ':memory:']),
            new Logger(sys_get_temp_dir() . '/almarah-settings-status-tests-' . bin2hex(random_bytes(6))),
            $defaults
        );
    }
}
