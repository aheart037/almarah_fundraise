<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * All monetary values are handled as integer minor units (paisa for PKR).
 * Floating point arithmetic is never used for money.
 *
 * Provider decimal conversion is standardised here and nowhere else:
 *   provider amount string = money_format(amount_minor / 100, 2, '.', '')
 */
final class Money
{
    public const DEFAULT_CURRENCY = 'PKR';
    public const MINOR_PER_MAJOR = 100;

    /** Parse a user-entered decimal string ("1,500.50") into minor units. */
    public static function toMinor(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value * self::MINOR_PER_MAJOR;
        }

        $clean = preg_replace('/[^0-9.]/', '', (string) $value) ?? '';
        if ($clean === '' || substr_count($clean, '.') > 1) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        $parts = explode('.', $clean, 2);
        $major = $parts[0] === '' ? '0' : $parts[0];
        $minor = isset($parts[1]) ? str_pad(substr($parts[1], 0, 2), 2, '0') : '00';

        return ((int) $major * self::MINOR_PER_MAJOR) + (int) $minor;
    }

    /**
     * Decimal string for provider APIs: 150050 -> "1500.50"
     *
     * Integer arithmetic only — the value is never routed through a float, so
     * large amounts cannot drift in the last paisa.
     */
    public static function toProviderString(int $minor): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);

        $major = intdiv($minor, self::MINOR_PER_MAJOR);
        $cents = $minor % self::MINOR_PER_MAJOR;

        return ($negative ? '-' : '')
            . number_format($major, 0, '.', '')
            . '.'
            . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }

    /** Parse a provider decimal string back into minor units, strictly. */
    public static function fromProviderString(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Provider amount is not a valid decimal value.');
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        $parts = explode('.', $value, 2);
        $major = (int) $parts[0];
        $minor = isset($parts[1]) ? (int) str_pad($parts[1], 2, '0') : 0;

        $total = ($major * self::MINOR_PER_MAJOR) + $minor;
        return $negative ? -$total : $total;
    }

    /** Display formatter: 150050 -> "Rs 1,500.50" */
    public static function format(int $minor, string $currency = self::DEFAULT_CURRENCY, bool $withDecimals = true): string
    {
        $symbol = self::symbol($currency);
        $negative = $minor < 0;
        $abs = abs($minor);

        $major = intdiv($abs, self::MINOR_PER_MAJOR);
        $cents = $abs % self::MINOR_PER_MAJOR;

        $formatted = number_format($major);
        if ($withDecimals && $cents > 0) {
            $formatted .= '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
        }

        return ($negative ? '-' : '') . $symbol . ' ' . $formatted;
    }

    /** Compact display for cards: "Rs 34.2 Lac", "Rs 1.05 Cr" */
    public static function short(int $minor, string $currency = self::DEFAULT_CURRENCY): string
    {
        $symbol = self::symbol($currency);
        $major = intdiv(abs($minor), self::MINOR_PER_MAJOR);

        if ($major >= 10_000_000) {
            return $symbol . ' ' . rtrim(rtrim(number_format($major / 10_000_000, 2), '0'), '.') . ' Cr';
        }
        if ($major >= 100_000) {
            return $symbol . ' ' . rtrim(rtrim(number_format($major / 100_000, 2), '0'), '.') . ' Lac';
        }
        if ($major >= 1_000) {
            return $symbol . ' ' . rtrim(rtrim(number_format($major / 1_000, 1), '0'), '.') . 'k';
        }

        return $symbol . ' ' . number_format($major);
    }

    public static function symbol(string $currency = self::DEFAULT_CURRENCY): string
    {
        return match (strtoupper($currency)) {
            'PKR' => 'Rs',
            'USD' => '$',
            'AED' => 'AED',
            'GBP' => '£',
            'EUR' => '€',
            default => strtoupper($currency),
        };
    }

    /** Percentage of goal, capped display value, no float drift (scale 1). */
    public static function percentage(int $raisedMinor, int $goalMinor): float
    {
        if ($goalMinor <= 0) {
            return 0.0;
        }
        $ratio = ($raisedMinor * 1000) / $goalMinor; // integer-safe scaled ratio
        return round($ratio / 10, 1);
    }

    public static function percentCapped(int $raisedMinor, int $goalMinor, float $cap = 100.0): float
    {
        return min($cap, max(0.0, self::percentage($raisedMinor, $goalMinor)));
    }
}
