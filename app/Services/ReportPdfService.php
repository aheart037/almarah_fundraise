<?php

declare(strict_types=1);

namespace App\Services;

require_once __DIR__ . '/../Support/Pdf/fpdf.php';

/**
 * Turns a report (the array built by ReportService::run) into a real PDF
 * document using FPDF: text, tables and vector shapes, not a screenshot.
 *
 * FPDF's built-in fonts cover Western European text. Characters outside that
 * set (for example Urdu or Arabic names) are replaced with "?" in the PDF; the
 * on-screen report shows them correctly.
 */
final class ReportPdfService
{
    /** Brand colours (RGB). */
    private const BRAND = [169, 45, 99];
    private const DARK = [26, 8, 16];
    private const MUTED = [110, 100, 105];
    private const LINE = [226, 218, 221];
    private const TINT = [252, 246, 249];

    /**
     * @param array<string,mixed> $report   result of ReportService::run()
     * @param array<int,array{0:string,1:string}> $filters describeFilters() output
     * @param array{org?:string,by?:string,logo?:?string,compress?:bool} $meta
     */
    public function render(array $report, array $filters, array $meta = []): string
    {
        $pdf = new ReportPdfDocument('L', 'mm', 'A4');
        $pdf->configure(
            (string) $report['title'],
            (string) ($meta['org'] ?? 'Almarah Foundation'),
            (string) ($report['generatedAt'] ?? gmdate('Y-m-d H:i:s')),
            $meta['logo'] ?? null
        );
        if (($meta['compress'] ?? true) === false) {
            $pdf->SetCompression(false); // readable output, used by the tests
        }
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $cur = (string) ($report['currency'] ?? 'PKR');

        // ---- title block -------------------------------------------------
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor(...self::DARK);
        $pdf->Cell(0, 9, $pdf->t((string) $report['title']), 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->MultiCell(0, 4.6, $pdf->t((string) ($report['description'] ?? '')), 0, 'L');
        $pdf->Ln(2);

        // ---- meta box: generated, filters, basis ---------------------------
        $pdf->metaBox(array_merge(
            [['Generated', gmdate('j M Y, H:i', (int) strtotime((string) ($report['generatedAt'] ?? 'now') . ' UTC')) . ' UTC' . (!empty($meta['by']) ? ' by ' . $meta['by'] : '')]],
            $filters,
            [['Basis', (string) ($report['basis'] ?? '')]]
        ));
        $pdf->Ln(4);

        // ---- summary cards ---------------------------------------------------
        $summary = (array) ($report['summary'] ?? []);
        if ($summary !== []) {
            $pdf->summaryCards($summary, $cur);
            $pdf->Ln(3);
        }

        // ---- main table ------------------------------------------------------
        $rows = (array) ($report['rows'] ?? []);
        $columns = (array) ($report['columns'] ?? []);
        if (!empty($report['rowsTitle'])) {
            $pdf->sectionTitle((string) $report['rowsTitle']);
        } elseif ($rows !== []) {
            $pdf->sectionTitle('Results (' . number_format(count($rows)) . ($report['total'] > count($rows) ? ' of ' . number_format((int) $report['total']) : '') . ')');
        }
        if ($rows === []) {
            $pdf->emptyBox('No records match the selected filters.');
        } else {
            $pdf->table($columns, $rows, $cur);
        }
        if (!empty($report['truncated'])) {
            $pdf->Ln(2);
            $pdf->SetFont('Helvetica', 'I', 8);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->MultiCell(0, 4, $pdf->t('Only the first ' . number_format(ReportService::EXPORT_LIMIT) . ' of ' . number_format((int) $report['total']) . ' rows are included. Narrow the filters to export the rest.'), 0, 'L');
        }

        // ---- extra tables ------------------------------------------------------
        foreach ((array) ($report['extra'] ?? []) as $extra) {
            $pdf->Ln(5);
            $pdf->needSpace(30);
            $pdf->sectionTitle((string) $extra['title']);
            if (($extra['rows'] ?? []) === []) {
                $pdf->emptyBox('Nothing to show for these filters.');
            } else {
                $pdf->table((array) $extra['columns'], (array) $extra['rows'], $cur);
            }
        }

        // ---- notes ---------------------------------------------------------------
        $notes = (array) ($report['notes'] ?? []);
        if ($notes !== []) {
            $pdf->Ln(5);
            $pdf->needSpace(24);
            $pdf->sectionTitle('Notes and limitations');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(...self::MUTED);
            foreach ($notes as $note) {
                $pdf->SetX(12);
                $pdf->MultiCell(0, 4.2, $pdf->t('- ' . $note), 0, 'L');
                $pdf->Ln(0.8);
            }
        }

        return $pdf->Output('S');
    }

    public static function fileName(string $slug): string
    {
        return 'almarah-' . preg_replace('/[^a-z0-9-]/', '', strtolower($slug)) . '-report-' . gmdate('Ymd-Hi') . '.pdf';
    }

    /** Absolute path of the uploaded header logo when it is a JPEG/PNG, else null. */
    public static function resolveLogo(): ?string
    {
        try {
            $path = (string) app(SettingsService::class)->get('site.header_logo', '');
            if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
                return null;
            }
            if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)) {
                return null; // FPDF cannot read WebP
            }
            $abs = \App\Core\Paths::publicPath(app()->basePath()) . '/' . $path;

