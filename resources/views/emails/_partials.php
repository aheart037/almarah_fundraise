<?php
/**
 * Presentational helpers shared by every default email template.
 *
 * Visual language: the Almarah thank-you email. A wine hero with a gold pill
 * and serif title, soft pink cards with gold accents, numbered gold steps and
 * a wine footer (see layout.php).
 *
 * How it fits together: each helper returns one or more complete table ROWS
 * (<tr>) for the single 600px card that layout.php opens. Rows carry their own
 * 36px side padding (class "em-pad", reduced on phones), so templates only
 * call the helpers and never write markup themselves.
 *
 * Every function escapes its own output, so callers pass raw values. The
 * function names and arguments are unchanged from the previous design; the
 * only additions are OPTIONAL arguments and a few new helpers
 * (email_status, email_amount, email_quote).
 *
 * Email clients are unpredictable, so everything is inline-styled tables with
 * bgcolor fallbacks. No external CSS, fonts or scripts.
 */

if (!function_exists('email_fonts')) {
    /** @return array{sans:string,serif:string} */
    function email_fonts(): array
    {
        return [
            'sans'  => "Arial,Helvetica,sans-serif",
            'serif' => "Georgia,'Times New Roman',serif",
        ];
    }
}

if (!function_exists('email_heading')) {
    /**
     * The hero: gold pill (the eyebrow), serif title and a short gold rule.
     * An empty eyebrow simply omits the pill.
     *
     * @return string
     */
    function email_heading(string $text, string $eyebrow = ''): string
    {
        $f = email_fonts();
        $out = '<tr><td class="em-hero" align="center" bgcolor="#A92D63" '
            . 'style="background-color:#a92d63;background-image:linear-gradient(135deg,#a92d63 0%,#8a2050 100%);padding:42px 36px 40px 36px">';

        if ($eyebrow !== '') {
            $out .= '<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center"><tr>'
                . '<td align="center" style="border:1px solid #f2c200;border-radius:30px;padding:7px 18px;font-family:' . $f['sans']
                . ';font-size:11px;line-height:16px;letter-spacing:2px;font-weight:bold;color:#ffd740;text-transform:uppercase">'
                . e($eyebrow) . '</td></tr></table>';
        }

        $out .= '<h1 class="em-h1" style="margin:' . ($eyebrow !== '' ? '22px' : '0') . ' 0 0 0;font-family:' . $f['serif']
            . ';font-size:32px;line-height:40px;font-weight:normal;color:#ffffff;text-align:center">' . e($text) . '</h1>'
            . '<table role="presentation" border="0" width="60" cellspacing="0" cellpadding="0" align="center" style="margin:20px auto 0 auto"><tr>'
            . '<td bgcolor="#F2C200" height="3" style="background-color:#f2c200;font-size:0;line-height:0;height:3px">&nbsp;</td></tr></table>'
            . '</td></tr>';

        return $out;
    }
}

if (!function_exists('email_paragraph')) {
    /** Renders a paragraph, converting blank lines into separate paragraphs. */
    function email_paragraph(string $text): string
    {
        $f = email_fonts();
        $blocks = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $paras = '';
        foreach ($blocks as $block) {
            $block = trim((string) $block);
            if ($block === '') {
                continue;
            }
            $paras .= '<p style="margin:' . ($paras === '' ? '0' : '14px') . ' 0 0 0;font-family:' . $f['sans']
                . ';font-size:15px;line-height:25px;color:#5a2040">' . nl2br(e($block)) . '</p>';
        }
        if ($paras === '') {
            return '';
        }
        return '<tr><td class="em-pad" style="padding:22px 36px 0 36px">' . $paras . '</td></tr>';
    }
}

if (!function_exists('email_button')) {
    function email_button(string $label, string $url): string
    {
        if ($label === '' || $url === '') {
            return '';
        }
        $f = email_fonts();
        return '<tr><td class="em-pad" align="center" style="padding:28px 36px 0 36px">'
            . '<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center"><tr>'
            . '<td class="em-btn" align="center" bgcolor="#A92D63" style="background-color:#a92d63;border-radius:8px">'
            . '<a href="' . e($url) . '" target="_blank" rel="noopener" style="display:inline-block;padding:15px 34px;font-family:' . $f['sans']
            . ';font-size:15px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;border-bottom:3px solid #f2c200">'
            . e($label) . '</a></td></tr></table></td></tr>';
    }
}

