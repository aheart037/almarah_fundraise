<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fundraiser;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Services\AuthService;

/**
 * Account self-service: profile details, password and email preferences.
 */
final class ProfileController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private UserRepository $users
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function profile(): Response
    {
        return $this->render('fundraiser/profile', [
            'pageTitle' => 'Your profile',
            'user'      => $this->auth->user(),
        ], 'layouts/dashboard');
    }

    public function updateProfile(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|min:3|max:60',
            'last_name'  => 'required|string|min:2|max:60',
            'phone'      => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $this->auth->updateProfile((int) $this->auth->id(), [
            'first_name'       => (string) $request->input('first_name'),
            'last_name'        => (string) $request->input('last_name'),
            'phone'            => $request->input('phone'),
            'marketing_opt_in' => (bool) $request->input('marketing_opt_in'),
        ]);

        $this->flashSuccess('Your profile has been saved.');
        return $this->redirect('/dashboard/profile');
    }

    public function security(): Response
    {
        return $this->render('fundraiser/security', [
            'pageTitle' => 'Password & security',
            'user'      => $this->auth->user(),
        ], 'layouts/dashboard');
    }

    public function updatePassword(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'current_password'      => 'required|string|max:200',
            'password'              => 'required|string|min:8|max:200',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $result = $this->auth->updatePassword(
            (int) $this->auth->id(),
            (string) $request->input('current_password'),
            (string) $request->input('password')
        );

        if (!$result['ok']) {
            $this->flashError($result['message']);
            return $this->redirect('/dashboard/security');
        }

        $this->flashSuccess($result['message']);
        return $this->redirect('/dashboard/security');
    }

    public function emailPreferences(): Response
    {
        $userId = (int) $this->auth->id();

        return $this->render('fundraiser/email-preferences', [
            'pageTitle'    => 'Email preferences',
            'preferences'  => $this->users->emailPreferences($userId),
            'user'         => $this->auth->user(),
        ], 'layouts/dashboard');
    }

    public function updateEmailPreferences(Request $request): Response
    {
        $userId = (int) $this->auth->id();
        $current = $this->users->emailPreferences($userId);

        foreach ($current as $eventKey => $_enabled) {
            // Essential account email can never be switched off.
            if (in_array($eventKey, ['account.email_verification', 'account.password_reset', 'donation.receipt'], true)) {
                continue;
            }

            $this->users->setEmailPreference($userId, (string) $eventKey, (bool) $request->input('pref_' . $eventKey));
        }

        $this->flashSuccess('Your email preferences have been saved.');
        return $this->redirect('/dashboard/email-preferences');
    }
}
