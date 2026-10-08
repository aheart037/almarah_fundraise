<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Database\Migrator;
use App\Repositories\UserRepository;
use Throwable;

/**
 * First-time installation, driven from the browser.
 *
 * Hosting accounts with limited access often have no terminal and no Composer
 * CLI, so every step that `bin/console` performs on a server is available here
 * as a service the setup page can call: apply the schema, load the reference
 * data, create the first administrator, and then lock the installer.
 *
 * Safety rules:
 *  - the installer only runs while no administrator exists and no lock file is
 *    present, so it switches itself off for good the moment an installation
 *    succeeds — there is no key to fetch, read or lose;
 *  - a second, optional lock can be set in config.php ('setup_lock'), for the
 *    owner who has to leave the site reachable before they can finish;
 *  - it never touches an already-installed installation;
 *  - it holds no credentials and writes no secrets other than the admin's
 *    password hash.
 */
final class InstallerService
{
    /**
     * Reference data every installation needs, in dependency order.
     *
     * Demo content (DemoSeeder) is deliberately absent: a live site must not
     * contain sample fundraisers and donations. It remains available through
     * `php bin/console db:seed Demo` on a development machine.
     */
    public const REFERENCE_SEEDERS = [
        'RoleSeeder',
        'CategorySeeder',
        'CampaignSeeder',
        'GatewaySeeder',
        'EmailTemplateSeeder',
        'ContentSeeder',
        'FaqSeeder',
        'SettingSeeder',
    ];

    public function __construct(private Database $db, private Logger $logger)
    {
    }

    // ------------------------------------------------------------------ paths

    public function lockFile(): string
    {
        return app()->basePath('storage/app/installed.lock');
    }

    // ------------------------------------------------------------------ state

    /** True once an installation has been completed on this deployment. */
    public function isInstalled(): bool
    {
        return is_file($this->lockFile());
    }

    /**
     * Whether an administrator already exists.
     *
     * Used as a second lock: even if the lock file is deleted or the storage
     * directory is replaced, an installation with a super administrator can
     * never be re-run through the browser.
     */
    public function hasAdministrator(): bool
    {
        try {
            $count = $this->db->int(
                'SELECT COUNT(*) FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id
                 JOIN roles r ON r.id = ur.role_id
                 WHERE r.name IN (:a, :b)',
                ['a' => 'admin', 'b' => 'super_admin']
            );
        } catch (Throwable) {
            // Table missing or database unreachable: not installed yet.
            return false;
        }

        return $count > 0;
    }

    /** Refuse to install twice, on either lock. */
    public function isLocked(): bool
    {
        return $this->isInstalled() || $this->hasAdministrator();
    }

    /**
     * @return array{ok:bool, error:string, version:string}
     */
    public function databaseStatus(): array
    {
        try {
            $version = (string) $this->db->scalar('SELECT VERSION()');
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $this->friendlyDbError($e), 'version' => ''];
        }

