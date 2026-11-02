<?php

declare(strict_types=1);

/**
 * Reports test suite (v20). Dependency-free: run with  php tests/reports_test.php
 * See tests/bootstrap.php for the database settings it needs.
 *
 * What it proves, using the hand-worked data set in tests/Reports/Fixture.php:
 *  - every total and count in the reports equals the independently calculated value;
 *  - a donation is never counted twice, and other currencies are never mixed in;
 *  - refunded, failed, cancelled, abandoned and pending money stays out of "confirmed";
 *  - filters, sorting and pagination behave, and hostile input is harmless;
 *  - report routes demand login + admin role, and the role check really denies donors;
 *  - every report produces a valid multi-page PDF, including when it is empty.
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Request;
use App\Core\Router;
use App\Exceptions\HttpException;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use Tests\Reports\Fixture;

$GLOBALS['t_pass'] = 0;
$GLOBALS['t_fail'] = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    if ($ok) {
        $GLOBALS['t_pass']++;
        echo "  ok   {$name}\n";
    } else {
        $GLOBALS['t_fail'][] = $name;
        echo "  FAIL {$name}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
    }
}

function same(string $name, mixed $expected, mixed $actual): void
{
    check($name, $expected === $actual, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function card(array $report, string $label): mixed
{
    foreach ($report['summary'] as $c) {
        if ($c['label'] === $label) {
            return $c['value'];
        }
    }

    return '(no card "' . $label . '")';
}

function section(string $t): void
{
    echo "\n== {$t}\n";
}

$db = Fixture::connect();
Fixture::migrate($db);
Fixture::seed($db);
$svc = new ReportService($db);
$oct = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];

// ---------------------------------------------------------------------------
section('Filters are cleaned on the server');
$f = $svc->normalize('donations', ['date_from' => '2026-13-45', 'date_to' => 'garbage', 'gateway' => 'evil; DROP TABLE donations', 'campaign_id' => '1 OR 1=1', 'sort' => 'x;--', 'currency' => 'ZZZ', 'group' => 'decade']);
same('invalid dates are dropped', ['', ''], [$f['date_from'], $f['date_to']]);
same('unknown gateway is ignored', '', $f['gateway']);
same('non-numeric campaign id is ignored', '', $f['campaign_id']);
same('unknown currency falls back to the site currency', 'PKR', $f['currency']);
same('unknown group falls back to month', 'month', $f['group']);
$f = $svc->normalize('donations', ['date_from' => '2026-10-31', 'date_to' => '2026-10-01']);
same('reversed dates are swapped', ['2026-10-01', '2026-10-31'], [$f['date_from'], $f['date_to']]);
$f = $svc->normalize('overview', []);
check('overview defaults to the last 30 days', $f['date_from'] === gmdate('Y-m-d', strtotime('-29 days')) && $f['date_to'] === gmdate('Y-m-d'));
$f = $svc->normalize('overview', ['date_from' => '', 'date_to' => '']);
same('overview "all time" is respected when chosen', ['', ''], [$f['date_from'], $f['date_to']]);

// ---------------------------------------------------------------------------
section('Donation Summary: totals (October, PKR)');
$r = $svc->run('donations', $oct);
same('confirmed amount = completed only', 1030000, card($r, 'Confirmed amount'));
same('average is rounded the same everywhere', 171667, card($r, 'Average donation'));
same('largest donation', 500000, card($r, 'Largest donation'));
same('unique donors with a confirmed gift', 3, card($r, 'Unique donors'));
same('refunded amount is separate', 300000, card($r, 'Refunded'));
same('gross = confirmed + refunded', 1330000, card($r, 'Gross received'));
same('awaiting the bank = pending + processing', 240000, card($r, 'Awaiting the bank'));
same('failed + cancelled + abandoned counted, not summed in', 3, card($r, 'Failed / cancelled'));
same('one monthly row', 1, count($r['rows']));
same('row confirmed donations', 6, (int) $r['rows'][0]['donations']);
same('row confirmed amount', 1030000, (int) $r['rows'][0]['confirmed_minor']);
same('row gross amount', 1330000, (int) $r['rows'][0]['gross_minor']);
$r = $svc->run('donations', $oct + ['group' => 'day']);
same('day grouping gives one row per day with a paid gift', 7, $r['total']);
same('day rows add up to the same confirmed total', 1030000, array_sum(array_map(static fn ($x) => (int) $x['confirmed_minor'], $r['rows'])));

section('Currencies are never mixed');
$r = $svc->run('donations', $oct + ['currency' => 'USD']);
same('USD confirmed amount stands alone', 1000, card($r, 'Confirmed amount'));
$r = $svc->run('donations', ['currency' => 'PKR']);
same('PKR all-time confirmed (Aug + Sep + Oct) excludes the USD gift', 2180000, card($r, 'Confirmed amount'));

section('Filters narrow the numbers correctly');
$r = $svc->run('donations', $oct + ['gateway' => 'meezan']);
same('Meezan confirmed (d1, d3, d12, d15)', 710000, card($r, 'Confirmed amount'));
$r = $svc->run('donations', $oct + ['campaign_id' => '1']);
same('campaign A confirmed (d1,d2,d3,d14,d15)', 950000, card($r, 'Confirmed amount'));
$r = $svc->run('donations', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']);
same('September confirmed', 400000, card($r, 'Confirmed amount'));

// ---------------------------------------------------------------------------
section('Overview');
$r = $svc->run('overview', $oct);
same('confirmed', 1030000, card($r, 'Confirmed donations'));
same('refunds paid in range come from the refunds table', 300000, card($r, 'Refunded'));
same('success rate = (completed+refunded)/(finished)', 70.0, card($r, 'Payment success rate'));
same('pending money', 240000, card($r, 'Awaiting the bank'));
same('live fundraisers right now', 2, card($r, 'Live fundraisers'));
same('awaiting approval right now', 1, card($r, 'Awaiting approval'));
same('stuck payments right now (processing d7 + pending d13)', 2, card($r, 'Stuck payments'));
check('trend has one point per day of the range', count($r['chart']['series']) === 31);
same('trend total equals confirmed', 1030000, array_sum(array_column($r['chart']['series'], 'minor')));
same('top fundraiser is F1 (d1+d2+d15)', 780000, (int) $r['rows'][0]['raised_minor']);
same('general-fund money is reported', 80000, $r['general']);
$r = $svc->run('overview', ['date_from' => '2026-01-01', 'date_to' => '2026-10-31']);
check('long ranges switch to monthly points', $r['chart']['unit'] === 'month' && count($r['chart']['series']) === 10);

// ---------------------------------------------------------------------------
section('Payment Transactions');
$r = $svc->run('payments', $oct);
same('attempts in October', 12, card($r, 'Payment attempts'));
same('completed amount', 1030000, card($r, 'Completed'));
same('failed attempts', 2, card($r, 'Failed'));
same('refunded transactions', 1, card($r, 'Refunded'));
same('no duplicate completed payments in the clean data', 0, card($r, 'Duplicate completed payments'));
$r = $svc->run('payments', $oct + ['status' => 'failed']);
same('status filter', 2, $r['total']);
$r = $svc->run('payments', $oct + ['q' => 'B14']);
same('search by bank transaction', 1, $r['total']);
$r = $svc->run('payments', $oct + ['q' => 'DON-0003']);
same('search by donation reference finds both attempts', 2, $r['total']);

// ---------------------------------------------------------------------------
section('Campaign, Fundraiser and Team performance');
$r = $svc->run('campaigns', $oct);
$byTitle = array_column($r['rows'], null, 'title');
same('campaign A raised', 950000, (int) $byTitle['Campaign A']['raised_minor']);
same('campaign A donors', 3, (int) $byTitle['Campaign A']['donors']);
same('campaign A pending', 240000, (int) $byTitle['Campaign A']['pending_minor']);
same('campaign A refunded', 300000, (int) $byTitle['Campaign A']['refunded_minor']);
same('campaign A fundraisers exclude the deleted one', 2, (int) $byTitle['Campaign A']['fundraisers']);
same('campaign A combined goals exclude the deleted one', 3000000, (int) $byTitle['Campaign A']['goal_minor']);
same('campaign B raised nothing in October', 0, (int) $byTitle['Campaign B']['raised_minor']);
same('money outside campaigns', 80000, card($r, 'Raised outside campaigns'));
$r = $svc->run('fundraisers', $oct);
$by = array_column($r['rows'], null, 'title');
same('F1 raised in period', 780000, (int) $by['Fundraiser One']['raised_period']);
same('F2 raised in period', 170000, (int) $by['Fundraiser Two']['raised_period']);
same('F3 raised in period', 0, (int) $by['Fundraiser Three']['raised_period']);
same('F3 raised all time', 750000, (int) $by['Fundraiser Three']['raised_total']);
same('F1 percent of goal', 39.0, (float) $by['Fundraiser One']['goal_pct']);
check('deleted fundraiser never appears', !isset($by['Fundraiser Deleted']));
$sumPeriod = array_sum(array_map(static fn ($x) => (int) $x['raised_period'], $r['rows']));
$gen = $svc->run('overview', $oct)['general'];
same('fundraiser totals + general fund = all confirmed (nothing counted twice)', 1030000, $sumPeriod + $gen);
$r = $svc->run('fundraisers', $oct + ['campaign_id' => '2']);
same('fundraiser campaign filter', 1, $r['total']);
$r = $svc->run('teams', $oct);
same('team raised', 750000, (int) $r['rows'][0]['raised_period']);
same('team members', 2, (int) $r['rows'][0]['members']);
same('team donors', 2, (int) $r['rows'][0]['donors']);

// ---------------------------------------------------------------------------
section('Donor Report');
$r = $svc->run('donors', $oct);
same('donors with a confirmed gift', 3, card($r, 'Donors'));
same('new donors', 2, card($r, 'New donors'));
same('returning donors', 1, card($r, 'Returning donors'));
same('total given', 1030000, card($r, 'Total given'));
same('anonymous gifts counted', 1, card($r, 'Anonymous gifts'));
same('top donor first (Donor One, 600000)', 600000, (int) $r['rows'][0]['total_minor']);
$r = $svc->run('donors', $oct + ['donor_type' => 'returning']);
same('returning filter', 1, $r['total']);
$r = $svc->run('donors', $oct + ['min_gifts' => '2']);
same('repeat-giver filter (all three gave twice)', 3, $r['total']);
$r = $svc->run('donors', $oct + ['q' => 'd2@example']);
same('donor search by email', 1, $r['total']);

// ---------------------------------------------------------------------------
section('Fundraiser Approval Report');
$r = $svc->run('approvals', $oct);
same('fundraisers in review history (draft left out)', 4, card($r, 'Fundraisers in review history'));
same('waiting for review', 1, card($r, 'Waiting for review'));
same('approved', 2, card($r, 'Approved'));
same('rejected', 1, card($r, 'Rejected'));
same('approval rate', 66.7, card($r, 'Approval rate'));
same('average review time (hours)', 18.7, (float) card($r, 'Average review time'));
$by = array_column($r['rows'], null, 'title');
same('reviewer comes from the audit log', 'admin@example.org', $by['Fundraiser One']['reviewer']);
same('turnaround for F2 = 48 hours', 48.0, (float) $by['Fundraiser Two']['review_hours']);
same('rejection reason shown', 'Photo is not clear', $by['Fundraiser Five']['rejection_reason']);
$r = $svc->run('approvals', $oct + ['approval_status' => 'rejected']);
same('decision filter', 1, $r['total']);

// ---------------------------------------------------------------------------
section('Reconciliation');
$r = $svc->run('reconciliation', $oct);
same('payments reviewed', 12, card($r, 'Payments reviewed'));
same('exceptions found', 4, card($r, 'Exceptions found'));
same('status mismatches (bank paid, donation pending)', 1, card($r, 'Status mismatches'));
same('amount mismatches', 1, card($r, 'Amount mismatches'));
same('missing bank reference', 1, card($r, 'No bank reference'));
same('stuck unresolved', 1, card($r, 'Stuck unresolved'));
same('confirmed donation with no payment at all', 1, card($r, 'Confirmed without payment'));
same('default view lists exceptions only', 4, $r['total']);
$r = $svc->run('reconciliation', $oct + ['issue' => 'all']);
same('"all" includes matched rows', 12, $r['total']);
$r = $svc->run('reconciliation', $oct + ['issue' => 'amount_mismatch']);
same('single-issue filter', 1, $r['total']);
check('limitation about bank transfers is stated', str_contains(implode(' ', $r['notes']), 'Bank transfers are not recorded'));

// ---------------------------------------------------------------------------
section('Refund & Failed Payments');
$r = $svc->run('refunds', $oct);
same('rows = refunded + failed + cancelled + abandoned', 4, $r['total']);
same('refunded count', 1, card($r, 'Refunded donations'));
same('refunded amount', 300000, card($r, 'Refunded amount'));
same('failed count', 1, card($r, 'Failed'));
same('unsuccessful rate = 3 / 10 finished', 30.0, card($r, 'Unsuccessful rate'));
$reasons = array_column($r['extra'][0]['rows'], 'c', 'reason');
same('top failure reason is grouped', 1, (int) ($reasons['E51 Insufficient funds'] ?? 0));
same('failed refund requests are listed separately', 1, count($r['extra'][1]['rows']));
$r = $svc->run('refunds', $oct + ['type' => 'refunded']);
same('type filter', 1, $r['total']);
$anon = $svc->run('refunds', $oct)['rows'];
check('refunds report shows the refunded-by admin', in_array('admin@example.org', array_column($anon, 'refunded_by'), true));

// ---------------------------------------------------------------------------
section('Financial Summary');
$r = $svc->run('financial', $oct);
same('gross received', 1330000, card($r, 'Gross received'));
same('refunds paid', 300000, card($r, 'Refunded'));
same('net = gross - refunds', 1030000, card($r, 'Net received'));
same('average gift', 190000, card($r, 'Average gift'));
$r = $svc->run('financial', []);
same('all-time gross', 2480000, card($r, 'Gross received'));
same('all-time net', 2180000, card($r, 'Net received'));
same('three monthly rows (Aug, Sep, Oct)', 3, $r['total']);
$r = $svc->run('financial', $oct + ['currency' => 'USD']);
same('USD kept separate', 1000, card($r, 'Gross received'));
$r = $svc->run('financial', $oct + ['gateway' => 'meezan']);
same('gateway filter applies to gross (Meezan d1,d3,d4,d12,d15)', 1010000, card($r, 'Gross received'));

// ---------------------------------------------------------------------------
section('Donation Receipts');
$r = $svc->run('receipts', $oct);
same('receipts due', 7, card($r, 'Receipts due'));
same('sent', 4, card($r, 'Sent'));
same('queued', 1, card($r, 'Queued'));
same('failed', 1, card($r, 'Failed'));
same('not sent', 1, card($r, 'Not sent'));
same('delivery rate', 57.1, card($r, 'Delivery rate'));
$by = array_column($r['rows'], null, 'public_reference');
same('failed receipt shows the mail error', 'SMTP timeout', $by['DON-0012']['last_error']);
$r = $svc->run('receipts', $oct + ['receipt_status' => 'failed']);
same('receipt status filter', 1, $r['total']);

// ---------------------------------------------------------------------------
section('Audit log');
$r = $svc->run('audit', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
same('admins-only events (default): 2 approvals, 1 rejection, 1 refund, 1 settings change', 5, card($r, 'Events'));
$r = $svc->run('audit', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'who' => 'all']);
same('everyone', 12, card($r, 'Events'));
$r = $svc->run('audit', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'area' => 'payment']);
same('area filter', 1, $r['total']);
$r = $svc->run('audit', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'q' => 'admin@example']);
same('search by admin email', 5, $r['total']);
check('metadata is turned into readable text', str_contains((string) $r['rows'][0]['details'], 'note: x'));

// ---------------------------------------------------------------------------
section('Sorting, pagination and hostile input');
$r = $svc->run('payments', $oct + ['sort' => 'amount_minor', 'dir' => 'asc']);
same('sort ascending by amount', 30000, (int) $r['rows'][0]['amount_minor']);
$r = $svc->run('payments', $oct + ['sort' => 'amount_minor', 'dir' => 'desc']);
same('sort descending by amount', 500000, (int) $r['rows'][0]['amount_minor']);
$r = $svc->run('payments', $oct + ['sort' => 'id; DROP TABLE donations;--']);
check('unknown sort column is ignored, not executed', $r['total'] === 12 && (int) $db->int('SELECT COUNT(*) FROM donations') === 15);
$r = $svc->run('payments', $oct + ['q' => "' OR 1=1 --"]);
same('SQL in the search box matches nothing', 0, $r['total']);
$r = $svc->run('payments', $oct + ['q' => '%']);
same('wildcard characters are escaped', 0, $r['total']);
for ($i = 1; $i <= 60; $i++) {
    $db->insert('audit_logs', ['user_id' => 1, 'action' => 'test.bulk', 'entity_type' => 'x', 'entity_id' => (string) $i, 'safe_metadata_json' => '{}', 'created_at' => '2026-10-30 10:00:00']);
}
$r1 = $svc->run('audit', ['date_from' => '2026-10-30', 'date_to' => '2026-10-30'], 1);
$r3 = $svc->run('audit', ['date_from' => '2026-10-30', 'date_to' => '2026-10-30'], 3);
same('pagination: total', 60, $r1['total']);
same('pagination: first page size', 25, count($r1['rows']));
same('pagination: last page size', 10, count($r3['rows']));
same('pagination: page count', 3, $r1['lastPage']);

section('A donation is never counted twice');
$db->insert('payment_transactions', ['donation_id' => 1, 'gateway_id' => 1, 'environment' => 'live', 'merchant_order_id' => 'DUP', 'provider_transaction_id' => 'B1-again',
    'amount_minor' => 500000, 'currency' => 'PKR', 'status' => 'completed', 'idempotency_key' => 'dupe', 'callback_state_hash' => hash('sha256', 'dupe'), 'callback_state_expires_at' => '2030-01-01 00:00:00', 'request_reference' => 'REQDUP', 'created_at' => '2026-10-06 10:00:00']);
$r = $svc->run('donations', $oct);
same('confirmed total unchanged by a second payment record', 1030000, card($r, 'Confirmed amount'));
$r = $svc->run('financial', $oct);
same('financial gross unchanged', 1330000, card($r, 'Gross received'));
$r = $svc->run('payments', $oct);
same('the duplicate is flagged instead', 1, card($r, 'Duplicate completed payments'));

// ---------------------------------------------------------------------------
section('Empty results');
// Reports built on a fixed list (campaigns, fundraisers, teams) still list each
// record, with zero money, when nothing was raised in the period.
foreach (['campaigns', 'fundraisers', 'teams'] as $slug) {
    $r = $svc->run($slug, ['date_from' => '2030-01-01', 'date_to' => '2030-01-31']);
    $raised = array_sum(array_map(static fn ($x) => (int) ($x['raised_period'] ?? $x['raised_minor'] ?? 0), $r['rows']));
    same("{$slug}: nothing raised in an empty period", 0, $raised);
}
foreach (array_keys(ReportService::catalog()) as $slug) {
    $r = $svc->run($slug, ['q' => 'zzz-no-such-record-zzz', 'issue' => 'all', 'who' => 'all']);
    if (in_array($slug, ['overview', 'donations', 'financial'], true)) {
        $r = $svc->run($slug, ['date_from' => '2030-01-01', 'date_to' => '2030-01-31']);
    }
    check("{$slug}: no matching records gives an empty table, no error", $r['rows'] === [], 'rows=' . count($r['rows']));
}

// ---------------------------------------------------------------------------
section('Permissions');
$router = new Router();
$register = require dirname(__DIR__) . '/routes/admin.php';
$register($router);
$ref = new ReflectionProperty(Router::class, 'routes');
$ref->setAccessible(true);
$reportRoutes = 0;
$allProtected = true;
$getOnly = true;
foreach ($ref->getValue($router) as $route) {
    $pattern = (string) ($route['pattern'] ?? $route[1] ?? '');
    if (!str_starts_with($pattern, '/admin/reports')) {
        continue;
    }
    $reportRoutes++;
    $mw = (array) ($route['middleware'] ?? $route[3] ?? []);
    $allProtected = $allProtected && in_array(AuthMiddleware::class, $mw, true) && in_array(AdminMiddleware::class, $mw, true);
    $getOnly = $getOnly && strtoupper((string) ($route['method'] ?? $route[0] ?? 'GET')) === 'GET';
}
same('three report routes are registered (page, overview, pdf)', 3, $reportRoutes);
check('every report route requires login AND the admin role', $allProtected);
check('report routes are read-only (GET)', $getOnly);

$makeAuth = static function (?array $roles): AuthService {
    $rc = new ReflectionClass(AuthService::class);
    $a = $rc->newInstanceWithoutConstructor();
    $resolved = new ReflectionProperty(AuthService::class, 'resolved');
    $resolved->setAccessible(true);
    $resolved->setValue($a, true);
    $user = new ReflectionProperty(AuthService::class, 'cachedUser');
    $user->setAccessible(true);
    $user->setValue($a, $roles === null ? null : ['id' => 9, 'roles' => $roles, 'status' => 'active']);

    return $a;
};
$gate = static function (?array $roles) use ($makeAuth): string {
    $mw = new AdminMiddleware($makeAuth($roles));
    try {
        $mw->handle(new Request('GET', '/admin/reports/donations/pdf', [], [], [], []), static fn () => new App\Core\Response('ok'));

        return 'allowed';
    } catch (HttpException $e) {
        return (string) $e->getCode();
    }
};
same('signed-out visitor is sent to log in (302)', '302', $gate(null));
same('donor is refused (403)', '403', $gate(['donor']));
same('fundraiser is refused (403)', '403', $gate(['fundraiser']));
same('admin is allowed', 'allowed', $gate(['admin']));
same('super admin is allowed', 'allowed', $gate(['super_admin']));

// ---------------------------------------------------------------------------
section('PDF generation');
$pdfSvc = new ReportPdfService();
$pagesOf = static fn (string $pdf): int => preg_match_all('#/Type /Page\b(?!s)#', $pdf);
foreach (array_keys(ReportService::catalog()) as $slug) {
    $r = $svc->run($slug, $oct + ['who' => 'all'], 1, true);
    $pdf = $pdfSvc->render($r, $svc->describeFilters($r), ['org' => 'Almarah Foundation', 'by' => 'admin@example.org']);
    check("{$slug}: real PDF document", str_starts_with($pdf, '%PDF-') && str_contains(substr($pdf, -20), '%%EOF') && strlen($pdf) > 1500, 'size ' . strlen($pdf));
}
$r = $svc->run('financial', ['date_from' => '2030-01-01', 'date_to' => '2030-01-31'], 1, true);
$pdf = $pdfSvc->render($r, $svc->describeFilters($r), ['compress' => false]);
check('an empty report still produces a valid PDF', str_starts_with($pdf, '%PDF-') && str_contains($pdf, 'No records match the selected filters.'));

$r = $svc->run('financial', $oct, 1, true);
$pdf = $pdfSvc->render($r, $svc->describeFilters($r), ['compress' => false, 'org' => 'Almarah Foundation', 'by' => 'admin@example.org']);
check('PDF carries the report title', str_contains($pdf, 'Financial Summary Report'));
check('PDF carries the selected range', str_contains($pdf, '1 Oct 2026 to 31 Oct 2026'));
check('PDF carries a generated date/time', preg_match('/Generated \d{1,2} \w{3} \d{4}/', $pdf) === 1);
check('PDF carries summary totals', str_contains($pdf, 'Rs 13,300') && str_contains($pdf, 'Rs 10,300'));
check('PDF carries branding', str_contains($pdf, 'ALMARAH') && str_contains($pdf, 'confidential'));
check('PDF has page numbers', str_contains($pdf, 'Page 1 of'));
check('PDF states the limitations', str_contains($pdf, 'Gateway fees'));

// A long table must paginate and repeat its column header on every page.
for ($i = 1; $i <= 400; $i++) {
    $db->insert('audit_logs', ['user_id' => 1, 'action' => 'test.long', 'entity_type' => 'x', 'entity_id' => (string) $i, 'safe_metadata_json' => '{"note":"row ' . $i . '"}', 'created_at' => '2026-10-31 10:00:00']);
}
$r = $svc->run('audit', ['date_from' => '2026-10-31', 'date_to' => '2026-10-31'], 1, true);
$pdf = $pdfSvc->render($r, $svc->describeFilters($r), ['compress' => false]);
$pages = $pagesOf($pdf);
check('400-row table spans several pages', $pages >= 8, "pages={$pages}");
check('column header repeats on every page', substr_count($pdf, '(IP address)') >= $pages - 1, 'headers=' . substr_count($pdf, '(IP address)') . " pages={$pages}");
check('every page shows "Page n of N"', substr_count($pdf, 'of ' . $pages) >= $pages);

// Export cap is announced instead of silently dropping rows.
$r = $svc->run('audit', ['date_from' => '2026-10-31', 'date_to' => '2026-10-31'], 1, true);
check('rows under the cap are not flagged as truncated', $r['truncated'] === false);

// Non-Latin text must not crash the exporter.
$db->insert('donors', ['id' => 99, 'name' => 'احمد خان', 'email' => 'urdu@example.org']);
$db->insert('donations', ['id' => 99, 'public_reference' => 'DON-0099', 'donor_id' => 99, 'gateway_id' => 1, 'amount_minor' => 1000, 'currency' => 'PKR', 'status' => 'completed',
    'anonymous' => 0, 'created_at' => '2026-10-31 09:00:00', 'completed_at' => '2026-10-31 09:00:00']);
$r = $svc->run('donors', $oct, 1, true);
$pdf = $pdfSvc->render($r, $svc->describeFilters($r));
check('Urdu names do not break the PDF', str_starts_with($pdf, '%PDF-'));

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 60) . "\n";
echo $GLOBALS['t_pass'] . ' passed, ' . count($GLOBALS['t_fail']) . " failed\n";
if ($GLOBALS['t_fail'] !== []) {
    echo "Failed:\n - " . implode("\n - ", $GLOBALS['t_fail']) . "\n";
    exit(1);
}
exit(0);
