<?php
/**
 * One report page (all 13 reports share it). Built from the array returned by
 * ReportService::run(), so what is on screen is exactly what the PDF contains.
 *
 * @var array  $report   normalised report
 * @var array  $catalog  slug => [title, short, icon]
 * @var array  $describe active filters as [label, value] pairs
 * @var array  $pdfQuery active filters as a query array
 */

use App\Services\ReportService;

require_once __DIR__ . '/../../partials/charts.php';

$slug = (string) $report['slug'];
$f = $report['filters'];
$cur = (string) $report['currency'];
$basePath = $slug === 'overview' ? 'admin/reports' : 'admin/reports/' . $slug;
$qs = static fn (array $q): string => $q === [] ? '' : '?' . http_build_query($q);
$hasFilters = false;
foreach ($pdfQuery as $k => $v) {
    if (!in_array($k, ['sort', 'dir'], true) && $v !== '') {
        $hasFilters = true;
        break;
    }
}

// Quick date ranges keep every other filter.
$keep = array_diff_key($pdfQuery, array_flip(['date_from', 'date_to', 'sort', 'dir', 'page']));
$today = gmdate('Y-m-d');
$presets = [
    'Today'        => [$today, $today],
    'Last 7 days'  => [gmdate('Y-m-d', strtotime('-6 days')), $today],
    'Last 30 days' => [gmdate('Y-m-d', strtotime('-29 days')), $today],
    'This month'   => [gmdate('Y-m-01'), $today],
    'This year'    => [gmdate('Y-01-01'), $today],
    'All time'     => ['', ''],
];

// Sort links flip the direction of the current column.
$sortLink = static function (string $key) use ($pdfQuery, $report, $basePath, $qs): string {
    $q = $pdfQuery;
    unset($q['page']);
    $current = (string) ($report['sort'] ?? '');
    $dir = (string) ($report['dir'] ?? 'desc');
    $q['sort'] = $key;
    $q['dir'] = $current === $key && $dir === 'asc' ? 'desc' : 'asc';

    return base_url($basePath) . $qs($q);
};

$cellHtml = static function (array $col, array $row) use ($cur): string {
    $text = ReportService::cell($col, $row, $cur);
    $raw = (string) ($row[$col['key']] ?? '');
    $type = (string) ($col['type'] ?? 'text');
    $out = e($text);
    if ($type === 'status' && $raw !== '') {
        $out = '<span class="' . e(status_badge_class($raw)) . '">' . e($text) . '</span>';
    } elseif ($type === 'badge' && $raw !== '') {
        $out = '<span class="badge badge-grey">' . e($text) . '</span>';
    } elseif ($type === 'code' && $raw !== '') {
        $out = '<span class="mono">' . e($text) . '</span>';
    }
    $link = (string) ($col['link'] ?? '');
    if ($link !== '' && $raw !== '') {
        $id = $row['donation_id'] ?? $row['id'] ?? null;
        $href = match ($link) {
            'donation'   => $id !== null ? 'admin/donations/' . $id : '',
            'fundraiser' => isset($row['id']) ? 'admin/fundraisers/' . $row['id'] : '',
            'campaign'   => isset($row['id']) ? 'admin/campaigns/' . $row['id'] . '/edit' : '',
            default      => '',
        };
        if ($href !== '') {
            $out = '<a href="' . e(base_url($href)) . '">' . $out . '</a>';
        }
    }

    return $out;
};

