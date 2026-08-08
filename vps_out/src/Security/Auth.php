<?php

declare(strict_types=1);

namespace MangaNexus\Security;

use MangaNexus\Database\Database;
use MangaNexus\Logging\Logger;

class Auth
{
    private static int $maxAttempts = 5;
    private static int $lockoutTime = 900; // 15 minutes

    /**
     * Start session if needed safely.
     */
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    /**
     * Check if the user is currently locked out.
     */
    public static function isLockedOut(): bool
    {
        self::startSession();

        if (isset($_SESSION['lockout_until']) && $_SESSION['lockout_until'] > time()) {
            return true;
        }

        // Lockout expired, reset attempts
        if (isset($_SESSION['lockout_until'])) {
            unset($_SESSION['lockout_until']);
            unset($_SESSION['failed_logins']);
        }

        return false;
    }

    /**
     * Get remaining lockout duration in seconds.
     */
    public static function getLockoutRemaining(): int
    {
        self::startSession();

        if (isset($_SESSION['lockout_until'])) {
            $remaining = $_SESSION['lockout_until'] - time();
            return max(0, $remaining);
        }

        return 0;
    }

    /**
     * Authenticate admin credentials and migrate plain password to hashed representation if detected.
     */
    public static function verify(string $username, string $password): bool
    {
        if (self::isLockedOut()) {
            Logger::warning("Login attempt during lockout for user: $username");
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT admin_username, admin_password FROM site_settings WHERE id = 'global'");
        $stmt->execute();
        $settings = $stmt->fetch();

        if (!$settings) {
            Logger::error("Global site settings row is missing in database.");
            return false;
        }

        $dbUsername = $settings['admin_username'];
        $dbPasswordHash = $settings['admin_password'];

        // Validate username
        if ($username !== $dbUsername) {
            self::registerFailedAttempt($username);
            return false;
        }

        // Validate password (plain match fallback or password_verify)
        $info = password_get_info($dbPasswordHash);
        $isHashed = ($info['algo'] !== null && $info['algo'] !== 0);

        $valid = false;
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        if ($isHashed) {
            $valid = password_verify($password, $dbPasswordHash);
            if ($valid && password_needs_rehash($dbPasswordHash, $algo)) {
                $newHash = password_hash($password, $algo);
                $updateStmt = $pdo->prepare("UPDATE site_settings SET admin_password = ? WHERE id = 'global'");
                $updateStmt->execute([$newHash]);
                Logger::info("Successfully rehashed admin password to updated algorithm.");
            }
        } else {
            // Migrating plain text password path
            $valid = ($password === $dbPasswordHash);
            if ($valid) {
                // Migrate to hashed password immediately
                $newHash = password_hash($password, $algo);
                $updateStmt = $pdo->prepare("UPDATE site_settings SET admin_password = ? WHERE id = 'global'");
                $updateStmt->execute([$newHash]);
                Logger::info("Successfully migrated plain text admin password to secure hash.");
            }
        }

        if ($valid) {
            // Successful login, clear failed attempts
            self::startSession();
            unset($_SESSION['failed_logins']);
            unset($_SESSION['lockout_until']);
            
            // Regenerate session ID to avoid session fixation
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            Csrf::regenerate();
            
            $_SESSION['admin_auth'] = true;
            Logger::info("Successful admin login for user: $username");
            return true;
        }

        self::registerFailedAttempt($username);
        return false;
    }

    /**
     * Register a failed login attempt.
     */
    private static function registerFailedAttempt(string $username): void
    {
        self::startSession();

        if (!isset($_SESSION['failed_logins'])) {
            $_SESSION['failed_logins'] = 0;
        }

        $_SESSION['failed_logins']++;
        Logger::warning("Failed login attempt for user: $username. Attempt count: " . $_SESSION['failed_logins']);

        if ($_SESSION['failed_logins'] >= self::$maxAttempts) {
            $_SESSION['lockout_until'] = time() + self::$lockoutTime;
            Logger::warning("User login locked out until " . date('Y-m-d H:i:s', $_SESSION['lockout_until']));
            session_write_close();
        }
    }

    /* ── Visitor Registration & Authentication Methods ── */

    /**
     * Register a new general visitor user.
     * Returns true on success, or a string describing validation/insertion failure.
     */
    public static function registerVisitor(string $email, string $username, string $password): string|bool
    {
        $email = trim($email);
        $username = trim($username);

        if (empty($email) || empty($username) || empty($password)) {
            return "All fields are required.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email address formatting.";
        }

        if (strlen($password) < 8) {
            return "Password must be at least 8 characters long.";
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
        }

        $pdo = Database::getConnection();

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            return "This email address is already registered.";
        }

        // Generate visitor UUID
        $userId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hashedPassword = password_hash($password, $algo);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (id, email, password, username) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $email, $hashedPassword, $username]);
            Logger::info("New visitor registered successfully: $email ($username)");
            return true;
        } catch (\PDOException $e) {
            Logger::error("Failed to register visitor: " . $e->getMessage());
            return "Database failure: Failed to register user.";
        }
    }

    /**
     * Validate and log in a general visitor.
     */
    public static function verifyVisitor(string $email, string $password): bool
    {
        $email = trim($email);

        if (empty($email) || empty($password)) {
            return false;
        }

        if (self::isLockedOut()) {
            Logger::warning("Visitor login attempt during lockout for email: $email");
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            self::registerFailedAttempt($email);
            return false;
        }

        if (password_verify($password, $user['password'])) {
            self::startSession();

            // Store visitor user credentials in session
            $_SESSION['visitor_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ];

            unset($_SESSION['failed_logins']);
            unset($_SESSION['lockout_until']);

            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            if (password_needs_rehash($user['password'], $algo)) {
                $newHash = password_hash($password, $algo);
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$newHash, $user['id']]);
                Logger::info("Successfully rehashed visitor password to updated algorithm.");
            }

            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            Csrf::regenerate();

            Logger::info("Visitor logged in successfully: " . $user['email']);
            return true;
        }

        self::registerFailedAttempt($email);
        return false;
    }

    /**
     * Check if a general visitor is logged in.
     */
    public static function isVisitorLoggedIn(): bool
    {
        self::startSession();
        return isset($_SESSION['visitor_user']) && !empty($_SESSION['visitor_user']['id']);
    }

    /**
     * Retrieve current logged-in visitor user details.
     */
    public static function getVisitorUser(): ?array
    {
        self::startSession();
        return $_SESSION['visitor_user'] ?? null;
    }

    /**
     * Log out the current visitor user.
     */
    public static function logoutVisitor(): void
    {
        self::startSession();
        unset($_SESSION['visitor_user']);
        Csrf::regenerate();
        Logger::info("Visitor logged out successfully.");
    }
}
