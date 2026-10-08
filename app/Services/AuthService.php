<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Str;
use App\Mail\Mailer;
use App\Repositories\UserRepository;
use InvalidArgumentException;

/**
 * Authentication: registration, login throttling, email verification and
 * password reset. Password hashing uses password_hash(); session ids are
 * regenerated on every privilege change.
 */
final class AuthService
{
    private ?array $cachedUser = null;
    private bool $resolved = false;

    private const SELF_SERVICE_ROLES = ['fundraiser', 'donor'];

    public function __construct(
        private UserRepository $users,
        private Database $db,
        private Session $session,
        private RateLimiter $rateLimiter,
        private Mailer $mailer,
        private AuditService $audit,
        private Logger $logger
    ) {
    }

    // ------------------------------------------------------------ registration

    /**
     * @param array<string,mixed> $data
     * @return array{user_id:int, verification_sent:bool}
     */
    public function register(array $data): array
    {
        $email = mb_strtolower(trim((string) $data['email']));

        if ($this->users->findByEmail($email) !== null) {
            throw new InvalidArgumentException('An account with that email address already exists.');
        }

        $userId = $this->db->transaction(function () use ($data, $email): int {
            $id = $this->users->create([
                'first_name'    => trim((string) $data['first_name']),
                'last_name'     => trim((string) $data['last_name']),
                'email'         => $email,
                'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                'phone'         => $data['phone'] ?? null,
                'status'        => 'active',
                'marketing_opt_in' => !empty($data['marketing_opt_in']),
            ]);

            $this->users->assignRole($id, 'fundraiser');

            return $id;
        });

        $this->audit->log('account.registered', 'user', (string) $userId, ['role' => 'fundraiser'], $userId);

        $user = $this->users->find($userId);
        $verificationSent = $user !== null ? $this->sendVerificationEmail($user) : false;

        $this->mailer->accountCreated($email, (string) $data['first_name'], $userId);

        return ['user_id' => $userId, 'verification_sent' => $verificationSent];
    }

    // ------------------------------------------------------------------ login

    /**
     * @return array{ok:bool, message:string, user_id:?int}
     */
    public function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $maxAttempts = (int) Config::get('security.login.max_attempts', 5);
        $decay = (int) Config::get('security.login.decay_minutes', 15);

        $throttleKey = 'login:' . $email . ':' . $ip;

        if ($this->rateLimiter->tooManyAttempts($throttleKey, $maxAttempts)) {
            $minutes = max(1, (int) ceil($this->rateLimiter->availableIn($throttleKey) / 60));

            $this->audit->log('account.login_throttled', 'user', null, ['email_hash' => hash('sha256', $email)]);

            return [
                'ok'      => false,
                'message' => "Too many sign-in attempts. Please try again in {$minutes} minute(s).",
                'user_id' => null,
            ];
        }

        $user = $this->users->findByEmail($email);

        // Always run a hash comparison so that a missing account and a wrong
        // password take a similar amount of time.
        $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $valid = password_verify($password, (string) $hash);

        if ($user === null || !$valid) {
            $this->rateLimiter->hit($throttleKey, $decay);

            $this->audit->log('account.login_failed', 'user', $user['id'] ?? null, [
                'email_hash' => hash('sha256', $email),
            ]);

            return ['ok' => false, 'message' => 'Those credentials do not match our records.', 'user_id' => null];
        }

        if ((string) $user['status'] === 'suspended') {
            $this->audit->log('account.login_suspended', 'user', (string) $user['id']);
            return ['ok' => false, 'message' => 'Your account is suspended. Please contact support.', 'user_id' => (int) $user['id']];
        }

        $this->rateLimiter->clear($throttleKey);
        $this->login($user, $ip);

