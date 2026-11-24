<?php
/**
 * Funciones auxiliares usadas por las páginas y plantillas
 */

declare(strict_types=1);

use Recam\Core\Config;

/** Escapa texto para imprimirlo en HTML (previene XSS) */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Fecha/hora actual con el formato de la base de datos*/
function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Formatea 'Y-m-d H:i:s' como 'd/m/Y H:i' para mostrarlo al usuario */
function format_dt(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts === false ? e($datetime) : date('d/m/Y H:i', $ts);
}

/** Redirige y termina la ejecución*/
function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}


 /** Solo se aceptan páginas locales simples*/
function safe_return(?string $target, string $fallback = 'dashboard.php'): string
{
    if ($target !== null && preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $target)) {
        return $target;
    }
    return $fallback;
}

// ---------------------------------------------------------------------
// Mensajes "flash": se guardan en sesión y se muestran una sola vez.
// ---------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/** IP del cliente (se guarda en el historial). */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// ---------------------------------------------------------------------
// Paginación
// ---------------------------------------------------------------------

function paginate(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return [
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
        'per_page' => $perPage,
        'total'    => $total,
    ];
}

/** HTML del paginador conservando los filtros actuales */
function pager_html(array $pg, string $script, array $query = []): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $link = static function (int $p, string $label, bool $active = false, bool $disabled = false) use ($script, $query): string {
        if ($disabled) {
            return '<span class="page disabled">' . $label . '</span>';
        }
        $qs = http_build_query(array_merge($query, ['page' => $p]));
        return '<a class="page' . ($active ? ' active' : '') . '" href="' . e($script . '?' . $qs) . '">' . $label . '</a>';
    };

    $html = '<nav class="pager" aria-label="Paginación">';
    $html .= $link($pg['page'] - 1, '&laquo; Anterior', false, $pg['page'] <= 1);
    $start = max(1, $pg['page'] - 2);
    $end   = min($pg['pages'], $pg['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $link($i, (string) $i, $i === $pg['page']);
    }
    $html .= $link($pg['page'] + 1, 'Siguiente &raquo;', false, $pg['page'] >= $pg['pages']);
    return $html . '</nav>';
}

// ---------------------------------------------------------------------
// Catálogos usados en formularios, tablas y validaciones
// ---------------------------------------------------------------------

function preference_categories(): array
{
    return [
        'material'    => 'Material',
        'servicio'    => 'Servicio',
        'acabado'     => 'Acabado',
        'contacto'    => 'Contacto',
        'entrega'     => 'Entrega',
        'facturacion' => 'Facturación',
        'otro'        => 'Otro',
    ];
}

function priority_labels(): array
{
    return [1 => 'Baja', 2 => 'Media', 3 => 'Alta'];
}

/** Nombre de la aplicación para títulos*/
function app_name(): string
{
    return (string) Config::get('app.name', 'CRM');
}
