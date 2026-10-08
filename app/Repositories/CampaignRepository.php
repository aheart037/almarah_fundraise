<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CampaignRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT c.*, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count
             FROM campaigns c LEFT JOIN v_campaign_totals v ON v.campaign_id = c.id
             WHERE c.id = :id AND c.deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function findActiveBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT c.*, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count
             FROM campaigns c LEFT JOIN v_campaign_totals v ON v.campaign_id = c.id
             WHERE c.slug = :slug AND c.status = 'active' AND c.deleted_at IS NULL",
            ['slug' => $slug]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = 'SELECT id FROM campaigns WHERE slug = :slug AND deleted_at IS NULL';
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
        return $this->db->insert('campaigns', [
            'title'       => $data['title'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'image_path'  => $data['image_path'] ?? null,
            'status'      => $data['status'] ?? 'active',
            'start_at'    => $data['start_at'] ?? null,
            'end_at'      => $data['end_at'] ?? null,
            'featured'    => !empty($data['featured']) ? 1 : 0,
            'created_by'  => $data['created_by'] ?? null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['title', 'slug', 'description', 'image_path', 'status', 'start_at', 'end_at', 'featured'];
        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }
        $this->db->update('campaigns', $payload, 'id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function active(int $limit = 50): array
    {
        return $this->db->select(
            "SELECT c.*, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count
             FROM campaigns c LEFT JOIN v_campaign_totals v ON v.campaign_id = c.id
             WHERE c.status = 'active' AND c.deleted_at IS NULL
             ORDER BY c.featured DESC, c.id DESC LIMIT " . (int) $limit
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->select(
            "SELECT c.*, COALESCE(v.raised_minor,0) AS raised_minor
             FROM campaigns c LEFT JOIN v_campaign_totals v ON v.campaign_id = c.id
             WHERE c.deleted_at IS NULL ORDER BY c.id DESC"
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 3): array
    {
        return $this->db->select(
            "SELECT c.*, COALESCE(v.raised_minor,0) AS raised_minor, COALESCE(v.donor_count,0) AS donor_count
             FROM campaigns c LEFT JOIN v_campaign_totals v ON v.campaign_id = c.id
             WHERE c.status = 'active' AND c.deleted_at IS NULL
             ORDER BY c.featured DESC, c.id DESC LIMIT " . (int) $limit
        );
    }

    public function countActive(): int
    {
        return $this->db->int("SELECT COUNT(*) FROM campaigns WHERE status = 'active' AND deleted_at IS NULL");
    }

    /** @return array<int,array<string,mixed>> */
    public function fundraisersFor(int $campaignId, int $limit = 12): array
    {
        return $this->db->select(
            "SELECT f.id, f.title, f.slug, f.cover_image_path, f.goal_minor, f.currency, f.impact_statement,
                    COALESCE(t.raised_minor,0) AS raised_minor, COALESCE(t.donor_count,0) AS donor_count
             FROM fundraisers f
             LEFT JOIN v_fundraiser_totals t ON t.fundraiser_id = f.id
             WHERE f.campaign_id = :id AND f.status = 'published' AND f.approval_status = 'approved'
               AND f.deleted_at IS NULL
             ORDER BY raised_minor DESC LIMIT " . (int) $limit,
            ['id' => $campaignId]
        );
    }
}
