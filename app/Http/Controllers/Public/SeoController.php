<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\AuthService;

/**
 * Search-engine files, generated from the live database so they never go out
 * of date: /robots.txt and /sitemap.xml.
 *
 * Added in v15. The .htaccess routes these two addresses here before its
 * "no .txt files" rule runs.
 */
final class SeoController extends Controller
{
    /** Google accepts at most 50,000 URLs per sitemap file. */
    private const MAX_URLS = 50000;

    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private Database $db
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function robots(): Response
    {
        // When the site lives in a folder (https://host/donate) every path
        // below needs that folder in front of it.
        $prefix = rtrim((string) (parse_url(base_url(), PHP_URL_PATH) ?: ''), '/');

        $private = [
            '/admin', '/dashboard', '/login', '/register', '/forgot-password',
            '/reset-password', '/verify-email', '/team-invitations', '/setup',
            '/donate/', '/donation/', '/payments/',
            '/fundraise-submit.php', '/fundraise-response.php', '/meezanreturn.php',
        ];

        $lines = ['User-agent: *', 'Allow: /'];
        foreach ($private as $path) {
            $lines[] = 'Disallow: ' . $prefix . $path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . base_url('sitemap.xml');
        $lines[] = '';

        return (new Response(implode("\n", $lines), 200, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]));
    }

    public function sitemap(): Response
    {
        $urls = [];

        // Fixed pages. Priority and frequency are hints only.
        foreach ([
            ['',            '1.0', 'daily'],
            ['fundraisers', '0.9', 'daily'],
            ['campaigns',   '0.8', 'weekly'],
            ['teams',       '0.6', 'weekly'],
            ['about-us',    '0.6', 'monthly'],
            ['faq',         '0.5', 'monthly'],
            ['support',     '0.4', 'monthly'],
            ['privacy-policy', '0.2', 'yearly'],
            ['terms',       '0.2', 'yearly'],
        ] as [$path, $priority, $freq]) {
            $urls[] = ['loc' => base_url($path), 'lastmod' => null, 'freq' => $freq, 'priority' => $priority];
        }

        $sources = [
            ['fundraisers',
                "SELECT slug, updated_at FROM fundraisers
                 WHERE deleted_at IS NULL AND status = 'published' AND approval_status = 'approved'
                 ORDER BY updated_at DESC LIMIT " . self::MAX_URLS,
                'fundraisers', '0.8', 'daily'],
            ['campaigns',
                "SELECT slug, updated_at FROM campaigns
                 WHERE deleted_at IS NULL AND status = 'active'
                 ORDER BY updated_at DESC LIMIT 5000",
                'campaigns', '0.7', 'weekly'],
            ['teams',
                "SELECT slug, updated_at FROM teams
                 WHERE deleted_at IS NULL AND status = 'active'
                 ORDER BY updated_at DESC LIMIT 5000",
                'teams', '0.5', 'weekly'],
        ];

        foreach ($sources as [$label, $sql, $folder, $priority, $freq]) {
            try {
                $rows = $this->db->select($sql);
            } catch (\Throwable) {
                $rows = []; // a database hiccup must not break the whole sitemap
            }

            foreach ($rows as $row) {
                $slug = (string) ($row['slug'] ?? '');
                if ($slug === '' || count($urls) >= self::MAX_URLS) {
                    continue;
                }
                $urls[] = [
                    'loc'      => base_url($folder . '/' . rawurlencode($slug)),
                    'lastmod'  => $this->isoDate((string) ($row['updated_at'] ?? '')),
                    'freq'     => $freq,
                    'priority' => $priority,
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $xml .= "  <url>\n    <loc>" . $this->xml($u['loc']) . "</loc>\n";
            if ($u['lastmod'] !== null) {
                $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . $u['freq'] . "</changefreq>\n"
                  . '    <priority>' . $u['priority'] . "</priority>\n  </url>\n";
        }
        $xml .= "</urlset>\n";

        return new Response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function isoDate(string $utc): ?string
    {
        if ($utc === '') {
            return null;
        }
        $ts = strtotime($utc . ' UTC');

        return $ts === false ? null : gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
