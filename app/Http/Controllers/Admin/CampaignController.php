<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\CampaignRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\UploadService;
use InvalidArgumentException;
use Throwable;

/**
 * Create, edit and delete the campaigns (appeals) shown on the public site.
 *
 * Deleting is a soft delete: the campaign disappears from the site but the
 * donations and fundraisers that referenced it keep their history.
 */
final class CampaignController extends Controller
{
    private const STATUSES = ['draft', 'active', 'paused', 'closed'];

    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private CampaignRepository $campaigns,
        private UploadService $uploads,
        private AuditService $audit
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        $rows = $this->campaigns->all();

        $counts = ['active' => 0, 'draft' => 0, 'paused' => 0, 'closed' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return $this->render('admin/campaigns/index', [
            'pageTitle' => 'Campaigns',
            'campaigns' => $rows,
            'counts'    => $counts,
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->render('admin/campaigns/form', [
            'pageTitle' => 'New campaign',
            'campaign'  => null,
            'statuses'  => self::STATUSES,
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $invalid = $this->validationFailure($request, null);
        if ($invalid !== null) {
            return $invalid;
        }

        $data = $this->dataFrom($request);
        $data['slug'] = $this->uniqueSlug((string) $request->input('slug', ''), (string) $data['title'], null);
        $data['created_by'] = (int) $this->auth->id();

        $file = $request->file('image');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $data['image_path'] = $this->uploads->storeImage($file, 'campaigns');
            } catch (InvalidArgumentException $e) {
                return $this->flashFormState(['image' => $e->getMessage()], $request->all());
            }
        }

        try {
            $id = $this->campaigns->create($data);
        } catch (Throwable $e) {
            if (!empty($data['image_path'])) {
                $this->uploads->delete((string) $data['image_path']);
            }
            error_log('[almarah] campaign create failed: ' . $e->getMessage());
            $this->session->flash('_old', $this->stripSensitive($request->all()));
            $this->flashError('The campaign could not be saved. Please try again.');
            return $this->redirect('/admin/campaigns/create');
        }

        $this->audit->log('campaign.created', 'campaign', (string) $id, ['title' => $data['title']], (int) $this->auth->id());

        $this->flashSuccess('Campaign created.');
        return $this->redirect('/admin/campaigns');
    }

    public function edit(Request $request): Response
    {
        $campaign = $this->requireCampaign((int) $request->routeParam('id', 0));
        if ($campaign === null) {
            return $this->redirect('/admin/campaigns');
        }

        return $this->render('admin/campaigns/form', [
            'pageTitle' => 'Edit campaign',
            'campaign'  => $campaign,
            'statuses'  => self::STATUSES,
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $campaign = $this->requireCampaign($id);
        if ($campaign === null) {
            return $this->redirect('/admin/campaigns');
        }

        $invalid = $this->validationFailure($request, $id);
        if ($invalid !== null) {
            return $invalid;
        }

        $data = $this->dataFrom($request);

        // A blank slug keeps the current address (links already shared keep working).
        $postedSlug = trim((string) $request->input('slug', ''));
        $data['slug'] = $postedSlug === ''
            ? (string) $campaign['slug']
            : $this->uniqueSlug($postedSlug, (string) $data['title'], $id);

        $oldImage = (string) ($campaign['image_path'] ?? '');
        $newImage = null;

        $file = $request->file('image');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newImage = $this->uploads->storeImage($file, 'campaigns');
            } catch (InvalidArgumentException $e) {
                return $this->flashFormState(['image' => $e->getMessage()], $request->all());
            }
            $data['image_path'] = $newImage;
        } elseif ($request->bool('remove_image')) {
            $data['image_path'] = null;
        }

        try {
            $this->campaigns->update($id, $data);
        } catch (Throwable $e) {
            if ($newImage !== null) {
                $this->uploads->delete($newImage);
            }
            error_log('[almarah] campaign update failed: ' . $e->getMessage());
            $this->session->flash('_old', $this->stripSensitive($request->all()));
            $this->flashError('The campaign could not be saved. Please try again.');
            return $this->redirect('/admin/campaigns/' . $id . '/edit');
        }

        // Only now that the row points at the new picture is the old upload
        // removed (UploadService only ever deletes files under uploads/).
        if (array_key_exists('image_path', $data) && $oldImage !== '' && $oldImage !== (string) $data['image_path']) {
            $this->uploads->delete($oldImage);
        }

        $this->audit->log('campaign.updated', 'campaign', (string) $id, ['title' => $data['title']], (int) $this->auth->id());

        $this->flashSuccess('Campaign updated.');
        return $this->redirect('/admin/campaigns');
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $campaign = $this->requireCampaign($id);
        if ($campaign === null) {
            return $this->redirect('/admin/campaigns');
        }

        $this->campaigns->softDelete($id);
        $this->uploads->delete((string) ($campaign['image_path'] ?? ''));

        $this->audit->log('campaign.deleted', 'campaign', (string) $id, ['title' => (string) $campaign['title']], (int) $this->auth->id());

        $this->flashSuccess('Campaign deleted. Donations already made to it are kept in the records.');
        return $this->redirect('/admin/campaigns');
    }

    // ---------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function requireCampaign(int $id): ?array
    {
        $campaign = $id > 0 ? $this->campaigns->find($id) : null;
        if ($campaign === null) {
            $this->flashError('That campaign could not be found.');
        }
        return $campaign;
    }

    private function validationFailure(Request $request, ?int $exceptId): ?Response
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|min:5|max:180',
            'slug'        => 'nullable|string|max:150',
            'description' => 'nullable|string|max:5000',
            'status'      => 'required|in:' . implode(',', self::STATUSES),
            'start_at'    => 'nullable|date',
            'end_at'      => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $errors = [];

        $slug = trim((string) $request->input('slug', ''));
        if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errors['slug'] = 'The address may only contain lowercase letters, numbers and hyphens.';
        } elseif ($slug !== '' && $this->campaigns->slugTaken($slug, $exceptId)) {
            $errors['slug'] = 'Another campaign already uses that address. Choose a different one.';
        }

        $start = trim((string) $request->input('start_at', ''));
        $end = trim((string) $request->input('end_at', ''));
        if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
            $errors['end_at'] = 'The end date cannot be before the start date.';
        }

        if ($errors !== []) {
            return $this->flashFormState($errors, $request->all());
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function dataFrom(Request $request): array
    {
        $start = trim((string) $request->input('start_at', ''));
        $end = trim((string) $request->input('end_at', ''));
        $description = trim((string) $request->input('description', ''));

        return [
            'title'       => trim((string) $request->input('title')),
            'description' => $description === '' ? null : $description,
            'status'      => (string) $request->input('status', 'active'),
            'start_at'    => $start === '' ? null : gmdate('Y-m-d', (int) strtotime($start)),
            'end_at'      => $end === '' ? null : gmdate('Y-m-d', (int) strtotime($end)),
            'featured'    => $request->bool('featured') ? 1 : 0,
        ];
    }

    /** The wanted slug (or one made from the title), made unique with -2, -3 … */
    private function uniqueSlug(string $wanted, string $title, ?int $exceptId): string
    {
        $base = Str::slug(trim($wanted) !== '' ? $wanted : $title, 140);
        $candidate = $base;
        $n = 2;
        while ($this->campaigns->slugTaken($candidate, $exceptId)) {
            $candidate = $base . '-' . $n++;
        }
        return $candidate;
    }
}
