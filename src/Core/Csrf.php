<?php
declare(strict_types=1);

namespace Recam\Core;

/**
 * Protección contra CSRF
 *
 * Cada sesión recibe un token aleatorio. Todos los formularios que modifican
 * datos (POST) lo incluyen como campo oculto y el servidor lo compara con el
 * guardado en sesión
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** HTML del campo oculto para pegar dentro de cualquier <form method="post">*/
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /** Valida el token recibido; si no coincide corta la petición con HTTP 419 */
    public static function verifyOrFail(): void
    {
        $sent = (string) ($_POST['_csrf'] ?? '');
        // hash_equals compara en tiempo constante para evitar ataques de temporizados
        if ($sent === '' || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            exit('Solicitud no válida o sesión expirada. Regresa, recarga la página e inténtalo de nuevo.');
        }
    }
}
