<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL',
            ['email' => mb_strtolower(trim($email))]
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert('users', [
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => mb_strtolower(trim((string) $data['email'])),
            'password_hash' => $data['password_hash'],
            'phone'         => $data['phone'] ?? null,
            'status'        => $data['status'] ?? 'active',
            'avatar_path'   => $data['avatar_path'] ?? null,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'marketing_opt_in'  => !empty($data['marketing_opt_in']) ? 1 : 0,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('users', $data, 'id = :id', ['id' => $id]);
    }

    public function markEmailVerified(int $id): void
    {
        $this->db->run(
            'UPDATE users SET email_verified_at = UTC_TIMESTAMP(), status = :status WHERE id = :id AND email_verified_at IS NULL',
            ['id' => $id, 'status' => 'active']
        );
    }

    public function touchLogin(int $id, string $ip): void
    {
        $this->db->update('users', [
            'last_login_at' => gmdate('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ], 'id = :id', ['id' => $id]);
    }

    /** @return array<int,string> */
    public function rolesFor(int $userId): array
    {
        $rows = $this->db->select(
            'SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id',
            ['id' => $userId]
        );
        return array_map(static fn (array $r): string => (string) $r['name'], $rows);
    }

    public function assignRole(int $userId, string $roleName): void
    {
        $roleId = $this->db->scalar('SELECT id FROM roles WHERE name = :n', ['n' => $roleName]);
        if ($roleId === null) {
            return;
        }
        $this->db->insertIgnore('user_roles', [
            'user_id' => $userId,
            'role_id' => (int) $roleId,
        ]);
    }

    public function removeRole(int $userId, string $roleName): void
    {
        $this->db->run(
            'DELETE ur FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :uid AND r.name = :n',
            ['uid' => $userId, 'n' => $roleName]
        );
    }

    public function hasRole(int $userId, string $roleName): bool
    {
        return $this->db->int(
            'SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND r.name = :n',
            ['uid' => $userId, 'n' => $roleName]
        ) > 0;
    }

    /** @return array{rows:array<int,array<string,mixed>>, total:int} */
    public function paginate(string $search, string $status, int $page, int $perPage): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.email LIKE :s OR u.first_name LIKE :s2 OR u.last_name LIKE :s3)';
            $like = '%' . $search . '%';
            $params['s'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
        }

        if ($status !== '' && in_array($status, ['active', 'suspended', 'pending'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }

        $clause = implode(' AND ', $where);
        $total = $this->db->int("SELECT COUNT(*) FROM users u WHERE {$clause}", $params);

        $offset = max(0, ($page - 1) * $perPage);

        $rows = $this->db->select(
            "SELECT u.*, GROUP_CONCAT(r.name) AS role_names
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE {$clause}
             GROUP BY u.id
             ORDER BY u.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function countByStatus(string $status): int
    {
        return $this->db->int('SELECT COUNT(*) FROM users WHERE status = :s AND deleted_at IS NULL', ['s' => $status]);
    }

    public function countAll(): int
    {
        return $this->db->int('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 8): array
    {
        return $this->db->select(
            'SELECT id, first_name, last_name, email, status, email_verified_at, created_at
             FROM users WHERE deleted_at IS NULL ORDER BY id DESC LIMIT ' . (int) $limit
        );
    }

    // --- email verification tokens -----------------------------------------

    public function createVerificationToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        // Invalidate previous unused tokens for this user.
        $this->db->run('DELETE FROM email_verification_tokens WHERE user_id = :id', ['id' => $userId]);
        $this->db->insert('email_verification_tokens', [
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findValidVerificationToken(string $tokenHash): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM email_verification_tokens
             WHERE token_hash = :h AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            ['h' => $tokenHash]
        );
    }

    public function consumeVerificationToken(int $id): void
    {
        $this->db->update('email_verification_tokens', ['used_at' => gmdate('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    // --- password reset tokens ---------------------------------------------

    public function createPasswordResetToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $this->db->run('DELETE FROM password_reset_tokens WHERE user_id = :id', ['id' => $userId]);
        $this->db->insert('password_reset_tokens', [
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findValidResetToken(string $tokenHash): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM password_reset_tokens
             WHERE token_hash = :h AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            ['h' => $tokenHash]
        );
    }

    public function consumeResetToken(int $id): void
    {
        $this->db->update('password_reset_tokens', ['used_at' => gmdate('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    // --- email preferences --------------------------------------------------

    /** @return array<string,bool> */
    public function emailPreferences(int $userId): array
    {
        $rows = $this->db->select('SELECT event_key, enabled FROM email_preferences WHERE user_id = :id', ['id' => $userId]);
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['event_key']] = (bool) $row['enabled'];
        }
        return $out;
    }

    public function setEmailPreference(int $userId, string $eventKey, bool $enabled): void
    {
        $exists = $this->db->int(
            'SELECT COUNT(*) FROM email_preferences WHERE user_id = :id AND event_key = :k',
            ['id' => $userId, 'k' => $eventKey]
        );

        if ($exists > 0) {
            $this->db->update(
                'email_preferences',
                ['enabled' => $enabled ? 1 : 0],
                'user_id = :id AND event_key = :k',
                ['id' => $userId, 'k' => $eventKey]
            );
            return;
        }

        $this->db->insert('email_preferences', [
            'user_id'   => $userId,
            'event_key' => $eventKey,
            'enabled'   => $enabled ? 1 : 0,
        ]);
    }
}
