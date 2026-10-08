<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\FundraiserRepository;
use App\Services\AuthService;
use App\Services\ContentService;
use App\Services\SettingsService;

final class FundraiserController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private FundraiserRepository $fundraisers,
        private SettingsService $settings
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q'           => trim((string) $request->input('q', '')),
            'category_id' => $request->input('category'),
            'state'       => (string) $request->input('state', ''),
            'sort'        => (string) $request->input('sort', 'newest'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 12;

        $result = $this->fundraisers->paginatePublished($filters, $page, $perPage);

        return $this->render('public/fundraisers/index', [
            'pageTitle'   => 'Browse fundraisers',
            'metaDescription' => 'Support verified fundraisers raising money for orphaned children and families across Pakistan.',
            'fundraisers' => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'perPage'     => $perPage,
            'lastPage'    => max(1, (int) ceil($result['total'] / $perPage)),
            'filters'     => $filters,
            'categories'  => $this->fundraisers->categories(),
        ], 'layouts/public');
    }

    public function show(Request $request): Response
    {
        $fundraiser = $this->fundraisers->findPublishedBySlug((string) $request->routeParam('slug', ''));

        if ($fundraiser === null) {
            return $this->notFound();
        }

        $id = (int) $fundraiser['id'];
        $donations = app(\App\Repositories\DonationRepository::class);

        $supporters = $donations->completedForFundraiser($id, 12);

        return $this->render('public/fundraisers/show', [
            'pageTitle'       => (string) $fundraiser['title'],
            'metaDescription' => mb_substr(strip_tags((string) ($fundraiser['impact_statement'] ?? $fundraiser['story'] ?? '')), 0, 160),
            'ogImage'         => $fundraiser['cover_image_path'] ?? null,
            'fundraiser'      => $fundraiser,
            'updates'         => $this->fundraisers->updatesFor($id, true),
            'supporters'      => $supporters,
            'donorCount'      => $donations->countForFundraiserByStatus($id, 'completed'),
            'topDonation'     => $donations->sumForFundraiserByStatus($id, 'completed'),
            'otherFundraisers'=> array_slice(
                array_filter(
                    $this->fundraisers->topByRaised(8),
                    static fn (array $row): bool => (int) $row['id'] !== $id
                ),
                0,
                3
            ),
        ], 'layouts/public');
    }

    private function notFound(): Response
    {
        return $this->render('public/errors/404', [
            'pageTitle' => 'Fundraiser not found',
        ], 'layouts/public', 404);
    }
}