            return is_file($abs) ? $abs : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

/**
 * The FPDF document with branded header/footer and the table helpers.
 * Kept in this file so it is loaded together with the service.
 */
class ReportPdfDocument extends \FPDF
{
    private string $reportTitle = '';
    private string $org = '';
    private string $generated = '';
    private ?string $logo = null;
    /** @var array<int,array<string,mixed>> */
    private array $headerColumns = [];
    /** @var array<int,float> */
    private array $headerWidths = [];
    private bool $inTable = false;

    public function configure(string $title, string $org, string $generated, ?string $logo): void
    {
        $this->reportTitle = $title;
        $this->org = $org;
        $this->generated = $generated;
        $this->logo = $logo;
        $this->SetTitle($this->t($title), false);
        $this->SetAuthor($this->t($org), false);
        $this->SetCreator($this->t($org . ' reports'), false);
        $this->SetSubject($this->t($title), false);
    }

    /** UTF-8 to the single-byte encoding FPDF's core fonts expect. */
    public function t(string $s): string
    {
        $s = str_replace(["\u{2192}", "\u{2013}", "\u{2014}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2026}"], ['->', '-', '-', "'", "'", '"', '"', '...'], $s);
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
            if ($out !== false) {
                return $out;
            }
        }

        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    // ---------------------------------------------------------------- page chrome

