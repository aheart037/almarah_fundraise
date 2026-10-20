<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Site-wide typography chosen in Admin → Fonts.
 *
 * Three roles can be styled: headings (h1, h2), sub-headings (h3–h6) and
 * paragraph text. Each has a font, a weight and a size.
 *
 * Nothing changes on the site until something is saved: with every setting
 * left at "site default" css() returns an empty string and the stylesheet is
 * untouched. The fonts are bundled in assets/fonts and served from this
 * website, so there are no external requests (the security policy only allows
 * fonts from this site).
 */
final class TypographyService
{
    public const SIZE_MIN = 70;
    public const SIZE_MAX = 150;

    /** Catalogue of choices. 'files' are woff2 files in assets/fonts. */
    private const FONTS = [
        'default'   => ['label' => 'Site default', 'group' => 'Default'],

        'raleway'   => ['label' => 'Raleway',   'group' => 'Sans-serif', 'family' => 'Raleway',   'generic' => 'sans-serif', 'min' => 100, 'max' => 900,
                        'files' => [['raleway-latin-wght-normal.woff2', '100 900']]],
        'montserrat' => ['label' => 'Montserrat', 'group' => 'Sans-serif', 'family' => 'Montserrat', 'generic' => 'sans-serif', 'min' => 100, 'max' => 900,
                        'files' => [['montserrat-latin-wght-normal.woff2', '100 900']]],
        'inter'     => ['label' => 'Inter',     'group' => 'Sans-serif', 'family' => 'Inter',     'generic' => 'sans-serif', 'min' => 100, 'max' => 900,
                        'files' => [['inter-latin-wght-normal.woff2', '100 900']]],
        'open-sans' => ['label' => 'Open Sans', 'group' => 'Sans-serif', 'family' => 'Open Sans', 'generic' => 'sans-serif', 'min' => 300, 'max' => 800,
                        'files' => [['open-sans-latin-wght-normal.woff2', '300 800']]],
        'nunito'    => ['label' => 'Nunito',    'group' => 'Sans-serif', 'family' => 'Nunito',    'generic' => 'sans-serif', 'min' => 200, 'max' => 1000,
                        'files' => [['nunito-latin-wght-normal.woff2', '200 1000']]],
        'dm-sans'   => ['label' => 'DM Sans',   'group' => 'Sans-serif', 'family' => 'DM Sans',   'generic' => 'sans-serif', 'min' => 100, 'max' => 1000,
                        'files' => [['dm-sans-latin-wght-normal.woff2', '100 1000']]],
        'source-sans-3' => ['label' => 'Source Sans 3', 'group' => 'Sans-serif', 'family' => 'Source Sans 3', 'generic' => 'sans-serif', 'min' => 200, 'max' => 900,
                        'files' => [['source-sans-3-latin-wght-normal.woff2', '200 900']]],
        'poppins'   => ['label' => 'Poppins',   'group' => 'Sans-serif', 'family' => 'Poppins',   'generic' => 'sans-serif', 'min' => 400, 'max' => 800,
                        'files' => [
                            ['poppins-latin-400-normal.woff2', '400'],
                            ['poppins-latin-500-normal.woff2', '500'],
                            ['poppins-latin-600-normal.woff2', '600'],
                            ['poppins-latin-700-normal.woff2', '700'],
                            ['poppins-latin-800-normal.woff2', '800'],
                        ]],

        'playfair-display' => ['label' => 'Playfair Display', 'group' => 'Serif', 'family' => 'Playfair Display', 'generic' => 'serif', 'min' => 400, 'max' => 900,
                        'files' => [['playfair-display-latin-wght-normal.woff2', '400 900']]],
        'merriweather' => ['label' => 'Merriweather', 'group' => 'Serif', 'family' => 'Merriweather', 'generic' => 'serif', 'min' => 300, 'max' => 900,
                        'files' => [['merriweather-latin-wght-normal.woff2', '300 900']]],
        'lora'      => ['label' => 'Lora',      'group' => 'Serif', 'family' => 'Lora',      'generic' => 'serif', 'min' => 400, 'max' => 700,
                        'files' => [['lora-latin-wght-normal.woff2', '400 700']]],

        'system-sans' => ['label' => 'System sans-serif (fastest)', 'group' => 'System fonts', 'min' => 100, 'max' => 900,
                        'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'],
        'georgia'   => ['label' => 'Georgia', 'group' => 'System fonts', 'min' => 400, 'max' => 700,
                        'stack' => 'Georgia, "Times New Roman", Times, serif'],
    ];

