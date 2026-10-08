<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class TeamRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT t.*, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count,
                    u.first_name, u.last_name, u.email AS owner_email,
                    cp.title AS campaign_title, cp.slug AS campaign_slug
             FROM teams t
             LEFT JOIN v_team_totals v ON v.team_id = t.id
             LEFT JOIN users u ON u.id = t.owner_user_id
             LEFT JOIN campaigns cp ON cp.id = t.campaign_id
             WHERE t.id = :id AND t.deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT t.*, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count,
                    u.first_name, u.last_name
             FROM teams t
             LEFT JOIN v_team_totals v ON v.team_id = t.id
             LEFT JOIN users u ON u.id = t.owner_user_id
             WHERE t.slug = :slug AND t.status = 'active' AND t.deleted_at IS NULL",
            ['slug' => $slug]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = 'SELECT id FROM teams WHERE slug = :slug AND deleted_at IS NULL';
        $params = ['slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return $this->db->selectOne($sql, $params);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert('teams', [
            'owner_user_id' => $data['owner_user_id'],
            'campaign_id'   => $data['campaign_id'] ?: null,
            'name'          => $data['name'],
            'slug'          => $data['slug'],
            'description'   => $data['description'] ?? null,
            'goal_minor'    => $data['goal_minor'] ?? null,
            'currency'      => $data['currency'] ?? 'PKR',
            'status'        => $data['status'] ?? 'active',
        ]);
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['campaign_id', 'name', 'slug', 'description', 'goal_minor', 'currency', 'status'];
        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }
        $this->db->update('teams', $payload, 'id = :id', ['id' => $id]);
    }

    public function addMember(int $teamId, int $userId, string $role = 'member'): bool
    {
        if (!in_array($role, ['owner', 'member'], true)) {
            $role = 'member';
        }
        return $this->db->insertIgnore('team_members', [
            'team_id' => $teamId,
            'user_id' => $userId,
            'role'    => $role,
        ]);
    }

    public function removeMember(int $teamId, int $userId): void
    {
        $this->db->delete('team_members', 'team_id = :t AND user_id = :u', ['t' => $teamId, 'u' => $userId]);
    }

    public function isMember(int $teamId, int $userId): bool
    {
        return $this->db->int(
            'SELECT COUNT(*) FROM team_members WHERE team_id = :t AND user_id = :u',
            ['t' => $teamId, 'u' => $userId]
        ) > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function members(int $teamId): array
    {
        return $this->db->select(
            'SELECT tm.role, tm.joined_at, u.id, u.first_name, u.last_name, u.email, u.avatar_path
             FROM team_members tm JOIN users u ON u.id = tm.user_id
             WHERE tm.team_id = :t ORDER BY tm.role DESC, tm.joined_at ASC',
            ['t' => $teamId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId): array
    {
        return $this->db->select(
            'SELECT t.*, tm.role, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count
             FROM team_members tm
             JOIN teams t ON t.id = tm.team_id
             LEFT JOIN v_team_totals v ON v.team_id = t.id
             WHERE tm.user_id = :u AND t.deleted_at IS NULL
             ORDER BY t.id DESC',
            ['u' => $userId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function topByRaised(int $limit = 5): array
    {
        return $this->db->select(
            "SELECT t.id, t.name, t.slug, t.description, COALESCE(v.raised_minor,0) AS raised_minor,
                    COALESCE(v.donor_count,0) AS donor_count, t.goal_minor, t.currency
             FROM teams t
             LEFT JOIN v_team_totals v ON v.team_id = t.id
             WHERE t.status = 'active' AND t.deleted_at IS NULL
             ORDER BY raised_minor DESC, t.id DESC LIMIT " . (int) $limit
        );
    }

    /** @return array{rows:array<int,array<string,mixed>>, total:int} */
    public function paginate(int $page, int $perPage, string $financial = ''): array
    {
        $where = ["t.status = 'active'", 't.deleted_at IS NULL'];
        $params = [];

        if ($financial === 'fundraising') {
            $where[] = 'COALESCE(v.raised_minor, 0) > 0';
        }

        $clause = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        $total = $this->db->int(
            "SELECT COUNT(*) FROM teams t LEFT JOIN v_team_totals v ON v.team_id = t.id WHERE {$clause}",
            $params
        );

        $rows = $this->db->select(
            "SELECT t.*, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count,
                    u.first_name, u.last_name
             FROM teams t
             LEFT JOIN v_team_totals v ON v.team_id = t.id
             LEFT JOIN users u ON u.id = t.owner_user_id
             WHERE {$clause}
             ORDER BY raised_minor DESC, t.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function countActive(): int
    {
        return $this->db->int("SELECT COUNT(*) FROM teams WHERE status = 'active' AND deleted_at IS NULL");
    }

    // --- invitations --------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function createInvitation(array $data): int
    {
        return $this->db->insert('team_invitations', [
            'team_id'    => $data['team_id'],
            'email'      => mb_strtolower($data['email']),
            'token_hash' => $data['token_hash'],
            'invited_by' => $data['invited_by'] ?? null,
            'expires_at' => $data['expires_at'],
        ]);
    }

    public function findValidInvitation(string $tokenHash): ?array
    {
        return $this->db->selectOne(
            "SELECT ti.*, t.name AS team_name, t.slug AS team_slug
             FROM team_invitations ti JOIN teams t ON t.id = ti.team_id
             WHERE ti.token_hash = :h AND ti.status = 'pending' AND ti.expires_at > UTC_TIMESTAMP()",
            ['h' => $tokenHash]
        );
    }

    public function acceptInvitation(int $id): void
    {
        $this->db->update('team_invitations', [
            'status'      => 'accepted',
            'accepted_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function invitationsFor(int $teamId): array
    {
        return $this->db->select(
            'SELECT * FROM team_invitations WHERE team_id = :t ORDER BY id DESC',
            ['t' => $teamId]
        );
    }

    public function revokeInvitation(int $id): void
    {
        $this->db->update('team_invitations', ['status' => 'revoked'], 'id = :id', ['id' => $id]);
    }
}
