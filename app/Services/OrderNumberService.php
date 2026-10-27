<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Sequential order numbers shown to the banks: 0001, 0002, 0003 …
 *
 * One counter serves every payment gateway, so each payment attempt gets a
 * number nobody else has had. The same number is what the bank shows on its
 * payment page, what appears in the bank's reports and what Admin → Donations
 * shows as "Order id", so a payment can be traced end to end.
 *
 * The counter lives in site_settings ('orders.counter' = the last number
 * handed out). It is advanced with a single atomic UPDATE using
 * LAST_INSERT_ID(), so two donors paying at the same instant can never be given
 * the same number, and no table needs to be added to the database.
 *
 * Numbers are padded to at least four digits. After 9999 the counter simply
 * carries on (10000, 10001 …) rather than repeating, because a bank refuses an
 * order number it has already seen.
 */
final class OrderNumberService
{
    public const SETTING_KEY = 'orders.counter';

    /** Keeps numbers inside every gateway's limit (EPG: 16 characters). */
    private const MAX = 99999999;

    public function __construct(private Database $db)
    {
    }

    /** Hand out the next number, e.g. "0042". */
    public function next(): string
    {
        $this->ensureCounterRow();

        // Skip any number an earlier payment already used (for example after
        // the counter was moved), so the unique index on the table can never
        // be hit.
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $this->db->run(
                'UPDATE site_settings SET setting_value = LAST_INSERT_ID(CAST(setting_value AS UNSIGNED) + 1) WHERE setting_key = :k',
                ['k' => self::SETTING_KEY]
            );
            $number = (int) $this->db->scalar('SELECT LAST_INSERT_ID()');

            if ($number > self::MAX) {
                throw new InvalidArgumentException('The order number counter has reached its limit.');
            }

            $formatted = self::format($number);
            if ($this->db->selectOne('SELECT id FROM payment_transactions WHERE merchant_order_id = :o', ['o' => $formatted]) === null) {
                return $formatted;
            }
        }

        throw new InvalidArgumentException('Could not find a free order number.');
    }

    /** The number the next payment will get (not reserved). */
    public function peek(): string
    {
        return self::format($this->lastUsed() + 1);
    }

    public function lastUsed(): int
    {
        $raw = $this->db->scalar('SELECT setting_value FROM site_settings WHERE setting_key = :k', ['k' => self::SETTING_KEY]);
        return is_numeric($raw) ? max(0, (int) $raw) : 0;
    }

    /**
     * Make $next the number the next payment will get. It can only move
     * forwards: going back could reuse a number a bank has already seen.
     */
    public function setNext(int $next, ?int $userId): void
    {
        if ($next < 1 || $next > self::MAX) {
            throw new InvalidArgumentException('Enter a number between 1 and ' . self::MAX . '.');
        }
        if ($next <= $this->lastUsed()) {
            throw new InvalidArgumentException(
                'The next number must be higher than ' . self::format($this->lastUsed())
                . ', the last one used. Going back could repeat a number the bank has already seen.'
            );
        }

        $this->ensureCounterRow();
        $this->db->run(
            'UPDATE site_settings SET setting_value = :v, updated_by = :u WHERE setting_key = :k',
            ['v' => (string) ($next - 1), 'u' => $userId, 'k' => self::SETTING_KEY]
        );
    }

    public static function format(int $number): string
    {
        return str_pad((string) max(0, $number), 4, '0', STR_PAD_LEFT);
    }

    private function ensureCounterRow(): void
    {
        $this->db->run(
            'INSERT IGNORE INTO site_settings (setting_key, setting_value, is_encrypted) VALUES (:k, :v, 0)',
            ['k' => self::SETTING_KEY, 'v' => '0']
        );
    }
}
