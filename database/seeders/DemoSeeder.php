<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Str;

/**
 * Demonstration content: administrator account, fundraiser accounts,
 * fundraisers, teams and a realistic mix of donations.
 *
 * Every donation created here is written straight to the database together
 * with its payment_transactions row, exactly as the live pipeline would. No
 * gateway is contacted, and no production credential is required.
 *
 * Run with `db:seed Demo` (or leave it out entirely on a production install).
 */
final class DemoSeeder
{
    /** @var array<string,int> */
    private array $gatewayIds = [];

    public function __construct(private Database $db, private Logger $logger)
    {
    }

    public function run(): int
    {
        if ($this->db->int('SELECT COUNT(*) FROM fundraisers') > 0) {
            $this->logger->info('DemoSeeder skipped: fundraisers already exist');
            return 0;
        }

        $this->gatewayIds = [
            'meezan'   => (int) $this->db->scalar("SELECT id FROM gateways WHERE code = 'meezan'"),
            'etisalat' => (int) $this->db->scalar("SELECT id FROM gateways WHERE code = 'etisalat'"),
        ];

        $adminPassword = (string) (getenv('DEMO_ADMIN_PASSWORD') ?: 'AlmarahAdmin2026!');
        $ownerPassword = (string) (getenv('DEMO_OWNER_PASSWORD') ?: 'Fundraiser2026!');

        $adminId = $this->createUser('Almarah', 'Admin', 'admin@almarah.org', $adminPassword, ['super_admin', 'admin']);
        $owner1 = $this->createUser('Ayesha', 'Khan', 'ayesha@example.com', $ownerPassword, ['fundraiser']);
        $owner2 = $this->createUser('Bilal', 'Ahmed', 'bilal@example.com', $ownerPassword, ['fundraiser']);
        $owner3 = $this->createUser('Sana', 'Raza', 'sana@example.com', $ownerPassword, ['fundraiser']);
        $donor1 = $this->createUser('Fatima', 'Siddiqui', 'fatima@example.com', $ownerPassword, ['donor']);

        $categories = [];
        foreach ($this->db->select('SELECT id, slug FROM fundraiser_categories') as $row) {
            $categories[(string) $row['slug']] = (int) $row['id'];
        }

        $campaigns = [];
        foreach ($this->db->select('SELECT id, slug FROM campaigns') as $row) {
            $campaigns[(string) $row['slug']] = (int) $row['id'];
        }

        $fundraisers = [
            [
                'owner'    => $owner1,
                'title'    => 'Ayesha\'s Birthday for 50 Ration Packs',
                'category' => 'feed-the-hungry',
                'campaign' => null,
                'image'    => 'assets/img/insp-birthday.jpg',
                'goal'     => 500000,
                'impact'   => 'Funds 50 monthly ration packs for families in Lahore',
                'status'   => 'published',
                'days'     => 45,
                'featured' => 1,
                'story'    => "Every year I ask my friends and family for the same present: help me feed a family instead of buying me something I do not need.\n\nThis year we are aiming for 50 monthly ration packs. Each pack contains flour, rice, lentils, cooking oil, sugar and tea — enough to take real pressure off a household for a month. My family will pack and deliver every single one ourselves, and I will post photos and updates here as we go.\n\nRs 10,000 covers one full ration pack. If you can give more than that, please do — and if you can only give a little, that is still a meal on a table. Thank you.",
                'donations' => [
                    ['Tehmina Malik', 100000, 'completed', 'meezan', 'Best birthday idea ever. So proud of you.'],
                    ['Anonymous', 500000, 'completed', 'etisalat', ''],
                    ['Faisal Qureshi', 25000, 'completed', 'meezan', 'From all of us at the office.'],
                    ['Marianne Dubois', 15000, 'completed', 'etisalat', 'Sending love from Paris ❤️'],
                    ['Hassan Raza', 50000, 'completed', 'meezan', 'JazakAllah for doing this.'],
                    ['Anonymous', 75000, 'completed', 'etisalat', ''],
                    ['Kiran Shah', 10000, 'completed', 'meezan', ''],
                    ['Imran Yousaf', 20000, 'completed', 'etisalat', 'Happy birthday Ayesha!'],
                    ['Sadia Mirza', 30000, 'pending', 'meezan', ''],
                    ['Omar Farooq', 20000, 'failed', 'etisalat', ''],
                ],
            ],
            [
                'owner'    => $owner2,
                'title'    => 'Bilal\'s Ramadan Iftar Drive for 1,000 Families',
                'category' => 'izzat-ki-roti',
                'campaign' => $campaigns['tandoors-all-winter'] ?? null,
                'image'    => 'assets/img/insp-meals.jpg',
                'goal'     => 2500000,
                'impact'   => 'Serves 25,000 hot iftar meals across Lahore',
                'status'   => 'published',
                'days'     => 28,
                'featured' => 1,
                'story'    => "Our team cooked and delivered iftar meals every night of Ramadan last year. This year we want to double it.\n\nRs 2,500 feeds a family of five for a week. We cook in our own kitchen, pack in reusable boxes, and deliver to families who have been referred to us by the local community — no queues, no paperwork, no shame.\n\nEvery rupee you give here goes into ingredients and transport. Our volunteers give their time for free.",
                'donations' => [
                    ['Zainab Ali', 250000, 'completed', 'etisalat', 'May Allah accept it from all of us.'],
                    ['Anonymous', 1000000, 'completed', 'meezan', ''],
                    ['Usman Tariq', 150000, 'completed', 'meezan', 'Matched by my employer.'],
                    ['Anonymous', 300000, 'completed', 'etisalat', ''],
                    ['Nadia Hussain', 75000, 'completed', 'meezan', ''],
                    ['Sara Khan', 120000, 'completed', 'etisalat', 'From our whole extended family.'],
                    ['Anonymous', 200000, 'processing', 'meezan', ''],
                    ['Tariq Jameel', 50000, 'cancelled', 'etisalat', ''],
                ],
            ],
            [
                'owner'    => $owner1,
                'title'    => 'Almarah Grammar School — Sponsor 40 Children',
                'category' => 'educate-pakistan',
                'campaign' => $campaigns['school-places-2026'] ?? null,
                'image'    => 'assets/img/insp-school.jpg',
                'goal'     => 1920000,
                'impact'   => 'Keeps 40 children in school for a full academic year',
                'status'   => 'published',
                'days'     => 90,
                'featured' => 1,
                'story'    => "Rs 48,000 keeps one child at Almarah Grammar School for a whole year: fees, uniform, books, stationery and a hot lunch every day.\n\nMost of our students would otherwise be working. Parents come to us because they want something different for their children, and we want to make sure money is never the reason a child stops coming.\n\nSponsor one child, share it with friends, or give whatever you can towards the pot — it all goes into the same fund.",
                'donations' => [
                    ['Anonymous', 480000, 'completed', 'meezan', 'Ten children sponsored alhamdulillah.'],
                    ['Saeed Anwar', 96000, 'completed', 'etisalat', 'Two children, one year each.'],
                    ['Anonymous', 240000, 'completed', 'meezan', ''],
                    ['Rabia Nadeem', 48000, 'completed', 'etisalat', 'For my late mother.'],
                    ['Fahad Iqbal', 144000, 'completed', 'meezan', ''],
                    ['Anonymous', 40000, 'pending', 'etisalat', ''],
                ],
            ],
            [
                'owner'    => $owner3,
                'title'    => 'Sensory Room for Compassionate Haven',
                'category' => 'compassionate-haven',
                'campaign' => null,
                'image'    => 'assets/img/about-care.jpg',
                'goal'     => 3500000,
                'impact'   => 'Builds a therapy wing for 40 children with special needs',
                'status'   => 'pending_review',
                'days'     => 120,
                'featured' => 0,
                'story'    => "Our special needs home is at capacity and the therapy waiting list keeps growing. This page funds a purpose-built sensory room and two fully equipped speech and occupational therapy bays.\n\nFor a child who cannot yet speak, this room is how they finally get to tell us how they feel. We have quotes from three contractors and will publish the invoices as soon as the work begins.",
                'donations' => [],
            ],
            [
                'owner'    => $owner3,
                'title'    => 'Fatima\'s Marathon for Apna Ghar',
                'category' => 'parent-the-orphan',
                'campaign' => $campaigns['new-apna-ghar-25-children'] ?? null,
                'image'    => 'assets/img/insp-trek.jpg',
                'goal'     => 750000,
                'impact'   => 'Furnishes four bedrooms in our new care home',
                'status'   => 'published',
                'days'     => -6,
                'featured' => 0,
                'story'    => "I am running the Lahore Marathon in November and I would like every kilometre to pay for something real.\n\nThe new care home on the outskirts of Lahore needs beds, wardrobes, bedding and desks for four more bedrooms. Every rupee raised here goes straight into furnishing them before the children move in.\n\nI have never run more than 10km in my life. Wish me luck.",
                'donations' => [
                    ['Anonymous', 100000, 'completed', 'meezan', ''],
                    ['Hamza Sheikh', 50000, 'completed', 'etisalat', 'Run fast!'],
                    ['Anonymous', 150000, 'completed', 'meezan', ''],
                    ['Amina Tariq', 35000, 'completed', 'etisalat', ''],
                    ['Anonymous', 40000, 'refunded', 'meezan', ''],
                ],
            ],
            [
                'owner'    => $owner2,
                'title'    => 'Zakat for Winter Rations',
                'category' => 'where-its-needed-most',
                'campaign' => null,
                'image'    => 'assets/img/insp-meals.jpg',
                'goal'     => 1500000,
                'impact'   => 'Delivers winter rations to 150 households',
                'status'   => 'draft',
                'days'     => 60,
                'featured' => 0,
                'story'    => "Winter ration packs include flour, rice, oil, lentils, tea and a warm blanket for each household.\n\nWe are finalising our household list with the local community committee and will publish it once confirmed.",
                'donations' => [],
            ],
        ];

        $createdFundraisers = 0;
        $createdDonations = 0;

        foreach ($fundraisers as $definition) {
            $slug = Str::slug($definition['title']);
            $endAt = gmdate('Y-m-d', time() + ((int) $definition['days'] * 86400));

            $fundraiserId = $this->db->insert('fundraisers', [
                'owner_user_id'     => $definition['owner'],
                'campaign_id'       => $definition['campaign'],
                'category_id'       => $categories[$definition['category']] ?? null,
                'title'             => $definition['title'],
                'slug'              => $slug,
                'story'             => $definition['story'],
                'impact_statement'  => $definition['impact'],
                'cover_image_path'  => $definition['image'],
                'goal_minor'        => (int) $definition['goal'],
                'currency'          => 'PKR',
                'start_at'          => gmdate('Y-m-d', time() - (30 * 86400)),
                'end_at'            => $endAt,
                'status'            => $definition['status'],
                'approval_status'   => $definition['status'] === 'published' ? 'approved' : 'pending',
                'featured'          => (int) $definition['featured'],
                'published_at'      => $definition['status'] === 'published' ? gmdate('Y-m-d H:i:s', time() - (20 * 86400)) : null,
            ]);

            $createdFundraisers++;

            foreach ((array) $definition['donations'] as $index => [$donorName, $amountMinor, $status, $gateway, $message]) {
                if ($this->createDonation(
                    $fundraiserId,
                    $donorName,
                    (int) $amountMinor,
                    (string) $status,
                    (string) $gateway,
                    (string) $message,
                    $index
                )) {
                    $createdDonations++;
                }
            }

            // A published fundraiser gets a couple of updates.
            if ($definition['status'] === 'published') {
                $this->db->insert('fundraiser_updates', [
                    'fundraiser_id'  => $fundraiserId,
                    'author_user_id' => $definition['owner'],
                    'title'          => 'Thank you — we are overwhelmed',
                    'body'           => "We are genuinely lost for words. Thank you to everyone who has given, shared or messaged us so far.\n\nWe will post photographs and receipts here as the money is spent, so you can see exactly where your donation went.",
                    'status'         => 'published',
                    'published_at'   => gmdate('Y-m-d H:i:s', time() - (5 * 86400)),
                ]);
            }
        }

        // Teams
        $teamId = $this->db->insert('teams', [
            'owner_user_id' => $owner2,
            'campaign_id'   => $campaigns['tandoors-all-winter'] ?? null,
            'name'          => 'Team Izzat ki Roti',
            'slug'          => 'team-izzat-ki-roti',
            'description'   => 'Volunteers, kitchen staff and fundraisers keeping all twelve tandoors firing through the winter.',
            'goal_minor'    => 3000000,
            'currency'      => 'PKR',
            'status'        => 'active',
        ]);

        $this->db->insertIgnore('team_members', ['team_id' => $teamId, 'user_id' => $owner2, 'role' => 'owner']);
        $this->db->insertIgnore('team_members', ['team_id' => $teamId, 'user_id' => $owner1, 'role' => 'member']);

        $team2 = $this->db->insert('teams', [
            'owner_user_id' => $owner1,
            'campaign_id'   => $campaigns['school-places-2026'] ?? null,
            'name'          => 'Almarah School Sponsors',
            'slug'          => 'almarah-school-sponsors',
            'description'   => 'Alumni, teachers and friends sponsoring school places at Almarah Grammar School.',
            'goal_minor'    => 1920000,
            'currency'      => 'PKR',
            'status'        => 'active',
        ]);

        $this->db->insertIgnore('team_members', ['team_id' => $team2, 'user_id' => $owner1, 'role' => 'owner']);
        $this->db->insertIgnore('team_members', ['team_id' => $team2, 'user_id' => $donor1, 'role' => 'member']);

        $this->logger->info('Demo data seeded', [
            'fundraisers' => $createdFundraisers,
            'donations'   => $createdDonations,
        ]);

        echo "  Demo accounts:\n";
        echo "    admin@almarah.org      / {$adminPassword}   (super_admin)\n";
        echo "    ayesha@example.com     / {$ownerPassword}   (fundraiser)\n";
        echo "    bilal@example.com      / {$ownerPassword}   (fundraiser)\n";
        echo "    sana@example.com       / {$ownerPassword}   (fundraiser)\n";
        echo "    fatima@example.com     / {$ownerPassword}   (donor)\n";
        echo "  Change these passwords immediately on any non-local environment.\n";

        return $createdFundraisers + $createdDonations;
    }

