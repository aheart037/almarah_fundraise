<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;

final class FaqSeeder
{
    public function __construct(private Database $db, private Logger $logger)
    {
    }

    public function run(): int
    {
        $faqs = [
            ['How much of my donation reaches the children?',
             'Almarah Foundation receives donations directly and applies them to the programme the fundraiser selected. Our core programmes — Parent the Orphan, Feed the Hungry and Educate Pakistan — are designed so that the overwhelming majority of every rupee is spent on the child, the meal or the classroom rather than on administration.', 1],

            ['Which payment methods can I use?',
             'Donations are processed through Meezan Bank and Etisalat (UBL EPG). In both cases you are taken to the bank\'s own secure hosted payment page, so your card details are never entered on or stored by this website.', 2],

            ['Is my donation secure?',
             'Yes. All traffic is encrypted over HTTPS, card details are handled entirely by the payment provider, and we never mark a donation as complete until the bank confirms it directly with our server. Our own records of every transaction are kept for reconciliation and audit.', 3],

            ['Can I choose which programme my fundraiser supports?',
             'Yes. When you create your page you choose one of our programmes, or "Where It\'s Needed Most" if you would like us to direct your gift to whichever need is most urgent that month. You can change this at any time from your fundraiser dashboard.', 4],

            ['Is there a minimum fundraising goal?',
             'No. Your goal is entirely up to you — Rs 25,000 funds a month of school for a child. You can always raise your goal later if your page does better than you expected.', 5],

            ['Do I need to handle the money myself?',
             'Never. You never touch the funds. Donations go directly to Almarah Foundation, and your page displays a running total of verified donations so your supporters can see exactly how the campaign is doing.', 6],

            ['Why does my new fundraiser need approval?',
             'Every new page is reviewed by our team before it becomes public. This protects the children and families we work with, keeps our platform free of misleading pages, and protects your own reputation as a fundraiser. Reviews are usually completed within one working day.', 7],

            ['Can I fundraise as a team, a company or a school?',
             'Absolutely. Teams, offices, schools and families regularly raise for Almarah. You can start a team page and invite members so everyone collects towards one shared goal.', 8],

            ['When will I receive my donation receipt?',
             'A receipt is emailed automatically the moment the bank confirms your payment to our server. If your bank is still processing the transaction you will receive a "being processed" email first, followed by the receipt as soon as it is confirmed.', 9],

            ['Can I get a refund?',
             'If you donated in error, contact us at info@almarah.org with your donation reference. Refunds are processed through the same payment gateway you used and are confirmed to us by the bank before we mark them complete.', 10],

            ['Can I visit an Almarah care home?',
             'We welcome visits from donors and fundraisers, subject to safeguarding rules and with advance notice so our staff can prepare. Please write to us at info@almarah.org to arrange a date.', 11],

            ['Who do I contact if I need help?',
             'Email info@almarah.org with your fundraiser link or donation reference and a description of what you need. Our fundraising team replies to every message, usually within one working day.', 12],
        ];

        $count = 0;
        foreach ($faqs as [$question, $answer, $order]) {
            $exists = $this->db->int('SELECT COUNT(*) FROM faqs WHERE question = :q', ['q' => $question]);
            if ($exists > 0) {
                continue;
            }
            $this->db->insert('faqs', [
                'question'   => $question,
                'answer'     => $answer,
                'sort_order' => $order,
                'status'     => 'published',
            ]);
            $count++;
        }

        return $count;
    }
}
