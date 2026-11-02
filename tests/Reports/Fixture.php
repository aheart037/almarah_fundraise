<?php

declare(strict_types=1);

namespace Tests\Reports;

use App\Core\Database;

/**
 * Builds a small, fully known data set. Every number the report tests expect
 * is worked out by hand from this file; see the comments next to each row.
 * All amounts are in minor units (100 = Rs 1).
 */
final class Fixture
{
    public static function connect(): Database
    {
        $name = (string) (getenv('TEST_DB_NAME') ?: 'almarah_test');
        if (!str_contains($name, 'test')) {
            throw new \RuntimeException('Refusing to wipe a database whose name does not contain "test".');
        }

        return new Database([
            'driver'   => 'mysql',
            'host'     => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
            'port'     => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'database' => $name,
            'username' => (string) (getenv('TEST_DB_USER') ?: 'alm'),
            'password' => (string) (getenv('TEST_DB_PASS') ?: 'almpass'),
            'charset'  => 'utf8mb4',
        ]);
    }

    /** Drops everything and re-runs the project's own migrations. */
    public static function migrate(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->select("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $r) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $r['t'] . '`');
        }
        foreach ($db->select("SELECT table_name AS t FROM information_schema.views WHERE table_schema = DATABASE()") as $r) {
            $pdo->exec('DROP VIEW IF EXISTS `' . $r['t'] . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $files = glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $pdo->exec((string) file_get_contents($file));
        }
    }

    public static function seed(Database $db): void
    {
        $at = static fn (string $d, string $t = '12:00:00'): string => $d . ' ' . $t;

        // roles + people ---------------------------------------------------
        $db->insert('roles', ['id' => 1, 'name' => 'admin', 'label' => 'Admin']);
        $db->insert('roles', ['id' => 2, 'name' => 'super_admin', 'label' => 'Super admin']);
        $db->insert('roles', ['id' => 3, 'name' => 'fundraiser', 'label' => 'Fundraiser']);
        foreach ([
            [1, 'Ada', 'Admin', 'admin@example.org'],
            [2, 'Omar', 'Owner', 'owner@example.org'],
            [3, 'Dina', 'Donor', 'donor@example.org'],
        ] as [$id, $fn, $ln, $email]) {
            $db->insert('users', ['id' => $id, 'first_name' => $fn, 'last_name' => $ln, 'email' => $email, 'password_hash' => 'x', 'status' => 'active']);
        }
        $db->insert('user_roles', ['user_id' => 1, 'role_id' => 1]);
        $db->insert('user_roles', ['user_id' => 2, 'role_id' => 3]);

        $db->insert('gateways', ['id' => 1, 'code' => 'meezan', 'name' => 'Meezan Bank', 'enabled' => 1, 'sort_order' => 1]);
        $db->insert('gateways', ['id' => 2, 'code' => 'etisalat', 'name' => 'Etisalat / UBL', 'enabled' => 1, 'sort_order' => 2]);

        $db->insert('fundraiser_categories', ['id' => 1, 'name' => 'Education', 'slug' => 'education']);

        // campaigns (A active, B closed) -----------------------------------
        $db->insert('campaigns', ['id' => 1, 'title' => 'Campaign A', 'slug' => 'campaign-a', 'status' => 'active', 'start_at' => '2026-01-01']);
        $db->insert('campaigns', ['id' => 2, 'title' => 'Campaign B', 'slug' => 'campaign-b', 'status' => 'closed']);

        // fundraisers ------------------------------------------------------
        $fr = static fn (int $id, string $title, ?int $camp, int $goal, string $status, string $approval, ?string $reason = null): array => [
            'id' => $id, 'owner_user_id' => 2, 'campaign_id' => $camp, 'category_id' => 1, 'title' => $title, 'slug' => 'f' . $id,
            'story' => 'story', 'goal_minor' => $goal, 'currency' => 'PKR', 'status' => $status, 'approval_status' => $approval,
            'rejection_reason' => $reason, 'created_at' => '2026-09-01 08:00:00',
        ];
        $db->insert('fundraisers', $fr(1, 'Fundraiser One', 1, 2000000, 'published', 'approved'));
        $db->insert('fundraisers', $fr(2, 'Fundraiser Two', 1, 1000000, 'published', 'approved'));
        $db->insert('fundraisers', $fr(3, 'Fundraiser Three', 2, 5000000, 'pending_review', 'pending'));
        $db->insert('fundraisers', $fr(4, 'Fundraiser Draft', null, 100000, 'draft', 'pending'));
        $db->insert('fundraisers', $fr(5, 'Fundraiser Five', null, 100000, 'rejected', 'rejected', 'Photo is not clear'));
        $db->insert('fundraisers', $fr(6, 'Fundraiser Deleted', 1, 9999999, 'published', 'approved') + ['deleted_at' => '2026-09-02 08:00:00']);

        // team with two members -------------------------------------------
        $db->insert('teams', ['id' => 1, 'owner_user_id' => 2, 'campaign_id' => 1, 'name' => 'Team Alpha', 'slug' => 'team-alpha', 'goal_minor' => 5000000, 'currency' => 'PKR', 'status' => 'active']);
        $db->insert('team_members', ['team_id' => 1, 'user_id' => 2, 'role' => 'owner']);
        $db->insert('team_members', ['team_id' => 1, 'user_id' => 3, 'role' => 'member']);

        // donors -----------------------------------------------------------
        foreach ([[1, 'Donor One', 'd1@example.org'], [2, 'Donor Two', 'd2@example.org'], [3, 'Donor Three', 'd3@example.org'], [4, 'Donor Four', 'd4@example.org']] as [$id, $n, $e]) {
            $db->insert('donors', ['id' => $id, 'name' => $n, 'email' => $e, 'phone' => '0300000000' . $id]);
        }

        // donations: id, ref, donor, fundraiser, campaign, team, gateway, amount, currency, status, anon, created, completed, receipt
        $rows = [
            [1, 'DON-0001', 1, 1, 1, 1, 1, 500000, 'PKR', 'completed', 0, '2026-10-05', '2026-10-05', 'sent'],
            [2, 'DON-0002', 2, 1, 1, 1, 2, 250000, 'PKR', 'completed', 0, '2026-10-10', '2026-10-10', 'sent'],
            [3, 'DON-0003', 1, 2, 1, null, 1, 100000, 'PKR', 'completed', 1, '2026-10-15', '2026-10-15', 'queued'],
            [4, 'DON-0004', 3, 2, 1, null, 1, 300000, 'PKR', 'refunded', 0, '2026-10-12', '2026-10-12', 'sent'],
            [5, 'DON-0005', 2, 1, 1, null, 2, 200000, 'PKR', 'failed', 0, '2026-10-14', null, 'not_sent'],
            [6, 'DON-0006', 4, 3, 2, null, 2, 100000, 'PKR', 'cancelled', 0, '2026-10-16', null, 'not_sent'],
            [7, 'DON-0007', 4, 1, 1, null, 1, 150000, 'PKR', 'processing', 0, '2026-10-29', null, 'not_sent'],
            [8, 'DON-0008', 3, null, null, null, 1, 400000, 'PKR', 'completed', 0, '2026-09-20', '2026-09-20', 'sent'],
            [9, 'DON-0009', 1, null, null, null, 1, 1000, 'USD', 'completed', 0, '2026-10-08', '2026-10-08', 'sent'],
            [10, 'DON-0010', 2, 3, 2, null, 2, 750000, 'PKR', 'completed', 0, '2026-08-30', '2026-08-30', 'sent'],
            [11, 'DON-0011', 1, 2, 1, null, 2, 50000, 'PKR', 'abandoned', 0, '2026-10-18', null, 'not_sent'],
            [12, 'DON-0012', 4, null, null, null, 1, 80000, 'PKR', 'completed', 0, '2026-10-22', '2026-10-22', 'failed'],
            [13, 'DON-0013', 3, 1, 1, null, 1, 90000, 'PKR', 'pending', 0, '2026-10-23', null, 'not_sent'],
            [14, 'DON-0014', 2, 2, 1, null, 2, 70000, 'PKR', 'completed', 0, '2026-10-24', '2026-10-24', 'not_sent'],
            [15, 'DON-0015', 4, 1, 1, null, 1, 30000, 'PKR', 'completed', 0, '2026-10-25', '2026-10-25', 'sent'],
        ];
        foreach ($rows as [$id, $ref, $donor, $fund, $camp, $team, $gw, $amt, $cur, $status, $anon, $created, $completed, $receipt]) {
            $db->insert('donations', [
                'id' => $id, 'public_reference' => $ref, 'donor_id' => $donor, 'fundraiser_id' => $fund, 'campaign_id' => $camp, 'team_id' => $team,
                'gateway_id' => $gw, 'amount_minor' => $amt, 'currency' => $cur, 'status' => $status, 'anonymous' => $anon,
                'created_at' => $at($created), 'updated_at' => $at($completed ?? $created), 'completed_at' => $completed ? $at($completed) : null,
                'receipt_email_status' => $receipt,
            ]);
        }

        // payment transactions: donation, gateway, status, amount, currency, bank id, created, response
        $txns = [
            [1, 1, 1, 'completed', 500000, 'PKR', 'B1', '2026-10-05', null],
            [2, 2, 2, 'completed', 250000, 'PKR', 'B2', '2026-10-10', null],
            [3, 3, 1, 'failed', 100000, 'PKR', null, '2026-10-15', ['E51', 'Insufficient funds']],  // first attempt
            [3, 3, 1, 'completed', 100000, 'PKR', 'B3', '2026-10-15', null],                          // second attempt
            [4, 4, 1, 'refunded', 300000, 'PKR', 'B4', '2026-10-12', null],
            [5, 5, 2, 'failed', 200000, 'PKR', null, '2026-10-14', ['E51', 'Insufficient funds']],
            [6, 6, 2, 'cancelled', 100000, 'PKR', null, '2026-10-16', ['C01', 'Cancelled by customer']],
            [7, 7, 1, 'processing', 150000, 'PKR', null, '2026-10-29', null],                          // stuck
            [8, 8, 1, 'completed', 400000, 'PKR', 'B8', '2026-09-20', null],
            [9, 9, 1, 'completed', 1000, 'USD', 'B9', '2026-10-08', null],
            [10, 10, 2, 'completed', 750000, 'PKR', 'B10', '2026-08-30', null],
            [11, 11, 2, 'abandoned', 50000, 'PKR', null, '2026-10-18', null],
            [13, 13, 1, 'completed', 90000, 'PKR', 'B13', '2026-10-23', null],                         // bank paid, donation pending
            [14, 14, 2, 'completed', 60000, 'PKR', 'B14', '2026-10-24', null],                         // amount differs (donation 70000)
            [15, 15, 1, 'completed', 30000, 'PKR', null, '2026-10-25', null],                          // no bank reference
        ];
        foreach ($txns as $i => [$don, , $gw, $status, $amt, $cur, $bank, $created, $resp]) {
            $db->insert('payment_transactions', [
                'donation_id' => $don, 'gateway_id' => $gw, 'environment' => 'live', 'merchant_order_id' => 'ORD' . ($i + 1),
                'provider_transaction_id' => $bank, 'provider_response_code' => $resp[0] ?? null, 'provider_response_description' => $resp[1] ?? null,
                'amount_minor' => $amt, 'currency' => $cur, 'status' => $status, 'idempotency_key' => 'idem' . ($i + 1), 'callback_state_hash' => hash('sha256', 'cb' . $i), 'callback_state_expires_at' => '2030-01-01 00:00:00', 'request_reference' => 'REQ' . $i,
                'created_at' => $at($created, '11:00:00'), 'completed_at' => $status === 'completed' ? $at($created, '11:05:00') : null,
            ]);
        }

        // refund: donation 4 refunded on 20 Oct; plus one refund request that failed
        $db->insert('payment_refunds', ['payment_transaction_id' => 5, 'donation_id' => 4, 'gateway_id' => 1, 'amount_minor' => 300000, 'currency' => 'PKR',
            'status' => 'completed', 'idempotency_key' => 'rf1', 'requested_by' => 1, 'completed_at' => $at('2026-10-20'), 'created_at' => $at('2026-10-20', '10:00:00')]);
        $db->insert('payment_refunds', ['payment_transaction_id' => 1, 'donation_id' => 1, 'gateway_id' => 1, 'amount_minor' => 500000, 'currency' => 'PKR',
            'status' => 'failed', 'idempotency_key' => 'rf2', 'provider_response_description' => 'Refund window closed', 'requested_by' => 1, 'created_at' => $at('2026-10-21', '10:00:00')]);

        // receipt emails
        foreach ([[1, 'sent', null], [2, 'sent', null], [4, 'sent', null], [15, 'sent', null], [12, 'failed', 'SMTP timeout']] as [$id, $st, $err]) {
            $db->insert('email_jobs', ['event_key' => 'donation.receipt', 'dedupe_key' => 'donation.receipt:' . $id, 'recipient_email' => 'x@example.org', 'subject' => 's',
                'status' => $st, 'sent_at' => $st === 'sent' ? $at('2026-10-25') : null, 'last_error' => $err]);
        }

        // audit trail --------------------------------------------------------
        $audit = [
            [1, 'fundraiser.submitted', 'fundraiser', '1', '2026-10-01 09:00:00', 2],
            [1, 'fundraiser.approved', 'fundraiser', '1', '2026-10-01 15:00:00', 1],
            [2, 'fundraiser.submitted', 'fundraiser', '2', '2026-10-02 10:00:00', 2],
            [2, 'fundraiser.approved', 'fundraiser', '2', '2026-10-04 10:00:00', 1],
            [3, 'fundraiser.submitted', 'fundraiser', '3', '2026-10-28 10:00:00', 2],
            [5, 'fundraiser.submitted', 'fundraiser', '5', '2026-10-03 10:00:00', 2],
            [5, 'fundraiser.rejected', 'fundraiser', '5', '2026-10-03 12:00:00', 1],
            [4, 'payment.refunded', 'donation', '4', '2026-10-20 10:00:00', 1],
            [0, 'settings.site_updated', 'settings', '', '2026-10-22 10:00:00', 1],
            [0, 'account.login', 'user', '3', '2026-10-23 10:00:00', 3],
            [0, 'account.login', 'user', '3', '2026-10-24 10:00:00', 3],
            [0, 'account.login_failed', 'user', '', '2026-10-24 11:00:00', null],
        ];
        foreach ($audit as [, $action, $type, $eid, $when, $user]) {
            $db->insert('audit_logs', ['user_id' => $user, 'action' => $action, 'entity_type' => $type, 'entity_id' => $eid === '' ? null : $eid,
                'safe_metadata_json' => '{"note":"x"}', 'ip_address' => '127.0.0.1', 'created_at' => $when]);
        }
    }
}
