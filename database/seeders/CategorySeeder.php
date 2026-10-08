<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;

/**
 * Categories mirror Almarah Foundation's real programmes.
 */
final class CategorySeeder
{
    public function __construct(private Database $db, private Logger $logger)
    {
    }

    public function run(): int
    {
        $categories = [
            [
                'name' => 'Parent the Orphan',
                'slug' => 'parent-the-orphan',
                'description' => 'Safe, nurturing care homes that meet international standards of care.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Feed the Hungry',
                'slug' => 'feed-the-hungry',
                'description' => 'Daily meals and monthly ration packs for families in need.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Educate Pakistan',
                'slug' => 'educate-pakistan',
                'description' => 'Free schooling at Almarah Grammar School, Canal Road, Lahore.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Izzat ki Roti',
                'slug' => 'izzat-ki-roti',
                'description' => 'Dignified food initiative — a meal without the stigma of charity.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Compassionate Haven',
                'slug' => 'compassionate-haven',
                'description' => 'A dedicated home and therapy facilities for children with special needs.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Where It\'s Needed Most',
                'slug' => 'where-its-needed-most',
                'description' => 'Unrestricted giving — directed to whichever programme needs it most.',
                'sort_order' => 6,
            ],
        ];

        $count = 0;
        foreach ($categories as $category) {
            $exists = $this->db->int('SELECT COUNT(*) FROM fundraiser_categories WHERE slug = :s', ['s' => $category['slug']]);
            if ($exists > 0) {
                continue;
            }
            $this->db->insert('fundraiser_categories', $category + ['status' => 'active']);
            $count++;
        }

        return $count;
    }
}
