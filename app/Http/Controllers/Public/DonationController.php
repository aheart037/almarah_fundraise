<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentGatewayManager;
use App\Repositories\CampaignRepository;
use App\Repositories\FundraiserRepository;
use App\Repositories\TeamRepository;
use App\Services\AuthService;
use App\Services\DonationService;
use InvalidArgumentException;

/**
 * The donation wizard: pick an amount, pick a payment method, hand off to the
 * hosted payment page. Nothing here decides that a payment succeeded.
 */
final class DonationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private DonationService $donations,
        private PaymentGatewayManager $gateways,
        private FundraiserRepository $fundraisers,
        private CampaignRepository $campaigns,
        private TeamRepository $teams,
        private Logger $logger
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function form(Request $request): Response
    {
        $target = (string) $request->routeParam('target', '');
        $resolved = $this->resolveTarget($target, (string) $request->input('type', ''));

        if ($resolved === null) {
            return $this->render('public/errors/404', ['pageTitle' => 'Not found'], 'layouts/public', 404);
        }

        $amount = $request->input('amount');
        $presetAmounts = (array) Config::get('app.donations.presets_minor', []);
        $selectedMinor = is_numeric($amount) ? (int) round((float) $amount * 100) : (int) ($presetAmounts[1] ?? 1000000);

        $user = $this->auth->user();

        return $this->render('public/donate/form', [
            'pageTitle'       => 'Donate to ' . ($resolved['record']['title'] ?? $resolved['record']['name'] ?? 'Almarah Foundation'),
            'metaDescription' => 'Make a secure donation by debit/credit card, bank transfer or mobile wallet.',
            'target'          => $resolved,
            'targetSlug'      => $target,
            'targetType'      => $resolved['kind'],
            'presets'         => $presetAmounts,
            'selectedMinor'   => $selectedMinor,
            'gateways'        => $this->gateways->choices(),
            'gatewayReady'    => $this->gateways->hasUsableGateway(),
            'currency'        => (string) Config::get('app.currency', 'PKR'),
            'minMinor'        => (int) Config::get('app.donations.min_minor', 10000),
            'maxMinor'        => (int) Config::get('app.donations.max_minor', 500000000),
            'donorName'       => $user !== null ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '',
            'donorEmail'      => $user['email'] ?? '',
            'donorPhone'      => $user['phone'] ?? '',
        ], 'layouts/public');
    }

    public function submit(Request $request): Response
    {
        $target = (string) $request->routeParam('target', '');
        $resolved = $this->resolveTarget($target, (string) $request->input('type', ''));

        if ($resolved === null) {
            $this->flashError('We could not find that fundraiser or campaign.');
            return $this->redirect('/fundraisers');
        }

        $validator = Validator::make($request->all(), [
            'amount'       => 'required',
            'gateway'      => 'required|string',
            'name'         => 'required|string|min:3|max:120',
            'email'        => 'required|email|max:190',
            'phone'        => 'nullable|string|max:30',
            'message'      => 'nullable|string|max:500',
            'anonymous'    => 'nullable|in:1,on,yes',
            'cover_fees'   => 'nullable|in:1,on,yes',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $amount = (string) $request->input('amount');
        if (!is_numeric($amount) || (float) $amount <= 0) {
            return $this->flashFormState(['amount' => 'Enter a valid donation amount in rupees.'], $request->all());
        }

        // Rupees in the form, integer minor units everywhere else.
        $amountMinor = (int) round(((float) $amount) * 100);

        $gatewayCode = (string) $request->input('gateway');
        if (!in_array($gatewayCode, $this->gateways->availableCodes(), true)) {
            return $this->flashFormState(['gateway' => 'Choose a valid payment method.'], $request->all());
        }

        $user = $this->auth->user();

        try {
            $result = $this->donations->initiate([
                'amount_minor' => $amountMinor,
                'name'         => (string) $request->input('name'),
                'email'        => (string) $request->input('email'),
                'phone'        => $request->input('phone'),
                'message'      => $request->input('message'),
                'anonymous'    => (bool) $request->input('anonymous'),
                'cover_fees'   => (bool) $request->input('cover_fees'),
                'ip'           => $request->ip(),
                'user_id'      => $user['id'] ?? null,
            ], $resolved, $gatewayCode);
        } catch (PaymentGatewayException $e) {
            $this->logger->payment('Donation initiation failed', [
                'gateway' => $gatewayCode,
                'reason'  => $e->reason(),
            ]);
            $this->session->flash('_old', $this->stripSensitive($request->all()));
            $this->flashError($e->getMessage());
            return $this->redirect('/donate/' . rawurlencode($target) . ($resolved['kind'] !== 'fundraiser' ? '?type=' . $resolved['kind'] : ''));
        } catch (InvalidArgumentException $e) {
            $this->session->flash('_old', $this->stripSensitive($request->all()));
            $this->flashError($e->getMessage());
            return $this->redirect('/donate/' . rawurlencode($target) . ($resolved['kind'] !== 'fundraiser' ? '?type=' . $resolved['kind'] : ''));
        }

        // Hand the donor to the provider's own hosted payment page. The
        // redirect URL is validated against the gateway host allowlist.
        return $this->redirect($result['redirect_url']);
    }

    /** @return array<string,mixed>|null */
    private function resolveTarget(string $target, string $type): ?array
    {
        $target = trim($target);

        if ($target === '' ) {
            return null;
        }

        if ($target === 'general' || $target === 'foundation') {
            return [
                'kind'   => 'general',
                'record' => ['title' => 'Almarah Foundation general fund'],
            ];
        }

        $resolved = $this->donations->resolveTarget($target, $type !== '' ? $type : null);

        if ($resolved === null) {
            return null;
        }

        // Only cause pages that are actually live can accept money.
        $record = $resolved['record'];
        if ($resolved['kind'] === 'fundraiser' && (string) ($record['status'] ?? '') !== 'published') {
            return null;
        }

        return $resolved;
    }
}
