<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';

        if (
            !is_string($token) ||
            empty($_SESSION['_csrf']) ||
            !hash_equals($_SESSION['_csrf'], $token)
        ) {
            http_response_code(419);
            exit('Session expirée ou formulaire invalide. Recharge la page.');
        }
    }

    public static function escape(string|null $value): string
    {
        return htmlspecialchars(
            $value ?? '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::currentUser()) {
            header('Location: ?r=login');
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        if (!in_array($_SESSION['user']['role'], $roles, true)) {
            http_response_code(403);
            exit('Accès interdit.');
        }
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        unset($_SESSION['_csrf']);
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
    }
}
