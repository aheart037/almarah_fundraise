<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class DonorRepository
{
    public function __construct(private Database $db)
    {
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM donors WHERE email = :e ORDER BY id ASC LIMIT 1',
            ['e' => mb_strtolower(trim($email))]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM donors WHERE id = :id', ['id' => $id]);
    }

    /**
     * Find or create a donor record, keeping the most recent name and phone.
     */
    public function findOrCreate(string $name, string $email, ?string $phone = null, ?int $userId = null): int
    {
        $email = mb_strtolower(trim($email));
        $existing = $this->findByEmail($email);

        if ($existing !== null) {
            $this->db->update('donors', [
                'name'    => $name !== '' ? $name : $existing['name'],
                'phone'   => $phone !== null && $phone !== '' ? $phone : $existing['phone'],
                'user_id' => $userId ?? $existing['user_id'],
            ], 'id = :id', ['id' => (int) $existing['id']]);

            return (int) $existing['id'];
        }

        return $this->db->insert('donors', [
            'user_id' => $userId,
            'name'    => $name !== '' ? $name : 'Anonymous',
            'email'   => $email,
            'phone'   => $phone !== null && $phone !== '' ? $phone : null,
        ]);
    }

    /** @return array{rows:array<int,array<string,mixed>>, total:int} */
    public function paginate(string $search, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(name LIKE :s OR email LIKE :s2)';
            $params['s'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
        }

        $clause = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        $total = $this->db->int("SELECT COUNT(*) FROM donors WHERE {$clause}", $params);

        $rows = $this->db->select(
            "SELECT dn.*, 
                    COALESCE(SUM(CASE WHEN d.status = 'completed' THEN d.amount_minor ELSE 0 END), 0) AS given_minor,
                    COUNT(DISTINCT CASE WHEN d.status = 'completed' THEN d.id END) AS donation_count
             FROM donors dn
             LEFT JOIN donations d ON d.donor_id = dn.id
             WHERE {$clause}
             GROUP BY dn.id
             ORDER BY given_minor DESC, dn.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function countAll(): int
    {
        return $this->db->int('SELECT COUNT(*) FROM donors');
    }
}
