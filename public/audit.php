<?php
/**
 * Historial de actividad (solo administradores)
 * Permite filtrar por texto/usuario, entidad, acción y rango de fechas
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Audit;
use Recam\Core\Auth;
use Recam\Core\Config;

Auth::requireAdmin();

// Listas blancas: solo se aceptan valores conocidos en los filtros
$entities = ['client' => 'Cliente', 'preference' => 'Preferencia', 'user' => 'Sesión', 'report' => 'Reporte PDF'];
$actions  = ['CREATE', 'UPDATE', 'DELETE', 'RESTORE', 'LOGIN', 'LOGIN_FAILED', 'LOGOUT', 'EXPORT'];

$isDate = static fn (string $v): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

$filters = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'entity'    => array_key_exists((string) ($_GET['entity'] ?? ''), $entities) ? (string) $_GET['entity'] : '',
    'action'    => in_array($_GET['action'] ?? '', $actions, true) ? (string) $_GET['action'] : '',
    'date_from' => $isDate((string) ($_GET['date_from'] ?? '')) ? (string) $_GET['date_from'] : '',
    'date_to'   => $isDate((string) ($_GET['date_to'] ?? '')) ? (string) $_GET['date_to'] : '',
];

$perPage = 20;
$pg      = paginate(Audit::search($filters, 1, 0)['total'], (int) ($_GET['page'] ?? 1), $perPage);
$data    = Audit::search($filters, $perPage, $pg['offset']);
$query   = array_filter($filters, static fn ($v) => $v !== '');

/** Formatea un JSON guardado para mostrarlo legible */
$pretty = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '';
    }
    $decoded = json_decode($json, true);
    return $decoded === null ? $json : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$pageTitle = 'Historial de actividad';
$active    = 'audit';
require BASE_PATH . '/src/views/header.php';
?>
<div class="page-head">
    <div>
        <h1>Historial de actividad</h1>
        <p class="muted"><?= (int) $data['total'] ?> movimiento(s) · solo lectura</p>
    </div>
</div>

<div class="card">
    <form method="get" action="audit.php" class="filters">
        <div class="field">
            <label for="q">Usuario o descripción</label>
            <input type="text" id="q" name="q" value="<?= e($filters['q']) ?>">
        </div>
        <div class="field">
            <label for="entity">Entidad</label>
            <select id="entity" name="entity">
                <option value="">Todas</option>
                <?php foreach ($entities as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $filters['entity'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="action">Acción</label>
            <select id="action" name="action">
                <option value="">Todas</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="date_from">Desde</label>
            <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="field">
            <label for="date_to">Hasta</label>
            <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
        </div>
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($query): ?><a class="btn btn-ghost" href="audit.php">Limpiar</a><?php endif; ?>
    </form>
</div>

<div class="card table-wrap">
    <table>
        <thead>
            <tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Registro</th><th>Detalle</th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php if (!$data['rows']): ?>
            <tr><td colspan="6" class="empty">No hay movimientos con esos filtros.</td></tr>
        <?php endif; ?>
        <?php foreach ($data['rows'] as $a): ?>
            <tr>
                <td class="nowrap"><?= e(date('d/m/Y H:i:s', strtotime($a['created_at']))) ?></td>
                <td><?= e($a['username'] ?? '—') ?></td>
                <td><span class="badge act-<?= e($a['action']) ?>"><?= e($a['action']) ?></span></td>
                <td class="nowrap"><?= e($a['entity']) ?><?= $a['entity_id'] !== null ? ' #' . (int) $a['entity_id'] : '' ?></td>
                <td>
                    <?= e($a['description'] ?? '') ?>
                    <?php if ($a['old_values'] !== null || $a['new_values'] !== null): ?>
                        <details class="diff">
                            <summary>Ver cambios</summary>
                            <?php if ($a['old_values'] !== null): ?>
                                <strong>Antes</strong><pre class="json"><?= e($pretty($a['old_values'])) ?></pre>
                            <?php endif; ?>
                            <?php if ($a['new_values'] !== null): ?>
                                <strong>Después</strong><pre class="json"><?= e($pretty($a['new_values'])) ?></pre>
                            <?php endif; ?>
                        </details>
                    <?php endif; ?>
                </td>
                <td class="nowrap"><small><?= e($a['ip_address'] ?? '') ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?= pager_html($pg, 'audit.php', $query) ?>
</div>
<?php require BASE_PATH . '/src/views/footer.php';
