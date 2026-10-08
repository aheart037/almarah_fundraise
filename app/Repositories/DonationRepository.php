<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class DonationRepository
{
    public const RAISED_STATUS = 'completed';

    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert('donations', [
            'public_reference' => $data['public_reference'],
            'donor_id'         => $data['donor_id'] ?? null,
            'fundraiser_id'    => $data['fundraiser_id'] ?? null,
            'campaign_id'      => $data['campaign_id'] ?? null,
            'team_id'          => $data['team_id'] ?? null,
            'gateway_id'       => $data['gateway_id'],
            'amount_minor'     => $data['amount_minor'],
            'currency'         => $data['currency'] ?? 'PKR',
            'donor_message'    => $data['donor_message'] ?? null,
            'anonymous'        => !empty($data['anonymous']) ? 1 : 0,
            'status'           => $data['status'] ?? 'pending',
        ]);
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne($this->baseSelect() . ' WHERE d.id = :id', ['id' => $id]);
    }

    public function findByReference(string $reference): ?array
    {
        return $this->db->selectOne($this->baseSelect() . ' WHERE d.public_reference = :ref', ['ref' => $reference]);
    }

    private function baseSelect(): string
    {
        return 'SELECT d.*, g.code AS gateway_code, g.name AS gateway_name,
                       f.title AS fundraiser_title, f.slug AS fundraiser_slug,
                       dn.name AS donor_name, dn.email AS donor_email, dn.phone AS donor_phone
                FROM donations d
                LEFT JOIN gateways g ON g.id = d.gateway_id
                LEFT JOIN fundraisers f ON f.id = d.fundraiser_id
                LEFT JOIN donors dn ON dn.id = d.donor_id';
    }

    public function updateStatus(int $id, string $status, ?string $completedAt = null): void
    {
        $allowed = ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'abandoned'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid donation status.');
        }

        $payload = ['status' => $status];
        if ($status === 'completed') {
            $payload['completed_at'] = $completedAt ?? gmdate('Y-m-d H:i:s');
        }

        $this->db->update('donations', $payload, 'id = :id', ['id' => $id]);
    }

    public function setReceiptStatus(int $id, string $status): void
    {
        if (!in_array($status, ['not_sent', 'queued', 'sent', 'failed'], true)) {
            return;
        }
        $this->db->update('donations', ['receipt_email_status' => $status], 'id = :id', ['id' => $id]);
    }

    /**
     * Verified donations for a fundraiser, newest first. Anonymous donations
     * have their donor name masked at the display layer, not here.
     *
     * @return array<int,array<string,mixed>>
     */
    public function completedForFundraiser(int $fundraiserId, int $limit = 20): array
    {
        return $this->db->select(
            "SELECT d.public_reference, d.amount_minor, d.currency, d.donor_message, d.anonymous,
                    d.completed_at, d.created_at, dn.name AS donor_name
             FROM donations d
             LEFT JOIN donors dn ON dn.id = d.donor_id
             WHERE d.fundraiser_id = :id AND d.status = 'completed'
             ORDER BY d.completed_at DESC, d.id DESC
             LIMIT " . (int) $limit,
            ['id' => $fundraiserId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function completedForTeam(int $teamId, int $limit = 20): array
    {
        return $this->db->select(
            "SELECT d.public_reference, d.amount_minor, d.currency, d.donor_message, d.anonymous,
                    d.completed_at, dn.name AS donor_name, f.title AS fundraiser_title, f.slug AS fundraiser_slug
             FROM donations d
             LEFT JOIN donors dn ON dn.id = d.donor_id
             LEFT JOIN fundraisers f ON f.id = d.fundraiser_id
             WHERE d.team_id = :id AND d.status = 'completed'
             ORDER BY d.completed_at DESC, d.id DESC
             LIMIT " . (int) $limit,
            ['id' => $teamId]
        );
    }

    public function countForFundraiserByStatus(int $fundraiserId, string $status): int
    {
        return $this->db->int(
            'SELECT COUNT(*) FROM donations WHERE fundraiser_id = :id AND status = :s',
            ['id' => $fundraiserId, 's' => $status]
        );
    }

    public function sumForFundraiserByStatus(int $fundraiserId, string $status): int
    {
        return $this->db->int(
            'SELECT COALESCE(SUM(amount_minor), 0) FROM donations WHERE fundraiser_id = :id AND status = :s',
            ['id' => $fundraiserId, 's' => $status]
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>, total:int, sums:array<string,int>}
     */
    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(d.public_reference LIKE :q OR dn.name LIKE :q2 OR dn.email LIKE :q3
                         OR f.title LIKE :q4 OR pt.provider_transaction_id LIKE :q5
                         OR pt.merchant_order_id LIKE :q6)';
            $like = '%' . $filters['q'] . '%';
            for ($i = 1; $i <= 6; $i++) {
                $params['q' . ($i === 1 ? '' : $i)] = $like;
            }
        }

        if (!empty($filters['gateway']) && in_array($filters['gateway'], ['meezan', 'etisalat'], true)) {
            $where[] = 'g.code = :gateway';
            $params['gateway'] = $filters['gateway'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'abandoned'], true)) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['fundraiser_id'])) {
            $where[] = 'd.fundraiser_id = :fundraiser_id';
            $params['fundraiser_id'] = (int) $filters['fundraiser_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'd.created_at >= :from';
            $params['from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'd.created_at <= :to';
            $params['to'] = $filters['date_to'] . ' 23:59:59';
        }

        $clause = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        $base = "FROM donations d
                 LEFT JOIN donors dn ON dn.id = d.donor_id
                 LEFT JOIN gateways g ON g.id = d.gateway_id
                 LEFT JOIN fundraisers f ON f.id = d.fundraiser_id
                 LEFT JOIN payment_transactions pt ON pt.donation_id = d.id";

        $total = $this->db->int("SELECT COUNT(DISTINCT d.id) {$base} WHERE {$clause}", $params);

        $rows = $this->db->select(
            "SELECT d.*, dn.name AS donor_name, dn.email AS donor_email, g.code AS gateway_code,
                    f.title AS fundraiser_title, f.slug AS fundraiser_slug,
                    pt.provider_transaction_id, pt.merchant_order_id, pt.provider_response_code,
                    pt.environment
             {$base}
             WHERE {$clause}
             GROUP BY d.id
             ORDER BY d.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $sums = ['completed' => 0, 'pending' => 0, 'failed' => 0, 'refunded' => 0];
        $sumRows = $this->db->select("SELECT d.status, COALESCE(SUM(d.amount_minor),0) AS total {$base} WHERE {$clause} GROUP BY d.status", $params);
        foreach ($sumRows as $row) {
            $sums[(string) $row['status']] = (int) $row['total'];
        }

        return ['rows' => $rows, 'total' => $total, 'sums' => $sums];
    }

    /** @return array<string,int> */
    public function totalsByStatus(): array
    {
        $rows = $this->db->select('SELECT status, COALESCE(SUM(amount_minor),0) AS total, COUNT(*) AS count FROM donations GROUP BY status');
        $out = [];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $out[$status . '_minor'] = (int) $row['total'];
            $out[$status . '_count'] = (int) $row['count'];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 10): array
    {
        return $this->db->select(
            "SELECT d.*, dn.name AS donor_name, g.code AS gateway_code, f.title AS fundraiser_title
             FROM donations d
             LEFT JOIN donors dn ON dn.id = d.donor_id
             LEFT JOIN gateways g ON g.id = d.gateway_id
             LEFT JOIN fundraisers f ON f.id = d.fundraiser_id
             ORDER BY d.id DESC LIMIT " . (int) $limit
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forDonorEmail(string $email, int $limit = 50): array
    {
        return $this->db->select(
            "SELECT d.*, f.title AS fundraiser_title, f.slug AS fundraiser_slug, g.code AS gateway_code
             FROM donations d
             JOIN donors dn ON dn.id = d.donor_id
             LEFT JOIN fundraisers f ON f.id = d.fundraiser_id
             LEFT JOIN gateways g ON g.id = d.gateway_id
             WHERE dn.email = :email
             ORDER BY d.id DESC LIMIT " . (int) $limit,
            ['email' => mb_strtolower($email)]
        );
    }

    public function countCompleted(): int
    {
        return $this->db->int("SELECT COUNT(*) FROM donations WHERE status = 'completed'");
    }

    public function sumCompleted(): int
    {
        return $this->db->int("SELECT COALESCE(SUM(amount_minor),0) FROM donations WHERE status = 'completed'");
    }
}
