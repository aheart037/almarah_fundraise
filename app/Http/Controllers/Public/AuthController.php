<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\AuthService;
use InvalidArgumentException;

final class AuthController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private Logger $logger
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    // ------------------------------------------------------------------ login

    public function showLogin(Request $request): Response
    {
        return $this->render('public/auth/login', [
            'pageTitle'  => 'Sign in',
            'intended'   => (string) $request->input('next', ''),
            'resetDone'  => $this->session->get('reset_done', false),
        ], 'layouts/public');
    }

    public function login(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|max:190',
            'password' => 'required|string|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $result = $this->auth->attempt(
            (string) $request->input('email'),
            (string) $request->input('password'),
            $request->ip()
        );

        if (!$result['ok']) {
            $this->logger->security('Failed sign-in attempt', [
                'email_hash' => hash('sha256', mb_strtolower((string) $request->input('email'))),
                'ip'         => $request->ip(),
            ]);
            $this->session->flash('_old', ['email' => (string) $request->input('email')]);
            $this->flashError($result['message']);
            return $this->redirect('/login');
        }

        $this->flashSuccess('Welcome back, ' . $this->auth->fullName() . '.');

        // Only same-site destinations are accepted: prevents open redirects.
        $next = (string) $request->input('next', '');
        if ($next !== '' && $this->isSafePath($next)) {
            return $this->redirect($next);
        }

        $intended = (string) $this->session->pull('_intended', '');

        return $this->redirect($intended !== '' && $this->isSafePath($intended) ? $intended : $this->auth->homeUrl());
    }

    public function logout(): Response
    {
        $this->auth->logout();
        $this->session->regenerate();

        $this->flashSuccess('You have been signed out.');
        return $this->redirect('/');
    }

    // --------------------------------------------------------------- register

    public function showRegister(Request $request): Response
    {
        return $this->render('public/auth/register', [
            'pageTitle' => 'Start a fundraiser',
            'next'      => (string) $request->input('next', ''),
        ], 'layouts/public');
    }

    public function register(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|min:3|max:60',
            'last_name'  => 'required|string|min:2|max:60',
            'email'      => 'required|email|max:190',
            'phone'      => 'nullable|string|max:30',
            'password'   => 'required|string|min:8|max:200',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        try {
            $result = $this->auth->register([
                'first_name'       => (string) $request->input('first_name'),
                'last_name'        => (string) $request->input('last_name'),
                'email'            => (string) $request->input('email'),
                'phone'            => $request->input('phone'),
                'password'         => (string) $request->input('password'),
                'marketing_opt_in' => (bool) $request->input('marketing_opt_in'),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->session->flash('_old', $this->stripSensitive($request->all()));
            $this->flashError($e->getMessage());
            return $this->redirect('/register');
        }

        // Sign the new fundraiser in straight away, then send them to verify.
        $user = app(\App\Repositories\UserRepository::class)->find($result['user_id']);
        if ($user !== null) {
            $this->auth->login($user, $request->ip());
        }

        $this->flashSuccess('Your account is ready. Check your inbox to verify your email address.');
        return $this->redirect('/dashboard');
    }

    // ---------------------------------------------------------- password reset

    public function showForgot(): Response
    {
        return $this->render('public/auth/forgot-password', [
            'pageTitle' => 'Reset your password',
        ], 'layouts/public');
    }

    public function sendReset(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:190',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $this->auth->sendPasswordReset((string) $request->input('email'));

        // Same message whether or not the address exists.
        $this->flashSuccess('If that email address is registered, a password reset link is on its way.');
        return $this->redirect('/forgot-password');
    }

    public function showReset(Request $request): Response
    {
        return $this->render('public/auth/reset-password', [
            'pageTitle' => 'Choose a new password',
            'token'     => (string) $request->routeParam('token', ''),
        ], 'layouts/public');
    }

    public function reset(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'token'    => 'required|string|min:10|max:200',
            'password' => 'required|string|min:8|max:200',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            $token = (string) $request->input('token');
            $this->session->flash('_errors', $validator->errors());
            return $this->redirect('/reset-password/' . rawurlencode($token));
        }

        $result = $this->auth->resetPassword(
            (string) $request->input('token'),
            (string) $request->input('password')
        );

        if (!$result['ok']) {
            $this->flashError($result['message']);
            return $this->redirect('/reset-password/' . rawurlencode((string) $request->input('token')));
        }

        $this->session->flash('reset_done', true);
        $this->flashSuccess($result['message']);
        return $this->redirect('/login');
    }

    // ------------------------------------------------------------- verification

    public function verify(Request $request): Response
    {
        if ($this->auth->verifyEmail((string) $request->routeParam('token', ''))) {
            $this->flashSuccess('Thank you — your email address is now verified.');
        } else {
            $this->flashError('That verification link is invalid or has expired. Request a new one below.');
        }

        return $this->redirect($this->auth->check() ? '/dashboard' : '/verify-email');
    }

    public function verifyNotice(): Response
    {
        if (!$this->auth->check()) {
            $this->flashError('Please sign in to verify your email address.');
            return $this->redirect('/login');
        }

        if ($this->auth->isVerified()) {
            return $this->redirect('/dashboard');
        }

        return $this->render('public/auth/verify-email', [
            'pageTitle' => 'Verify your email',
            'user'      => $this->auth->user(),
        ], 'layouts/public');
    }

    public function resendVerification(): Response
    {
        if (!$this->auth->check()) {
            return $this->redirect('/login');
        }

        $user = $this->auth->user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        if ($this->auth->isVerified()) {
            $this->flashSuccess('Your email address is already verified.');
            return $this->redirect('/dashboard');
        }

        $this->auth->sendVerificationEmail($user);
        $this->flashSuccess('We have sent a fresh verification link to ' . (string) $user['email'] . '.');

        return $this->redirect('/verify-email');
    }

    /** A relative path on this site only — no scheme, no host, no protocol-relative. */
    private function isSafePath(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }
        if (str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return false;
        }
        return (bool) preg_match('#^/[a-zA-Z0-9\-_/\.\?=&%\+]*$#', $path);
    }
}
