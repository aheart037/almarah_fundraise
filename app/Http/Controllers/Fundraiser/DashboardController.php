<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fundraiser;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\DonationRepository;
use App\Repositories\FundraiserRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\TeamRepository;
use App\Services\AuthService;
use App\Services\FundraiserService;

/**
 * Fundraiser dashboard overview: totals across every fundraiser the signed-in
 * user owns, plus pending actions.
 */
final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private FundraiserRepository $fundraisers,
        private DonationRepository $donations,
        private TeamRepository $teams,
        private Database $db,
        private FundraiserService $fundraiserService
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        $userId = (int) $this->auth->id();

        $own = $this->fundraisers->forOwner($userId);

        $totals = $this->db->selectOne(
            "SELECT
                COALESCE(SUM(d.amount_minor), 0) AS raised_minor,
                COUNT(*) AS donation_count
             FROM donations d
             JOIN fundraisers f ON f.id = d.fundraiser_id
             WHERE f.owner_user_id = :uid AND d.status = 'completed' AND f.deleted_at IS NULL",
            ['uid' => $userId]
        ) ?? ['raised_minor' => 0, 'donation_count' => 0];

        $recentDonations = $this->db->select(
            "SELECT d.public_reference, d.amount_minor, d.currency, d.status, d.created_at, d.anonymous,
                    dn.name AS donor_name,
                    f.title AS fundraiser_title, f.id AS fundraiser_id
             FROM donations d
             JOIN fundraisers f ON f.id = d.fundraiser_id
             LEFT JOIN donors dn ON dn.id = d.donor_id
             WHERE f.owner_user_id = :uid AND f.deleted_at IS NULL
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT 8",
            ['uid' => $userId]
        );

        $needsAttention = array_values(array_filter($own, static function (array $row): bool {
            $status = (string) $row['status'];
            return in_array($status, ['draft', 'changes_requested', 'rejected'], true);
        }));

        return $this->render('fundraiser/dashboard', [
            'pageTitle'       => 'Dashboard',
            'fundraisers'     => $own,
            'totals'          => $totals,
            'recentDonations' => $recentDonations,
            'needsAttention'  => $needsAttention,
            'teams'           => $this->teams->forUser($userId),
            'endingSoon'      => $this->fundraiserService->endingSoon(14),
            'verified'        => $this->auth->isVerified(),
        ], 'layouts/dashboard');
    }
}
