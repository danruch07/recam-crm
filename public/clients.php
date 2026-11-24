<?php
/**
 * Listado de clientes: búsqueda, paginación y papelera 
 *   clients.php                -> clientes activos
 *   clients.php?view=trash     -> clientes eliminados (solo administrador)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Auth;
use Recam\Core\Config;
use Recam\Core\Csrf;
use Recam\Repository\ClientRepository;

Auth::requireLogin();

$trash = ($_GET['view'] ?? '') === 'trash';
if ($trash) {
    Auth::requireAdmin(); // solo el administrador ve y restaura la papelera
}

$q       = trim((string) ($_GET['q'] ?? ''));
$perPage = (int) Config::get('app.per_page', 10);

$repo = new ClientRepository();
$pg   = paginate($repo->search($q, $trash, 1, 0)['total'], (int) ($_GET['page'] ?? 1), $perPage);
$data = $repo->search($q, $trash, $perPage, $pg['offset']);

$query     = array_filter(['q' => $q, 'view' => $trash ? 'trash' : ''], static fn ($v) => $v !== '');
$returnUrl = 'clients.php' . ($query || $pg['page'] > 1 ? '?' . http_build_query($query + ['page' => $pg['page']]) : '');

$pageTitle = $trash ? 'Papelera de clientes' : 'Clientes';
$active    = 'clients';
require BASE_PATH . '/src/views/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= $trash ? 'Papelera de clientes' : 'Clientes' ?></h1>
        <p class="muted"><?= (int) $data['total'] ?> registro(s)<?= $q !== '' ? ' con la búsqueda "' . e($q) . '"' : '' ?></p>
    </div>
    <div class="actions">
        <?php if ($trash): ?>
            <a class="btn btn-ghost" href="clients.php">← Volver a clientes</a>
        <?php else: ?>
            <a class="btn btn-ghost" href="report.php?type=clients<?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>" target="_blank">Listado PDF</a>
            <?php if (Auth::isAdmin()): ?>
                <a class="btn btn-ghost" href="clients.php?view=trash">Papelera</a>
            <?php endif; ?>
            <a class="btn" href="client_form.php">+ Nuevo cliente</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="clients.php" class="filters">
        <?php if ($trash): ?><input type="hidden" name="view" value="trash"><?php endif; ?>
        <div class="field">
            <label for="q">Buscar</label>
            <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Nombre, empresa, correo o teléfono">
        </div>
        <button class="btn" type="submit">Buscar</button>
        <?php if ($q !== ''): ?>
            <a class="btn btn-ghost" href="clients.php<?= $trash ? '?view=trash' : '' ?>">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<div class="card table-wrap">
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Empresa</th>
                <th>Contacto</th>
                <th>Ciudad</th>
                <th>Prefs.</th>
                <th><?= $trash ? 'Eliminado' : 'Actualizado' ?></th>
                <th class="right">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$data['rows']): ?>
            <tr><td colspan="7" class="empty"><?= $trash ? 'La papelera está vacía.' : 'No hay clientes para mostrar.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($data['rows'] as $c): ?>
            <tr>
                <td><a href="client_view.php?id=<?= (int) $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a></td>
                <td><?= e($c['company'] ?? '—') ?></td>
                <td>
                    <?= e($c['email'] ?? '—') ?><br>
                    <small><?= e($c['phone'] ?? '') ?></small>
                </td>
                <td><?= e($c['city'] ?? '—') ?></td>
                <td><span class="badge"><?= (int) $c['pref_count'] ?></span></td>
                <td class="nowrap"><?= format_dt($trash ? $c['deleted_at'] : $c['updated_at']) ?></td>
                <td class="right">
                    <?php if ($trash): ?>
                        <form class="inline" method="post" action="actions.php">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="entity" value="client">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                            <button class="btn btn-sm" type="submit">Restaurar</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-ghost btn-sm" href="client_view.php?id=<?= (int) $c['id'] ?>">Ver</a>
                        <a class="btn btn-ghost btn-sm" href="client_form.php?id=<?= (int) $c['id'] ?>">Editar</a>
                        <form class="inline" method="post" action="actions.php" data-confirm="¿Eliminar este cliente? Se enviará a la papelera y podrá restaurarse.">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="entity" value="client">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?= pager_html($pg, 'clients.php', $query) ?>
</div>
<script src="assets/app.js"></script>
<?php require BASE_PATH . '/src/views/footer.php';
