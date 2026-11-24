<?php
/**
 * Ficha de un cliente: datos generales y sus preferencias para el
 * administrador, las eliminadas que puede restaurar
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Auth;
use Recam\Core\Csrf;
use Recam\Repository\ClientRepository;
use Recam\Repository\PreferenceRepository;

Auth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);

$clients = new ClientRepository();
// El administrador puede abrir clientes eliminados (solo lectura)
$client = $clients->find($id, Auth::isAdmin());
if ($client === null) {
    flash('error', 'El cliente no existe o fue eliminado.');
    redirect('clients.php');
}
$isDeleted = $client['deleted_at'] !== null;

$prefsRepo   = new PreferenceRepository();
$preferences = $prefsRepo->byClient($id, false);
$trashPrefs  = Auth::isAdmin() ? $prefsRepo->byClient($id, true) : [];

$categories = preference_categories();
$priorities = priority_labels();
$returnUrl  = 'client_view.php?id=' . $id;

$pageTitle = $client['name'];
$active    = 'clients';
require BASE_PATH . '/src/views/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($client['name']) ?></h1>
        <p class="muted"><?= e($client['company'] ?? 'Sin empresa registrada') ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="clients.php">← Clientes</a>
        <a class="btn btn-ghost" href="report.php?type=client&amp;id=<?= $id ?>" target="_blank">Generar PDF</a>
        <?php if (!$isDeleted): ?>
            <a class="btn btn-ghost" href="client_form.php?id=<?= $id ?>">Editar</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isDeleted): ?>
    <div class="alert alert-warning">
        Este cliente fue eliminado el <?= format_dt($client['deleted_at']) ?> (eliminación lógica).
        <form class="inline" method="post" action="actions.php">
            <?= Csrf::field() ?>
            <input type="hidden" name="entity" value="client">
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
            <button class="btn btn-sm" type="submit">Restaurar cliente</button>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Datos del cliente</h2>
    <dl class="kv">
        <dt>Correo</dt><dd><?= e($client['email'] ?? '—') ?></dd>
        <dt>Teléfono</dt><dd><?= e($client['phone'] ?? '—') ?></dd>
        <dt>Ciudad</dt><dd><?= e($client['city'] ?? '—') ?></dd>
        <dt>Notas</dt><dd><?= nl2br(e($client['notes'] ?? '—')) ?></dd>
        <dt>Registrado</dt><dd><?= format_dt($client['created_at']) ?></dd>
        <dt>Última edición</dt><dd><?= format_dt($client['updated_at']) ?></dd>
    </dl>
</div>

<div class="card">
    <div class="page-head">
        <h2>Preferencias (<?= count($preferences) ?>)</h2>
        <?php if (!$isDeleted): ?>
            <a class="btn btn-sm" href="preference_form.php?client_id=<?= $id ?>">+ Nueva preferencia</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Categoría</th><th>Título</th><th>Detalle</th><th>Prioridad</th><th>Últ. cambio</th><th class="right">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (!$preferences): ?>
                <tr><td colspan="6" class="empty">Este cliente aún no tiene preferencias registradas.</td></tr>
            <?php endif; ?>
            <?php foreach ($preferences as $p): ?>
                <tr>
                    <td><span class="badge"><?= e($categories[$p['category']] ?? $p['category']) ?></span></td>
                    <td><strong><?= e($p['title']) ?></strong></td>
                    <td><?= nl2br(e($p['detail'] ?? '—')) ?></td>
                    <td><span class="badge badge-<?= (int) $p['priority'] ?>"><?= e($priorities[(int) $p['priority']] ?? '') ?></span></td>
                    <td class="nowrap"><?= format_dt($p['updated_at']) ?><br><small>por <?= e($p['updated_by_name']) ?></small></td>
                    <td class="right">
                        <?php if (!$isDeleted): ?>
                            <a class="btn btn-ghost btn-sm" href="preference_form.php?id=<?= (int) $p['id'] ?>">Editar</a>
                            <form class="inline" method="post" action="actions.php" data-confirm="¿Eliminar esta preferencia? Podrá restaurarla un administrador.">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="entity" value="preference">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($trashPrefs): ?>
<div class="card">
    <h2>Preferencias eliminadas (<?= count($trashPrefs) ?>)</h2>
    <p class="muted">Eliminación lógica: siguen en la base de datos y pueden restaurarse.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Categoría</th><th>Título</th><th>Eliminada</th><th class="right">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($trashPrefs as $p): ?>
                <tr>
                    <td><span class="badge"><?= e($categories[$p['category']] ?? $p['category']) ?></span></td>
                    <td><?= e($p['title']) ?></td>
                    <td class="nowrap"><?= format_dt($p['deleted_at']) ?></td>
                    <td class="right">
                        <?php if (!$isDeleted): ?>
                            <form class="inline" method="post" action="actions.php">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="entity" value="preference">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                                <button class="btn btn-sm" type="submit">Restaurar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<script src="assets/app.js"></script>
<?php require BASE_PATH . '/src/views/footer.php';