if (!function_exists('email_fallback_url')) {
    function email_fallback_url(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $f = email_fonts();
        return '<tr><td class="em-pad" align="center" style="padding:14px 36px 0 36px">'
            . '<p style="margin:0;font-family:' . $f['sans'] . ';font-size:12px;line-height:20px;color:#9a6080;text-align:center">'
            . 'If the button does not work, copy this address into your browser:<br>'
            . '<span style="color:#8a2050;word-break:break-all">' . e($url) . '</span></p></td></tr>';
    }
}

if (!function_exists('email_rows')) {
    /**
     * A key/value summary: small-caps labels on the left, bold values on the
     * right, hairline separators.
     *
     * @param array<string,string> $rows
     * @param string               $title optional serif sub-heading above the rows
     */
    function email_rows(array $rows, string $title = ''): string
    {
        $f = email_fonts();

        $lines = [];
        foreach ($rows as $label => $value) {
            if ((string) $value === '') {
                continue;
            }
            $lines[] = [(string) $label, (string) $value];
        }
        if ($lines === []) {
            return '';
        }

        $out = '<tr><td class="em-pad" style="padding:28px 36px 0 36px">';
        if ($title !== '') {
            $out .= '<h2 style="margin:0 0 6px 0;font-family:' . $f['serif'] . ';font-size:20px;line-height:28px;font-weight:normal;color:#a92d63">'
                . e($title) . '</h2>';
        }
        $out .= '<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">';

        $last = count($lines) - 1;
        foreach ($lines as $i => [$label, $value]) {
            $border = $i === $last ? '' : 'border-bottom:1px solid #f0dce5;';
            $out .= '<tr>'
                . '<td valign="top" width="38%" style="padding:13px 10px 13px 0;' . $border . 'font-family:' . $f['sans']
                . ';font-size:12px;line-height:18px;letter-spacing:1px;text-transform:uppercase;color:#9a6080">' . e($label) . '</td>'
                . '<td valign="top" width="62%" align="right" style="padding:13px 0;' . $border . 'font-family:' . $f['sans']
                . ';font-size:15px;line-height:22px;font-weight:bold;color:#1a0810;word-break:break-word">' . e($value) . '</td>'
                . '</tr>';
        }

        return $out . '</table></td></tr>';
    }
}

if (!function_exists('email_note')) {
    /**
     * A highlighted note. Tones: info (gold accent, the default), success,
     * warning, danger.
     */
    function email_note(string $text, string $tone = 'info'): string
    {
        if ($text === '') {
            return '';
        }
        $f = email_fonts();
        $palette = [
            'info'    => ['#fdf5f8', '#f2c200', '#5a2040'],
            'success' => ['#eef9f1', '#1c7a41', '#1d4f31'],
            'warning' => ['#fff8dc', '#f2c200', '#5a4400'],
            'danger'  => ['#fdeeed', '#a3231b', '#6e1a14'],
        ];
        [$bg, $bar, $ink] = $palette[$tone] ?? $palette['info'];

        return '<tr><td class="em-pad" style="padding:24px 36px 0 36px">'
            . '<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="' . $bg . '" '
            . 'style="background-color:' . $bg . ';border-left:4px solid ' . $bar . ';border-radius:6px"><tr>'
            . '<td style="padding:16px 18px;font-family:' . $f['sans'] . ';font-size:14px;line-height:22px;color:' . $ink . '">'
            . nl2br(e($text)) . '</td></tr></table></td></tr>';
    }
}

if (!function_exists('email_list')) {
    /**
     * Numbered gold steps. Works for steps and for short tips alike.
     *
     * @param array<int,string> $items
     * @param string            $title optional serif sub-heading above the list
     */
    function email_list(array $items, string $title = ''): string
    {
        $f = email_fonts();
        $items = array_values(array_filter($items, static fn ($i): bool => trim((string) $i) !== ''));
        if ($items === []) {
            return '';
        }

        $out = '<tr><td class="em-pad" style="padding:28px 36px 0 36px">';
        if ($title !== '') {
            $out .= '<h2 style="margin:0 0 14px 0;font-family:' . $f['serif'] . ';font-size:20px;line-height:28px;font-weight:normal;color:#a92d63">'
                . e($title) . '</h2>';
        }
        $out .= '<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">';

        $last = count($items) - 1;
        foreach ($items as $i => $item) {
            $gap = $i === $last ? '0' : '14px';
            $out .= '<tr>'
                . '<td valign="top" width="40" style="padding:0 0 ' . $gap . ' 0">'
                . '<table role="presentation" border="0" cellspacing="0" cellpadding="0"><tr>'
                . '<td align="center" valign="middle" bgcolor="#F2C200" width="28" height="28" style="background-color:#f2c200;border-radius:14px;'
                . 'font-family:' . $f['sans'] . ';font-size:13px;line-height:28px;font-weight:bold;color:#1a0810">' . ($i + 1) . '</td>'
                . '</tr></table></td>'
                . '<td valign="top" style="padding:3px 0 ' . $gap . ' 0;font-family:' . $f['sans'] . ';font-size:14px;line-height:22px;color:#5a2040">'
                . e((string) $item) . '</td>'
                . '</tr>';
        }

        return $out . '</table></td></tr>';
    }
}

