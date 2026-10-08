<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\TeamRepository;
use App\Services\AuthService;

final class TeamController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private TeamRepository $teams
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 12;
        $financial = (string) $request->input('type', '');

        $result = $this->teams->paginate($page, $perPage, $financial);

        return $this->render('public/teams/index', [
            'pageTitle'       => 'Fundraising teams',
            'metaDescription' => 'Join or support a community fundraising team raising money with Almarah Foundation.',
            'teams'           => $result['rows'],
            'total'           => $result['total'],
            'page'            => $page,
            'lastPage'        => max(1, (int) ceil($result['total'] / $perPage)),
            'financial'       => $financial,
        ], 'layouts/public');
    }

    public function show(Request $request): Response
    {
        $team = $this->teams->findPublishedBySlug((string) $request->routeParam('slug', ''));

        if ($team === null) {
            return $this->render('public/errors/404', ['pageTitle' => 'Team not found'], 'layouts/public', 404);
        }

        $id = (int) $team['id'];

        return $this->render('public/teams/show', [
            'pageTitle'       => (string) $team['name'],
            'metaDescription' => mb_substr(strip_tags((string) ($team['description'] ?? '')), 0, 160),
            'team'            => $team,
            'members'         => $this->teams->members($id),
            'supporters'      => app(\App\Repositories\DonationRepository::class)->completedForTeam($id, 12),
        ], 'layouts/public');
    }
}
