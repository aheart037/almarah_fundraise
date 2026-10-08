<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Reads and writes fundraisers.
 *
 * Totals always come from the v_fundraiser_totals view, which counts only
 * verified completed donations — never from a cached column that a pending or
 * failed payment could inflate.
 */
final class FundraiserRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT f.*, t.raised_minor, t.donor_count, t.pending_minor, t.pending_count,
                    u.first_name, u.last_name, u.email AS owner_email,
                    c.name AS category_name, c.slug AS category_slug,
                    cp.title AS campaign_title, cp.slug AS campaign_slug
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             LEFT JOIN users u ON u.id = f.owner_user_id
             LEFT JOIN fundraiser_categories c ON c.id = f.category_id
             LEFT JOIN campaigns cp ON cp.id = f.campaign_id
             WHERE f.id = :id AND f.deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** Public lookup: only published fundraisers are visible to visitors. */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT f.*, t.raised_minor, t.donor_count, t.pending_minor, t.pending_count,
                    u.first_name, u.last_name,
                    c.name AS category_name, c.slug AS category_slug,
                    cp.title AS campaign_title, cp.slug AS campaign_slug
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             LEFT JOIN users u ON u.id = f.owner_user_id
             LEFT JOIN fundraiser_categories c ON c.id = f.category_id
             LEFT JOIN campaigns cp ON cp.id = f.campaign_id
             WHERE f.slug = :slug AND f.deleted_at IS NULL
               AND f.status = 'published' AND f.approval_status = 'approved'",
            ['slug' => $slug]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = 'SELECT id FROM fundraisers WHERE slug = :slug AND deleted_at IS NULL';
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
        return $this->db->insert('fundraisers', [
            'owner_user_id'     => $data['owner_user_id'],
            'campaign_id'       => $data['campaign_id'] ?: null,
            'category_id'       => $data['category_id'] ?: null,
            'title'             => $data['title'],
            'slug'              => $data['slug'],
            'story'             => $data['story'],
            'impact_statement'  => $data['impact_statement'] ?? null,
            'cover_image_path'  => $data['cover_image_path'] ?? null,
            'goal_minor'        => $data['goal_minor'],
            'currency'          => $data['currency'] ?? 'PKR',
            'start_at'          => $data['start_at'] ?? null,
            'end_at'            => $data['end_at'] ?? null,
            'status'            => $data['status'] ?? 'draft',
            'approval_status'   => $data['approval_status'] ?? 'pending',
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $allowed = [
            'campaign_id', 'category_id', 'title', 'slug', 'story', 'impact_statement',
            'cover_image_path', 'goal_minor', 'currency', 'start_at', 'end_at',
            'status', 'approval_status', 'rejection_reason', 'featured', 'published_at',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }

        $this->db->update('fundraisers', $payload, 'id = :id', ['id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->run('UPDATE fundraisers SET deleted_at = UTC_TIMESTAMP() WHERE id = :id', ['id' => $id]);
    }

    /**
     * Public listing with search, filters, sorting and pagination.
     *
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginatePublished(array $filters, int $page, int $perPage): array
    {
        $where = [
            "f.deleted_at IS NULL",
            "f.status = 'published'",
            "f.approval_status = 'approved'",
        ];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(f.title LIKE :q OR f.story LIKE :q2)';
            $params['q'] = '%' . $search . '%';
            $params['q2'] = '%' . $search . '%';
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'f.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['campaign_id'])) {
            $where[] = 'f.campaign_id = :campaign_id';
            $params['campaign_id'] = (int) $filters['campaign_id'];
        }

        $state = (string) ($filters['state'] ?? '');
        if ($state === 'active') {
            $where[] = '(f.end_at IS NULL OR f.end_at >= UTC_DATE())';
        } elseif ($state === 'completed') {
            $where[] = 'f.end_at IS NOT NULL AND f.end_at < UTC_DATE()';
        } elseif ($state === 'ending_soon') {
            $where[] = 'f.end_at IS NOT NULL AND f.end_at BETWEEN UTC_DATE() AND DATE_ADD(UTC_DATE(), INTERVAL 14 DAY)';
        }

        $clause = implode(' AND ', $where);

        $total = $this->db->int("SELECT COUNT(*) FROM fundraisers f WHERE {$clause}", $params);

        // Sorting is an allowlist — never interpolate user input.
        $orderBy = match ((string) ($filters['sort'] ?? 'newest')) {
            'most_raised'   => 'COALESCE(t.raised_minor, 0) DESC, f.id DESC',
            'goal_percent'  => 'CASE WHEN f.goal_minor > 0 THEN COALESCE(t.raised_minor,0)/f.goal_minor ELSE 0 END DESC, f.id DESC',
            'donors'        => 'COALESCE(t.donor_count, 0) DESC, f.id DESC',
            'ending_soon'   => 'f.end_at IS NULL, f.end_at ASC',
            'oldest'        => 'f.id ASC',
            default         => 'f.published_at DESC, f.id DESC',
        };

        $offset = max(0, ($page - 1) * $perPage);

        $rows = $this->db->select(
            "SELECT f.id, f.title, f.slug, f.story, f.impact_statement, f.cover_image_path,
                    f.goal_minor, f.currency, f.end_at, f.featured, f.published_at, f.created_at,
                    COALESCE(t.raised_minor, 0) AS raised_minor,
                    COALESCE(t.donor_count, 0) AS donor_count,
                    c.name AS category_name, c.slug AS category_slug,
                    u.first_name, u.last_name
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             LEFT JOIN fundraiser_categories c ON c.id = f.category_id
             LEFT JOIN users u ON u.id = f.owner_user_id
             WHERE {$clause}
             ORDER BY {$orderBy}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function topByRaised(int $limit = 5): array
    {
        return $this->db->select(
            "SELECT f.id, f.title, f.slug, f.impact_statement, f.cover_image_path, f.goal_minor,
                    f.currency, f.end_at, f.featured,
                    COALESCE(t.raised_minor, 0) AS raised_minor,
                    COALESCE(t.donor_count, 0) AS donor_count,
                    c.name AS category_name
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             LEFT JOIN fundraiser_categories c ON c.id = f.category_id
             WHERE f.status = 'published' AND f.approval_status = 'approved' AND f.deleted_at IS NULL
             ORDER BY raised_minor DESC, f.id DESC
             LIMIT " . (int) $limit
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 3): array
    {
        return $this->db->select(
            "SELECT f.id, f.title, f.slug, f.story, f.impact_statement, f.cover_image_path, f.goal_minor,
                    f.currency, f.end_at,
                    COALESCE(t.raised_minor, 0) AS raised_minor,
                    COALESCE(t.donor_count, 0) AS donor_count,
                    c.name AS category_name
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             LEFT JOIN fundraiser_categories c ON c.id = f.category_id
             WHERE f.status = 'published' AND f.approval_status = 'approved' AND f.deleted_at IS NULL
               AND f.featured = 1
             ORDER BY f.published_at DESC LIMIT " . (int) $limit
        );
    }

    /**
     * Fundraiser listing for a dashboard or the admin area.
     *
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginateForAdmin(array $filters, int $page, int $perPage): array
    {
        $where = ['f.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['owner_user_id'])) {
            $where[] = 'f.owner_user_id = :owner';
            $params['owner'] = (int) $filters['owner_user_id'];
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(f.title LIKE :q OR f.slug LIKE :q2 OR u.email LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        if (!empty($filters['status']) && in_array($filters['status'], $this->statuses(), true)) {
            $where[] = 'f.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['approval_status']) && in_array($filters['approval_status'], ['pending', 'approved', 'rejected', 'changes_requested'], true)) {
            $where[] = 'f.approval_status = :approval';
            $params['approval'] = $filters['approval_status'];
        }

        $clause = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        $total = $this->db->int(
            "SELECT COUNT(*) FROM fundraisers f LEFT JOIN users u ON u.id = f.owner_user_id WHERE {$clause}",
            $params
        );

        $rows = $this->db->select(
            "SELECT f.*, COALESCE(t.raised_minor, 0) AS raised_minor, COALESCE(t.donor_count, 0) AS donor_count,
                    COALESCE(t.pending_count, 0) AS pending_count,
                    u.first_name, u.last_name, u.email AS owner_email,
                    c.name AS category_name
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             LEFT JOIN users u ON u.id = f.owner_user_id
             LEFT JOIN fundraiser_categories c ON c.id = f.category_id
             WHERE {$clause}
             ORDER BY f.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function forOwner(int $ownerId): array
    {
        return $this->db->select(
            "SELECT f.*, COALESCE(t.raised_minor, 0) AS raised_minor, COALESCE(t.donor_count, 0) AS donor_count,
                    COALESCE(t.pending_count, 0) AS pending_count,
                    c.name AS category_name
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             LEFT JOIN fundraiser_categories c ON c.id = f.category_id
             WHERE f.owner_user_id = :owner AND f.deleted_at IS NULL
             ORDER BY f.id DESC",
            ['owner' => $ownerId]
        );
    }

    public function countByStatus(string $status): int
    {
        return $this->db->int(
            'SELECT COUNT(*) FROM fundraisers WHERE status = :s AND deleted_at IS NULL',
            ['s' => $status]
        );
    }

    public function countPendingApproval(): int
    {
        return $this->db->int(
            "SELECT COUNT(*) FROM fundraisers
             WHERE deleted_at IS NULL AND approval_status = 'pending'
               AND status IN ('pending_review','draft','changes_requested')"
        );
    }

    public function countAll(): int
    {
        return $this->db->int('SELECT COUNT(*) FROM fundraisers WHERE deleted_at IS NULL');
    }

    // --- updates ------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function createUpdate(array $data): int
    {
        return $this->db->insert('fundraiser_updates', [
            'fundraiser_id'  => $data['fundraiser_id'],
            'author_user_id' => $data['author_user_id'] ?? null,
            'title'          => $data['title'],
            'body'           => $data['body'],
            'image_path'     => $data['image_path'] ?? null,
            'status'         => $data['status'] ?? 'published',
            'published_at'   => $data['published_at'] ?? gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function updatesFor(int $fundraiserId, bool $publishedOnly = true): array
    {
        $sql = 'SELECT * FROM fundraiser_updates WHERE fundraiser_id = :id';
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $sql .= ' ORDER BY published_at DESC, id DESC';

        return $this->db->select($sql, ['id' => $fundraiserId]);
    }

    public function findUpdate(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM fundraiser_updates WHERE id = :id', ['id' => $id]);
    }

    public function updateUpdate(int $id, array $data): void
    {
        $this->db->update('fundraiser_updates', $data, 'id = :id', ['id' => $id]);
    }

    public function deleteUpdate(int $id): void
    {
        $this->db->delete('fundraiser_updates', 'id = :id', ['id' => $id]);
    }

    public function countUpdates(int $fundraiserId): int
    {
        return $this->db->int('SELECT COUNT(*) FROM fundraiser_updates WHERE fundraiser_id = :id', ['id' => $fundraiserId]);
    }

    // --- categories ---------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function categories(): array
    {
        return $this->db->select("SELECT * FROM fundraiser_categories WHERE status = 'active' ORDER BY sort_order, name");
    }

    /** @return array<int,array<string,mixed>> */
    public function allCategories(): array
    {
        return $this->db->select('SELECT * FROM fundraiser_categories ORDER BY sort_order, name');
    }

    public function findCategory(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM fundraiser_categories WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int,string> */
    public function statuses(): array
    {
        return ['draft', 'pending_review', 'changes_requested', 'approved', 'published', 'paused', 'rejected', 'archived'];
    }
}