if (!function_exists('email_status')) {
    /**
     * A centred status pill, e.g. "Status: Processing".
     * Tones: warning (default), success, danger, neutral.
     */
    function email_status(string $text, string $tone = 'warning'): string
    {
        if (trim($text) === '') {
            return '';
        }
        $f = email_fonts();
        $palette = [
            'warning' => ['#fff8dc', '#f2c200', '#7a5c00'],
            'success' => ['#eef9f1', '#9fd6b2', '#1c7a41'],
            'danger'  => ['#fdeeed', '#e6b3ae', '#a3231b'],
            'neutral' => ['#fdf5f8', '#ebd3de', '#8a2050'],
        ];
        [$bg, $border, $ink] = $palette[$tone] ?? $palette['warning'];

        return '<tr><td class="em-pad" align="center" style="padding:28px 36px 0 36px">'
            . '<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" bgcolor="' . $bg . '" '
            . 'style="background-color:' . $bg . ';border:1px solid ' . $border . ';border-radius:30px"><tr>'
            . '<td style="padding:9px 20px;font-family:' . $f['sans'] . ';font-size:13px;line-height:18px;font-weight:bold;color:' . $ink . '">'
            . '&#9679;&nbsp; Status: ' . e($text) . '</td></tr></table></td></tr>';
    }
}

if (!function_exists('email_amount')) {
    /** The big serif amount card. Skipped when there is no amount. */
    function email_amount(string $label, string $amount): string
    {
        if (trim($amount) === '') {
            return '';
        }
        $f = email_fonts();
        return '<tr><td class="em-pad" style="padding:26px 36px 0 36px">'
            . '<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#FDF5F8" '
            . 'style="background-color:#fdf5f8;border:1px solid #f3dce6;border-radius:12px"><tr>'
            . '<td align="center" style="padding:24px 20px">'
            . '<div style="font-family:' . $f['sans'] . ';font-size:11px;line-height:16px;letter-spacing:2px;font-weight:bold;color:#8a2050;text-transform:uppercase">'
            . e($label) . '</div>'
            . '<div class="em-amount" style="margin-top:8px;font-family:' . $f['serif'] . ';font-size:42px;line-height:48px;color:#a92d63">'
            . e($amount) . '</div>'
            . '</td></tr></table></td></tr>';
    }
}

if (!function_exists('email_quote')) {
    /** A gold-edged pull quote with an optional smaller line beneath it. */
    function email_quote(string $text, string $sub = ''): string
    {
        if (trim($text) === '') {
            return '';
        }
        $f = email_fonts();
        $out = '<tr><td class="em-pad" style="padding:28px 36px 0 36px">'
            . '<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#FDF5F8" '
            . 'style="background-color:#fdf5f8;border-left:4px solid #f2c200;border-radius:6px"><tr>'
            . '<td style="padding:18px 20px">'
            . '<p style="margin:0;font-family:' . $f['serif'] . ';font-size:17px;line-height:26px;font-style:italic;color:#a92d63">' . e($text) . '</p>';
        if (trim($sub) !== '') {
            $out .= '<p style="margin:4px 0 0 0;font-family:' . $f['sans'] . ';font-size:13px;line-height:20px;color:#9a6080">' . e($sub) . '</p>';
        }
        return $out . '</td></tr></table></td></tr>';
    }
}

if (!function_exists('email_signoff')) {
    function email_signoff(string $orgName, string $closing = 'With gratitude,'): string
    {
        $f = email_fonts();
        return '<tr><td class="em-pad" style="padding:28px 36px 0 36px">'
            . '<p style="margin:0;font-family:' . $f['sans'] . ';font-size:15px;line-height:24px;color:#5a2040">'
            . e($closing) . '<br><strong style="color:#a92d63">' . e($orgName) . '</strong></p></td></tr>';
    }
}
