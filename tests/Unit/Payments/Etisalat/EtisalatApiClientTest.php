<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Etisalat;

use App\Core\Logger;
use App\Payments\Etisalat\EtisalatApiClient;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Http\HttpClientInterface;
use App\Payments\Http\HttpResult;
use PHPUnit\Framework\TestCase;

final class EtisalatApiClientTest extends TestCase
{
    public function testLegacyRestPathIsRemovedAndPortIsKept(): void
    {
        $client = $this->client([
            'environment' => 'sandbox',
            'sandbox_url' => 'https://demo-ipg.ctdev.comtrust.ae:2443/epg/rest/',
            'port' => 2555,
        ]);

        self::assertSame('https://demo-ipg.ctdev.comtrust.ae:2443', $client->baseUrl());
    }

    public function testHostOnlyUrlUsesConfiguredPort(): void
    {
        $client = $this->client([
            'environment' => 'live',
            'live_url' => 'https://ipg.comtrust.ae',
            'port' => 2444,
        ]);

        self::assertSame('https://ipg.comtrust.ae:2444', $client->baseUrl());
    }

    public function testUnexpectedApiPathIsRejectedInsteadOfGuessed(): void
    {
        $client = $this->client([
            'environment' => 'sandbox',
            'sandbox_url' => 'https://gateway.example.test/v2/api',
            'port' => 2443,
        ]);

        $this->expectException(PaymentGatewayException::class);
        $client->baseUrl();
    }

    public function testOnlyHttpsGatewayHostsAreAccepted(): void
    {
        $client = $this->client([
            'environment' => 'sandbox',
            'sandbox_url' => 'http://gateway.example.test',
            'port' => 2443,
        ]);

        $this->expectException(PaymentGatewayException::class);
        $client->baseUrl();
    }

    public function testSendUsesHostRootPortAndBankAcceptHeader(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(self::once())
            ->method('postJson')
            ->with(
                'https://demo-ipg.ctdev.comtrust.ae:2443',
                ['Registration' => ['OrderID' => 'ALM-1']],
                self::callback(static function (array $options): bool {
                    return ($options['headers'] ?? []) === ['Accept: text/xml-standard-api']
                        && is_int($options['tls_version'] ?? null)
                        && ($options['tls_version'] ?? 0) > 0
                        && ($options['forbid_reuse'] ?? false) === true
                        && ($options['timeout'] ?? null) === 12;
                })
            )
            ->willReturn(new HttpResult(200, '{"Transaction":{"ResponseCode":"0"}}'));

        $client = new EtisalatApiClient($http, $this->logger(), [
            'environment' => 'sandbox',
            'sandbox_url' => 'https://demo-ipg.ctdev.comtrust.ae',
            'port' => 2443,
            'accept_header' => 'text/xml-standard-api',
            'timeout' => 12,
        ]);

        self::assertSame(
            ['Transaction' => ['ResponseCode' => '0']],
            $client->send('Registration', ['Registration' => ['OrderID' => 'ALM-1']])
        );
    }

    public function testExtractReadsTransactionResponseAndNumericCode(): void
    {
        $client = $this->client([]);
        $result = $client->extract('Finalization', [
            'Transaction' => [
                'ResponseCode' => 0,
                'ResponseDescription' => 'Approved',
                'TransactionID' => 'TXN-1',
            ],
        ]);

        self::assertSame('0', $result['code']);
        self::assertSame('Approved', $result['message']);
        self::assertSame('TXN-1', $result['block']['TransactionID']);
    }

    public function testExtractDoesNotInventStatusFromUnknownFields(): void
    {
        $client = $this->client([]);
        $result = $client->extract('Finalization', ['status' => 'success']);

        self::assertNull($result['code']);
        self::assertSame([], $result['block']);
    }

    /** @param array<string,mixed> $config */
    private function client(array $config): EtisalatApiClient
    {
        return new EtisalatApiClient(
            $this->createMock(HttpClientInterface::class),
            $this->logger(),
            $config
        );
    }

    private function logger(): Logger
    {
        return new Logger(sys_get_temp_dir() . '/almarah-etisalat-tests-' . bin2hex(random_bytes(6)));
    }
}