    public function Header(): void
    {
        $y = 8;
        $drawn = false;
        if ($this->logo !== null) {
            try {
                $this->Image($this->logo, 10, $y - 1, 0, 9);
                $drawn = true;
            } catch (\Throwable) {
                $drawn = false; // unsupported image: use the built-in mark
            }
        }
        if (!$drawn) {
            $this->SetFillColor(242, 194, 0);
            $this->Rect(10, $y - 1, 9, 9, 'F');
            $this->SetFont('Helvetica', 'B', 11);
            $this->SetTextColor(107, 15, 53);
            $this->SetXY(10, $y + 0.6);
            $this->Cell(9, 6, 'A', 0, 0, 'C');
            $this->SetXY(21, $y - 1.4);
            $this->SetFont('Helvetica', 'B', 10);
            $this->SetTextColor(107, 15, 53);
            $this->Cell(60, 5, $this->t(strtoupper($this->org === 'Almarah Foundation' ? 'Almarah' : $this->org)), 0, 1);
            $this->SetX(21);
            $this->SetFont('Helvetica', '', 7.5);
            $this->SetTextColor(110, 100, 105);
            $this->Cell(60, 3.6, $this->t($this->org === 'Almarah Foundation' ? 'Foundation' : ''), 0, 0);
        }

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(110, 100, 105);
        $this->SetXY(-110, $y);
        $this->Cell(100, 4, $this->t($this->reportTitle), 0, 1, 'R');
        $this->SetXY(-110, $y + 4);
        $this->Cell(100, 4, $this->t('Admin report'), 0, 0, 'R');

        $this->SetDrawColor(169, 45, 99);
        $this->SetLineWidth(0.5);
        $this->Line(10, 19, $this->GetPageWidth() - 10, 19);
        $this->SetLineWidth(0.2);
        $this->SetY(23);

        // A table that runs onto a new page repeats its header row.
        if ($this->inTable && $this->headerColumns !== []) {
            $this->tableHeaderRow();
        }
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(226, 218, 221);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(110, 100, 105);
        $this->SetY(-10);
        $this->Cell(120, 4, $this->t($this->org . ' - confidential, for administrator use only'), 0, 0, 'L');
        $this->Cell(60, 4, $this->t('Generated ' . gmdate('j M Y H:i', (int) strtotime($this->generated . ' UTC')) . ' UTC'), 0, 0, 'C');
        $this->Cell(0, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    // ---------------------------------------------------------------- blocks

    public function needSpace(float $mm): void
    {
        if ($this->GetY() + $mm > $this->PageBreakTrigger) {
            $this->AddPage();
        }
    }

    public function sectionTitle(string $text): void
    {
        // A heading must never be stranded at the bottom of a page: keep it
        // with the table header and the first rows that follow.
        $this->needSpace(34);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(26, 8, 16);
        $this->Cell(0, 6, $this->t($text), 0, 1);
        $this->SetDrawColor(169, 45, 99);
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 28, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(2);
    }

    public function emptyBox(string $text): void
    {
        $this->SetFillColor(252, 246, 249);
        $this->SetDrawColor(226, 218, 221);
        $this->SetFont('Helvetica', 'I', 9);
        $this->SetTextColor(110, 100, 105);
        $this->Cell(0, 12, $this->t($text), 1, 1, 'C', true);
    }

    /** @param array<int,array{0:string,1:string}> $lines */
    public function metaBox(array $lines): void
    {
        $this->SetFillColor(252, 246, 249);
        $this->SetDrawColor(226, 218, 221);
        $labelW = 30;
        $w = $this->GetPageWidth() - 20;
        // Measure first so the box can be drawn behind the text.
        $this->SetFont('Helvetica', '', 8.5);
        $height = 3.0;
        foreach ($lines as [$label, $value]) {
            $height += max(1, $this->nbLines($w - $labelW - 6, $this->t((string) $value))) * 4.4;
        }
        $this->needSpace($height + 2);
        $x = 10;
        $y = $this->GetY();
        $this->Rect($x, $y, $w, $height, 'DF');
        $yy = $y + 1.5;
        foreach ($lines as [$label, $value]) {
            $this->SetXY($x + 3, $yy);
            $this->SetFont('Helvetica', 'B', 8.5);
            $this->SetTextColor(169, 45, 99);
            $this->Cell($labelW, 4.4, $this->t((string) $label), 0, 0);
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(26, 8, 16);
            $this->SetXY($x + 3 + $labelW, $yy);
            $this->MultiCell($w - $labelW - 6, 4.4, $this->t((string) $value), 0, 'L');
            $yy = $this->GetY();
        }
        $this->SetY($y + $height);
    }

    /** @param array<int,array<string,mixed>> $items */
    public function summaryCards(array $items, string $defaultCurrency): void
    {
        $perRow = 4;
        $gap = 3;
        $w = ($this->GetPageWidth() - 20 - $gap * ($perRow - 1)) / $perRow;
        $h = 17;
        $i = 0;
        foreach ($items as $item) {
            if ($i % $perRow === 0) {
                $this->needSpace($h + 2);
                $rowY = $this->GetY();
            }
            $x = 10 + ($i % $perRow) * ($w + $gap);
            $this->SetFillColor(252, 246, 249);
            $this->SetDrawColor(226, 218, 221);
            $this->Rect($x, $rowY, $w, $h, 'DF');
            $this->SetFillColor(169, 45, 99);
            $this->Rect($x, $rowY, 1.2, $h, 'F');

            $this->SetXY($x + 3, $rowY + 1.5);
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(110, 100, 105);
            $this->Cell($w - 5, 3.5, $this->t(strtoupper((string) $item['label'])), 0, 1);

            $this->SetXY($x + 3, $rowY + 5.2);
            $this->SetFont('Helvetica', 'B', strlen($this->summaryValue($item, $defaultCurrency)) > 16 ? 10 : 13);
            $this->SetTextColor(26, 8, 16);
            $this->Cell($w - 5, 6, $this->t($this->clip($this->summaryValue($item, $defaultCurrency), 30)), 0, 1);

            $this->SetXY($x + 3, $rowY + 12);
            $this->SetFont('Helvetica', '', 6.8);
            $this->SetTextColor(110, 100, 105);
            $this->Cell($w - 5, 3.5, $this->t($this->clip((string) ($item['sub'] ?? ''), 52)), 0, 0);

            $i++;
            if ($i % $perRow === 0) {
                $this->SetY($rowY + $h + $gap);
            }
        }
        if ($i % $perRow !== 0) {
            $this->SetY($rowY + $h + $gap);
        }
    }

    /** @param array<string,mixed> $item */
    private function summaryValue(array $item, string $defaultCurrency): string
    {
        $v = $item['value'] ?? null;
        if ($v === null || $v === '') {
            return '—';
        }

        return match ((string) ($item['kind'] ?? 'text')) {
            'money'   => money((int) $v, (string) ($item['currency'] ?? $defaultCurrency)),
            'int'     => number_format((float) $v),
            'percent' => number_format((float) $v, 1) . '%',
            'hours'   => (float) $v >= 48 ? number_format((float) $v / 24, 1) . ' days' : number_format((float) $v, 1) . ' h',
            default   => (string) $v,
        };
    }

    // ---------------------------------------------------------------- table

    /**
     * @param array<int,array<string,mixed>> $columns
     * @param array<int,array<string,mixed>> $rows
     */
    public function table(array $columns, array $rows, string $defaultCurrency): void
    {
        $usable = $this->GetPageWidth() - 20;
        $sum = 0.0;
        foreach ($columns as $c) {
            $sum += (float) ($c['w'] ?? 1);
        }
        $widths = [];
        foreach ($columns as $c) {
            $widths[] = $usable * ((float) ($c['w'] ?? 1) / max(0.1, $sum));
        }
        $this->headerColumns = $columns;
        $this->headerWidths = $widths;

        // Keep the header with at least two rows.
        $this->needSpace(28);
        $this->inTable = true;
        $this->tableHeaderRow();

        $fontSize = count($columns) > 10 ? 6.6 : (count($columns) > 7 ? 7.2 : 8);
        $lh = $fontSize * 0.5 + 1.1;
        $zebra = false;
        foreach ($rows as $row) {
            $cells = [];
            $maxLines = 1;
            foreach ($columns as $i => $col) {
                $text = $this->t($this->clip(\App\Services\ReportService::cell($col, $row, $defaultCurrency), 220));
                $this->SetFont(($col['type'] ?? '') === 'code' ? 'Courier' : 'Helvetica', '', $fontSize);
                $cells[$i] = $text;
                $maxLines = max($maxLines, $this->nbLines($widths[$i] - 2, $text));
            }
            $h = $maxLines * $lh + 1.6;
            if ($this->GetY() + $h > $this->PageBreakTrigger) {
                $this->AddPage(); // Header() repeats the column header row
                $zebra = false;
            }
            $x = 10;
            $y = $this->GetY();
            if ($zebra) {
                $this->SetFillColor(252, 246, 249);
                $this->Rect($x, $y, $usable, $h, 'F');
            }
            foreach ($columns as $i => $col) {
                $type = (string) ($col['type'] ?? '');
                $this->SetFont($type === 'code' ? 'Courier' : 'Helvetica', in_array($type, ['money'], true) ? 'B' : '', $fontSize);
                $this->setStatusColor($type, (string) ($row[$col['key']] ?? ''));
                $this->SetXY($x + 1, $y + 0.8);
                $this->MultiCell($widths[$i] - 2, $lh, $cells[$i], 0, ($col['align'] ?? 'left') === 'right' ? 'R' : 'L');
                $x += $widths[$i];
            }
            $this->SetDrawColor(236, 229, 232);
            $this->Line(10, $y + $h, 10 + $usable, $y + $h);
            $this->SetY($y + $h);
            $zebra = !$zebra;
        }
        $this->inTable = false;
        $this->headerColumns = [];
    }

    private function tableHeaderRow(): void
    {
        $fontSize = count($this->headerColumns) > 10 ? 6.6 : 7.4;
        $this->SetFont('Helvetica', 'B', $fontSize);
        $lh = $fontSize * 0.5 + 1.1;
        $lines = 1;
        foreach ($this->headerColumns as $i => $col) {
            $lines = max($lines, $this->nbLines($this->headerWidths[$i] - 2, $this->t((string) $col['label'])));
        }
        $h = $lines * $lh + 2.4;
        $y = $this->GetY();
        $usable = array_sum($this->headerWidths);
        $this->SetFillColor(169, 45, 99);
        $this->Rect(10, $y, $usable, $h, 'F');
        $this->SetTextColor(255, 255, 255);
        $x = 10;
        foreach ($this->headerColumns as $i => $col) {
            $this->SetXY($x + 1, $y + 1.2);
            $this->MultiCell($this->headerWidths[$i] - 2, $lh, $this->t((string) $col['label']), 0, ($col['align'] ?? 'left') === 'right' ? 'R' : 'L');
            $x += $this->headerWidths[$i];
        }
        $this->SetY($y + $h);
        $this->SetTextColor(26, 8, 16);
    }

    private function setStatusColor(string $type, string $value): void
    {
        $this->SetTextColor(26, 8, 16);
        if ($type !== 'status' && $type !== 'badge') {
            return;
        }
        $good = ['completed', 'sent', 'approved', 'published', 'active', 'accepted'];
        $bad = ['failed', 'rejected', 'cancelled', 'abandoned', 'suspended'];
        $wait = ['pending', 'processing', 'queued', 'pending_review', 'changes_requested', 'paused'];
        if (in_array($value, $good, true)) {
            $this->SetTextColor(30, 122, 69);
        } elseif (in_array($value, $bad, true)) {
            $this->SetTextColor(179, 38, 30);
        } elseif (in_array($value, $wait, true)) {
            $this->SetTextColor(150, 110, 0);
        }
    }

    private function clip(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '...' : $s;
    }

    /** Number of lines a cell of width $w needs for text $txt (from the FPDF tutorials). */
    public function nbLines(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
            }
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }

        return $nl;
    }
}
