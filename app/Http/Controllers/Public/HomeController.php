<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Database;
use App\Http\Controllers\Controller;
use App\Repositories\CampaignRepository;
use App\Repositories\FundraiserRepository;
use App\Repositories\TeamRepository;
use App\Services\ContentService;
use App\Services\SettingsService;

/**
 * Public landing page: hero content, featured fundraisers, impact numbers,
 * campaigns and top teams — all from the database.
 */
final class HomeController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Csrf $csrf,
        \App\Services\AuthService $auth,
        private ContentService $content,
        private FundraiserRepository $fundraisers,
        private CampaignRepository $campaigns,
        private TeamRepository $teams,
        private SettingsService $settings,
        private Database $db
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): \App\Core\Response
    {
        $hero = $this->content->hero();

        $stats = $this->db->selectOne(
            "SELECT
                (SELECT COUNT(*) FROM fundraisers WHERE status = 'published' AND approval_status = 'approved' AND deleted_at IS NULL) AS fundraiser_count,
                (SELECT COUNT(*) FROM donations WHERE status = 'completed') AS donation_count,
                (SELECT COALESCE(SUM(amount_minor), 0) FROM donations WHERE status = 'completed') AS raised_minor,
                (SELECT COUNT(DISTINCT donor_id) FROM donations WHERE status = 'completed') AS donor_count"
        ) ?? [];

        return $this->render('public/home', [
            'pageTitle'        => $this->settings->get('site.tagline', 'Give hope. Change lives.'),
            'metaDescription'  => $this->settings->get(
                'site.meta_description',
                'Start a fundraiser for Almarah Foundation and help orphaned children across Pakistan.'
            ),
            'hero'             => $hero,
            'impact'           => $this->content->impactNumbers(),
            'trust'            => $this->content->trust(),
            'featured'         => $this->fundraisers->featured(3),
            'topFundraisers'   => $this->fundraisers->topByRaised(6),
            'campaigns'        => $this->campaigns->featured(3),
            'topTeams'         => $this->teams->topByRaised(3),
            'categories'       => $this->fundraisers->categories(),
            'stats'            => $stats,
            'endingSoon'       => app(\App\Services\FundraiserService::class)->endingSoon(6),
        ], 'layouts/public');
    }
}