        return ['ok' => true, 'error' => '', 'version' => $version];
    }

    /** Turns a PDO failure into something a site owner can act on. */
    private function friendlyDbError(Throwable $e): string
    {
        // Database::pdo() wraps the driver error in a generic RuntimeException
        // so no credential can leak through a stack trace. The driver's own
        // message (which names the user and the database, never the password)
        // is what makes a failed setup diagnosable, so it is read back here.
        $message = $e->getMessage();
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            $message .= ' ' . $cause->getMessage();
        }

        return match (true) {
            str_contains($message, '1045') => 'The database username or password is not correct. Check db_user and db_pass in config.php. Remember that on cPanel both include your account name, e.g. almarf1_almarah.',
            str_contains($message, '1044') => 'That database user is not allowed to use this database. In cPanel > MySQL Databases, use "Add User To Database" and grant ALL PRIVILEGES.',
            str_contains($message, '1049') => 'That database does not exist. Check the spelling of db_name in config.php — it must include the cPanel account prefix.',
            str_contains($message, '2002'), str_contains($message, '2003') => 'The database server could not be reached. Try changing db_host in config.php from localhost to 127.0.0.1, or the other way round.',
            str_contains($message, 'Access denied') => 'The database refused the connection. Check db_user and db_pass in config.php.',
            // 1130: the credentials are right but the server refuses this host.
            str_contains($message, '1130'), str_contains($message, 'is not allowed to connect') => 'The database user exists but is not allowed to connect from this server. In cPanel > MySQL Databases, recreate the user (or ask your host to allow the connection).',
            // Anything else: keep the owner's own words out of it and show the
            // driver's, which is the part that names the actual problem.
            default => 'Could not connect to the database. MySQL said: ' . $this->driverDetail($e),
        };
    }

    /**
     * The innermost driver message, trimmed to one line. Used for errors that
     * do not match a known case: without it the setup page can only say
     * "connection failed", which is the least useful sentence on it.
     */
    private function driverDetail(Throwable $e): string
    {
        $innermost = $e;
        while ($innermost->getPrevious() !== null) {
            $innermost = $innermost->getPrevious();
        }

        $detail = trim(preg_replace('/\s+/', ' ', $innermost->getMessage()) ?? '');

        return $detail === '' ? 'no detail available' : mb_substr($detail, 0, 300);
    }

    /** @return array{applied:int, pending:array<int,string>} */
    public function migrationStatus(): array
    {
        try {
            $migrator = $this->migrator();

            return [
                'applied' => count($migrator->applied()),
                'pending' => $migrator->pending(),
            ];
        } catch (Throwable) {
            // The database is not reachable, so we cannot tell what is already
            // applied; report the files we ship so the setup page can still be
            // rendered. files() never touches the database.
            return ['applied' => 0, 'pending' => $this->migrator()->files()];
        }
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->db, app()->basePath('database/migrations'), $this->logger);
    }

    // ------------------------------------------------------------ setup lock

    /**
     * The optional setup lock word from config.php.
     *
     * Installation deliberately needs nothing that can be lost: the owner
     * fills in three database lines, opens /setup and clicks Install. The
     * installer protects itself by closing permanently after the first
     * successful run, and it refuses to run at all once an administrator
     * exists — so the only window in which a stranger could install the site
     * is the few minutes between uploading it and finishing the form.
     *
     * An owner who cannot use that window (an already-public domain, a
     * maintenance window that is hours away) can type any word into
     * 'setup_lock' in config.php to close it: the setup form then requires
     * that same word. Empty — the default — means no word is asked for.
     */
    public function lockWord(): string
    {
        return trim((string) \App\Core\Config::get('app.setup_lock', ''));
    }

    /**
     * True when the supplied word satisfies the optional lock.
     *
     * With no word configured this is always true, which is what makes the
     * default installation a single button press.
     */
    public function lockWordMatches(string $supplied): bool
    {
        $expected = $this->lockWord();

        if ($expected === '') {
            return true;
        }

        return hash_equals($expected, trim($supplied));
    }

    // ------------------------------------------------------------- installing

    /**
     * Applies the schema and loads the reference data.
     *
     * @return array{migrations:array<int,string>, seeders:array<int,string>}
     */
    public function install(): array
    {
        $migrations = $this->migrator()->run();

        $seeders = [];
        foreach (self::REFERENCE_SEEDERS as $name) {
            if ($this->runSeeder($name)) {
                $seeders[] = $name;
            }
        }

        $this->logger->info('Installation completed', [
            'migrations' => count($migrations),
            'seeders'    => count($seeders),
        ]);

        return ['migrations' => $migrations, 'seeders' => $seeders];
    }

    private function runSeeder(string $name): bool
    {
        $class = 'Database\\Seeders\\' . $name;
        $file = app()->basePath('database/seeders/' . $name . '.php');

        if (!is_file($file)) {
            return false;
        }

        require_once $file;

        if (!class_exists($class)) {
            return false;
        }

        $instance = new $class($this->db, $this->logger);

        if (method_exists($instance, 'run')) {
            $instance->run();
            return true;
        }

        return false;
    }

    /**
     * Creates the first administrator.
     *
     * @param array<string,string> $data first_name, last_name, email, password
     * @return array{ok:bool, error:string, userId:int}
     */
    public function createAdministrator(array $data): array
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $email = mb_strtolower(trim($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($firstName === '') {
            return ['ok' => false, 'error' => 'Please enter your first name.', 'userId' => 0];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'That does not look like a valid email address.', 'userId' => 0];
        }

        if (mb_strlen($password) < 10) {
            return ['ok' => false, 'error' => 'The password must be at least 10 characters long.', 'userId' => 0];
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return ['ok' => false, 'error' => 'The password must contain at least one letter and one number.', 'userId' => 0];
        }

        /** @var UserRepository $users */
        $users = app(UserRepository::class);

        if ($users->findByEmail($email) !== null) {
            return ['ok' => false, 'error' => 'An account with that email address already exists. Use a different address, or sign in instead.', 'userId' => 0];
        }

        $userId = $users->create([
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $email,
            'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
            'status'            => 'active',
            'email_verified_at' => gmdate('Y-m-d H:i:s'),
        ]);

        // Both roles, exactly as `bin/console make:admin` does: admin to
        // moderate, super_admin to manage credentials and roles.
        $users->assignRole($userId, 'admin');
        $users->assignRole($userId, 'super_admin');

        $this->logger->security('Super administrator created via setup page', ['user_id' => $userId]);

        return ['ok' => true, 'error' => '', 'userId' => $userId];
    }

    /**
     * Locks the installer for good.
     *
     * Called only after a successful installation, so a half-finished install
     * can still be retried.
     */
    public function complete(): void
    {
        $lock = $this->lockFile();
        $directory = dirname($lock);

        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents($lock, gmdate('c') . " installation completed\n", LOCK_EX);
        @chmod($lock, 0644);

        $this->logger->info('Installer locked', ['lock' => $lock]);
    }

    /** Directories the application must be able to write to. */
    public function writablePaths(): array
    {
        $paths = [
            app()->basePath('storage'),
            app()->basePath('storage/logs'),
            app()->basePath('storage/app'),
            app()->basePath('storage/cache'),
        ];

        // Resolved through Paths so a public_path that is really a web address
        // is ignored here exactly as it is everywhere else.
        $paths[] = \App\Core\Paths::publicPath(app()->basePath()) . '/uploads';

        $result = [];
        foreach ($paths as $path) {
            $result[$path] = is_dir($path) ? is_writable($path) : false;
        }

        return $result;
    }
}
