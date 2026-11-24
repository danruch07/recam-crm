<?php
/**
 * Alta y edición de preferencias de un cliente
 *   preference_form.php?client_id=3   -> nueva preferencia para el cliente 3
 *   preference_form.php?id=12         -> edita la preferencia 12
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Auth;
use Recam\Core\Csrf;
use Recam\Repository\ClientRepository;
use Recam\Repository\PreferenceRepository;

Auth::requireLogin();

$prefs   = new PreferenceRepository();
$clients = new ClientRepository();

$prefId     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$preference = null;

if ($prefId > 0) {
    $preference = $prefs->find($prefId);
    if ($preference === null) {
        flash('error', 'La preferencia no existe o fue eliminada.');
        redirect('clients.php');
    }
    $clientId = (int) $preference['client_id'];
} else {
    $clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
}

$client = $clients->find($clientId);
if ($client === null) {
    flash('error', 'El cliente no existe o fue eliminado.');
    redirect('clients.php');
}

$errors = [];
$values = $preference ?? ['category' => 'material', 'title' => '', 'detail' => '', 'priority' => 2];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    $result = PreferenceRepository::validate($_POST);
    $values = $result['data'];
    $errors = $result['errors'];

    if (!$errors) {
        if ($preference === null) {
            $prefs->create($values + ['client_id' => $clientId], Auth::id());
            flash('success', 'Preferencia registrada correctamente.');
        } else {
            $changed = $prefs->update($prefId, $values, Auth::id());
            flash($changed ? 'success' : 'info', $changed ? 'Preferencia actualizada correctamente.' : 'No hubo cambios que guardar.');
        }
        redirect('client_view.php?id=' . $clientId);
    }
}

$pageTitle = $preference ? 'Editar preferencia' : 'Nueva preferencia';
$active    = 'clients';
require BASE_PATH . '/src/views/header.php';
$cls = static fn (string $f): string => isset($errors[$f]) ? ' class="invalid"' : '';
?>
<div class="page-head">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p class="muted">Cliente: <strong><?= e($client['name']) ?></strong></p>
    </div>
    <a class="btn btn-ghost" href="client_view.php?id=<?= $clientId ?>">Cancelar</a>
</div>

<form class="card" method="post" action="preference_form.php" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $prefId ?>">
    <input type="hidden" name="client_id" value="<?= $clientId ?>">
    <div class="form-grid">
        <div class="field">
            <label for="category">Categoría *</label>
            <select id="category" name="category"<?= $cls('category') ?>>
                <?php foreach (preference_categories() as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $values['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category'])): ?><span class="error-text"><?= e($errors['category']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label for="priority">Prioridad *</label>
            <select id="priority" name="priority"<?= $cls('priority') ?>>
                <?php foreach (priority_labels() as $key => $label): ?>
                    <option value="<?= $key ?>" <?= (int) $values['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['priority'])): ?><span class="error-text"><?= e($errors['priority']) ?></span><?php endif; ?>
        </div>
        <div class="field full">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" maxlength="120" value="<?= e($values['title']) ?>"<?= $cls('title') ?> required>
            <?php if (isset($errors['title'])): ?><span class="error-text"><?= e($errors['title']) ?></span><?php endif; ?>
        </div>
        <div class="field full">
            <label for="detail">Detalle</label>
            <textarea id="detail" name="detail" maxlength="1000"<?= $cls('detail') ?>><?= e($values['detail']) ?></textarea>
            <?php if (isset($errors['detail'])): ?><span class="error-text"><?= e($errors['detail']) ?></span><?php endif; ?>
        </div>
    </div>
    <p class="mt-1"><button class="btn" type="submit"><?= $preference ? 'Guardar cambios' : 'Registrar preferencia' ?></button></p>
</form>
<?php require BASE_PATH . '/src/views/footer.php';
