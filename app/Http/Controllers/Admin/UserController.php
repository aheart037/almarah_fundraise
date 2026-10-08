<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\DonorRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Mail\Mailer;

/**
 * User administration. Role changes are super-admin only and guarded against
 * removing the last super administrator.
 */
final class UserController extends Controller
{
    private const ASSIGNABLE_ROLES = ['super_admin', 'admin', 'fundraiser', 'donor'];

    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private UserRepository $users,
        private DonorRepository $donors,
        private AuditService $audit,
        private Mailer $mailer,
        private Database $db
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $role = (string) $request->input('role', '');

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = $this->users->paginate($search, $status, $page, $perPage);

        $rows = $result['rows'];

        if ($role !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($role): bool {
                $roles = array_filter(array_map('trim', explode(',', (string) ($row['roles'] ?? ''))));
                return in_array($role, $roles, true);
            }));
        }

        return $this->render('admin/users/index', [
            'pageTitle' => 'Users',
            'users'     => $rows,
            'total'     => $result['total'],
            'page'      => $page,
            'lastPage'  => max(1, (int) ceil($result['total'] / $perPage)),
            'filters'   => ['q' => $search, 'status' => $status, 'role' => $role],
            'counts'    => [
                'active'    => $this->users->countByStatus('active'),
                'suspended' => $this->users->countByStatus('suspended'),
                'all'       => $this->users->countAll(),
            ],
        ], 'layouts/admin');
    }

    public function show(Request $request): Response
    {
        $user = $this->requireUser((int) $request->routeParam('id', 0));
        $userId = (int) $user['id'];

        $donations = $this->db->select(
            "SELECT d.public_reference, d.amount_minor, d.currency, d.status, d.created_at
             FROM donations d JOIN donors dn ON dn.id = d.donor_id
             WHERE dn.user_id = :uid ORDER BY d.id DESC LIMIT 15",
            ['uid' => $userId]
        );

        return $this->render('admin/users/show', [
            'pageTitle'      => (string) $user['email'],
            'user'           => $user,
            'roles'          => $this->users->rolesFor($userId),
            'availableRoles' => self::ASSIGNABLE_ROLES,
            'fundraisers'    => app(\App\Repositories\FundraiserRepository::class)->forOwner($userId),
            'donations'      => $donations,
            'auditTrail'     => $this->audit->forUser($userId, 25),
            'isSelf'         => $userId === (int) $this->auth->id(),
        ], 'layouts/admin');
    }

    public function suspend(Request $request): Response
    {
        $user = $this->requireUser((int) $request->routeParam('id', 0));
        $userId = (int) $user['id'];

        if ($userId === (int) $this->auth->id()) {
            $this->flashError('You cannot suspend your own account.');
            return $this->redirect('/admin/users/' . $userId);
        }

        $validator = Validator::make($request->all(), ['reason' => 'required|string|min:5|max:500']);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $reason = (string) $request->input('reason');

        $this->users->update($userId, ['status' => 'suspended']);
        $this->audit->log('user.suspended', 'user', (string) $userId, ['reason' => $reason], (int) $this->auth->id());

        $this->mailer->accountSuspended((string) $user['email'], (string) $user['first_name'], $reason);

        $this->flashSuccess('Account suspended and the user has been notified.');
        return $this->redirect('/admin/users/' . $userId);
    }

    public function reactivate(Request $request): Response
    {
        $user = $this->requireUser((int) $request->routeParam('id', 0));
        $userId = (int) $user['id'];

        $this->users->update($userId, ['status' => 'active']);
        $this->audit->log('user.reactivated', 'user', (string) $userId, [], (int) $this->auth->id());

        $this->mailer->accountReactivated((string) $user['email'], (string) $user['first_name']);

        $this->flashSuccess('Account reactivated.');
        return $this->redirect('/admin/users/' . $userId);
    }

    public function forceVerify(Request $request): Response
    {
        $user = $this->requireUser((int) $request->routeParam('id', 0));

        $this->users->markEmailVerified((int) $user['id']);
        $this->audit->log('user.email_verified_by_admin', 'user', (string) $user['id'], [], (int) $this->auth->id());

        $this->flashSuccess('Email address marked as verified.');
        return $this->redirect('/admin/users/' . $user['id']);
    }

    public function forcePasswordReset(Request $request): Response
    {
        $user = $this->requireUser((int) $request->routeParam('id', 0));

        // Send the standard reset email rather than setting a password we
        // would then know.
        $this->auth->sendPasswordReset((string) $user['email']);
        $this->audit->log('user.password_reset_forced', 'user', (string) $user['id'], [], (int) $this->auth->id());

        $this->flashSuccess('A password reset link has been emailed to ' . (string) $user['email'] . '.');
        return $this->redirect('/admin/users/' . $user['id']);
    }

    public function updateRoles(Request $request): Response
    {
        $user = $this->requireUser((int) $request->routeParam('id', 0));
        $userId = (int) $user['id'];

        $submitted = $request->input('roles', []);
        if (!is_array($submitted)) {
            $submitted = [$submitted];
        }

        $requested = array_values(array_intersect(
            array_map(static fn (mixed $role): string => (string) $role, $submitted),
            self::ASSIGNABLE_ROLES
        ));

        $current = $this->users->rolesFor($userId);
        $currentNames = array_map(static fn (array $role): string => (string) $role['name'], $current);

        // Guard the last super administrator.
        if (in_array('super_admin', $currentNames, true) && !in_array('super_admin', $requested, true)) {
            $remaining = $this->db->int(
                "SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur
                 JOIN roles r ON r.id = ur.role_id
                 WHERE r.name = 'super_admin' AND ur.user_id <> :uid",
                ['uid' => $userId]
            );

            if ($remaining < 1) {
                $this->flashError('This is the last super administrator — promote somebody else first.');
                return $this->redirect('/admin/users/' . $userId);
            }
        }

        foreach (array_diff($requested, $currentNames) as $role) {
            $this->users->assignRole($userId, $role);
        }

        foreach (array_diff($currentNames, $requested) as $role) {
            $this->users->removeRole($userId, $role);
        }

        $this->audit->log('user.roles_updated', 'user', (string) $userId, [
            'from' => $currentNames,
            'to'   => $requested,
        ], (int) $this->auth->id());

        $this->flashSuccess('Roles updated. The change applies the next time the user loads a page.');
        return $this->redirect('/admin/users/' . $userId);
    }

    public function donors(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = $this->donors->paginate($search, $page, $perPage);

        return $this->render('admin/donors', [
            'pageTitle' => 'Donors',
            'donors'    => $result['rows'],
            'total'     => $result['total'],
            'page'      => $page,
            'lastPage'  => max(1, (int) ceil($result['total'] / $perPage)),
            'filters'   => ['q' => $search],
        ], 'layouts/admin');
    }

    /** @return array<string,mixed> */
    private function requireUser(int $id): array
    {
        $user = $this->users->find($id);

        if ($user === null) {
            abort(404, 'User not found.');
        }

        return $user;
    }
}
