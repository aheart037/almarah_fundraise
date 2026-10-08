<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Config;
use RuntimeException;

/**
 * Signed callback-state tokens.
 *
 * Every payment transaction gets a state value that we hand to the provider in
 * the return URL. When the donor's browser comes back, that state is the only
 * thing tying the return trip to a stored transaction — so it must be
 * unguessable, must expire, and must never be forgeable by the browser.
 *
 * The token is an HMAC over the merchant order id, keyed with the application
 * key. It is therefore:
 *
 *  - unguessable without APP_KEY;
 *  - re-derivable from the stored transaction row, which is what lets us
 *    resolve a return trip from an order number alone when a provider drops
 *    our query parameters (Meezan and the EPG both do this);
 *  - bound to exactly one transaction.
 *
 * The token deliberately does NOT encode the expiry. The window is enforced
 * from payment_transactions.callback_state_expires_at instead, so a donor who
 * returns after the window is recognised as expired rather than as a stranger:
 * the donation is found, refused for the right reason, and the callback log
 * says "state expired". Baking the expiry into the HMAC would make that case
 * indistinguishable from a forged state.
 *
 * Only sha256(token) is persisted (payment_transactions.callback_state_hash),
 * so a database leak does not expose usable state values, and the plaintext is
 * never logged.
 */
final class PaymentStateToken
{
    private const PREFIX = 'st2';

    /**
     * State value handed to the provider for this order.
     *
     * The expiry window is not part of the token; see the class docblock.
     */
    public static function issue(string $merchantOrderId): string
    {
        $merchantOrderId = trim($merchantOrderId);
        if ($merchantOrderId === '') {
            throw new RuntimeException('Cannot derive a payment state token without an order id.');
        }

        $key = (string) Config::get('app.key', '');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is not configured; refusing to issue a payment state token.');
        }

        $signature = hash_hmac('sha256', self::PREFIX . '|' . $merchantOrderId, $key);

        // Short, URL-safe, and long enough to be unguessable (128 bits of HMAC).
        return self::base64UrlEncode(self::PREFIX . ':' . substr($signature, 0, 32));
    }

    /**
     * Re-derives the state value for a stored transaction row.
     *
     * @param array<string,mixed> $transaction
     */
    public static function fromTransaction(array $transaction): string
    {
        $orderId = trim((string) ($transaction['merchant_order_id'] ?? ''));

        if ($orderId === '') {
            return '';
        }

        return self::issue($orderId);
    }

    /** @param array<string,mixed> $transaction */
    public static function expiryOf(array $transaction): int
    {
        $raw = (string) ($transaction['callback_state_expires_at'] ?? '');
        if ($raw === '') {
            return 0;
        }

        $timestamp = strtotime($raw . ' UTC');

        return $timestamp === false ? 0 : $timestamp;
    }

    /** @param array<string,mixed> $transaction */
    public static function hashFor(array $transaction): string
    {
        return hash('sha256', self::fromTransaction($transaction));
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
