<?php


declare(strict_types=1);

use Recam\Core\Config;

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Recam\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/helpers.php';

// Configuración y zona horaria
Config::load(BASE_PATH . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'America/Mexico_City'));

// Errores en producción no se muestran, solo se registran
if (Config::get('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// Sesión segura
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.use_strict_mode', '1');   // rechaza IDs de sesión inventados por un atacante
ini_set('session.use_only_cookies', '1');  // el ID no viaja en la URL
session_set_cookie_params([
    'lifetime' => 0,          // cookie de sesión: se borra al cerrar el navegador
    'path'     => '/',
    'secure'   => $isHttps,   // solo por HTTPS cuando el sitio lo use
    'httponly' => true,       // JavaScript no lee la cookie 
    'samesite' => 'Lax',      // no envía en peticiones cruzadas 
]);
if (PHP_SAPI !== 'cli') {
    session_start();
}

// Cabeceras de seguridad 
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'");
}
