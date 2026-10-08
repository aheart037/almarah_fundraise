<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\DonationRepository;
use App\Repositories\PaymentRepository;
use App\Services\AuthService;
use App\Services\DonationService;
use InvalidArgumentException;

/**
 * Donations, transactions and gateway callbacks.
 *
 * Refunds and manual reconciliation both ask the provider server-to-server and
 * write the outcome through PaymentStatusService — the admin screen cannot
 * simply declare a payment successful.
 */
final class DonationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private DonationRepository $donations,
        private PaymentRepository $payments,
        private DonationService $service
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q'        => trim((string) $request->input('q', '')),
            'status'   => (string) $request->input('status', ''),
            'gateway'  => (string) $request->input('gateway', ''),
            'date_from'=> (string) $request->input('date_from', ''),
            'date_to'  => (string) $request->input('date_to', ''),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = $this->donations->search($filters, $page, $perPage);

        return $this->render('admin/donations/index', [
            'pageTitle' => 'Donations',
            'donations' => $result['rows'],
            'total'     => $result['total'],
            'sums'      => $result['sums'],
            'page'      => $page,
            'lastPage'  => max(1, (int) ceil($result['total'] / $perPage)),
            'filters'   => $filters,
            'totals'    => $this->donations->totalsByStatus(),
            'counts'    => $this->payments->statusCounts(),
        ], 'layouts/admin');
    }

    public function show(Request $request): Response
    {
        $donation = $this->donations->find((int) $request->routeParam('id', 0));

        if ($donation === null) {
            abort(404, 'Donation not found.');
        }

        $transactions = $this->payments->forDonation((int) $donation['id']);

        $callbacks = [];
        foreach ($transactions as $transaction) {
            $callbacks[(int) $transaction['id']] = $this->payments->callbacksFor((int) $transaction['id']);
        }

        $refunds = [];
        foreach ($transactions as $transaction) {
            $refunds[(int) $transaction['id']] = $this->payments->refundsFor((int) $transaction['id']);
        }

        return $this->render('admin/donations/show', [
            'pageTitle'    => 'Donation ' . (string) $donation['public_reference'],
            'donation'     => $donation,
            'transactions' => $transactions,
            'callbacks'    => $callbacks,
            'refunds'      => $refunds,
            'auditTrail'   => [],
        ], 'layouts/admin');
    }

    /** Ask the provider what happened to a transaction we never heard back on. */
    public function reconcile(Request $request): Response
    {
        $donation = $this->donations->find((int) $request->routeParam('id', 0));

        if ($donation === null) {
            abort(404, 'Donation not found.');
        }

        $transactions = $this->payments->forDonation((int) $donation['id']);

        if ($transactions === []) {
            $this->flashError('There is no payment transaction for this donation yet.');
            return $this->redirect('/admin/donations/' . $donation['id']);
        }

        $result = $this->service->reconcile((int) $transactions[0]['id'], (int) $this->auth->id());

        $this->flashSuccess($result['message'] ?? 'Reconciliation finished.');
        return $this->redirect('/admin/donations/' . $donation['id']);
    }

    public function reconcileTransaction(Request $request): Response
    {
        $result = $this->service->reconcile((int) $request->routeParam('id', 0), (int) $this->auth->id());

        if (!empty($result['donation_id'])) {
            $this->flashSuccess($result['message'] ?? 'Reconciliation finished.');
            return $this->redirect('/admin/donations/' . $result['donation_id']);
        }

        $this->flashError($result['message'] ?? 'Reconciliation could not be completed.');
        return $this->redirect('/admin/donations');
    }

    public function refund(Request $request): Response
    {
        $donation = $this->donations->find((int) $request->routeParam('id', 0));

        if ($donation === null) {
            abort(404, 'Donation not found.');
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'nullable',
            'reason' => 'required|string|min:5|max:500',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $transactions = $this->payments->forDonation((int) $donation['id']);

        if ($transactions === []) {
            $this->flashError('There is no payment transaction to refund.');
            return $this->redirect('/admin/donations/' . $donation['id']);
        }

        $amount = (string) $request->input('amount', '');
        $amountMinor = $amount !== '' ? (int) round(((float) str_replace(',', '', $amount)) * 100) : null;

        try {
            $result = $this->service->refund(
                (int) $transactions[0]['id'],
                (int) $this->auth->id(),
                $amountMinor
            );
        } catch (InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
            return $this->redirect('/admin/donations/' . $donation['id']);
        }

        if (!empty($result['ok'])) {
            $this->flashSuccess($result['message'] ?? 'Refund submitted to the gateway.');
        } else {
            $this->flashError($result['message'] ?? 'The refund could not be completed.');
        }

        return $this->redirect('/admin/donations/' . $donation['id']);
    }

    public function export(Request $request): Response
    {
        $filters = [
            'q'         => trim((string) $request->input('q', '')),
            'status'    => (string) $request->input('status', ''),
            'gateway'   => (string) $request->input('gateway', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to'   => (string) $request->input('date_to', ''),
        ];

        $result = $this->donations->search($filters, 1, 5000);

        $csv = "Reference,Created,Status,Gateway,Amount,Currency,Donor,Donor email,Fundraiser,Transaction,Env\n";

        foreach ($result['rows'] as $row) {
            $csv .= implode(',', array_map(
                static fn (mixed $value): string => '"' . str_replace('"', '""', (string) $value) . '"',
                [
                    $row['public_reference'] ?? '',
                    $row['created_at'] ?? '',
                    $row['status'] ?? '',
                    $row['gateway_code'] ?? '',
                    number_format(((int) ($row['amount_minor'] ?? 0)) / 100, 2, '.', ''),
                    $row['currency'] ?? 'PKR',
                    ($row['anonymous'] ?? 0) ? 'Anonymous' : ($row['donor_name'] ?? ''),
                    ($row['anonymous'] ?? 0) ? '' : ($row['donor_email'] ?? ''),
                    $row['fundraiser_title'] ?? '',
                    $row['provider_transaction_id'] ?? '',
                    $row['environment'] ?? '',
                ]
            )) . "\n";
        }

        return Response::text($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="almarah-donations-' . gmdate('Y-m-d') . '.csv"',
        ]);
    }

    /** Raw provider traffic, for debugging a stuck payment. */
    public function callbacks(Request $request): Response
    {
        $gateway = (string) $request->input('gateway', '');

        return $this->render('admin/donations/callbacks', [
            'pageTitle' => 'Gateway callbacks',
            'callbacks' => $this->payments->recentCallbacks(80),
            'gateway'   => $gateway,
        ], 'layouts/admin');
    }
}
