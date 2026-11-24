<?php
/**
 * Alta y edición de clientes es el mismo formulario
 *   client_form.php          -> nuevo cliente
 *   client_form.php?id=5     -> editar al cliente 5
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Auth;
use Recam\Core\Csrf;
use Recam\Repository\ClientRepository;

Auth::requireLogin();

$repo   = new ClientRepository();
$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$client = null;

if ($id > 0) {
    $client = $repo->find($id); // ignora eliminados
    if ($client === null) {
        flash('error', 'El cliente no existe o fue eliminado.');
        redirect('clients.php');
    }
}

$errors = [];
$values = $client ?? ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'city' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    // 1) Valida y normaliza en el servidor
    $result = ClientRepository::validate($_POST);
    $values = $result['data'];
    $errors = $result['errors'];

    // 2) Si todo es válido, guarda
    if (!$errors) {
        if ($client === null) {
            $newId = $repo->create($values, Auth::id());
            flash('success', 'Cliente registrado correctamente.');
            redirect('client_view.php?id=' . $newId);
        }
        $changed = $repo->update($id, $values, Auth::id());
        flash($changed ? 'success' : 'info', $changed ? 'Cliente actualizado correctamente.' : 'No hubo cambios que guardar.');
        redirect('client_view.php?id=' . $id);
    }
}

$pageTitle = $client ? 'Editar cliente' : 'Nuevo cliente';
$active    = 'clients';
require BASE_PATH . '/src/views/header.php';

/** Atributos comunes de cada campo: valor previo y marca de error */
$cls = static fn (string $f): string => isset($errors[$f]) ? ' class="invalid"' : '';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn btn-ghost" href="<?= $client ? 'client_view.php?id=' . (int) $id : 'clients.php' ?>">Cancelar</a>
</div>

<form class="card" method="post" action="client_form.php<?= $client ? '?id=' . (int) $id : '' ?>" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="form-grid">
        <div class="field">
            <label for="name">Nombre *</label>
            <input type="text" id="name" name="name" maxlength="120" value="<?= e($values['name']) ?>"<?= $cls('name') ?> required>
            <?php if (isset($errors['name'])): ?><span class="error-text"><?= e($errors['name']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label for="company">Empresa</label>
            <input type="text" id="company" name="company" maxlength="120" value="<?= e($values['company']) ?>"<?= $cls('company') ?>>
            <?php if (isset($errors['company'])): ?><span class="error-text"><?= e($errors['company']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" maxlength="150" value="<?= e($values['email']) ?>"<?= $cls('email') ?>>
            <?php if (isset($errors['email'])): ?><span class="error-text"><?= e($errors['email']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label for="phone">Teléfono</label>
            <input type="text" id="phone" name="phone" maxlength="20" value="<?= e($values['phone']) ?>"<?= $cls('phone') ?>>
            <?php if (isset($errors['phone'])): ?><span class="error-text"><?= e($errors['phone']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label for="city">Ciudad</label>
            <input type="text" id="city" name="city" maxlength="80" value="<?= e($values['city']) ?>"<?= $cls('city') ?>>
            <?php if (isset($errors['city'])): ?><span class="error-text"><?= e($errors['city']) ?></span><?php endif; ?>
        </div>
        <div class="field full">
            <label for="notes">Notas</label>
            <textarea id="notes" name="notes" maxlength="1000"<?= $cls('notes') ?>><?= e($values['notes']) ?></textarea>
            <?php if (isset($errors['notes'])): ?><span class="error-text"><?= e($errors['notes']) ?></span><?php endif; ?>
        </div>
    </div>
    <p class="mt-1"><button class="btn" type="submit"><?= $client ? 'Guardar cambios' : 'Registrar cliente' ?></button></p>
</form>
<?php require BASE_PATH . '/src/views/footer.php';
