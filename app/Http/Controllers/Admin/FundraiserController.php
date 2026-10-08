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
use App\Repositories\DonationRepository;
use App\Repositories\FundraiserRepository;
use App\Services\AuthService;
use App\Services\FundraiserService;
use InvalidArgumentException;

/**
 * Moderation queue and fundraiser administration.
 *
 * Every state change runs through FundraiserService, which checks the current
 * status, writes an audit entry and queues the notification email.
 */
final class FundraiserController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private FundraiserRepository $fundraisers,
        private FundraiserService $service,
        private DonationRepository $donations,
        private Database $db
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q'            => trim((string) $request->input('q', '')),
            'status'       => (string) $request->input('status', ''),
            'approval'     => (string) $request->input('approval', ''),
            'category_id'  => $request->input('category'),
            'featured'     => (string) $request->input('featured', ''),
            'sort'         => (string) $request->input('sort', 'newest'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = $this->fundraisers->paginateForAdmin($filters, $page, $perPage);

        return $this->render('admin/fundraisers/index', [
            'pageTitle'  => 'Fundraisers',
            'fundraisers'=> $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'lastPage'   => max(1, (int) ceil($result['total'] / $perPage)),
            'filters'    => $filters,
            'categories' => $this->fundraisers->allCategories(),
            'counts'     => [
                'pending_review' => $this->fundraisers->countByStatus('pending_review'),
                'published'      => $this->fundraisers->countByStatus('published'),
                'draft'          => $this->fundraisers->countByStatus('draft'),
                'paused'         => $this->fundraisers->countByStatus('paused'),
                'archived'       => $this->fundraisers->countByStatus('archived'),
                'rejected'       => $this->fundraisers->countByStatus('rejected'),
            ],
        ], 'layouts/admin');
    }

    public function show(Request $request): Response
    {
        $fundraiser = $this->requireFundraiser((int) $request->routeParam('id', 0));
        $id = (int) $fundraiser['id'];
        $fundraiserId = (int) $fundraiser['id'];

        return $this->render('admin/fundraisers/show', [
            'pageTitle'   => (string) $fundraiser['title'],
            'fundraiser'  => $fundraiser,
            'donations'   => $this->donations->search(['fundraiser_id' => $fundraiserId], 1, 15)['rows'],
            'totals'      => $this->db->selectOne(
                "SELECT
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount_minor ELSE 0 END), 0) AS completed_minor,
                    COALESCE(SUM(CASE WHEN status IN ('pending','processing') THEN amount_minor ELSE 0 END), 0) AS pending_minor,
                    COUNT(*) AS all_count
                 FROM donations WHERE fundraiser_id = :fid",
                ['fid' => $fundraiserId]
            ),
            'updates'     => $this->fundraisers->updatesFor($fundraiserId, false),
            'owner'       => app(\App\Repositories\UserRepository::class)->find((int) $fundraiser['owner_user_id']),
            'auditTrail'  => $this->db->select(
                "SELECT * FROM audit_logs WHERE entity_type = 'fundraiser' AND entity_id = :id ORDER BY id DESC LIMIT 25",
                ['id' => (string) $fundraiserId]
            ),
        ], 'layouts/admin');
    }

    // ------------------------------------------------------------------ actions

    public function approve(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'approve');
    }

    public function reject(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'reject');
    }

    public function requestChanges(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'request_changes');
    }

    public function pause(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'pause');
    }

    public function publish(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'publish');
    }

    public function unpublish(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'unpublish');
    }

    public function archive(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'archive');
    }

    public function destroy(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'delete');
    }

    public function feature(Request $request, string $id): Response
    {
        return $this->transition($request, (int) $id, 'feature');
    }

    public function hideUpdate(Request $request): Response
    {
        $fundraiser = $this->requireFundraiser((int) $request->routeParam('id', 0));
        $update = $this->fundraisers->findUpdate((int) $request->routeParam('updateId', 0));

        if ($update === null || (int) $update['fundraiser_id'] !== (int) $fundraiser['id']) {
            $this->flashError('That update does not belong to this fundraiser.');
            return $this->redirect('/admin/fundraisers/' . $fundraiser['id']);
        }

        $this->service->hideUpdate((int) $updateId, (int) $this->auth->id());

        $this->flashSuccess('Update hidden from the public page.');
        return $this->redirect('/admin/fundraisers/' . $fundraiser['id']);
    }

    /**
     * Single audited entry point for every moderation button, so a
     * hand-crafted POST cannot move a fundraiser through an invalid path.
     */
    private function transition(Request $request, int $id, string $action): Response
    {
        $this->requireFundraiser($id);
        $adminId = (int) $this->auth->id();

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
            'note'   => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $reason = trim((string) ($request->input('reason') ?: $request->input('note') ?: ''));

        // These actions must carry a human explanation; without one the
        // fundraiser would be left guessing.
        $needsReason = [
            'reject'          => 'Please give the fundraiser a reason for rejection.',
            'request_changes' => 'Tell the fundraiser what needs changing.',
            'pause'           => 'Give a reason for pausing this fundraiser.',
        ];

        if (isset($needsReason[$action]) && mb_strlen($reason) < 5) {
            $this->flashError($needsReason[$action]);
            return $this->redirect('/admin/fundraisers/' . $id);
        }

        try {
            match ($action) {
                'approve'         => $this->service->approve($id, $adminId, $reason),
                'reject'          => $this->service->reject($id, $adminId, $reason),
                'request_changes' => $this->service->requestChanges($id, $adminId, $reason),
                'pause'           => $this->service->pause($id, $adminId, $reason),
                'publish'         => $this->service->publish($id, $adminId),
                'unpublish'       => $this->service->unpublish($id, $adminId),
                'archive'         => $this->service->archive($id, $adminId),
                'delete'          => $this->service->softDelete($id, $adminId),
                'feature'         => $this->service->setFeatured($id, $request->bool('featured', true), $adminId),
                default           => throw new InvalidArgumentException('Unknown action.'),
            };
        } catch (InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
            return $this->redirect('/admin/fundraisers/' . $id);
        }

        $messages = [
            'approve'         => 'Fundraiser approved.',
            'reject'          => 'Fundraiser rejected and the owner has been notified.',
            'request_changes' => 'Changes requested from the fundraiser.',
            'pause'           => 'Fundraiser paused — it no longer appears publicly.',
            'publish'         => 'Fundraiser is live.',
            'unpublish'       => 'Fundraiser unpublished.',
            'archive'         => 'Fundraiser archived.',
            'delete'          => 'Fundraiser removed. Donation history is retained.',
            'feature'         => 'Featured flag updated.',
        ];

        $this->flashSuccess($messages[$action] ?? 'Updated.');

        if ($action === 'delete') {
            return $this->redirect('/admin/fundraisers');
        }

        return $this->redirect('/admin/fundraisers/' . $id);
    }

    /** @return array<string,mixed> */
    private function requireFundraiser(int $id): array
    {
        $fundraiser = $this->fundraisers->find($id);

        if ($fundraiser === null) {
            abort(404, 'Fundraiser not found.');
        }

        return $fundraiser;
    }
}
