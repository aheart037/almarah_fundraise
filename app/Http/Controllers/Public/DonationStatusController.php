<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\DonationRepository;
use App\Repositories\PaymentRepository;
use App\Services\AuthService;

/**
 * Post-payment result screens.
 *
 * The page shows what our database believes — never what the URL or the
 * browser claims. A "completed" screen only appears for a donation whose
 * status was set by a verified server-to-server response.
 */
final class DonationStatusController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private DonationRepository $donations,
        private PaymentRepository $payments
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function success(Request $request): Response
    {
        return $this->resultPage($request, 'completed');
    }

    public function processing(Request $request): Response
    {
        return $this->resultPage($request, 'processing');
    }

    public function failed(Request $request): Response
    {
        return $this->resultPage($request, 'failed');
    }

    /** Receipt / status lookup by public reference. */
    public function show(Request $request): Response
    {
        $reference = (string) $request->routeParam('reference', '');
        $donation = $this->donations->findByReference($reference);

        if ($donation === null) {
            return $this->render('public/errors/404', ['pageTitle' => 'Donation not found'], 'layouts/public', 404);
        }

        return $this->render('public/donate/status', [
            'pageTitle'   => 'Donation ' . $reference,
            'donation'    => $donation,
            'transactions'=> $this->payments->forDonation((int) $donation['id']),
            'callbacks'   => [],
            'donorMessage'=> null,
        ], 'layouts/public');
    }

    private function resultPage(Request $request, string $expected): Response
    {
        $reference = trim((string) $request->input('ref', ''));
        $donation = $reference !== '' ? $this->donations->findByReference($reference) : null;

        if ($donation === null) {
            return $this->render('public/donate/status', [
                'pageTitle'    => 'Donation status',
                'donation'     => null,
                'transactions' => [],
                'callbacks'    => [],
                'expected'     => $expected,
                'donorMessage' => 'We could not find a donation with that reference. Check the link in your receipt email, or contact support.',
            ], 'layouts/public');
        }

        $status = (string) $donation['status'];

        // Always render the page for the donation's real status, whichever
        // landing URL the browser arrived on.
        return $this->render('public/donate/status', [
            'pageTitle'    => $status === 'completed' ? 'Thank you for your donation' : 'Donation status',
            'donation'     => $donation,
            'transactions' => $this->payments->forDonation((int) $donation['id']),
            'callbacks'    => [],
            'expected'     => $expected,
            'currency'     => (string) Config::get('app.currency', 'PKR'),
            'donorMessage' => null,
        ], 'layouts/public');
    }
}
