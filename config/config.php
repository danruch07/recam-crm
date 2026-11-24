<?php
/**
 * Configuración central de la aplicación.
 * Los valores salen de variables de entorno con valores por defecto para desarrollo local
 */

declare(strict_types=1);

/** Lee una variable de entorno; si no existe devuelve el valor por defecto */
$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? $default : $value;
};

$config = [
    'app' => [
        'name'                => 'RECAM LASER CRM',
        'timezone'            => 'America/Mexico_City',
        // En producción sería false para no mostrar detalles de errores al usuario
        'debug'               => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'session_idle_timeout' => 1800,
        'per_page'            => 10,
    ],

    'db' => [
        // DSN completo opcional (para pruebas con otro motor). Si es null se arma el de MySQL
        'dsn'     => $env('DB_DSN') !== '' ? $env('DB_DSN') : null,
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME', 'recam_crm'),
        'user'    => $env('DB_USER', 'root'),
        'pass'    => $env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'security' => [
        // Intentos fallidos consecutivos antes de bloquear temporalmente la cuenta
        'max_failed_attempts' => 5,
        'lock_minutes'        => 15,
    ],

    'api' => [
        // Token de Banxico y URL de ExchangeRate-API como respaldo.
        'banxico_token' => $env('eba595664ac62c2b43e427b2a4931861663c0399a070ff056fd12d89ac87f742'),
        'fx_secondary' => 'https://open.er-api.com/v6/latest/USD',
        'timeout'      => 5,      // segundos máximos por petición
        'cache_ttl'    => 3600,   // el tipo de cambio se refresca cada hora
    ],

    'company' => [
        'name'    => 'RECAM LASER',
        'tagline' => 'Corte y grabado láser',
    ],
];

// Sobrescritura local opcional
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
