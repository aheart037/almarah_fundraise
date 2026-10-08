<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fundraiser;

use App\Core\Config;
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
use App\Services\UploadService;
use InvalidArgumentException;

/**
 * Owner-facing fundraiser management.
 *
 * Ownership is re-checked on every request against the database — a hidden
 * button in a view is never the access control.
 */
final class FundraiserController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private FundraiserRepository $fundraisers,
        private DonationRepository $donations,
        private FundraiserService $service,
        private UploadService $uploads,
        private Database $db
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        return $this->render('fundraiser/fundraisers/index', [
            'pageTitle'   => 'My fundraisers',
            'fundraisers' => $this->fundraisers->forOwner((int) $this->auth->id()),
        ], 'layouts/dashboard');
    }

    public function create(Request $request): Response
    {
        return $this->render('fundraiser/fundraisers/form', [
            'pageTitle'  => 'Start a fundraiser',
            'fundraiser' => null,
            'categories' => $this->fundraisers->categories(),
            'campaigns'  => app(\App\Repositories\CampaignRepository::class)->active(50),
            'defaultEnd' => gmdate('Y-m-d', time() + 86400 * (int) Config::get('app.fundraisers.default_duration_days', 60)),
            'goalFloor'  => (int) Config::get('app.fundraisers.goal_min_minor', 1000000),
            'goalCeiling'=> (int) Config::get('app.fundraisers.goal_max_minor', 2000000000),
            'preselectCampaign' => $request->input('campaign'),
        ], 'layouts/dashboard');
    }

    public function store(Request $request): Response
    {
        $validator = $this->validateInput($request);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $data = $this->dataFrom($request);

        // Optional cover image upload.
        $file = $request->file('cover_image');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $data['cover_image_path'] = $this->uploads->storeImage($file, 'fundraisers');
            } catch (InvalidArgumentException $e) {
                return $this->flashFormState(['cover_image' => $e->getMessage()], $request->all());
            }
        }

        try {
            $id = $this->service->create((int) $this->auth->id(), $data);
        } catch (InvalidArgumentException $e) {
            $this->session->flash('_old', $this->stripSensitive($request->all()));
            $this->flashError($e->getMessage());
            return $this->redirect('/dashboard/fundraisers/create');
        }

        $this->flashSuccess(
            !empty($data['submit_for_review'])
                ? 'Your fundraiser has been submitted for review. Our team usually responds within one working day.'
                : 'Draft saved. Submit it for review whenever you are ready.'
        );

        return $this->redirect('/dashboard/fundraisers/' . $id . '/edit');
    }

    public function edit(Request $request): Response
    {
        $fundraiser = $this->requireOwned((int) $request->routeParam('id', 0));
        $id = (int) $fundraiser['id'];

        return $this->render('fundraiser/fundraisers/form', [
            'pageTitle'  => 'Edit fundraiser',
            'fundraiser' => $fundraiser,
            'categories' => $this->fundraisers->categories(),
            'campaigns'  => app(\App\Repositories\CampaignRepository::class)->active(50),
            'updates'    => $this->fundraisers->updatesFor((int) $id, false),
            'goalFloor'  => (int) Config::get('app.fundraisers.goal_min_minor', 1000000),
            'goalCeiling'=> (int) Config::get('app.fundraisers.goal_max_minor', 2000000000),
        ], 'layouts/dashboard');
    }

    public function update(Request $request): Response
    {
        $fundraiserId = (int) $request->routeParam('id', 0);
        $this->requireOwned($fundraiserId);

        $validator = $this->validateInput($request, true);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $data = $this->dataFrom($request, true);

        $file = $request->file('cover_image');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = $this->uploads->storeImage($file, 'fundraisers');
                $this->uploads->delete((string) ($this->fundraisers->find($fundraiserId)['cover_image_path'] ?? ''));
                $data['cover_image_path'] = $newPath;
            } catch (InvalidArgumentException $e) {
                return $this->flashFormState(['cover_image' => $e->getMessage()], $request->all());
            }
        }

        try {
            $this->service->update($fundraiserId, $data, (int) $this->auth->id());
        } catch (InvalidArgumentException $e) {
            $this->session->flash('_old', $this->stripSensitive($request->all()));
            $this->flashError($e->getMessage());
            return $this->redirect('/dashboard/fundraisers/' . $fundraiserId . '/edit');
        }

        $this->flashSuccess('Your fundraiser has been updated.');
        return $this->redirect('/dashboard/fundraisers/' . $fundraiserId . '/edit');
    }

    public function submit(Request $request): Response
    {
        $fundraiserId = (int) $request->routeParam('id', 0);
        $this->requireOwned($fundraiserId);

        try {
            $this->service->submitForReview($fundraiserId, (int) $this->auth->id());
            $this->flashSuccess('Submitted for review — we will email you as soon as it is approved.');
        } catch (InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
        }

        return $this->redirect('/dashboard/fundraisers/' . $fundraiserId . '/edit');
    }

    public function pause(Request $request): Response
    {
        $fundraiserId = (int) $request->routeParam('id', 0);
        $this->requireOwned($fundraiserId);

        try {
            $this->service->pause($fundraiserId, (int) $this->auth->id(), 'Paused by the fundraiser owner.');
            $this->flashSuccess('Your fundraiser is paused and no longer accepts donations.');
        } catch (InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
        }

        return $this->redirect('/dashboard/fundraisers');
    }

    // ------------------------------------------------------------------ updates

    public function updates(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $fundraiser = $this->requireOwned($id);

        return $this->render('fundraiser/fundraisers/updates', [
            'pageTitle'  => 'Updates — ' . (string) $fundraiser['title'],
            'fundraiser' => $fundraiser,
            'updates'    => $this->fundraisers->updatesFor((int) $id, false),
        ], 'layouts/dashboard');
    }

    public function storeUpdate(Request $request): Response
    {
        $fundraiserId = (int) $request->routeParam('id', 0);
        $this->requireOwned($fundraiserId);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:4|max:150',
            'body'  => 'required|string|min:20|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $data = [
            'title'   => (string) $request->input('title'),
            'body'    => (string) $request->input('body'),
            'publish' => true,
        ];

        $file = $request->file('image');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $data['image_path'] = $this->uploads->storeImage($file, 'updates');
            } catch (InvalidArgumentException $e) {
                return $this->flashFormState(['image' => $e->getMessage()], $request->all());
            }
        }

        $this->service->publishUpdate($fundraiserId, (int) $this->auth->id(), $data);

        $this->flashSuccess('Your update is live and your supporters have been emailed.');
        return $this->redirect('/dashboard/fundraisers/' . $fundraiserId . '/updates');
    }

    public function deleteUpdate(Request $request): Response
    {
        $fundraiserId = (int) $request->routeParam('id', 0);
        $updateId = (int) $request->routeParam('updateId', 0);
        $this->requireOwned($fundraiserId);

        $update = $this->fundraisers->findUpdate($updateId);

        if ($update === null || (int) $update['fundraiser_id'] !== $fundraiserId) {
            $this->flashError('That update could not be found.');
            return $this->redirect('/dashboard/fundraisers/' . $fundraiserId . '/updates');
        }

        $this->fundraisers->deleteUpdate((int) $updateId);

        $this->flashSuccess('Update removed.');
        return $this->redirect('/dashboard/fundraisers/' . $fundraiserId . '/updates');
    }

    // ----------------------------------------------------------------- donations

    public function donations(Request $request): Response
    {
        $fundraiserId = (int) $request->routeParam('id', 0);
        $fundraiser = $this->requireOwned($fundraiserId);

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = $this->donations->search([
            'fundraiser_id' => $fundraiserId,
            'status'        => (string) $request->input('status', ''),
            'q'             => (string) $request->input('q', ''),
            'owner_user_id' => (int) $this->auth->id(),
        ], $page, $perPage);

        return $this->render('fundraiser/fundraisers/donations', [
            'pageTitle'  => 'Donations — ' . (string) $fundraiser['title'],
            'fundraiser' => $fundraiser,
            'donations'  => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'lastPage'   => max(1, (int) ceil($result['total'] / $perPage)),
            'filters'    => [
                'status' => (string) $request->input('status', ''),
                'q'      => (string) $request->input('q', ''),
            ],
            'totals'     => $this->donations->totalsByStatus(),
        ], 'layouts/dashboard');
    }

    /** CSV export of the owner's own donations (never other people's). */
    public function exportDonations(Request $request): Response
    {
        $fundraiserId = (int) $request->routeParam('id', 0);
        $this->requireOwned($fundraiserId);

        $rows = $this->db->select(
            "SELECT d.public_reference, d.created_at, d.status, d.amount_minor, d.currency,
                    CASE WHEN d.anonymous = 1 THEN 'Anonymous' ELSE COALESCE(dn.name, 'Supporter') END AS donor,
                    CASE WHEN d.anonymous = 1 THEN '' ELSE COALESCE(dn.email, '') END AS email,
                    d.donor_message
             FROM donations d
             LEFT JOIN donors dn ON dn.id = d.donor_id
             WHERE d.fundraiser_id = :fid
             ORDER BY d.created_at DESC, d.id DESC",
            ['fid' => $fundraiserId]
        );

        $csv = "Reference,Date,Status,Amount,Currency,Donor,Email,Message\n";

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                static fn (mixed $value): string => '"' . str_replace('"', '""', (string) $value) . '"',
                [
                    $row['public_reference'],
                    $row['created_at'],
                    $row['status'],
                    number_format(((int) $row['amount_minor']) / 100, 2, '.', ''),
                    $row['currency'],
                    $row['donor'],
                    $row['email'],
                    (string) ($row['donor_message'] ?? ''),
                ]
            )) . "\n";
        }

        return Response::text($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="fundraiser-' . $fundraiserId . '-donations.csv"',
        ]);
    }

    // -------------------------------------------------------------------- helpers

    private function validateInput(Request $request, bool $isUpdate = false): Validator
    {
        $rules = [
            'title'                => 'required|string|min:5|max:150',
            'story'                => 'required|string|min:50|max:20000',
            'impact_statement'     => 'nullable|string|max:500',
            'category_id'          => 'required|integer',
            'campaign_id'          => 'nullable|integer',
            'goal'                 => 'required',
            'end_at'               => 'nullable|date',
            'cover_image'          => 'nullable|file',
            'submit_for_review'    => 'nullable|in:1,on,yes',
        ];

        return Validator::make($request->all(), $rules);
    }

    /** @return array<string,mixed> */
    private function dataFrom(Request $request, bool $isUpdate = false): array
    {
        $goal = (string) $request->input('goal');
        $goalMinor = (int) round(((float) str_replace(',', '', $goal)) * 100);

        $data = [
            'title'                => trim((string) $request->input('title')),
            'story'                => trim((string) $request->input('story')),
            'impact_statement'     => $request->input('impact_statement'),
            'category_id'          => (int) $request->input('category_id'),
            'campaign_id'          => (int) $request->input('campaign_id', 0),
            'goal_minor'           => $goalMinor,
            'end_at'               => $request->input('end_at') ?: null,
            'submit_for_review'    => (bool) $request->input('submit_for_review'),
        ];

        return $data;
    }

    /** @return array<string,mixed> */
    private function requireOwned(int $id): array
    {
        $fundraiser = $this->fundraisers->find($id);

        if ($fundraiser === null || (int) $fundraiser['owner_user_id'] !== (int) $this->auth->id()) {
            abort(403, 'You do not have access to that fundraiser.');
        }

        return $fundraiser;
    }
}
