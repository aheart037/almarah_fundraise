<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Payment transaction persistence: registration, callbacks, refunds.
 */
final class PaymentRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $data */
    public function createTransaction(array $data): int
    {
        return $this->db->insert('payment_transactions', [
            'donation_id'               => $data['donation_id'],
            'gateway_id'                => $data['gateway_id'],
            'environment'               => $data['environment'],
            'merchant_order_id'         => $data['merchant_order_id'],
            'provider_transaction_id'   => $data['provider_transaction_id'] ?? null,
            'provider_unique_id'        => $data['provider_unique_id'] ?? null,
            'provider_order_id'         => $data['provider_order_id'] ?? null,
            'callback_state_hash'       => $data['callback_state_hash'],
            'callback_state_expires_at' => $data['callback_state_expires_at'],
            'amount_minor'              => $data['amount_minor'],
            'currency'                  => $data['currency'],
            'status'                    => $data['status'] ?? 'pending',
            'request_reference'         => $data['request_reference'],
            'idempotency_key'           => $data['idempotency_key'],
            'registration_payload_hash' => $data['registration_payload_hash'] ?? null,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function updateTransaction(int $id, array $data): void
    {
        $allowed = [
            'provider_transaction_id', 'provider_unique_id', 'provider_order_id',
            'provider_response_code', 'provider_response_description', 'status',
            'amount_minor', 'currency', 'registered_at', 'callback_received_at',
            'finalized_at', 'completed_at', 'refunded_at', 'last_reconciled_at',
            'registration_payload_hash', 'environment',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }

        $this->db->update('payment_transactions', $payload, 'id = :id', ['id' => $id]);
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT pt.*, g.code AS gateway_code, g.name AS gateway_name
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             WHERE pt.id = :id',
            ['id' => $id]
        );
    }

    public function findByMerchantOrderId(string $merchantOrderId): ?array
    {
        return $this->db->selectOne(
            'SELECT pt.*, g.code AS gateway_code, g.name AS gateway_name
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             WHERE pt.merchant_order_id = :o',
            ['o' => $merchantOrderId]
        );
    }

    /**
     * Callback-state lookup. The presented state is hashed and matched against
     * the stored hash; the plaintext state is never persisted.
     */
    public function findByCallbackState(string $stateHash): ?array
    {
        return $this->db->selectOne(
            'SELECT pt.*, g.code AS gateway_code, g.name AS gateway_name
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             WHERE pt.callback_state_hash = :h',
            ['h' => $stateHash]
        );
    }

    public function findByProviderTransactionId(string $gatewayCode, string $providerTransactionId): ?array
    {
        return $this->db->selectOne(
            'SELECT pt.*, g.code AS gateway_code, g.name AS gateway_name
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             WHERE g.code = :code AND pt.provider_transaction_id = :txn',
            ['code' => $gatewayCode, 'txn' => $providerTransactionId]
        );
    }

    public function findByProviderOrderId(string $gatewayCode, string $providerOrderId): ?array
    {
        return $this->db->selectOne(
            'SELECT pt.*, g.code AS gateway_code, g.name AS gateway_name
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             WHERE g.code = :code AND pt.provider_order_id = :ord',
            ['code' => $gatewayCode, 'ord' => $providerOrderId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forDonation(int $donationId): array
    {
        return $this->db->select(
            'SELECT pt.*, g.code AS gateway_code, g.name AS gateway_name
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             WHERE pt.donation_id = :id ORDER BY pt.id DESC',
            ['id' => $donationId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingForReconciliation(int $limit = 50, int $olderThanMinutes = 10): array
    {
        return $this->db->select(
            "SELECT pt.*, g.code AS gateway_code
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             WHERE pt.status IN ('pending','processing')
               AND pt.registered_at IS NOT NULL
               AND pt.registered_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :mins MINUTE)
             ORDER BY pt.id ASC LIMIT " . (int) $limit,
            ['mins' => $olderThanMinutes]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['gateway'])) {
            $where[] = 'g.code = :gateway';
            $params['gateway'] = $filters['gateway'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'pt.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'pt.created_at >= :from';
            $params['from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'pt.created_at <= :to';
            $params['to'] = $filters['date_to'] . ' 23:59:59';
        }

        $clause = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        return $this->db->select(
            "SELECT pt.*, g.code AS gateway_code, g.name AS gateway_name, d.public_reference
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             JOIN donations d ON d.id = pt.donation_id
             WHERE {$clause}
             ORDER BY pt.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
    }

    public function countSearch(array $filters): int
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['gateway'])) {
            $where[] = 'g.code = :gateway';
            $params['gateway'] = $filters['gateway'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'pt.status = :status';
            $params['status'] = $filters['status'];
        }

        return $this->db->int(
            'SELECT COUNT(*) FROM payment_transactions pt JOIN gateways g ON g.id = pt.gateway_id
             WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    // --- callbacks ----------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function recordCallback(array $data): int
    {
        return $this->db->insert('payment_callbacks', [
            'payment_transaction_id'  => $data['payment_transaction_id'] ?? null,
            'gateway_id'              => $data['gateway_id'],
            'callback_method'         => $data['callback_method'],
            'raw_safe_payload_json'   => json_encode($data['safe_payload'] ?? [], JSON_UNESCAPED_SLASHES) ?: '{}',
            'received_transaction_id' => $data['received_transaction_id'] ?? null,
            'validation_result'       => $data['validation_result'],
            'reason'                  => $data['reason'] ?? null,
            'processed_at'            => $data['processed_at'] ?? gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function callbacksFor(int $transactionId): array
    {
        return $this->db->select(
            'SELECT * FROM payment_callbacks WHERE payment_transaction_id = :id ORDER BY id DESC',
            ['id' => $transactionId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recentCallbacks(int $limit = 40): array
    {
        return $this->db->select(
            'SELECT pc.*, g.code AS gateway_code FROM payment_callbacks pc
             JOIN gateways g ON g.id = pc.gateway_id
             ORDER BY pc.id DESC LIMIT ' . (int) $limit
        );
    }

    // --- refunds ------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function createRefund(array $data): int
    {
        return $this->db->insert('payment_refunds', [
            'payment_transaction_id' => $data['payment_transaction_id'],
            'donation_id'            => $data['donation_id'],
            'gateway_id'             => $data['gateway_id'],
            'amount_minor'           => $data['amount_minor'],
            'currency'               => $data['currency'],
            'status'                 => $data['status'] ?? 'pending',
            'idempotency_key'        => $data['idempotency_key'],
            'requested_by'           => $data['requested_by'] ?? null,
        ]);
    }

    public function updateRefund(int $id, array $data): void
    {
        $allowed = ['status', 'provider_response_code', 'provider_response_description', 'completed_at'];
        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }
        $this->db->update('payment_refunds', $payload, 'id = :id', ['id' => $id]);
    }

    public function findRefundByKey(string $idempotencyKey): ?array
    {
        return $this->db->selectOne('SELECT * FROM payment_refunds WHERE idempotency_key = :k', ['k' => $idempotencyKey]);
    }

    /** @return array<int,array<string,mixed>> */
    public function refundsFor(int $transactionId): array
    {
        return $this->db->select('SELECT * FROM payment_refunds WHERE payment_transaction_id = :id ORDER BY id DESC', ['id' => $transactionId]);
    }

    public function hasCompletedRefund(int $transactionId): bool
    {
        return $this->db->int(
            "SELECT COUNT(*) FROM payment_refunds WHERE payment_transaction_id = :id AND status = 'completed'",
            ['id' => $transactionId]
        ) > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT pt.*, g.code AS gateway_code, d.public_reference
             FROM payment_transactions pt
             JOIN gateways g ON g.id = pt.gateway_id
             JOIN donations d ON d.id = pt.donation_id
             ORDER BY pt.id DESC LIMIT ' . (int) $limit
        );
    }

    /** @return array<string,int> */
    public function statusCounts(): array
    {
        $rows = $this->db->select('SELECT status, COUNT(*) AS total FROM payment_transactions GROUP BY status');
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }
        return $out;
    }
}
