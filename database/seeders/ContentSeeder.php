<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;

/**
 * Seeds the editable marketing blocks from the ContentService defaults so the
 * homepage renders identically before and after the first admin edit.
 */
final class ContentSeeder
{
    public function __construct(private Database $db, private Logger $logger)
    {
    }

    public function run(): int
    {
        $defaults = (new \App\Services\ContentService($this->db))->defaults();

        $count = 0;
        foreach ($defaults as $key => $content) {
            $exists = $this->db->int('SELECT COUNT(*) FROM content_blocks WHERE block_key = :k', ['k' => $key]);
            if ($exists > 0) {
                continue;
            }

            $this->db->insert('content_blocks', [
                'block_key'    => $key,
                'content_json' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ]);
            $count++;
        }

        return $count;
    }
}
