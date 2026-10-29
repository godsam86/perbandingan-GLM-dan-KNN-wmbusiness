<?php
/**
 * Autentikasi, session, dan proteksi halaman.
 */
class Auth
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function check()
    {
        self::start();
        return !empty($_SESSION['id_user']);
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function attempt($username, $password)
    {
        self::start();
        $user = Db::fetchOne(
            'SELECT id_user, username, password, nama_lengkap, role
             FROM users WHERE username = ? LIMIT 1',
            [$username]
        );
        if (!$user) return false;
        if (!password_verify($password, $user['password'])) return false;

        session_regenerate_id(true);
        $_SESSION['id_user']      = (int) $user['id_user'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role']         = $user['role'];
        return true;
    }

    public static function logout()
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function user()
    {
        self::start();
        return [
            'id_user'      => $_SESSION['id_user'] ?? null,
            'username'     => $_SESSION['username'] ?? null,
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? null,
            'role'         => $_SESSION['role'] ?? null,
        ];
    }
}