        return ['ok' => true, 'message' => 'Signed in successfully.', 'user_id' => (int) $user['id']];
    }

    /** @param array<string,mixed> $user */
    public function login(array $user, string $ip): void
    {
        // Prevent session fixation: a fresh id is issued on login.
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->session->set('_auth_time', time());

        $this->users->touchLogin((int) $user['id'], $ip);
        $this->audit->log('account.login', 'user', (string) $user['id'], [], (int) $user['id']);

        $this->cachedUser = null;
        $this->resolved = false;
    }

    public function logout(): void
    {
        $userId = $this->id();

        if ($userId !== null) {
            $this->audit->log('account.logout', 'user', (string) $userId, [], $userId);
        }

        $this->session->destroy();

        $this->cachedUser = null;
        $this->resolved = false;
    }

    // ------------------------------------------------------------- current user

    public function id(): ?int
    {
        $id = $this->session->get('user_id');
        return is_int($id) || is_numeric($id) ? (int) $id : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->cachedUser;
        }

        $this->resolved = true;
        $id = $this->id();

        if ($id === null) {
            $this->cachedUser = null;
            return null;
        }

        $user = $this->users->find($id);
        if ($user === null || (string) $user['status'] === 'suspended') {
            $this->session->forget('user_id');
            $this->cachedUser = null;
            return null;
        }

        $user['roles'] = $this->users->rolesFor($id);
        $this->cachedUser = $user;

        return $this->cachedUser;
    }

    /** @return array<int,string> */
    public function roles(): array
    {
        $user = $this->user();
        return is_array($user['roles'] ?? null) ? $user['roles'] : [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /** @param array<int,string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        if ($roles === []) {
            return true;
        }
        return array_intersect($roles, $this->roles()) !== [];
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isVerified(): bool
    {
        $user = $this->user();
        return $user !== null && !empty($user['email_verified_at']);
    }

    /** Where a signed-in user belongs after login. */
    public function homeUrl(): string
    {
        if ($this->isAdmin()) {
            return '/admin';
        }
        return '/dashboard';
    }

    public function fullName(): string
    {
        $user = $this->user();
        if ($user === null) {
            return '';
        }
        return trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
    }

    // ------------------------------------------------------- email verification

    public function sendVerificationEmail(array $user): bool
    {
        $token = Str::randomToken(32);
        $ttl = (int) Config::get('security.tokens.email_verification_ttl_hours', 48);

        $this->users->createVerificationToken(
            (int) $user['id'],
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + ($ttl * 3600))
        );

        $url = base_url('verify-email/' . $token);

        $this->mailer->emailVerification(
            (string) $user['email'],
            (string) $user['first_name'],
            $url,
            (int) $user['id']
        );

        return true;
    }

    public function verifyEmail(string $token): bool
    {
        $record = $this->users->findValidVerificationToken(hash('sha256', $token));

        if ($record === null) {
            return false;
        }

        $this->users->markEmailVerified((int) $record['user_id']);
        $this->users->consumeVerificationToken((int) $record['id']);

        $this->audit->log('account.email_verified', 'user', (string) $record['user_id'], [], (int) $record['user_id']);

        $this->cachedUser = null;
        $this->resolved = false;

        return true;
    }

    // ------------------------------------------------------------ password reset

    public function sendPasswordReset(string $email): bool
    {
        $user = $this->users->findByEmail($email);

        // Always report success to avoid disclosing which emails are registered.
        if ($user === null) {
            $this->audit->log('account.password_reset_unknown_email', 'user', null, [
                'email_hash' => hash('sha256', mb_strtolower($email)),
            ]);
            return true;
        }

        $token = Str::randomToken(32);
        $ttl = (int) Config::get('security.tokens.password_reset_ttl_hours', 2);

        $this->users->createPasswordResetToken(
            (int) $user['id'],
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + ($ttl * 3600))
        );

        $this->mailer->passwordReset(
            (string) $user['email'],
            (string) $user['first_name'],
            base_url('reset-password/' . $token),
            (int) $user['id']
        );

        $this->audit->log('account.password_reset_requested', 'user', (string) $user['id'], [], (int) $user['id']);

        return true;
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        if (mb_strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'Your new password must be at least 8 characters.'];
        }

        $record = $this->users->findValidResetToken(hash('sha256', $token));

        if ($record === null) {
            return ['ok' => false, 'message' => 'That password reset link is invalid or has expired.'];
        }

        $userId = (int) $record['user_id'];

        $this->users->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);

        $this->users->consumeResetToken((int) $record['id']);

        $this->audit->log('account.password_reset_completed', 'user', (string) $userId, [], $userId);

        $user = $this->users->find($userId);
        if ($user !== null) {
            $this->mailer->passwordChanged(
                (string) $user['email'],
                (string) $user['first_name'],
                $userId,
                gmdate('j F Y \a\t H:i') . ' UTC'
            );
        }

        // Invalidate any existing session for safety.
        $this->session->regenerate();

        return ['ok' => true, 'message' => 'Your password has been changed. You can now sign in.'];
    }

    public function updatePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->users->find($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Account not found.'];
        }

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            return ['ok' => false, 'message' => 'Your current password is not correct.'];
        }

        if (mb_strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'Your new password must be at least 8 characters.'];
        }

        $this->users->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);

        $this->audit->log('account.password_changed', 'user', (string) $userId, [], $userId);

        $this->mailer->passwordChanged(
            (string) $user['email'],
            (string) $user['first_name'],
            $userId,
            gmdate('j F Y \a\t H:i') . ' UTC'
        );

        return ['ok' => true, 'message' => 'Your password has been updated.'];
    }

    /** @param array<string,mixed> $data */
    public function updateProfile(int $userId, array $data): void
    {
        $payload = [];

        foreach (['first_name', 'last_name', 'phone', 'avatar_path'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('marketing_opt_in', $data)) {
            $payload['marketing_opt_in'] = !empty($data['marketing_opt_in']) ? 1 : 0;
        }

        if ($payload !== []) {
            $this->users->update($userId, $payload);
        }

        $this->audit->log('account.profile_updated', 'user', (string) $userId, array_keys($payload), $userId);

        $this->cachedUser = null;
        $this->resolved = false;
    }

    /** True when the role may never be self-assigned through a public form. */
    public function isSelfServiceRole(string $role): bool
    {
        return in_array($role, self::SELF_SERVICE_ROLES, true);
    }
}
