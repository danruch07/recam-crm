<?php
/**
 * Generación de documentos PDF.
 *   report.php?type=client&id=5      -> ficha del cliente 5
 *   report.php?type=clients[&q=ana]  -> listado de clientes (filtro opcional)
 *
 * Cada descarga queda registrada en el historial
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Audit;
use Recam\Core\Auth;
use Recam\Pdf\ClientReport;
use Recam\Repository\ClientRepository;
use Recam\Repository\PreferenceRepository;
use Recam\Service\ExchangeRateService;

Auth::requireLogin();

$type      = (string) ($_GET['type'] ?? '');
$user      = Auth::user();
$clients   = new ClientRepository();
$generator = $user['full_name'] . ' (' . $user['username'] . ')';

if ($type === 'client') {
    $id     = (int) ($_GET['id'] ?? 0);
    $client = $clients->find($id);
    if ($client === null) {
        flash('error', 'El cliente no existe o fue eliminado.');
        redirect('clients.php');
    }

    $prefs = (new PreferenceRepository())->byClient($id);
    // La API puede fallar o tardar, el PDF se genera, sin esa referencia
    $fx    = (new ExchangeRateService())->usdToMxn();
    $pdf   = ClientReport::clientSheet($client, $prefs, $generator, $fx);

    Audit::log('EXPORT', 'report', $id, null, null, 'PDF ficha de cliente: ' . $client['name']);
    $filename = 'ficha-cliente-' . $id . '-' . date('Ymd') . '.pdf';

} elseif ($type === 'clients') {
    $q   = trim((string) ($_GET['q'] ?? ''));
    $pdf = ClientReport::clientList($clients->allActive($q), $generator, $q !== '' ? 'Búsqueda: "' . $q . '"' : '');

    Audit::log('EXPORT', 'report', null, null, $q !== '' ? ['q' => $q] : null, 'PDF listado de clientes');
    $filename = 'listado-clientes-' . date('Ymd-His') . '.pdf';

} else {
    http_response_code(400);
    exit('Tipo de reporte no válido.');
}

// Se abre en el navegador; el usuario puede descargarlo desde el visor
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store'); 
echo $pdf;
