<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Maps provider statuses onto the platform's payment statuses.
 *
 * The maps are data, not code branches, so that an operations team can extend
 * them from the merchant's documentation without touching gateway logic. Any
 * status not present in the map resolves to `unknown` (processing) — never to
 * `completed` — so an unrecognised provider value can never release funds.
 */
final class PaymentStatusMapper
{
    /**
     * Meezan (RBS-style) OrderStatus values.
     * 2 = paid, 6 = cancelled are the documented values used by the reference
     * integration; the remaining entries are commonly observed states. Confirm
     * against the merchant account's own documentation before go-live.
     */
    private const MEEZAN_STATUS_MAP = [
        '0'  => 'pending',      // registered, not paid
        '1'  => 'processing',   // authorised / partially paid
        '2'  => 'completed',    // fully paid
        '3'  => 'failed',       // declined
        '4'  => 'cancelled',    // reversed
        '5'  => 'processing',   // ACS in progress
        '6'  => 'cancelled',    // cancelled by user
        '7'  => 'processing',   // pending further confirmation
        '15' => 'pending',      // pre-authorised
    ];

    /**
     * Etisalat / UBL EPG Finalization response codes.
     * 0 = successful, 210 = in progress, 112 = cancelled.
     * Any other non-zero code is a failure.
     */
    private const ETISALAT_STATUS_MAP = [
        '0'   => 'completed',
        '210' => 'processing',
        '112' => 'cancelled',
    ];

    public function __construct(private array $config = [])
    {
    }

    public function mapMeezan(?string $orderStatus): string
    {
        return $this->map(self::MEEZAN_STATUS_MAP, $orderStatus, 'meezan');
    }

    public function mapEtisalat(?string $responseCode): string
    {
        return $this->map(self::ETISALAT_STATUS_MAP, $responseCode, 'etisalat', self::etisalatUnknownIsFailure(...));
    }

    /**
     * The EPG's failure codes are a documented, open-ended numeric range: any
     * non-zero numeric code other than "in progress" (210) and "cancelled"
     * (112) means the transaction did not go through. Mapping those to
     * `failed` gives the donor an honest answer instead of leaving the
     * donation in limbo — and it can never mark anything as paid, because
     * `failed` is not a success state.
     *
     * A blank or non-numeric code carries no verdict at all, so it stays
     * `processing` until reconciliation asks the provider again.
     */
    private static function etisalatUnknownIsFailure(string $code): bool
    {
        return $code !== '' && ctype_digit($code);
    }

    /**
     * @param array<string,string> $map
     */
    private function map(array $map, ?string $code, string $gateway, ?callable $unknownIsFailure = null): string
    {
        if ($code === null || $code === '') {
            return 'processing';
        }

        $normalised = ltrim(trim($code), '+');

        // Providers sometimes pad their codes ("00", "007"). Normalising the
        // digits before the lookup stops a padded success code from falling
        // through to the unknown-code branch.
        if ($normalised !== '' && ctype_digit($normalised)) {
            $normalised = (string) (int) $normalised;
        }

        // Allow operators to override/extend the map through settings.
        $override = $this->config['status_map'][$gateway] ?? [];
        if (is_array($override) && array_key_exists($normalised, $override)) {
            return $this->assertKnown((string) $override[$normalised]);
        }

        if (array_key_exists($normalised, $map)) {
            return $map[$normalised];
        }

        // Unknown codes are never treated as success. Gateways whose failure
        // range is open-ended may declare them failures via $unknownIsFailure,
        // which still cannot produce a completed status.
        if ($unknownIsFailure !== null && $unknownIsFailure($normalised)) {
            return 'failed';
        }

        return 'processing';
    }

    private function assertKnown(string $status): string
    {
        $allowed = $this->config['status_map']['statuses'] ?? [
            'pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'abandoned',
        ];

        return in_array($status, $allowed, true) ? $status : 'processing';
    }

    public function isTerminal(string $status): bool
    {
        $terminal = $this->config['status_map']['terminal'] ?? ['completed', 'refunded'];
        return in_array($status, $terminal, true);
    }

    /** @return array<int,string> */
    public function allStatuses(): array
    {
        return $this->config['status_map']['statuses'] ?? [
            'pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'abandoned',
        ];
    }

    public function isKnownStatus(string $status): bool
    {
        return in_array($status, $this->allStatuses(), true);
    }
}
