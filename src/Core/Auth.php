<?php
declare(strict_types=1);

namespace Recam\Core;

/**
 * Autenticación y control de acceso.
 *
 * Medidas de seguridad:
 *  - Contraseñas guardadas con password_hash() (bcrypt por defecto, con sal
 *    única por usuario) y verificadas con password_verify(). Nunca en texto plano
 *  - Bloqueo temporal tras varios intentos fallidos (frena fuerza bruta)
 *  - Mensaje de error genérico: no revela si el usuario existe o no
 *  - Cierre automático de sesión por inactividad
 */
final class Auth
{
    /**
     * Hash válido de una contraseña aleatoria. Se usa para que el tiempo de
     * respuesta sea parecido cuando el usuario no existe
     */
    private const DUMMY_HASH = '$2y$10$aIzqeaK.W8seGB/4OjHslOJ5odUNjOiH0iInJ9tZA71AQlIAssTGq';

    private const GENERIC_ERROR =
        'Usuario o contraseña incorrectos, o la cuenta está bloqueada temporalmente.';


    public static function attempt(string $username, string $password): array
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            password_verify($password, self::DUMMY_HASH); // gasta tiempo similar
            Audit::log('LOGIN_FAILED', 'user', null, null, null,
                'Usuario inexistente o inactivo: ' . mb_substr($username, 0, 50), null, mb_substr($username, 0, 50));
            return ['ok' => false, 'error' => self::GENERIC_ERROR];
        }

        // ¿Cuenta bloqueada temporalmente?
        if ($user['locked_until'] !== null && $user['locked_until'] > now()) {
            Audit::log('LOGIN_FAILED', 'user', (int) $user['id'], null, null,
                'Intento con la cuenta bloqueada', (int) $user['id'], $user['username']);
            return ['ok' => false, 'error' => self::GENERIC_ERROR];
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::registerFailure($user);
            return ['ok' => false, 'error' => self::GENERIC_ERROR];
        }

        // --- Login correcto ---
        $newHash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)
            ? password_hash($password, PASSWORD_DEFAULT)
            : $user['password_hash'];

        $pdo->prepare(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = ?, password_hash = ? WHERE id = ?'
        )->execute([now(), $newHash, $user['id']]);

        session_regenerate_id(true); // nuevo ID de sesión tras autenticarse
        $_SESSION['user'] = [
            'id'        => (int) $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
        ];
        $_SESSION['last_activity'] = time();

        Audit::log('LOGIN', 'user', (int) $user['id'], null, null, 'Inicio de sesión');
        return ['ok' => true, 'error' => null];
    }

    /** Suma un intento fallido y bloquea la cuenta al llegar al máximo */
    private static function registerFailure(array $user): void
    {
        $attempts = (int) $user['failed_attempts'] + 1;
        $max      = (int) Config::get('security.max_failed_attempts', 5);
        $lockedUntil = null;

        if ($attempts >= $max) {
            $minutes     = (int) Config::get('security.lock_minutes', 15);
            $lockedUntil = date('Y-m-d H:i:s', time() + $minutes * 60);
            $attempts    = 0; // reinicia el contador; el bloqueo ya está activo
        }

        Database::pdo()
            ->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?')
            ->execute([$attempts, $lockedUntil, $user['id']]);

        Audit::log('LOGIN_FAILED', 'user', (int) $user['id'], null, null,
            $lockedUntil ? "Contraseña incorrecta; cuenta bloqueada hasta $lockedUntil" : 'Contraseña incorrecta',
            (int) $user['id'], $user['username']);
    }

    /** Revisa si hay una sesión válida, también aplica el cierre por inactividad*/
    public static function check(): bool
    {
        if (empty($_SESSION['user'])) {
            return false;
        }
        $idle = (int) Config::get('app.session_idle_timeout', 1800);
        if (time() - (int) ($_SESSION['last_activity'] ?? 0) > $idle) {
            self::destroySession();
            session_start(); // sesión nueva para mostrar el aviso
            flash('warning', 'Tu sesión expiró por inactividad. Inicia sesión de nuevo.');
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /** @return array{id:int,username:string,full_name:string,role:string}|null */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    /** Protege las páginas: si no hay sesión, manda al login */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login.php');
        }
    }

    /** Protege una página solo para administradores */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Acceso denegado: se requiere rol de administrador.');
        }
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['user'])) {
            Audit::log('LOGOUT', 'user', (int) $_SESSION['user']['id'], null, null, 'Cierre de sesión');
        }
        self::destroySession();
    }

    private static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
