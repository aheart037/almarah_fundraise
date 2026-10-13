<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Etisalat;

use App\Core\Logger;
use App\Payments\Etisalat\EtisalatApiClient;
use App\Payments\Etisalat\EtisalatGateway;
use App\Payments\Dtos\PaymentRequest;
use App\Payments\Http\HttpClientInterface;
use App\Payments\Http\HttpResult;
use App\Payments\PaymentStatusMapper;
use PHPUnit\Framework\TestCase;

final class EtisalatGatewayTest extends TestCase
{
    public function testRegistrationUsesBankFieldsAndStoresTheHostedPageUrl(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(self::once())
            ->method('postJson')
            ->with(
                'https://demo-ipg.ctdev.comtrust.ae:2443',
                self::callback(static function (array $payload): bool {
                    $registration = $payload['Registration'] ?? [];
                    $orderInfo = $registration['OrderInfo'] ?? null;
                    return ($registration['Amount'] ?? null) === '10.50'
                        && ($registration['TransactionHint'] ?? null) === 'CPT:Y;VCC:Y;'
                        && ($registration['ReturnPath'] ?? null) === 'https://fundraise.example/payments/etisalat/return'
                        && is_string($orderInfo)
                        && strlen($orderInfo) <= 50
                        && preg_match('/^[A-Za-z0-9 ]*$/D', $orderInfo) === 1;
                }),
                self::callback(static function (array $options): bool {
                    return ($options['headers'] ?? []) === ['Accept: text/xml-standard-api'];
                })
            )
            ->willReturn(new HttpResult(200, json_encode([
                'Transaction' => [
                    'ResponseCode' => '0',
                    'ResponseDescription' => 'Registered',
                    'TransactionID' => 'TXN-123',
                    'PaymentPage' => 'https://demo-ipg.ctdev.comtrust.ae/PaymentPage',
                    'OrderID' => 'A1B2C3D4',
                ],
            ], JSON_THROW_ON_ERROR)));

        $gateway = $this->gateway($http);
        $result = $gateway->register($this->request());

        self::assertTrue($result->ok);
        self::assertSame('TXN-123', $result->providerTransactionId);
        self::assertSame('https://demo-ipg.ctdev.comtrust.ae/PaymentPage', $result->providerPaymentPageUrl);
        self::assertSame(
            'https://fundraise.example/payments/etisalat/bridge/test_state_token_1234567890',
            $result->redirectUrl
        );
    }

    public function testRegistrationRejectsOrderIdWithPunctuation(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(self::never())->method('postJson');

        $this->expectException(\App\Payments\Exceptions\PaymentGatewayException::class);
        $this->gateway($http)->register($this->request('ALM-123'));
    }

    public function testRegistrationRejectsOrderIdLongerThanSixteenCharacters(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(self::never())->method('postJson');

        $this->expectException(\App\Payments\Exceptions\PaymentGatewayException::class);
        $this->gateway($http)->register($this->request('12345678901234567'));
    }

    public function testSuccessfulFinalizationRequiresMatchingOrderAndAmount(): void
    {
        $gateway = $this->gatewayWithResponse([
            'ResponseCode' => '0',
            'ResponseDescription' => 'Approved',
            'ApprovalCode' => 'APPROVED-123',
            'TransactionID' => 'TXN-123',
            'OrderID' => 'A1B2C3D4',
            'Amount' => '10.500',
            'Currency' => 'PKR',
        ]);

        $result = $gateway->verify($this->verificationContext());

        self::assertTrue($result->verified);
        self::assertSame('completed', $result->status);
        self::assertSame(1050, $result->amountMinor);
        self::assertSame('TXN-123', $result->providerTransactionId);
        self::assertSame('A1B2C3D4', $result->providerOrderId);
    }

    public function testResponseCodeZeroWithoutApprovalCodeStaysProcessing(): void
    {
        $gateway = $this->gatewayWithResponse([
            'ResponseCode' => '0',
            'TransactionID' => 'TXN-123',
            'OrderID' => 'A1B2C3D4',
            'Amount' => '10.50',
            'Currency' => 'PKR',
        ]);

        $result = $gateway->verify($this->verificationContext());

        self::assertFalse($result->verified);
        self::assertSame('processing', $result->status);
    }

    public function testMissingOrMismatchedOrderAndAmountCannotComplete(): void
    {
        $responses = [
            ['ResponseCode' => '0', 'ApprovalCode' => 'APPROVED-123', 'TransactionID' => 'TXN-123', 'OrderID' => 'OTHER', 'Amount' => '10.50'],
            ['ResponseCode' => '0', 'ApprovalCode' => 'APPROVED-123', 'TransactionID' => 'TXN-123', 'OrderID' => 'A1B2C3D4', 'Amount' => '10.51'],
            ['ResponseCode' => '0', 'ApprovalCode' => 'APPROVED-123', 'TransactionID' => 'TXN-123', 'Amount' => '10.50'],
            ['ResponseCode' => '0', 'ApprovalCode' => 'APPROVED-123', 'TransactionID' => 'TXN-123', 'OrderID' => 'A1B2C3D4'],
        ];

        foreach ($responses as $transaction) {
            $gateway = $this->gatewayWithResponse($transaction);
            $result = $gateway->verify($this->verificationContext());

            self::assertFalse($result->verified);
            self::assertSame('processing', $result->status);
        }
    }

