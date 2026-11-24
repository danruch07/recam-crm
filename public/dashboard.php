<?php
/**
 * Tablero principal: indicadores, actividad reciente y tipo de cambio (BANXICO).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Audit;
use Recam\Core\Auth;
use Recam\Repository\ClientRepository;
use Recam\Repository\PreferenceRepository;
use Recam\Service\ExchangeRateService;

Auth::requireLogin();

$clients = new ClientRepository();
$prefs   = new PreferenceRepository();

$stats = [
    'Clientes activos'        => $clients->countByState(false),
    'Preferencias activas'    => $prefs->countActiveVisible(),
    'En papelera (clientes)'  => $clients->countByState(true),
    'Acciones registradas hoy' => Audit::countSince(date('Y-m-d') . ' 00:00:00'),
];

// --- API externa: tipo de cambio USD -> MXN (con caché y respaldo) ---
$fx     = (new ExchangeRateService())->usdToMxn();
$amount = null;
$mxn    = null;
if ($fx !== null && isset($_GET['usd']) && $_GET['usd'] !== '') {
    $raw = str_replace(',', '', (string) $_GET['usd']);
    if (is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= 100000000) {
        $amount = (float) $raw;
        $mxn    = ExchangeRateService::convert($amount, $fx['rate']);
    }
}

// Actividad reciente: solo el administrador ve todo el historial
$recent = Auth::isAdmin() ? Audit::latest(8) : [];

$pageTitle = 'Inicio';
$active    = 'dashboard';
require BASE_PATH . '/src/views/header.php';
?>
<div class="page-head">
    <div>
        <h1>Hola, <?= e(Auth::user()['full_name']) ?></h1>
        <p class="muted"><?= date('d/m/Y H:i') ?> · Resumen del CRM</p>
    </div>
    <div class="actions">
        <a class="btn" href="client_form.php">+ Nuevo cliente</a>
    </div>
</div>

<div class="grid grid-4 mb-2">
    <?php foreach ($stats as $label => $value): ?>
        <div class="card stat">
            <div class="num"><?= (int) $value ?></div>
            <div class="lbl"><?= e($label) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Tipo de cambio USD → MXN</h2>
        <?php if ($fx): ?>
            <p class="stat"><span class="num">$<?= number_format($fx['rate'], 4) ?></span> <span class="muted">MXN por 1 USD</span></p>
            <p class="muted">
                Fuente: <?= e($fx['source']) ?> · Fecha del dato: <?= e(date('d/m/Y', strtotime($fx['date']))) ?>
                <?php if ($fx['stale']): ?>
                    <br><span class="badge badge-2">Dato guardado: la API no respondió, puede estar desactualizado</span>
                <?php endif; ?>
            </p>

            <form method="get" action="dashboard.php" class="filters mt-1">
                <div class="field">
                    <label for="usd">Convertir dólares a pesos</label>
                    <input type="text" id="usd" name="usd" inputmode="decimal" placeholder="Ej. 250.00" value="<?= $amount !== null ? e((string) $amount) : '' ?>">
                </div>
                <button class="btn" type="submit">Convertir</button>
            </form>
            <?php if ($mxn !== null): ?>
                <p class="mt-1"><strong>USD $<?= number_format($amount, 2) ?></strong> = <strong>MXN $<?= number_format($mxn, 2) ?></strong></p>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-warning">No fue posible consultar el servicio de tipo de cambio en este momento. El resto del sistema funciona con normalidad.</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Actividad reciente</h2>
        <?php if (Auth::isAdmin()): ?>
            <?php if (!$recent): ?>
                <p class="muted">Aún no hay actividad registrada.</p>
            <?php endif; ?>
            <ul class="activity">
            <?php foreach ($recent as $a): ?>
                <li>
                    <span class="badge act-<?= e($a['action']) ?>"><?= e($a['action']) ?></span>
                    <?= e($a['description'] ?? $a['entity']) ?><br>
                    <small><?= e($a['username'] ?? 'desconocido') ?> · <?= format_dt($a['created_at']) ?></small>
                </li>
            <?php endforeach; ?>
            </ul>
            <p><a href="audit.php">Ver historial completo →</a></p>
        <?php else: ?>
            <p class="muted">El historial completo de movimientos está disponible para administradores.</p>
        <?php endif; ?>
    </div>
</div>
<?php require BASE_PATH . '/src/views/footer.php';
