<?php
/**
 * Dashboard charts (v18) - drawn on the server as plain SVG/HTML.
 *
 * No chart library and no external script: nothing extra to download, nothing
 * for the Content-Security-Policy to block. Animation is in style.css, the
 * hover tooltip is in app.js (initCharts). Every function returns a string
 * that is already escaped.
 *
 * Include once from a view:  require_once __DIR__ . '/../partials/charts.php';
 */

if (!function_exists('ch_area')) {
    /**
     * Smooth area/line chart over time.
     *
     * @param array<int,array{label:string,minor:int,count:int}> $series oldest first
     */
    function ch_area(array $series, string $id = 'trend'): string
    {
        $n = count($series);
        $max = 0;
        foreach ($series as $p) {
            $max = max($max, (int) $p['minor']);
        }
        if ($n < 2 || $max <= 0) {
            return '<div class="ch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-5 4 4 8-9"/><path d="M3 21h18"/></svg>'
                . '<p>No confirmed donations in the last 30 days yet. This chart fills in as soon as the first one arrives.</p></div>';
        }

        // Round the top of the scale up to a tidy number.
        $mag = 10 ** (int) floor(log10($max));
        $step = $mag / 2;
        $top = (int) (ceil($max / $step) * $step);
        if ($top <= 0) {
            $top = $max;
        }

        $w = 640; $h = 250;
        $padL = 72; $padR = 14; $padT = 14; $padB = 30;
        $pw = $w - $padL - $padR;
        $ph = $h - $padT - $padB;
        $x = static fn (int $i): float => $padL + ($pw * $i / ($n - 1));
        $y = static fn (int|float $v): float => $padT + $ph - ($ph * $v / $top);

        $safeId = preg_replace('/[^a-z0-9_-]/i', '', $id) ?: 'trend';
        $svg = '<svg class="ch-svg" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Confirmed donations per day, last 30 days">';
        $svg .= '<defs><linearGradient id="chg-' . $safeId . '" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0" stop-color="#a92d63" stop-opacity=".32"/><stop offset="1" stop-color="#a92d63" stop-opacity="0"/>'
            . '</linearGradient></defs>';

        // Grid lines + y labels.
        for ($g = 0; $g <= 4; $g++) {
            $val = $top * $g / 4;
            $gy = round($y($val), 1);
            $svg .= '<line class="ch-grid" x1="' . $padL . '" x2="' . ($w - $padR) . '" y1="' . $gy . '" y2="' . $gy . '"/>';
            $svg .= '<text class="ch-axis" x="' . ($padL - 8) . '" y="' . ($gy + 4) . '" text-anchor="end">'
                . e($val > 0 ? money_short((int) round($val)) : '0') . '</text>';
        }

        // X labels.
        foreach ($series as $i => $p) {
            if ($i % 5 === 0 || $i === $n - 1) {
                if ($i !== $n - 1 && $n - 1 - $i < 3) {
                    continue;
                }
                $svg .= '<text class="ch-axis" x="' . round($x($i), 1) . '" y="' . ($h - 8) . '" text-anchor="' . ($i === $n - 1 ? 'end' : 'middle') . '">' . e((string) $p['label']) . '</text>';
            }
        }

        // Smooth path (control points halfway between neighbours: no overshoot).
        $d = 'M' . round($x(0), 1) . ' ' . round($y((int) $series[0]['minor']), 1);
        for ($i = 1; $i < $n; $i++) {
            $x0 = $x($i - 1); $x1 = $x($i);
            $y0 = $y((int) $series[$i - 1]['minor']); $y1 = $y((int) $series[$i]['minor']);
            $mx = ($x0 + $x1) / 2;
            $d .= ' C' . round($mx, 1) . ' ' . round($y0, 1) . ',' . round($mx, 1) . ' ' . round($y1, 1) . ',' . round($x1, 1) . ' ' . round($y1, 1);
        }
        $base = $padT + $ph;
        $svg .= '<path class="ch-area" fill="url(#chg-' . $safeId . ')" d="' . $d . ' L' . round($x($n - 1), 1) . ' ' . $base . ' L' . round($x(0), 1) . ' ' . $base . ' Z"/>';
        $svg .= '<path class="ch-line" fill="none" stroke="#a92d63" stroke-width="3" pathLength="1" d="' . $d . '"/>';

        // Hover columns: guide line, dot and tooltip text per day.
        $colW = $pw / ($n - 1);
        foreach ($series as $i => $p) {
            $cx = round($x($i), 1);
            $cy = round($y((int) $p['minor']), 1);
            $tip = (string) $p['label'] . ' · ' . money((int) $p['minor']) . ' · '
                . (int) $p['count'] . ' donation' . ((int) $p['count'] === 1 ? '' : 's');
            $svg .= '<g class="ch-pt">'
                . '<line class="ch-guide" opacity="0" x1="' . $cx . '" x2="' . $cx . '" y1="' . $padT . '" y2="' . $base . '"/>'
                . '<circle class="ch-dot" opacity="0" fill="#fff" stroke="#a92d63" stroke-width="3" cx="' . $cx . '" cy="' . $cy . '" r="4.5"/>'
                . '<rect class="ch-hit" fill="none" pointer-events="all" x="' . round($cx - $colW / 2, 1) . '" y="' . $padT . '" width="' . round($colW, 1) . '" height="' . $ph . '" data-tip="' . e($tip) . '"/>'
                . '</g>';
        }

        return '<div class="ch-wrap">' . $svg . '</svg></div>';
    }
}

