<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Append-only audit trail. Metadata is redacted before storage so that
 * credentials never reach the audit table.
 */
final class AuditService
{
    public function __construct(private Database $db, private Logger $logger)
    {
    }

    /** @param array<string,mixed> $metadata */
    public function log(
        string $action,
        string $entityType = '',
        string|int|null $entityId = null,
        array $metadata = [],
        ?int $userId = null
    ): void {
        $safe = $this->logger->redact($metadata);

        try {
            $this->db->insert('audit_logs', [
                'user_id'           => $userId,
                'action'            => $action,
                'entity_type'       => $entityType !== '' ? $entityType : null,
                'entity_id'         => $entityId === null ? null : (string) $entityId,
                'safe_metadata_json'=> json_encode($safe, JSON_UNESCAPED_SLASHES) ?: '{}',
                'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'        => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the user-facing flow.
            $this->logger->warning('Audit log write failed', ['reason' => $e->getMessage(), 'action' => $action]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 50): array
    {
        return $this->db->select(
            'SELECT id, action, entity_type, entity_id, safe_metadata_json, ip_address, created_at
             FROM audit_logs WHERE user_id = :id ORDER BY id DESC LIMIT ' . (int) $limit,
            ['id' => $userId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->db->select(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.safe_metadata_json, a.ip_address,
                    a.created_at, u.email AS user_email, u.first_name, u.last_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT ' . (int) $limit
        );
    }
}
