<?php
/**
 * Small presentational helpers shared by the default email templates.
 *
 * Every function escapes its own output, so callers pass raw values.
 */

if (!function_exists('email_heading')) {
    /** @return string */
    function email_heading(string $text, string $eyebrow = ''): string
    {
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        $out = '';
        if ($eyebrow !== '') {
            $out .= '<div style="font-family:' . $stack . ';font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#a92d63;font-weight:700;margin-bottom:10px">'
                . e($eyebrow) . '</div>';
        }
        $out .= '<h1 style="margin:0 0 16px;font-family:' . $stack . ';font-size:23px;line-height:1.3;font-weight:700;color:#1a0810">'
            . e($text) . '</h1>';
        return $out;
    }
}

if (!function_exists('email_paragraph')) {
    /** Renders a paragraph, converting blank lines into separate paragraphs. */
    function email_paragraph(string $text): string
    {
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        $blocks = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $out = '';
        foreach ($blocks as $block) {
            $block = trim((string) $block);
            if ($block === '') {
                continue;
            }
            $out .= '<p style="margin:0 0 15px;font-family:' . $stack . ';font-size:15px;line-height:1.7;color:#333333">'
                . nl2br(e($block)) . '</p>';
        }
        return $out;
    }
}

if (!function_exists('email_button')) {
    function email_button(string $label, string $url): string
    {
        if ($label === '' || $url === '') {
            return '';
        }
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 8px"><tr>'
            . '<td style="background:#a92d63;border-radius:5px">'
            . '<a href="' . e($url) . '" style="display:inline-block;padding:14px 28px;font-family:' . $stack
            . ';font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#ffffff;text-decoration:none">'
            . e($label) . '</a></td></tr></table>';
    }
}

if (!function_exists('email_fallback_url')) {
    function email_fallback_url(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        return '<p style="margin:0 0 15px;font-family:' . $stack . ';font-size:12.5px;line-height:1.7;color:#717171">'
            . 'If the button does not work, copy this address into your browser:<br>'
            . '<span style="color:#2a1119;word-break:break-all">' . e($url) . '</span></p>';
    }
}

if (!function_exists('email_rows')) {
    /**
     * A simple key/value summary table.
     *
     * @param array<string,string> $rows
     */
    function email_rows(array $rows): string
    {
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="margin:6px 0 22px;border:1px solid #ece7ea;border-radius:10px;background:#fdf5f8">';
        foreach ($rows as $label => $value) {
            if ((string) $value === '') {
                continue;
            }
            $out .= '<tr>'
                . '<td style="padding:11px 16px;font-family:' . $stack . ';font-size:12.5px;color:#717171;width:44%;border-bottom:1px solid #ece7ea">' . e((string) $label) . '</td>'
                . '<td style="padding:11px 16px;font-family:' . $stack . ';font-size:13.5px;color:#2a1119;font-weight:700;border-bottom:1px solid #ece7ea">' . e((string) $value) . '</td>'
                . '</tr>';
        }
        return $out . '</table>';
    }
}

if (!function_exists('email_note')) {
    function email_note(string $text, string $tone = 'info'): string
    {
        if ($text === '') {
            return '';
        }
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        $palette = [
            'info'    => ['#fdf5f8', '#a92d63'],
            'success' => ['#eef9f1', '#1c7a41'],
            'warning' => ['#fff8e6', '#8a6400'],
            'danger'  => ['#fdeeed', '#a3231b'],
        ];
        [$bg, $bar] = $palette[$tone] ?? $palette['info'];
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px">'
            . '<tr><td style="background:' . $bg . ';border-left:3px solid ' . $bar . ';border-radius:6px;padding:14px 16px;'
            . 'font-family:' . $stack . ';font-size:13.5px;line-height:1.65;color:#333333">' . nl2br(e($text)) . '</td></tr></table>';
    }
}

if (!function_exists('email_list')) {
    /** @param array<int,string> $items */
    function email_list(array $items): string
    {
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        $out = '<ul style="margin:0 0 20px;padding-left:20px;font-family:' . $stack . ';font-size:14.5px;line-height:1.8;color:#333333">';
        foreach ($items as $item) {
            $out .= '<li>' . e((string) $item) . '</li>';
        }
        return $out . '</ul>';
    }
}

if (!function_exists('email_signoff')) {
    function email_signoff(string $orgName, string $closing = 'With gratitude,'): string
    {
        $stack = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
        return '<p style="margin:22px 0 0;font-family:' . $stack . ';font-size:14.5px;line-height:1.7;color:#333333">'
            . e($closing) . '<br><b style="color:#2a1119">' . e($orgName) . '</b></p>';
    }
}
