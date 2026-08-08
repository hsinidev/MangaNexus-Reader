<?php

declare(strict_types=1);

namespace MangaNexus\Security;

class Csrf
{
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
     * Generate a new CSRF token if one does not exist.
     */
    public static function getToken(): string
    {
        self::startSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Generate HTML input tag with CSRF token.
     */
    public static function getField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::getToken()) . '">';
    }

    /**
     * Validate the post request's token matches the session token.
     */
    public static function validate(?string $token): bool
    {
        self::startSession();
        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Regenerate the CSRF token (useful after login/logout to prevent session fixation).
     */
    public static function regenerate(): void
    {
        self::startSession();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
