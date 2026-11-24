<?php
/**
 * Plantilla: cabecera + menú. Variables esperadas:
 *   $pageTitle (string)  título de la página
 *   $active    (string)  clave del menú activo: dashboard | clients | audit
 */

use Recam\Core\Auth;
use Recam\Core\Csrf;

$active = $active ?? '';
$user   = Auth::user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Inicio') ?> · <?= e(app_name()) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($user): ?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="dashboard.php"><span class="brand-mark">▲</span> RECAM <strong>LASER</strong></a>
        <nav class="menu">
            <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'on' : '' ?>">Inicio</a>
            <a href="clients.php" class="<?= $active === 'clients' ? 'on' : '' ?>">Clientes</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="audit.php" class="<?= $active === 'audit' ? 'on' : '' ?>">Historial</a>
            <?php endif; ?>
        </nav>
        <form class="userbox" method="post" action="logout.php">
            <?= Csrf::field() ?>
            <span><?= e($user['full_name']) ?> <small>(<?= e($user['role'] === 'admin' ? 'administrador' : 'usuario') ?>)</small></span>
            <button type="submit" class="btn btn-ghost btn-sm">Salir</button>
        </form>
    </div>
</header>
<?php endif; ?>
<main class="container">
<?php foreach (flash_pull() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
<?php endforeach; ?>