if (!function_exists('ch_donut')) {
    /**
     * Donut chart with a legend.
     *
     * @param array<int,array{label:string,value:int|float,color:string,text?:string}> $slices
     */
    function ch_donut(array $slices, string $centerValue, string $centerLabel): string
    {
        $slices = array_values(array_filter($slices, static fn (array $s): bool => (float) $s['value'] > 0));
        $total = 0.0;
        foreach ($slices as $s) {
            $total += (float) $s['value'];
        }
        if ($total <= 0) {
            return '<div class="ch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l6 4"/></svg><p>Nothing to show here yet.</p></div>';
        }

        $svg = '<svg class="ch-donut-svg" viewBox="0 0 200 200" role="img" aria-label="' . e($centerLabel) . '">'
            . '<circle class="ch-donut-track" fill="none" stroke="#f6e3ec" stroke-width="24" cx="100" cy="100" r="70"/>';
        $offset = 0.0;
        $legend = '';
        $circ = 2 * M_PI * 70; // dash lengths are in real units, so every browser agrees
        foreach ($slices as $i => $s) {
            $pct = (float) $s['value'] / $total * 100;
            $gap = count($slices) > 1 && $pct > 2 ? 0.8 : 0.0;
            $len = max(0.0, $pct - $gap);
            $tip = (string) $s['label'] . ' · ' . ($s['text'] ?? number_format((float) $s['value'])) . ' · ' . number_format($pct, 1) . '%';
            $svg .= '<circle class="ch-seg" fill="none" stroke-width="24" style="--s:' . $i . '" cx="100" cy="100" r="70"'
                . ' stroke="' . e((string) $s['color']) . '"'
                . ' stroke-dasharray="' . round($len / 100 * $circ, 2) . ' ' . round($circ - $len / 100 * $circ, 2) . '"'
                . ' stroke-dashoffset="' . round(-$offset / 100 * $circ, 2) . '"'
                . ' data-tip="' . e($tip) . '"/>';
            $offset += $pct;
            $legend .= '<li><i style="background:' . e((string) $s['color']) . '"></i><span>' . e((string) $s['label']) . '</span>'
                . '<b>' . e((string) ($s['text'] ?? number_format((float) $s['value']))) . '</b>'
                . '<em>' . number_format($pct, 0) . '%</em></li>';
        }
        $svg .= '<text class="ch-donut-v" x="100" y="102" text-anchor="middle">' . e($centerValue) . '</text>'
            . '<text class="ch-donut-l" x="100" y="122" text-anchor="middle">' . e($centerLabel) . '</text></svg>';

        return '<div class="ch-donut">' . $svg . '<ul class="ch-legend">' . $legend . '</ul></div>';
    }
}

