<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Payments\PaymentStatusMapper;
use PHPUnit\Framework\TestCase;

final class PaymentStatusMapperTest extends TestCase
{
    public function testEtisalatSuccessPendingAndDefinitiveFailures(): void
    {
        $mapper = new PaymentStatusMapper();

        self::assertSame('completed', $mapper->mapEtisalat('0'));
        self::assertSame('completed', $mapper->mapEtisalat('000'));
        self::assertSame('processing', $mapper->mapEtisalat('210'));
        self::assertSame('failed', $mapper->mapEtisalat('112'));
        self::assertSame('failed', $mapper->mapEtisalat('6560'));
    }

    public function testMissingOrNonNumericResponseDoesNotCompletePayment(): void
    {
        $mapper = new PaymentStatusMapper();

        self::assertSame('processing', $mapper->mapEtisalat(null));
        self::assertSame('processing', $mapper->mapEtisalat(''));
        self::assertSame('processing', $mapper->mapEtisalat('APPROVED'));
    }
}
