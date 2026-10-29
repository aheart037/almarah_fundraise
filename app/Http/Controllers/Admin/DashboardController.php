<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\DonationRepository;
use App\Repositories\FundraiserRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\SettingsService;
use App\Payments\PaymentGatewayManager;

/**
 * Admin overview: the numbers an operator needs first, plus anything waiting
 * on a human decision.
 */
final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private Database $db,
        private DonationRepository $donations,
        private FundraiserRepository $fundraisers,
        private PaymentRepository $payments,
        private UserRepository $users,
        private AuditService $audit,
        private SettingsService $settings,
        private PaymentGatewayManager $gateways
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        $totals = $this->donations->totalsByStatus();

        $gatewayTotals = $this->db->select(
            "SELECT g.code, g.name, COALESCE(SUM(CASE WHEN d.status = 'completed' THEN d.amount_minor ELSE 0 END), 0) AS completed_minor,
                    COUNT(d.id) AS donation_count
             FROM gateways g
             LEFT JOIN donations d ON d.gateway_id = g.id
             GROUP BY g.id, g.code, g.name
             ORDER BY g.sort_order ASC"
        );

        $monthly = $this->db->select(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount_minor ELSE 0 END), 0) AS completed_minor,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count
             FROM donations
             WHERE created_at >= DATE_SUB(UTC_DATE(), INTERVAL 6 MONTH)
             GROUP BY month
             ORDER BY month ASC"
        );

        // Chart data (v18). Each query is guarded: a problem here must never
        // stop the overview from opening.
        try {
            $daily = chart_fill_days($this->db->select(
                "SELECT DATE(COALESCE(completed_at, created_at)) AS day,
                        COALESCE(SUM(amount_minor), 0) AS minor, COUNT(*) AS cnt
                 FROM donations
                 WHERE status = 'completed'
                   AND COALESCE(completed_at, created_at) >= DATE_SUB(UTC_DATE(), INTERVAL 59 DAY)
                 GROUP BY day"
            ), 60);
        } catch (\Throwable) {
            $daily = chart_fill_days([], 60);
        }
        $trend = array_slice($daily, 30);
        $trendPrev = array_slice($daily, 0, 30);

        try {
            $statusMix = $this->db->select('SELECT status, COUNT(*) AS c FROM donations GROUP BY status');
        } catch (\Throwable) {
            $statusMix = [];
        }

        try {
            $topFundraisers = $this->fundraisers->topByRaised(5);
        } catch (\Throwable) {
            $topFundraisers = [];
        }

        return $this->render('admin/dashboard', [
            'trend'            => $trend,
            'trendPrevMinor'   => array_sum(array_column($trendPrev, 'minor')),
            'trendPrevCount'   => array_sum(array_column($trendPrev, 'count')),
            'statusMix'        => $statusMix,
            'topFundraisers'   => $topFundraisers,
            'pageTitle'        => 'Admin overview',
            'totals'           => $totals,
            'gatewayTotals'    => $gatewayTotals,
            'monthly'          => $monthly,
            'pendingFundraisers' => $this->fundraisers->countPendingApproval(),
            'fundraiserStatuses' => $this->fundraisers->statuses(),
            'pendingDonations' => $this->db->int("SELECT COUNT(*) FROM donations WHERE status IN ('pending','processing')"),
            'failedDonations'  => $this->db->int("SELECT COUNT(*) FROM donations WHERE status IN ('failed','cancelled')"),
            'paymentStatusCounts' => $this->payments->statusCounts(),
            'userCount'        => $this->users->countAll(),
            'activeUsers'      => $this->users->countByStatus('active'),
            'recentDonations'  => $this->donations->recent(8),
            'recentFundraisers'=> array_slice($this->fundraisers->paginateForAdmin([], 1, 6)['rows'], 0, 6),
            'recentAudit'      => $this->audit->recent(10),
            'gatewayHealth'    => $this->settings->gatewayStatus(),
            'mailConfigured'   => $this->settings->mailConfigured(),
            'callbacks'        => $this->payments->recentCallbacks(5),
        ], 'layouts/admin');
    }
}