$table = static function (array $columns, array $rows, ?array $sortable = null) use ($cellHtml, $report, $sortLink): string {
    $html = '<div class="table-wrap"><table class="data-table rp-table"><thead><tr>';
    foreach ($columns as $c) {
        $cls = (($c['align'] ?? '') === 'right' ? 'num' : '');
        $label = e((string) $c['label']);
        if ($sortable !== null && !empty($c['sort'])) {
            $active = (string) ($report['sort'] ?? '') === (string) $c['key'];
            $arrow = $active ? ((string) ($report['dir'] ?? 'desc') === 'asc' ? ' ▲' : ' ▼') : '';
            $label = '<a class="rp-sort' . ($active ? ' is-active' : '') . '" href="' . e($sortLink((string) $c['key'])) . '">' . $label . $arrow . '</a>';
        }
        $html .= '<th class="' . $cls . '">' . $label . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $c) {
            $type = (string) ($c['type'] ?? 'text');
            $cls = trim((($c['align'] ?? '') === 'right' ? 'num ' : '') . (in_array($type, ['text'], true) ? 'rp-wrap' : ''));
            $html .= '<td class="' . $cls . '">' . $cellHtml($c, $row) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
};
?>
<div class="dash-head">
  <div>
    <h1><?= e((string) $report['title']) ?></h1>
    <p class="sub"><?= e((string) ($report['description'] ?? '')) ?></p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-brand" id="pdfExport" href="<?= e(base_url('admin/reports/' . $slug . '/pdf') . $qs(array_diff_key($pdfQuery, array_flip(['page'])))) ?>" download>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
      <span>Export PDF</span>
    </a>
  </div>
</div>

<!-- filters -->
<section class="panel rp-filters">
  <form method="get" action="<?= e(base_url($basePath)) ?>" class="rp-form">
    <div class="rp-fields">
      <div class="rp-field">
        <label for="date_from">From</label>
        <input class="input" type="date" id="date_from" name="date_from" value="<?= e((string) $f['date_from']) ?>">
      </div>
      <div class="rp-field">
        <label for="date_to">To</label>
        <input class="input" type="date" id="date_to" name="date_to" value="<?= e((string) $f['date_to']) ?>">
      </div>
      <?php foreach ($report['filterDefs'] as $def): $name = (string) $def['name']; ?>
        <div class="rp-field <?= $def['type'] === 'text' ? 'rp-field-wide' : '' ?>">
          <label for="f_<?= e($name) ?>"><?= e((string) $def['label']) ?></label>
          <?php if ($def['type'] === 'text'): ?>
            <input class="input" type="search" id="f_<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e((string) ($f[$name] ?? '')) ?>" placeholder="<?= e((string) ($def['placeholder'] ?? '')) ?>" maxlength="100">
          <?php else: ?>
            <select class="select-field" id="f_<?= e($name) ?>" name="<?= e($name) ?>">
              <?php if (empty($def['noAny'])): ?><option value="">Any</option><?php endif; ?>
              <?php foreach ($def['options'] as $value => $label): ?>
                <option value="<?= e((string) $value) ?>" <?= (string) ($f[$name] ?? '') === (string) $value ? 'selected' : '' ?>><?= e((string) $label) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="rp-form-actions">
      <button class="btn btn-brand" type="submit">Apply filters</button>
      <a class="btn btn-light" href="<?= e(base_url($basePath) . ($slug === 'overview' ? '' : '')) ?>">Reset</a>
    </div>
  </form>
  <div class="rp-presets" aria-label="Quick date ranges">
    <?php foreach ($presets as $label => [$from, $to]): ?>
      <?php $on = (string) $f['date_from'] === $from && (string) $f['date_to'] === $to; ?>
      <a class="fr-chip<?= $on ? ' is-active' : '' ?>" href="<?= e(base_url($basePath) . $qs($keep + ['date_from' => $from, 'date_to' => $to])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <p class="rp-basis"><b>Showing:</b> <?= e(ReportService::rangeText($f)) ?>. <?= e((string) ($report['basis'] ?? '')) ?> <span class="muted">All times are UTC.</span></p>
</section>

<!-- summary cards -->
<?php if ($report['summary'] !== []): ?>
  <div class="stat-cards rp-cards">
    <?php foreach ($report['summary'] as $i => $card): ?>
      <?php
      $v = $card['value'] ?? null;
      $shown = match ((string) ($card['kind'] ?? 'text')) {
          'money'   => money((int) $v, (string) ($card['currency'] ?? $cur)),
          'int'     => number_format((float) $v),
          'percent' => $v === null ? '—' : number_format((float) $v, 1) . '%',
          'hours'   => $v === null ? '—' : ((float) $v >= 48 ? number_format((float) $v / 24, 1) . ' days' : number_format((float) $v, 1) . ' h'),
          default   => (string) $v,
      };
      ?>
      <div class="stat-card<?= $i === 0 ? ' brand' : '' ?>">
        <div class="k"><?= e((string) $card['label']) ?></div>
        <div class="v<?= ($card['kind'] ?? '') === 'text' ? ' v-text' : '' ?>"><?= e($shown) ?></div>
        <?php if (($card['sub'] ?? '') !== ''): ?><div class="d"><?= e((string) $card['sub']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($slug === 'overview'): ?>
  <?php
  $series = $report['chart']['series'] ?? [];
  $trendTotal = (int) array_sum(array_column($series, 'minor'));
  $mixSlices = ch_status_slices($report['statusMix'] ?? []);
  $gwColors = ['#a92d63', '#f2c200', '#3b4b86', '#1e7a45', '#8e8d8d'];
  $gwSlices = [];
  foreach (array_values($report['gateways'] ?? []) as $i => $g) {
      $gwSlices[] = ['label' => (string) $g['gateway'], 'value' => (int) $g['confirmed_minor'], 'color' => $gwColors[$i % count($gwColors)], 'text' => money_short((int) $g['confirmed_minor'], $cur)];
  }
  $campRows = [];
  $campMax = max(1, (int) max([0] + array_column($report['topCampaigns'] ?? [], 'raised_minor')));
  foreach (($report['topCampaigns'] ?? []) as $c) {
      $campRows[] = ['label' => (string) $c['title'], 'pct' => (int) $c['raised_minor'] / $campMax * 100, 'right' => money_short((int) $c['raised_minor'], $cur), 'sub' => number_format((int) $c['donations']) . ' donation(s)'];
  }
  ?>
  <div class="grid-chart">
    <section class="panel">
      <div class="ch-head">
        <div>
          <h2>Confirmed donations over time</h2>
          <p class="panel-sub"><?= ($report['chart']['unit'] ?? 'day') === 'day' ? 'Per day' : 'Per month' ?>, completed donations only.</p>
        </div>
        <div class="ch-kpi"><b><?= e(money($trendTotal, $cur)) ?></b><small>in this period</small></div>
      </div>
      <?= ch_area($series, 'rpt') ?>
    </section>
    <section class="panel">
      <h2>Donation outcomes</h2>
      <p class="panel-sub">Every donation made in the period.</p>
      <?= ch_donut($mixSlices, number_format((int) array_sum(array_column($mixSlices, 'value'))), 'donations') ?>
    </section>
  </div>
  <div class="grid-chart grid-chart-rev">
    <section class="panel">
      <h2>Top campaigns</h2>
      <p class="panel-sub">Confirmed money raised in the period.</p>
      <?= ch_hbars($campRows, 'No campaign received a confirmed donation in this period.') ?>
    </section>
    <section class="panel">
      <h2>Share by payment method</h2>
      <p class="panel-sub">Confirmed money per gateway.</p>
      <?= ch_donut($gwSlices, money_short((int) array_sum(array_column($gwSlices, 'value')), $cur), 'confirmed') ?>
    </section>
  </div>
<?php endif; ?>

<!-- main results -->
<section class="panel">
  <div class="panel-head">
    <h2><?= e((string) ($report['rowsTitle'] ?? 'Results')) ?></h2>
    <span class="muted rp-count"><?= e(number_format((int) $report['total'])) ?> record<?= (int) $report['total'] === 1 ? '' : 's' ?></span>
  </div>
  <?php if ($report['rows'] === []): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
      <h3>No records match these filters</h3>
      <p>Try a wider date range, or clear the filters.</p>
      <a class="btn btn-brand" href="<?= e(base_url($basePath) . $qs(['date_from' => '', 'date_to' => ''])) ?>">Show all time</a>
    </div>
  <?php else: ?>
    <?= $table((array) $report['columns'], (array) $report['rows'], $slug === 'overview' ? null : []) ?>
    <?php
    $query = array_diff_key($pdfQuery, array_flip(['page']));
    $page = (int) $report['page'];
    $lastPage = (int) $report['lastPage'];
    $total = (int) $report['total'];
    require __DIR__ . '/../../partials/pagination.php';
    ?>
  <?php endif; ?>
</section>

<?php foreach ((array) ($report['extra'] ?? []) as $extra): ?>
  <section class="panel">
    <h2><?= e((string) $extra['title']) ?></h2>
    <?php if (($extra['rows'] ?? []) === []): ?>
      <p class="muted">Nothing to show for these filters.</p>
    <?php else: ?>
      <?= $table((array) $extra['columns'], (array) $extra['rows']) ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php if (!empty($report['notes'])): ?>
  <section class="panel rp-notes">
    <h2>Notes and limitations</h2>
    <ul>
      <?php foreach ($report['notes'] as $note): ?><li><?= e((string) $note) ?></li><?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($slug === 'overview'): ?>
  <section class="panel">
    <h2>All reports</h2>
    <p class="panel-sub">Each report has its own filters and an Export PDF button.</p>
    <div class="rp-directory">
      <?php foreach ($catalog as $key => $item): if ($key === 'overview') { continue; } ?>
        <a class="rp-tile" href="<?= e(base_url('admin/reports/' . $key)) ?>">
          <b><?= e($item['title']) ?></b>
          <span><?= e($item['short']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
