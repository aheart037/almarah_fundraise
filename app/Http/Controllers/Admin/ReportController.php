<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\ReportPdfService;
use App\Services\ReportService;

/**
 * Admin reports (v20). Read-only.
 *
 * Authorization is enforced by the route middleware (login + admin role) for
 * both the web pages and the PDF downloads; this controller adds no way around
 * it. Filters are re-validated on the server for every request, so a PDF is
 * always built from the same cleaned filters as the page.
 */
final class ReportController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private ReportService $reports,
        private ReportPdfService $pdf,
        private AuditService $audit
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    /** /admin/reports opens the overview dashboard. */
    public function overview(Request $request): Response
    {
        return $this->render('admin/reports/show', $this->data('overview', $request), 'layouts/admin');
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->routeParam('slug', '');
        if (!ReportService::exists($slug)) {
            abort(404, 'Report not found.');
        }

        return $this->render('admin/reports/show', $this->data($slug, $request), 'layouts/admin');
    }

    public function pdf(Request $request): Response
    {
        $slug = (string) $request->routeParam('slug', '');
        if (!ReportService::exists($slug)) {
            abort(404, 'Report not found.');
        }

        $report = $this->reports->run($slug, $request->all(), 1, true);
        $user = $this->auth->user() ?? [];
        $filters = $this->reports->describeFilters($report);

        $binary = $this->pdf->render($report, $filters, [
            'org'  => (string) \App\Core\Config::get('app.name', 'Almarah Foundation'),
            'by'   => (string) ($user['email'] ?? ''),
            'logo' => ReportPdfService::resolveLogo(),
        ]);

        // Leave a trail: exports contain donor and financial data.
        $this->audit->log('report.exported', 'report', $slug, [
            'rows'    => min((int) $report['total'], ReportService::EXPORT_LIMIT),
            'range'   => ReportService::rangeText($report['filters']),
            'filters' => array_filter(array_diff_key($report['filters'], array_flip(['sort', 'dir', 'dir_given', 'date_from', 'date_to'])), static fn ($v): bool => $v !== '' && $v !== null),
        ], isset($user['id']) ? (int) $user['id'] : null);

        return new Response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . ReportPdfService::fileName($slug) . '"',
            'Content-Length'      => (string) strlen($binary),
            'Cache-Control'       => 'private, no-store, max-age=0',
            'X-Robots-Tag'        => 'noindex, nofollow',
        ]);
    }

    /** @return array<string,mixed> */
    private function data(string $slug, Request $request): array
    {
        $report = $this->reports->run($slug, $request->all(), max(1, (int) $request->input('page', 1)), false);

        return [
            'pageTitle' => $report['title'],
            'report'    => $report,
            'catalog'   => ReportService::catalog(),
            'describe'  => $this->reports->describeFilters($report),
            'pdfQuery'  => $this->queryFor($report),
        ];
    }

    /**
     * Active filters as a query array, used for the PDF button, sort links and
     * pagination so every control keeps the current filters.
     *
     * @param array<string,mixed> $report
     * @return array<string,string>
     */
    private function queryFor(array $report): array
    {
        $f = $report['filters'];
        $out = [];
        // Only the filters this report actually has (plus the dates), so links
        // and the PDF button stay short and free of irrelevant defaults.
        $names = array_merge(['date_from', 'date_to'], array_column($report['filterDefs'], 'name'));
        foreach ($names as $k) {
            if (($f[$k] ?? '') !== '') {
                $out[$k] = (string) $f[$k];
            }
        }
        // The overview defaults to the last 30 days; keep an explicit "all time" choice.
        if ($report['slug'] === 'overview' && ($f['date_from'] ?? '') === '' && ($f['date_to'] ?? '') === '') {
            $out['date_from'] = '';
            $out['date_to'] = '';
        }
        if (($f['sort'] ?? '') !== '' || !empty($report['sort'])) {
            $out['sort'] = (string) ($f['sort'] !== '' ? $f['sort'] : $report['sort']);
            $out['dir'] = (string) ($report['dir'] ?? $f['dir']);
        }

        return $out;
    }
}
