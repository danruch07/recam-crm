<?php
/**
 * Controlador único para acciones de un solo clic que modifican datos *
 *  - Solo acepta POST con token CSRF válido 
 *  - Cualquier usuario autenticado puede eliminar
 *  - Solo un administrador puede restaurar
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Auth;
use Recam\Core\Csrf;
use Recam\Repository\ClientRepository;
use Recam\Repository\PreferenceRepository;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
Csrf::verifyOrFail();

$entity = (string) ($_POST['entity'] ?? '');
$action = (string) ($_POST['action'] ?? '');
$id     = (int) ($_POST['id'] ?? 0);
$back   = safe_return($_POST['return'] ?? null, 'clients.php');

$repos = [
    'client'     => [new ClientRepository(), 'Cliente'],
    'preference' => [new PreferenceRepository(), 'Preferencia'],
];

if (!isset($repos[$entity]) || !in_array($action, ['delete', 'restore'], true) || $id <= 0) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

if ($action === 'restore') {
    Auth::requireAdmin();
}

[$repo, $label] = $repos[$entity];
$userId = Auth::id();

if ($action === 'delete') {
    $ok = $repo->softDelete($id, $userId);
    flash($ok ? 'success' : 'error', $ok ? "$label enviado a la papelera." : "$label no encontrado o ya eliminado.");
} else {
    $ok = $repo->restore($id, $userId);
    flash($ok ? 'success' : 'error', $ok ? "$label restaurado correctamente." : "$label no encontrado o no estaba eliminado.");
}

redirect($back);
