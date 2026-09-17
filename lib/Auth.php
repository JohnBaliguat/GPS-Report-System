<?php
/**
 * Session-based authentication & authorization helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        self::start();
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND status = "active" LIMIT 1');
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            return false;
        }
        $_SESSION['uid']  = (int) $u['id'];
        $_SESSION['role'] = $u['role'];
        $_SESSION['name'] = $u['name'];
        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
        return true;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['uid']);
    }

    public static function id(): int
    {
        self::start();
        return (int) ($_SESSION['uid'] ?? 0);
    }

    public static function role(): string
    {
        self::start();
        return (string) ($_SESSION['role'] ?? '');
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        $stmt = db()->prepare('SELECT id, name, username, email, phone, role, status, last_login, created_at FROM users WHERE id = ?');
        $stmt->execute([self::id()]);
        return $stmt->fetch() ?: null;
    }

    /** Guard a normal page — redirect to login if not authenticated. */
    public static function requirePage(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function requireAdminPage(): void
    {
        self::requirePage();
        if (!self::isAdmin()) {
            header('Location: index.php');
            exit;
        }
    }

    /** Guard a JSON API — emit 401/403 instead of redirecting. */
    public static function requireApi(bool $admin = false): void
    {
        if (!self::check()) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            exit;
        }
        if ($admin && !self::isAdmin()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
            exit;
        }
    }
}
