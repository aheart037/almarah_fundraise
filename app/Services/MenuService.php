<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CampaignRepository;
use InvalidArgumentException;
use Throwable;

/**
 * Menus managed in Admin → Menus.
 *
 * A menu is a named list of links assigned to a place on the site (the
 * header, or one of the footer link columns). A place with no menu assigned,
 * or whose menu has no links, keeps the site's built-in links, so the site
 * can never end up without navigation.
 *
 * Everything is stored as one JSON value in site_settings ('menus.data'),
 * so no database change is needed.
 */
final class MenuService
{
    public const SETTING_KEY = 'menus.data';

    public const MAX_MENUS = 10;
    public const MAX_ITEMS = 30;

    /** location => [label, shows a heading] */
    public const LOCATIONS = [
        'header'   => ['label' => 'Header (main menu)',        'heading' => false],
        'footer_1' => ['label' => 'Footer – first link column',  'heading' => true],
        'footer_2' => ['label' => 'Footer – second link column', 'heading' => true],
    ];

    /** path => label. These are the site's own pages. */
    private const PAGES = [
        ''               => 'Home',
        'fundraisers'    => 'Support a Fundraiser (browse fundraisers)',
        'teams'          => 'Teams',
        'campaigns'      => 'Campaigns (all appeals)',
        'about-us'       => 'About Us',
        'faq'            => 'FAQ',
        'support'        => 'Support / Contact',
        'privacy-policy' => 'Privacy Policy',
        'terms'          => 'Terms & Conditions',
        'donate/general' => 'Donate (general fund)',
        'register'       => 'Start a Fundraiser (register)',
        'login'          => 'Login',
    ];

    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private SettingsService $settings,
        private CampaignRepository $campaigns
    ) {
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->load()['menus'];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        foreach ($this->all() as $menu) {
            if ((int) $menu['id'] === $id) {
                return $menu;
            }
        }
        return null;
    }

    /**
     * The menu shown in a place, with its links resolved to URLs.
     * Null means "use the built-in links".
     *
     * @return array{name:string,title:string,items:array<int,array{label:string,url:string,path:string,newtab:bool}>}|null
     */
    public function forLocation(string $location): ?array
    {
        if (!isset(self::LOCATIONS[$location])) {
            return null;
        }

        foreach ($this->all() as $menu) {
            if (($menu['location'] ?? '') !== $location) {
                continue;
            }

            $items = [];
            foreach ((array) $menu['items'] as $item) {
                $resolved = $this->resolve($item);
                if ($resolved !== null) {
                    $items[] = $resolved;
                }
            }
            if ($items === []) {
                return null;
            }

            return [
                'name'  => (string) $menu['name'],
                'title' => trim((string) ($menu['title'] ?? '')) !== '' ? (string) $menu['title'] : (string) $menu['name'],
                'items' => $items,
            ];
        }

        return null;
    }

    /**
     * Pages an admin can add, grouped for a <select>.
     *
     * @return array<string,array<string,string>> group => [value => label]
     */
    public function pageChoices(): array
    {
        $groups = ['Site pages' => self::PAGES];

        try {
            $campaigns = [];
            foreach ($this->campaigns->active(100) as $campaign) {
                $campaigns['campaigns/' . $campaign['slug']] = (string) $campaign['title'];
            }
            if ($campaigns !== []) {
                $groups['Campaigns'] = $campaigns;
            }
        } catch (Throwable) {
            // the campaign list is a convenience; the menu still works without it
        }

        return $groups;
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    public function create(string $name, string $location, ?int $userId): int
    {
        $name = $this->cleanText($name, 60);
        if ($name === '') {
            throw new InvalidArgumentException('Give the menu a name.');
        }
        $location = $this->cleanLocation($location);

        $data = $this->load();
        if (count($data['menus']) >= self::MAX_MENUS) {
            throw new InvalidArgumentException('You can have up to ' . self::MAX_MENUS . ' menus. Delete one you no longer need first.');
        }

        $id = (int) $data['next_id'];
        $data['next_id'] = $id + 1;
        $data['menus'][] = [
            'id'       => $id,
            'name'     => $name,
            'title'    => '',
            'location' => '',
            'items'    => [],
        ];
        $this->store($data, $userId);

        if ($location !== '') {
            $this->assignLocation($id, $location, $userId);
        }

        return $id;
    }

    /**
     * Save a menu from the edit form.
     *
     * @param array<string,mixed> $input name, title, location, items[]
     * @return string|null a notice, e.g. which menu lost the place it was in
     */
    public function update(int $id, array $input, ?int $userId): ?string
    {
        $data = $this->load();
        $index = $this->indexOf($data, $id);
        if ($index === null) {
            throw new InvalidArgumentException('That menu no longer exists.');
        }

        $name = $this->cleanText((string) ($input['name'] ?? ''), 60);
        if ($name === '') {
            throw new InvalidArgumentException('Give the menu a name.');
        }

        $items = $this->cleanItems((array) ($input['items'] ?? []));
        $location = $this->cleanLocation((string) ($input['location'] ?? ''));

        // A place shows one menu: taking it over releases it from the previous one.
        $notice = null;
        if ($location !== '') {
            foreach ($data['menus'] as $i => $other) {
                if ($i !== $index && ($other['location'] ?? '') === $location) {
                    $data['menus'][$i]['location'] = '';
                    $notice = '"' . $other['name'] . '" is no longer used for ' . strtolower(self::LOCATIONS[$location]['label']) . '.';
                }
            }
        }

        $data['menus'][$index]['name'] = $name;
        $data['menus'][$index]['title'] = $this->cleanText((string) ($input['title'] ?? ''), 60);
        $data['menus'][$index]['location'] = $location;
        $data['menus'][$index]['items'] = $items;

        $this->store($data, $userId);
        return $notice;
    }

    public function delete(int $id, ?int $userId): void
    {
        $data = $this->load();
        $index = $this->indexOf($data, $id);
        if ($index === null) {
            return;
        }
        array_splice($data['menus'], $index, 1);
        $this->store($data, $userId);
    }

    private function assignLocation(int $id, string $location, ?int $userId): void
    {
        $data = $this->load();
        foreach ($data['menus'] as $i => $menu) {
            if ($menu['location'] === $location) {
                $data['menus'][$i]['location'] = '';
            }
            if ((int) $menu['id'] === $id) {
                $data['menus'][$i]['location'] = $location;
            }
        }
        $this->store($data, $userId);
    }

    // ------------------------------------------------------------------
    // Cleaning / validation
    // ------------------------------------------------------------------

    /**
     * @param array<int|string,mixed> $rows posted rows: label, type, page, url, newtab, order
     * @return array<int,array{label:string,type:string,target:string,newtab:bool}>
     */
    private function cleanItems(array $rows): array
    {
        $valid = $this->validPagePaths();
        $clean = [];
        $position = 0;

        foreach ($rows as $row) {
            if (!is_array($row) || !empty($row['remove'])) {
                continue;
            }
            $position++;

            $label = $this->cleanText((string) ($row['label'] ?? ''), 60);
            $type = ($row['type'] ?? 'page') === 'custom' ? 'custom' : 'page';
            $target = $type === 'custom'
                ? trim((string) ($row['url'] ?? ''))
                : trim((string) ($row['page'] ?? ''), " \t\n\r\0\x0B/");

            // An untouched "add a link" row is simply ignored.
            if ($label === '' && $target === '') {
                continue;
            }
            if ($label === '') {
                throw new InvalidArgumentException('Every link needs a label (the text people click).');
            }

            if ($type === 'page') {
                if (!isset($valid[$target])) {
                    throw new InvalidArgumentException('"' . $label . '": choose a page from the list.');
                }
            } else {
                $target = $this->cleanUrl($target, $label);
            }

            $order = isset($row['order']) && is_numeric($row['order']) ? (float) $row['order'] : (float) $position;
            $clean[] = [
                'order'  => $order,
                'seq'    => $position,
                'item'   => [
                    'label'  => $label,
                    'type'   => $type,
                    'target' => $target,
                    'newtab' => !empty($row['newtab']),
                ],
            ];
        }

        usort($clean, static fn (array $a, array $b): int => [$a['order'], $a['seq']] <=> [$b['order'], $b['seq']]);

        if (count($clean) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('A menu can have up to ' . self::MAX_ITEMS . ' links.');
        }

        return array_map(static fn (array $c): array => $c['item'], $clean);
    }

    /** Only safe link targets: no javascript:, data: or other schemes. */
    private function cleanUrl(string $url, string $label): string
    {
        $url = trim($url);
        $ok = $url !== '' && mb_strlen($url) <= 500 && !preg_match('/[\s<>"\']/u', $url) && (
            preg_match('~^https?://[^/\s]+~i', $url) === 1
            || (str_starts_with($url, '/') && !str_starts_with($url, '//'))
            || str_starts_with($url, '#')
            || preg_match('~^(mailto|tel):[^\s]+$~i', $url) === 1
        );

        if (!$ok) {
            throw new InvalidArgumentException('"' . $label . '": the web address must start with https://, / (a page on this site), #, mailto: or tel:.');
        }
        return $url;
    }

    private function cleanText(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        return mb_substr($text, 0, $max);
    }

    private function cleanLocation(string $location): string
    {
        return isset(self::LOCATIONS[$location]) ? $location : '';
    }

    /** @return array<string,true> */
    private function validPagePaths(): array
    {
        $valid = [];
        foreach (self::PAGES as $path => $_) {
            $valid[$path] = true;
        }
        foreach ($this->pageChoices() as $group) {
            foreach ($group as $path => $_) {
                $valid[$path] = true;
            }
        }
        return $valid;
    }

    // ------------------------------------------------------------------
    // Resolving links
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $item
     * @return array{label:string,url:string,path:string,newtab:bool}|null
     */
    private function resolve(array $item): ?array
    {
        $label = (string) ($item['label'] ?? '');
        $target = (string) ($item['target'] ?? '');
        if ($label === '') {
            return null;
        }

        if (($item['type'] ?? 'page') === 'custom') {
            $url = str_starts_with($target, '/') && !str_starts_with($target, '//')
                ? base_url(ltrim($target, '/'))
                : $target;
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            $external = preg_match('~^https?://~i', $url) === 1 && !$this->isThisSite($url);
            return ['label' => $label, 'url' => $url, 'path' => $external ? '' : $path, 'newtab' => !empty($item['newtab'])];
        }

        $url = base_url($target);
        return [
            'label'  => $label,
            'url'    => $url,
            'path'   => (string) (parse_url($url, PHP_URL_PATH) ?? ''),
            'newtab' => !empty($item['newtab']),
        ];
    }

    private function isThisSite(string $url): bool
    {
        $site = parse_url(base_url('/'), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($site) && is_string($host) && strcasecmp($site, $host) === 0;
    }

    // ------------------------------------------------------------------
    // Storage
    // ------------------------------------------------------------------

    /** @return array{next_id:int,menus:array<int,array<string,mixed>>} */
    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $data = ['next_id' => 1, 'menus' => []];
        $raw = $this->settings->get(self::SETTING_KEY, '');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['menus']) && is_array($decoded['menus'])) {
                $maxId = 0;
                foreach ($decoded['menus'] as $menu) {
                    if (!is_array($menu) || !isset($menu['id'])) {
                        continue;
                    }
                    $location = (string) ($menu['location'] ?? '');
                    $data['menus'][] = [
                        'id'       => (int) $menu['id'],
                        'name'     => (string) ($menu['name'] ?? 'Menu'),
                        'title'    => (string) ($menu['title'] ?? ''),
                        'location' => isset(self::LOCATIONS[$location]) ? $location : '',
                        'items'    => is_array($menu['items'] ?? null) ? array_values($menu['items']) : [],
                    ];
                    $maxId = max($maxId, (int) $menu['id']);
                }
                $data['next_id'] = max((int) ($decoded['next_id'] ?? 1), $maxId + 1);
            }
        }

        return $this->cache = $data;
    }

    /** @param array{next_id:int,menus:array<int,array<string,mixed>>} $data */
    private function store(array $data, ?int $userId): void
    {
        $data['menus'] = array_values($data['menus']);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new InvalidArgumentException('The menu could not be saved.');
        }
        $this->settings->set(self::SETTING_KEY, $json, false, $userId);
        $this->cache = $data;
    }

    /** @param array{next_id:int,menus:array<int,array<string,mixed>>} $data */
    private function indexOf(array $data, int $id): ?int
    {
        foreach ($data['menus'] as $i => $menu) {
            if ((int) $menu['id'] === $id) {
                return $i;
            }
        }
        return null;
    }
}