if (!function_exists('ch_hbars')) {
    /**
     * Ranked horizontal bars.
     *
     * @param array<int,array{label:string,pct:float|int,right:string,sub?:string}> $rows pct is 0-100
     */
    function ch_hbars(array $rows, string $emptyText = 'Nothing to show yet.'): string
    {
        if ($rows === []) {
            return '<div class="ch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg><p>' . e($emptyText) . '</p></div>';
        }
        $out = '<ul class="ch-hbars">';
        foreach ($rows as $i => $r) {
            $pct = max(0, min(100, (float) $r['pct']));
            $out .= '<li style="--r:' . $i . '"><div class="ch-hrow"><span class="ch-hlabel">' . e((string) $r['label']) . '</span>'
                . '<b class="ch-hright">' . e((string) $r['right']) . '</b></div>'
                . '<div class="ch-htrack"><i class="ch-hfill" style="--w:' . round($pct, 1) . '%" data-tip="' . e((string) $r['label'] . ' · ' . $r['right']) . '"></i></div>'
                . (!empty($r['sub']) ? '<small>' . e((string) $r['sub']) . '</small>' : '')
                . '</li>';
        }

        return $out . '</ul>';
    }
}

if (!function_exists('ch_gauge')) {
    /** Circular progress ring. $pct is 0-100. */
    function ch_gauge(float $pct, string $label, string $sub = ''): string
    {
        $pct = max(0.0, min(100.0, $pct));

        return '<div class="ch-gauge"><svg viewBox="0 0 200 200" role="img" aria-label="' . e($label) . ' ' . (int) round($pct) . ' percent">'
            . '<defs><linearGradient id="chg-gauge" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c4386f"/><stop offset="1" stop-color="#f2c200"/></linearGradient></defs>'
            . '<circle class="ch-donut-track" fill="none" stroke="#f6e3ec" stroke-width="20" cx="100" cy="100" r="76"/>'
            . '<circle class="ch-seg ch-gauge-arc" fill="none" stroke-width="20" cx="100" cy="100" r="76" stroke="url(#chg-gauge)"'
            . ' stroke-dasharray="' . round($pct / 100 * 2 * M_PI * 76, 2) . ' ' . round(2 * M_PI * 76 - $pct / 100 * 2 * M_PI * 76, 2) . '" stroke-linecap="round"/>'
            . '<text class="ch-donut-v" x="100" y="106" text-anchor="middle" data-count="' . (int) round($pct) . '" data-suffix="%">' . (int) round($pct) . '%</text>'
            . '<text class="ch-donut-l" x="100" y="128" text-anchor="middle">' . e($label) . '</text>'
            . '</svg>' . ($sub !== '' ? '<p class="ch-gauge-sub">' . e($sub) . '</p>' : '') . '</div>';
    }
}

if (!function_exists('ch_delta')) {
    /** "up 12% vs previous 30 days" pill. */
    function ch_delta(int $current, int $previous): string
    {
        if ($previous <= 0) {
            return $current > 0 ? '<span class="ch-delta up">New activity</span>' : '';
        }
        $change = (($current - $previous) / $previous) * 100;
        $dir = $change >= 0 ? 'up' : 'down';
        $arrow = $change >= 0 ? '&#9650;' : '&#9660;';

        return '<span class="ch-delta ' . $dir . '">' . $arrow . ' ' . number_format(abs($change), 0) . '% vs previous 30 days</span>';
    }
}

if (!function_exists('ch_status_slices')) {
    /**
     * Groups raw donation statuses into four easy-to-read slices.
     *
     * @param array<int,array<string,mixed>> $rows each with status and c
     * @return array<int,array{label:string,value:int,color:string}>
     */
    function ch_status_slices(array $rows): array
    {
        $groups = [
            'Completed'  => ['color' => '#1e7a45', 'statuses' => ['completed']],
            'In progress' => ['color' => '#f2c200', 'statuses' => ['pending', 'processing']],
            'Failed or cancelled' => ['color' => '#b3261e', 'statuses' => ['failed', 'cancelled', 'abandoned']],
            'Refunded'   => ['color' => '#6b7bb8', 'statuses' => ['refunded']],
        ];
        $out = [];
        foreach ($groups as $label => $g) {
            $sum = 0;
            foreach ($rows as $r) {
                if (in_array((string) ($r['status'] ?? ''), $g['statuses'], true)) {
                    $sum += (int) ($r['c'] ?? 0);
                }
            }
            $out[] = ['label' => $label, 'value' => $sum, 'color' => $g['color']];
        }

        return $out;
    }
}
