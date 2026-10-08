<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;

final class GatewaySeeder
{
    public function __construct(private Database $db, private Logger $logger)
    {
    }

    public function run(): int
    {
        $gateways = [
            ['code' => 'meezan',   'name' => 'Meezan Bank',        'sort_order' => 1],
            ['code' => 'etisalat', 'name' => 'Etisalat / UBL EPG', 'sort_order' => 2],
        ];

        $count = 0;
        foreach ($gateways as $gateway) {
            $exists = $this->db->int('SELECT COUNT(*) FROM gateways WHERE code = :c', ['c' => $gateway['code']]);
            if ($exists > 0) {
                continue;
            }
            $this->db->insert('gateways', $gateway + ['enabled' => 1]);
            $count++;
        }

        return $count;
    }
}
