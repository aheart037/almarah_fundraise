<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\AuthService;

/**
 * Read-only audit trail. Entries are appended by AuditService and are never
 * edited or deleted from the interface.
 */
final class AuditController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private Database $db
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));
        $entity = (string) $request->input('entity', '');

        $where = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(al.action LIKE :q OR al.entity_type LIKE :q2 OR al.entity_id LIKE :q3 OR u.email LIKE :q4)';
            for ($i = 1; $i <= 4; $i++) {
                $params['q' . ($i === 1 ? '' : $i)] = '%' . $search . '%';
            }
        }

        if ($entity !== '') {
            $where[] = 'al.entity_type = :entity';
            $params['entity'] = $entity;
        }

        $clause = implode(' AND ', $where);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $offset = max(0, ($page - 1) * $perPage);

        $total = $this->db->int("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE {$clause}", $params);

        $rows = $this->db->select(
            "SELECT al.*, u.email AS user_email
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE {$clause}
             ORDER BY al.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $entities = $this->db->select('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type ASC');

        return $this->render('admin/audit', [
            'pageTitle' => 'Audit log',
            'entries'   => $rows,
            'total'     => $total,
            'page'      => $page,
            'lastPage'  => max(1, (int) ceil($total / $perPage)),
            'filters'   => ['q' => $search, 'entity' => $entity],
            'entities'  => $entities,
        ], 'layouts/admin');
    }
}