    /** role => [settings prefix, label] */
    private const ROLES = ['heading', 'sub', 'body'];

    public function __construct(private SettingsService $settings)
    {
    }

    // ------------------------------------------------------------------
    // Catalogue
    // ------------------------------------------------------------------

    /** @return array<string,array<string,mixed>> */
    public function fonts(): array
    {
        return self::FONTS;
    }

    /**
     * What the admin form needs to build its pickers and live preview.
     *
     * @return array<string,array{label:string,group:string,css:string,min:int,max:int}>
     */
    public function catalogForForm(): array
    {
        $out = [];
        foreach (self::FONTS as $key => $font) {
            $out[$key] = [
                'label' => $font['label'],
                'group' => $font['group'],
                'css'   => $key === 'default' ? '' : $this->stack($key),
                'min'   => (int) ($font['min'] ?? 100),
                'max'   => (int) ($font['max'] ?? 900),
            ];
        }
        return $out;
    }

    public function isKnownFont(string $key): bool
    {
        return isset(self::FONTS[$key]);
    }

    private function stack(string $key): string
    {
        $font = self::FONTS[$key];
        if (isset($font['stack'])) {
            return (string) $font['stack'];
        }
        return '"' . $font['family'] . '", ' . $font['generic'];
    }

    // ------------------------------------------------------------------
    // Stored settings
    // ------------------------------------------------------------------

    /**
     * The current choices, always valid.
     *
     * @return array<string,array{font:string,weight:int,size:int}>
     *   font: catalogue key ('' = same as headings, sub-heading role only)
     *   weight: 0 = site default
     */
    public function current(): array
    {
        $out = [];
        foreach (self::ROLES as $role) {
            $font = (string) ($this->settings->get("typography.{$role}_font", '') ?? '');
            if ($role === 'sub') {
                $font = ($font === '' || !$this->isKnownFont($font)) ? '' : $font;
            } else {
                $font = $this->isKnownFont($font) ? $font : 'default';
            }

            $out[$role] = [
                'font'   => $font,
                'weight' => $this->cleanWeight($font, (int) ($this->settings->get("typography.{$role}_weight", '0') ?? 0)),
                'size'   => $this->cleanSize((int) ($this->settings->get("typography.{$role}_size", '100') ?? 100)),
            ];
        }
        return $out;
    }

    /**
     * Validate and save what was posted from the form.
     *
     * @param array<string,mixed> $input
     */
    public function save(array $input, ?int $userId): void
    {
        $pairs = [];
        foreach (self::ROLES as $role) {
            $font = trim((string) ($input["{$role}_font"] ?? ''));
            if ($role === 'sub') {
                $font = ($font === '' || !$this->isKnownFont($font)) ? '' : $font;
            } else {
                $font = $this->isKnownFont($font) ? $font : 'default';
            }

            $pairs["typography.{$role}_font"]   = $font;
            $pairs["typography.{$role}_weight"] = (string) $this->cleanWeight($font, (int) ($input["{$role}_weight"] ?? 0));
            $pairs["typography.{$role}_size"]   = (string) $this->cleanSize((int) ($input["{$role}_size"] ?? 100));
        }
        $this->settings->setMany($pairs, $userId);
    }

    /** Back to the original look. */
    public function reset(?int $userId): void
    {
        $pairs = [];
        foreach (self::ROLES as $role) {
            $pairs["typography.{$role}_font"]   = $role === 'sub' ? '' : 'default';
            $pairs["typography.{$role}_weight"] = '0';
            $pairs["typography.{$role}_size"]   = '100';
        }
        $this->settings->setMany($pairs, $userId);
    }

    private function cleanSize(int $size): int
    {
        if ($size <= 0) {
            return 100;
        }
        return max(self::SIZE_MIN, min(self::SIZE_MAX, $size));
    }

    /** 0 keeps the site's own weight; otherwise snapped to the font's range. */
    private function cleanWeight(string $font, int $weight): int
    {
        if ($weight <= 0) {
            return 0;
        }
        $min = 100;
        $max = 900;
        if ($font !== '' && isset(self::FONTS[$font]['min'])) {
            $min = (int) self::FONTS[$font]['min'];
            $max = (int) self::FONTS[$font]['max'];
        }
        $weight = (int) (round($weight / 100) * 100);
        return max($min, min($max, $weight, 900));
    }

