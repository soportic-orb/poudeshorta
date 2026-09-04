<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const KEY = 'admin_id';
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $admin = Db::first('SELECT * FROM `admins` WHERE `email` = :e AND `active` = 1', ['e' => mb_strtolower($email)]);
        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            return false;
        }
        if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], '`id` = :id', ['id' => $admin['id']]);
        }
        Session::regenerate();
        Session::set(self::KEY, (int) $admin['id']);
        Db::update('admins', ['last_login_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $admin['id']]);
        self::$user = $admin;
        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::forget(self::KEY);
        Session::regenerate();
    }

    public static function id(): ?int
    {
        $id = Session::get(self::KEY);
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $user = Db::first('SELECT * FROM `admins` WHERE `id` = :id AND `active` = 1', ['id' => $id]);
        self::$user = $user ?: null;
        if (self::$user === null) {
            Session::forget(self::KEY);
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function is(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array((string) $user['role'], $roles, true);
    }

    /** Middleware: exigeix sessió d'administrador. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            Session::set('_intended', Request::path());
            Flash::error('Cal iniciar la sessió per accedir al Panell de Gestió.');
            Response::redirect(Url::to('/admin/login'));
        }
    }

    /** Middleware: exigeix rol de propietari o administrador. */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::is('owner', 'admin')) {
            http_response_code(403);
            View::render('admin/error', ['code' => 403, 'message' => 'No teniu permisos per a aquesta acció.'], 'layouts/admin');
            exit;
        }
    }
}