    /** @param array<int,string> $roles */
    private function createUser(string $first, string $last, string $email, string $password, array $roles): int
    {
        $existing = $this->db->scalar('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        if ($existing !== null) {
            return (int) $existing;
        }

        $userId = $this->db->insert('users', [
            'first_name'        => $first,
            'last_name'         => $last,
            'email'             => $email,
            'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
            'status'            => 'active',
            'email_verified_at' => gmdate('Y-m-d H:i:s'),
        ]);

        foreach ($roles as $role) {
            $roleId = $this->db->scalar('SELECT id FROM roles WHERE name = :n', ['n' => $role]);
            if ($roleId !== null) {
                $this->db->insertIgnore('user_roles', ['user_id' => $userId, 'role_id' => (int) $roleId]);
            }
        }

        return $userId;
    }

    /**
     * Write a donation together with its transaction row, exactly as the live
     * pipeline would, so that reporting and reconciliation screens have
     * realistic data to display.
     */
    private function createDonation(
        int $fundraiserId,
        string $donorName,
        int $amountMinor,
        string $status,
        string $gateway,
        string $message,
        int $index
    ): bool {
        $gatewayId = $this->gatewayIds[$gateway] ?? null;
        if ($gatewayId === null) {
            return false;
        }

        $email = $donorName === 'Anonymous'
            ? 'anonymous' . $index . '@donor.example.com'
            : Str::slug($donorName, 40) . '@example.com';

        $donorId = $this->db->insert('donors', [
            'name'  => $donorName,
            'email' => $email,
        ]);

        $reference = Str::publicReference('ALM');
        $createdAt = gmdate('Y-m-d H:i:s', time() - (($index + 1) * 7200) - random_int(0, 3600));

        $donationId = $this->db->insert('donations', [
            'public_reference' => $reference,
            'donor_id'         => $donorId,
            'fundraiser_id'    => $fundraiserId,
            'gateway_id'       => $gatewayId,
            'amount_minor'     => $amountMinor,
            'currency'         => 'PKR',
            'donor_message'    => $message !== '' ? $message : null,
            'anonymous'        => $donorName === 'Anonymous' ? 1 : 0,
            'status'           => $status,
            'receipt_email_status' => $status === 'completed' ? 'sent' : 'not_sent',
            'completed_at'     => $status === 'completed' ? $createdAt : null,
            'created_at'       => $createdAt,
        ]);

        // Force the created_at value: insert() does not accept it twice.
        $this->db->run('UPDATE donations SET created_at = :c WHERE id = :id', ['c' => $createdAt, 'id' => $donationId]);

        $merchantOrderId = strtoupper(str_replace('-', '', $reference)) . strtoupper(Str::randomHex(2));
        $providerTxnId = $gateway === 'meezan'
            ? 'MZN' . strtoupper(Str::randomHex(6))
            : 'EPG' . strtoupper(Str::randomHex(6));

        $this->db->insert('payment_transactions', [
            'donation_id'               => $donationId,
            'gateway_id'                => $gatewayId,
            'environment'               => 'sandbox',
            'merchant_order_id'         => $merchantOrderId,
            'provider_transaction_id'   => $providerTxnId,
            'provider_order_id'         => $gateway === 'meezan' ? 'ORD' . strtoupper(Str::randomHex(6)) : null,
            'callback_state_hash'       => hash('sha256', Str::randomToken(16)),
            'callback_state_expires_at' => gmdate('Y-m-d H:i:s', strtotime($createdAt) + 10800),
            'amount_minor'              => $amountMinor,
            'currency'                  => 'PKR',
            'status'                    => $status,
            'request_reference'         => Str::randomHex(8),
            'idempotency_key'           => 'demo:' . $gateway . ':' . $donationId,
            'provider_response_code'    => $status === 'completed'
                ? ($gateway === 'meezan' ? '2' : '0')
                : ($status === 'cancelled' ? ($gateway === 'meezan' ? '6' : '112') : '1'),
            'provider_response_description' => $status === 'completed' ? 'Approved' : 'Demo transaction',
            'registered_at'             => $createdAt,
            'finalized_at'              => $status === 'completed' ? $createdAt : null,
            'completed_at'              => $status === 'completed' ? $createdAt : null,
        ]);

        return true;
    }
}
