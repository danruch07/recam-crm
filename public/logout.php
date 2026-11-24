<?php
/**
 * Cierre de sesión. Solo por POST y token CSRF
*/

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Recam\Core\Auth;
use Recam\Core\Csrf;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
Csrf::verifyOrFail();
Auth::logout();

session_start(); 
flash('success', 'Sesión cerrada correctamente.');
redirect('login.php');
