<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

/**
 * Base controller with view rendering, redirects and flash messaging.
 */
abstract class Controller
{
    public function __construct(
        protected View $view,
        protected Session $session,
        protected Csrf $csrf,
        protected AuthService $auth
    ) {
    }

    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/public', int $status = 200): Response
    {
        $this->shareGlobals();

        return Response::html(
            $this->view->render($view, $data, $layout),
            $status
        );
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    /** Redirect back to the referring page, but only within this application. */
    protected function back(string $fallback = '/'): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($referer !== '' && $host !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            // Only same-host redirects are followed: prevents open redirects.
            if (is_string($refererHost) && strcasecmp($refererHost, $host) === 0) {
                return $this->redirect($referer);
            }
        }

        return $this->redirect($fallback);
    }

    protected function flashSuccess(string $message): void
    {
        $this->session->flash('success', $message);
    }

    protected function flashError(string $message): void
    {
        $this->session->flash('error', $message);
    }

    protected function flashWarning(string $message): void
    {
        $this->session->flash('warning', $message);
    }

    /**
     * Flash validation state so the form can be redisplayed with the user's
     * input and inline errors.
     *
     * @param array<string,string> $errors
     * @param array<string,mixed>  $old
     */
    protected function flashFormState(array $errors, array $old): Response
    {
        $this->session->flash('_errors', $errors);
        $this->session->flash('_old', $this->stripSensitive($old));
        return $this->back();
    }

    /** Never echo passwords or tokens back into a form. */
    protected function stripSensitive(array $data): array
    {
        foreach (['password', 'password_confirmation', 'current_password', 'new_password', '_token', 'token'] as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    protected function shareGlobals(): void
    {
        $user = $this->auth->user();

        $this->view->share('authUser', $user);
        $this->view->share('isAdmin', $this->auth->isAdmin());
        $this->view->share('currentUserRoles', $this->auth->roles());
    }

    protected function isPost(Request $request): bool
    {
        return $request->isPost();
    }
}
