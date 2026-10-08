<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Editable marketing content: hero, impact numbers, trust copy, support page,
 * privacy policy and FAQs. Falls back to shipped defaults so a fresh install
 * renders without any admin input.
 */
final class ContentService
{
    /** @var array<string,mixed> */
    private array $cache = [];

    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed> */
    public function block(string $key): array
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $json = null;
        try {
            $json = $this->db->scalar('SELECT content_json FROM content_blocks WHERE block_key = :k', ['k' => $key]);
        } catch (\Throwable $e) {
            $json = null;
        }

        $stored = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        $defaults = $this->defaults()[$key] ?? [];

        $merged = array_merge($defaults, is_array($stored) ? $stored : []);
        $this->cache[$key] = $merged;

        return $merged;
    }

    /** @param array<string,mixed> $content */
    public function saveBlock(string $key, array $content, ?int $userId = null): void
    {
        unset($this->cache[$key]);

        $json = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $existing = $this->db->scalar('SELECT id FROM content_blocks WHERE block_key = :k', ['k' => $key]);

        if ($existing) {
            $this->db->update('content_blocks', [
                'content_json' => $json,
                'updated_by'   => $userId,
            ], 'block_key = :k', ['k' => $key]);
            return;
        }

        $this->db->insert('content_blocks', [
            'block_key'   => $key,
            'content_json'=> $json,
            'updated_by'  => $userId,
        ]);
    }

    /** @return array<string,mixed> */
    public function hero(): array
    {
        return $this->block('homepage_hero');
    }

    /** @return array<int,array{value:string,label:string}> */
    public function impactNumbers(): array
    {
        $block = $this->block('impact_numbers');
        $items = $block['items'] ?? [];
        return is_array($items) ? $items : [];
    }

    /** @return array<string,string> */
    public function trust(): array
    {
        return $this->block('trust');
    }

    /** @return array<string,string> */
    public function supportInfo(): array
    {
        return $this->block('support');
    }

    /** @return array<string,string> */
    public function privacyPolicy(): array
    {
        return $this->block('privacy_policy');
    }

    /** @return array<int,array<string,mixed>> */
    public function faqs(bool $publishedOnly = true): array
    {
        $sql = 'SELECT * FROM faqs';
        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        try {
            return $this->db->select($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function findFaq(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM faqs WHERE id = :id', ['id' => $id]);
    }

    /** @param array<string,mixed> $data */
    public function saveFaq(?int $id, array $data): int
    {
        $payload = [
            'question'   => (string) $data['question'],
            'answer'     => (string) $data['answer'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status'     => in_array(($data['status'] ?? 'published'), ['published', 'draft'], true) ? $data['status'] : 'published',
        ];

        if ($id !== null) {
            $this->db->update('faqs', $payload, 'id = :id', ['id' => $id]);
            return $id;
        }

        return $this->db->insert('faqs', $payload);
    }

    public function deleteFaq(int $id): void
    {
        $this->db->delete('faqs', 'id = :id', ['id' => $id]);
    }

    /** @return array<string,array<string,mixed>> */
    public function defaults(): array
    {
        return [
            'homepage_hero' => [
                'eyebrow'     => 'Transform Lives Through Care',
                'heading'     => 'Fundraise for a child’s Apna Ghar',
                'heading_em'  => 'Apna Ghar',
                'subheading'  => 'Create your personal fundraiser now and rally your friends and family. Together, we can give orphaned and vulnerable children in Pakistan a safe home, a full plate and a place in school.',
                'note'        => 'Host a birthday bash, run a marathon, or give up your gifts — all you need is one creative idea to get started.',
                'primary_cta_label'   => 'Start Your Fundraiser',
                'secondary_cta_label' => 'Support a Fundraiser',
                'image'       => 'assets/img/hero.jpg',
            ],
            'impact_numbers' => [
                'items' => [
                    ['value' => '275+',    'label' => 'Children Supported'],
                    ['value' => '13',      'label' => 'Care Homes'],
                    ['value' => '21,000+', 'label' => 'Ration Packs'],
                    ['value' => '1,500+',  'label' => 'Meals Daily'],
                ],
            ],
            'trust' => [
                'heading' => 'Your donation is safe and accountable',
                'body'    => 'Donations are received directly by Almarah Foundation and applied to the programme you choose. Payment is processed on the bank’s own secure page — we never see or store your card details.',
                'points'  => 'Card details never touch our servers|Payments are verified server-to-server before a donation is counted|Every donation is recorded with a public reference you can quote',
            ],
            'support' => [
                'heading'   => 'We are here to help',
                'body'      => 'Whether you are starting your first fundraiser, need help with a donation, or want to visit one of our care homes, our team in Lahore replies to every message.',
                'email'     => 'info@almarah.org',
                'phone'     => '+92 42 111 262 272',
                'address'   => 'Canal Road, Lahore, Punjab, Pakistan',
                'hours'     => 'Monday to Saturday, 9:00am – 6:00pm (PKT)',
                'response'  => 'We usually reply within one working day.',
            ],
            'terms' => [
                'title' => 'Terms of use',
                'updated' => 'Last updated: ' . gmdate('F Y'),
                'body' => "These terms govern your use of the Almarah Foundation fundraising platform.\n\n"
                    . "Fundraisers\nWhen you create a fundraiser you confirm that the information you publish is accurate and that you will not misrepresent Almarah Foundation or the programmes the funds support. Fundraisers are reviewed before they appear publicly and may be paused or removed if they breach these terms.\n\n"
                    . "Donations\nDonations are voluntary and, once processed, are generally non-refundable except where a duplicate or erroneous payment has occurred. Refunds are issued to the original payment method.\n\n"
                    . "Use of funds\nFunds raised through the platform are applied to the programme described on the fundraiser page or, where that programme is fully funded, to the closest equivalent programme. Almarah Foundation retains discretion to apply funds where they are needed most.\n\n"
                    . "Prohibited conduct\nYou may not use the platform for fraudulent activity, to process payments on behalf of a third party, to publish unlawful content, or to attempt to interfere with the security of the platform.\n\n"
                    . "Liability\nThe platform is provided on an as-is basis. We do not guarantee uninterrupted availability, but we will always act to protect donations already made and to give you a clear record of them.\n\n"
                    . "Contact\nQuestions about these terms can be sent to info@almarah.org.",
            ],
            'privacy_policy' => [
                'heading'  => 'Privacy Policy',
                'updated'  => 'Last updated: ' . gmdate('F Y'),
                'body'     => "Almarah Foundation respects your privacy. This policy explains what we collect when you use our fundraising platform and how we use it.\n\n"
                    . "What we collect\nWe collect the information you provide when you create an account, start a fundraiser or make a donation: your name, email address, optional phone number, and the details of your fundraiser. We also record technical information such as your IP address and browser, which we use for security and fraud prevention.\n\n"
                    . "Payments\nCard payments are processed entirely on the secure hosted pages of Meezan Bank or Etisalat (UBL EPG). We never receive or store your card number, expiry date or CVV. We store the transaction reference, amount, currency and payment status returned by the payment provider so that we can issue your receipt and reconcile donations.\n\n"
                    . "How we use your information\nWe use your information to operate your account, publish your fundraiser, process and receipt your donations, send you service emails, prevent fraud, and meet our legal and accounting obligations. We do not sell your personal information.\n\n"
                    . "Email\nWe send transactional emails such as verification links, receipts and fundraiser status updates. You can manage optional emails from your account settings at any time.\n\n"
                    . "Retention\nWe keep donation records for as long as required to meet our financial and legal obligations. Account data is retained while your account is active.\n\n"
                    . "Your rights\nYou may request access to, correction of, or deletion of your personal information by writing to info@almarah.org. Where deletion would conflict with our legal obligations, we will explain what we must retain.\n\n"
                    . "Security\nWe use encrypted connections, hashed passwords, access controls and audit logging to protect your information. Access to donor data is limited to staff who need it to do their work.\n\n"
                    . "Contact\nQuestions about this policy can be sent to info@almarah.org or to Almarah Foundation, Canal Road, Lahore, Punjab, Pakistan.",
            ],
        ];
    }
}
