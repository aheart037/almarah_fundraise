<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;

final class CampaignSeeder
{
    public function __construct(private Database $db, private Logger $logger)
    {
    }

    public function run(): int
    {
        $campaigns = [
            [
                'title'       => 'A New Apna Ghar for 25 Children',
                'slug'        => 'new-apna-ghar-25-children',
                'description' => 'Furnishing our 14th care home on the outskirts of Lahore: warm beds, three meals a day, school uniforms, books and full medical care for 25 orphaned children.',
                'image_path'  => 'assets/img/about-care.jpg',
                'featured'    => 1,
            ],
            [
                'title'       => 'Keep the Tandoors Firing All Winter',
                'slug'        => 'tandoors-all-winter',
                'description' => 'Izzat ki Roti serves anyone who walks up — no ration card, no questions, no shame. Help us keep all twelve tandoors running through the winter months.',
                'image_path'  => 'assets/img/insp-meals.jpg',
                'featured'    => 1,
            ],
            [
                'title'       => 'School Places for 2026',
                'slug'        => 'school-places-2026',
                'description' => 'A gift of Rs 48,000 keeps one child at Almarah Grammar School for a full year — fees, uniform, books and a hot lunch.',
                'image_path'  => 'assets/img/insp-school.jpg',
                'featured'    => 1,
            ],
        ];

        $count = 0;
        foreach ($campaigns as $campaign) {
            $exists = $this->db->int('SELECT COUNT(*) FROM campaigns WHERE slug = :s', ['s' => $campaign['slug']]);
            if ($exists > 0) {
                continue;
            }
            $this->db->insert('campaigns', $campaign + ['status' => 'active']);
            $count++;
        }

        return $count;
    }
}
