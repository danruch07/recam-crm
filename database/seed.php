<?php
/**
 * Datos iniciales: usuarios de acceso y algunos clientes/preferencias de ejemplo
 * Los datos de ejemplo se crean usando los mismos repositorios que la
 * aplicación, también generan su historial
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Database;
use Recam\Repository\ClientRepository;
use Recam\Repository\PreferenceRepository;

// Credenciales de demostración
$users = [
    ['admin',    getenv('ADMIN_PASSWORD') ?: 'Admin#2026',    'Administrador RECAM', 'admin'],
    ['operador', getenv('USER_PASSWORD')  ?: 'Operador#2026', 'Operador de ventas',  'user'],
];

$pdo = Database::pdo();

// --- Usuarios ---
$ids = [];
foreach ($users as [$username, $password, $fullName, $role]) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $id = $stmt->fetchColumn();

    if ($id === false) {
        $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, ?)'
        )->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $role, now()]);
        $id = (int) $pdo->lastInsertId();
        echo "Usuario creado: $username ($role)\n";
    } else {
        echo "Usuario ya existía: $username\n";
    }
    $ids[$username] = (int) $id;
}

$clients = new ClientRepository();
$prefs   = new PreferenceRepository();

if ($clients->countByState(false) + $clients->countByState(true) > 0) {
    echo "Ya existen clientes; no se crean datos de ejemplo.\n";
    exit(0);
}

$actor = $ids['admin'];
$demo = [
    [
        ['name' => 'Laura Méndez', 'company' => 'Trofeos y Regalos Méndez', 'email' => 'laura@trofeosmendez.example', 'phone' => '712 123 4567', 'city' => 'Jocotitlán', 'notes' => 'Compra por volumen en temporada escolar.'],
        [
            ['material', 'Acrílico transparente 3 mm', 'Siempre pide placas de acrílico transparente para trofeos.', 3],
            ['acabado', 'Grabado en bajo relieve', 'Prefiere acabado mate, sin pulido.', 2],
            ['contacto', 'WhatsApp por la mañana', 'Responde mejor entre 9:00 y 12:00.', 1],
        ],
    ],
    [
        ['name' => 'Carlos Ramírez', 'company' => 'Muebles Ramírez', 'email' => 'carlos@mueblesramirez.example', 'phone' => '722 987 6543', 'city' => 'Toluca', 'notes' => null],
        [
            ['material', 'MDF 6 mm', 'Corte de piezas decorativas para mobiliario.', 3],
            ['entrega', 'Entrega en taller', 'Recoge personalmente los viernes.', 2],
            ['facturacion', 'Factura inmediata', 'Requiere CFDI el mismo día de la entrega.', 3],
        ],
    ],
    [
        ['name' => 'Sofía Villegas', 'company' => null, 'email' => 'sofia.villegas@example.com', 'phone' => '55 1234 5678', 'city' => 'Ciudad de México', 'notes' => 'Regalos personalizados.'],
        [
            ['material', 'Madera de pino', 'Llaveros y portavasos grabados con nombres.', 2],
            ['servicio', 'Grabado personalizado', 'Diseños con tipografía manuscrita.', 2],
        ],
    ],
    [
        ['name' => 'Industrias Delta', 'company' => 'Industrias Delta S.A. de C.V.', 'email' => 'compras@delta.example', 'phone' => '722 555 0101', 'city' => 'Lerma', 'notes' => 'Cuenta empresarial. Pedir orden de compra.'],
        [
            ['material', 'Acero inoxidable', 'Marcado de placas de identificación.', 3],
            ['facturacion', 'Pago a 30 días', 'Crédito autorizado con orden de compra.', 2],
        ],
    ],
];

foreach ($demo as [$client, $clientPrefs]) {
    $clientId = $clients->create($client, $actor);
    foreach ($clientPrefs as [$category, $title, $detail, $priority]) {
        $prefs->create([
            'client_id' => $clientId,
            'category'  => $category,
            'title'     => $title,
            'detail'    => $detail,
            'priority'  => $priority,
        ], $actor);
    }
}

echo "Datos de ejemplo creados: " . count($demo) . " clientes con sus preferencias.\n";
