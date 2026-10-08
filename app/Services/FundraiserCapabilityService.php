<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use InvalidArgumentException;

/** Global controls for actions available to fundraiser-role accounts. */
final class FundraiserCapabilityService
{
    /** @var array<string,string> capability => setting key */
    private const SETTING_KEYS = [
        'manage_pages'   => 'fundraiser.capabilities.manage_pages',
        'manage_teams'   => 'fundraiser.capabilities.manage_teams',
        'publish_updates'=> 'fundraiser.capabilities.publish_updates',
    ];

    public function __construct(private SettingsService $settings)
    {
    }

    public function enabled(string $capability): bool
    {
        $settingKey = self::SETTING_KEYS[$capability] ?? null;
        if ($settingKey === null) {
            throw new InvalidArgumentException('Unknown fundraiser capability.');
        }

        return $this->settings->bool($settingKey, $this->defaultFor($capability));
    }

    /** @return array{manage_pages:bool,manage_teams:bool,publish_updates:bool} */
    public function all(): array
    {
        return [
            'manage_pages'    => $this->enabled('manage_pages'),
            'manage_teams'    => $this->enabled('manage_teams'),
            'publish_updates' => $this->enabled('publish_updates'),
        ];
    }

    /** @param array<string,bool> $values */
    public function save(array $values, ?int $userId = null): void
    {
        $pairs = [];
        foreach (self::SETTING_KEYS as $capability => $settingKey) {
            $pairs[$settingKey] = !empty($values[$capability]) ? '1' : '0';
        }

        $this->settings->setMany($pairs, $userId);
    }

    private function defaultFor(string $capability): bool
    {
        return match ($capability) {
            'manage_pages', 'publish_updates' => true,
            'manage_teams' => (bool) Config::get('app.features.team_fundraising', true),
            default => false,
        };
    }
}
