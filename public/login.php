<?php
/**
 * Inicio de sesión (usuario + contraseña).
 * La verificación segura va en Recam\Core\Auth::attempt()
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Auth;
use Recam\Core\Csrf;

// Si ya hay sesión válida, no muestra el login
if (Auth::check()) {
    redirect('dashboard.php');
}

$error    = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Escribe tu usuario y contraseña.';
    } else {
        $result = Auth::attempt($username, $password);
        if ($result['ok']) {
            redirect('dashboard.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = 'Iniciar sesión';
$active    = '';
require BASE_PATH . '/src/views/header.php';
?>
<div class="login-wrap">
    <div class="brand-big"><span class="brand-mark">▲</span> RECAM <strong>LASER</strong></div>
    <div class="card">
        <h1>Iniciar sesión</h1>
        <p class="muted">Accede al CRM de clientes y preferencias.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" autocomplete="on">
            <?= Csrf::field() ?>
            <div class="field mb-1">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
            </div>
            <div class="field mb-2">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-block" type="submit">Entrar</button>
        </form>
    </div>
</div>
<?php require BASE_PATH . '/src/views/footer.php';