    public function testMismatchedFinalizationTransactionIdCannotComplete(): void
    {
        $gateway = $this->gatewayWithResponse([
            'ResponseCode' => '0',
            'ApprovalCode' => 'APPROVED-123',
            'TransactionID' => 'TXN-OTHER',
            'OrderID' => 'A1B2C3D4',
            'Amount' => '10.50',
        ]);

        $result = $gateway->verify($this->verificationContext());

        self::assertFalse($result->verified);
        self::assertSame('processing', $result->status);
    }

    public function testBridgePostsStoredTransactionIdToAllowlistedPaymentPage(): void
    {
        $bridge = $this->gateway($this->createMock(HttpClientInterface::class))->bridgePayload([
            'provider_transaction_id' => 'TXN-123',
            'provider_payment_page_url' => 'https://demo-ipg.ctdev.comtrust.ae/PaymentPage',
        ]);

        self::assertSame('https://demo-ipg.ctdev.comtrust.ae/PaymentPage', $bridge['action']);
        self::assertSame(['TransactionID' => 'TXN-123'], $bridge['fields']);
    }

    public function testBridgeRejectsAnUntrustedPaymentPageHost(): void
    {
        $this->expectException(\App\Payments\Exceptions\PaymentGatewayException::class);
        $this->gateway($this->createMock(HttpClientInterface::class))->bridgePayload([
            'provider_transaction_id' => 'TXN-123',
            'provider_payment_page_url' => 'https://attacker.example.test/PaymentPage',
        ]);
    }

    public function testReturnPathIsCompactAndDoesNotEmbedTheStateToken(): void
    {
        $returnPath = $this->gateway($this->createMock(HttpClientInterface::class))
            ->returnUrl('secret-callback-state-token');

        self::assertSame('https://fundraise.example/payments/etisalat/return', $returnPath);
        self::assertLessThanOrEqual(256, strlen($returnPath));
        self::assertStringNotContainsString('secret-callback-state-token', $returnPath);
    }

    public function testPendingAndFailureCodesAreNotReportedAsSuccess(): void
    {
        $pending = $this->gatewayWithResponse([
            'ResponseCode' => '210',
            'TransactionID' => 'TXN-123',
        ])->verify($this->verificationContext());
        $failure = $this->gatewayWithResponse([
            'ResponseCode' => '112',
            'TransactionID' => 'TXN-123',
        ])->verify($this->verificationContext());

        self::assertTrue($pending->verified);
        self::assertSame('processing', $pending->status);
        self::assertTrue($failure->verified);
        self::assertSame('failed', $failure->status);
    }

    private function gatewayWithResponse(array $transaction): EtisalatGateway
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(self::once())
            ->method('postJson')
            ->with('https://demo-ipg.ctdev.comtrust.ae:2443', self::anything(), self::anything())
            ->willReturn(new HttpResult(200, json_encode([
                'Transaction' => $transaction,
            ], JSON_THROW_ON_ERROR)));

        return $this->gateway($http);
    }

    private function gateway(HttpClientInterface $http): EtisalatGateway
    {
        $logger = new Logger(sys_get_temp_dir() . '/almarah-etisalat-tests-' . bin2hex(random_bytes(6)));
        $client = new EtisalatApiClient($http, $logger, [
            'environment' => 'sandbox',
            'sandbox_url' => 'https://demo-ipg.ctdev.comtrust.ae',
            'port' => 2443,
            'accept_header' => 'text/xml-standard-api',
            'timeout' => 10,
        ]);

        return new EtisalatGateway(
            $client,
            new PaymentStatusMapper(),
            $logger,
            [
                'enabled' => true,
                'environment' => 'sandbox',
                'sandbox_url' => 'https://demo-ipg.ctdev.comtrust.ae',
                'live_url' => 'https://ipg.comtrust.ae',
                'customer' => 'TEST-CUSTOMER',
                'username' => 'test-user',
                'password' => 'test-password',
                'currency' => 'PKR',
                'transaction_hint' => 'CPT:Y;VCC:Y;',
                'allowed_hosts' => ['demo-ipg.ctdev.comtrust.ae'],
            ],
            'https://fundraise.example'
        );
    }

    private function request(string $merchantOrderId = 'A1B2C3D4'): PaymentRequest
    {
        return new PaymentRequest(
            merchantOrderId: $merchantOrderId,
            amountMinor: 1050,
            currency: 'PKR',
            description: 'Test donation — ' . str_repeat('fundraiser title ', 5),
            returnUrl: 'https://fundraise.example/payments/etisalat/return',
            callbackUrl: 'https://fundraise.example/payments/etisalat/return',
            idempotencyKey: 'test-idempotency-key',
            metadata: ['callback_state' => 'test_state_token_1234567890']
        );
    }

    /** @return array<string,mixed> */
    private function verificationContext(): array
    {
        return [
            'providerOrderId' => 'A1B2C3D4',
            'providerTransactionId' => 'TXN-123',
            'merchantOrderId' => 'A1B2C3D4',
            'expectedAmountMinor' => 1050,
            'expectedCurrency' => 'PKR',
        ];
    }
}
