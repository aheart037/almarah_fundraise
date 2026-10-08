<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\CampaignRepository;
use App\Repositories\FundraiserRepository;
use App\Services\AuthService;

final class CampaignController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private CampaignRepository $campaigns,
        private FundraiserRepository $fundraisers
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        return $this->render('public/campaigns/index', [
            'pageTitle'       => 'Appeal campaigns',
            'metaDescription' => 'Almarah Foundation appeal campaigns — seasonal giving, ration drives, education and emergency relief.',
            'campaigns'       => $this->campaigns->active(24),
        ], 'layouts/public');
    }

    public function show(Request $request): Response
    {
        $campaign = $this->campaigns->findActiveBySlug((string) $request->routeParam('slug', ''));

        if ($campaign === null) {
            return $this->render('public/errors/404', ['pageTitle' => 'Campaign not found'], 'layouts/public', 404);
        }

        return $this->render('public/campaigns/show', [
            'pageTitle'       => (string) $campaign['title'],
            'metaDescription' => mb_substr(strip_tags((string) ($campaign['description'] ?? '')), 0, 160),
            'campaign'        => $campaign,
            'fundraisers'     => $this->campaigns->fundraisersFor((int) $campaign['id'], 12),
        ], 'layouts/public');
    }
}