    // ------------------------------------------------------------------
    // CSS
    // ------------------------------------------------------------------

    /**
     * @font-face rules for the given catalogue keys (or every font).
     *
     * @param array<int,string>|null $keys
     */
    public function fontFaceCss(?array $keys = null): string
    {
        $keys ??= array_keys(self::FONTS);
        $css = '';
        foreach (array_unique($keys) as $key) {
            $font = self::FONTS[$key] ?? null;
            if ($font === null || !isset($font['files'])) {
                continue;
            }
            foreach ($font['files'] as [$file, $weight]) {
                $css .= '@font-face{font-family:"' . $font['family'] . '";font-style:normal;font-weight:' . $weight
                    . ';font-display:swap;src:url("' . asset('assets/fonts/' . $file) . '") format("woff2")}' . "\n";
            }
        }
        return $css;
    }

    /**
     * The stylesheet to print in <head>, or '' when nothing was customised.
     */
    public function css(): string
    {
        $c = $this->current();

        $customised = $c['heading']['font'] !== 'default' || $c['heading']['weight'] !== 0 || $c['heading']['size'] !== 100
            || $c['sub']['font'] !== '' || $c['sub']['weight'] !== 0 || $c['sub']['size'] !== 100
            || $c['body']['font'] !== 'default' || $c['body']['weight'] !== 0 || $c['body']['size'] !== 100;
        if (!$customised) {
            return '';
        }

        $used = array_filter([$c['heading']['font'], $c['sub']['font'], $c['body']['font']], static fn (string $f): bool => $f !== '' && $f !== 'default');
        $css = $this->fontFaceCss(array_values($used));

        // The stylesheet already runs on --font-head / --font-body, so these two
        // reach headings, buttons, menus and body text alike.
        $vars = [];
        if ($c['heading']['font'] !== 'default') {
            $vars[] = '--font-head:' . $this->stack($c['heading']['font']);
        }
        if ($c['body']['font'] !== 'default') {
            $vars[] = '--font-body:' . $this->stack($c['body']['font']);
        }
        if ($c['sub']['font'] !== '') {
            $vars[] = '--font-sub:' . $this->stack($c['sub']['font']);
        }
        if ($vars !== []) {
            $css .= ':root{' . implode(';', $vars) . "}\n";
        }

        // Body text weight: inherited by ordinary text; components with their
        // own explicit weight (buttons, labels) keep it.
        if ($c['body']['weight'] !== 0) {
            $css .= 'body,p,.lead,.section-sub{font-weight:' . $c['body']['weight'] . "}\n";
        }

        // Headings (h1, h2) and sub-headings (h3–h6). !important because many
        // page sections give their own heading a fixed weight/family.
        $css .= $this->headingRule('h1,h2,h1.display,h2.section-title', $c['heading'], $c['heading']['font'] !== 'default' ? 'var(--font-head)' : null);
        $css .= $this->headingRule('h3,h4,h5,h6', $c['sub'], $c['sub']['font'] !== '' ? 'var(--font-sub)' : null);

        // Paragraph size: zoom scales the text together with its spacing, and
        // works whatever unit a section uses for its own paragraph size.
        if ($c['body']['size'] !== 100) {
            $css .= 'p,.lead,.section-sub,.prose li{zoom:' . $this->zoom($c['body']['size']) . "}\n";
            $css .= ".prose li li{zoom:1}\n";
        }

        return $css;
    }

    /** @param array{font:string,weight:int,size:int} $role */
    private function headingRule(string $selector, array $role, ?string $familyOverride): string
    {
        $decl = [];
        if ($familyOverride !== null) {
            $decl[] = 'font-family:' . $familyOverride . '!important';
        }
        if ($role['weight'] !== 0) {
            $decl[] = 'font-weight:' . $role['weight'] . '!important';
        }
        if ($role['size'] !== 100) {
            $decl[] = 'zoom:' . $this->zoom($role['size']);
        }
        return $decl === [] ? '' : $selector . '{' . implode(';', $decl) . "}\n";
    }

    private function zoom(int $percent): string
    {
        return rtrim(rtrim(number_format($percent / 100, 2, '.', ''), '0'), '.');
    }
}
