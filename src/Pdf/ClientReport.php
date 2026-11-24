<?php
declare(strict_types=1);

namespace Recam\Pdf;

use Recam\Core\Config;

/**
 * Documentos PDF formales. Todos comparten el mismo formato
 * encabezado con empresa y título, folio, fecha de emisión, contenido
 * organizado en secciones/tablas y pie con el usuario que lo generó.
 */
final class ClientReport
{

    public static function clientSheet(array $client, array $preferences, string $generatedBy, ?array $fx = null): string
    {
        $categories = preference_categories();
        $priorities = priority_labels();

        $pdf = new ReportPdf(
            (string) Config::get('company.name', 'RECAM LASER'),
            'Ficha de cliente',
            sprintf('FC-%06d', (int) $client['id']),
            date('d/m/Y H:i'),
            $generatedBy,
            (string) Config::get('company.tagline', '')
        );

        $pdf->heading('Datos generales');
        $pdf->keyValues([
            'Cliente'          => (string) $client['name'],
            'Empresa'          => (string) ($client['company'] ?? ''),
            'Correo'           => (string) ($client['email'] ?? ''),
            'Teléfono'         => (string) ($client['phone'] ?? ''),
            'Ciudad'           => (string) ($client['city'] ?? ''),
            'Cliente desde'    => date('d/m/Y', strtotime((string) $client['created_at'])),
            'Última edición'   => date('d/m/Y H:i', strtotime((string) $client['updated_at'])),
        ]);
        if (!empty($client['notes'])) {
            $pdf->keyValues(['Notas' => (string) $client['notes']]);
        }

        $pdf->spacer(6);
        $pdf->heading('Preferencias registradas (' . count($preferences) . ')');

        if ($preferences === []) {
            $pdf->paragraph('Este cliente aún no tiene preferencias registradas.');
        } else {
            $w = ReportPdf::usableWidth();
            $pdf->table(
                ['Categoría', 'Preferencia', 'Detalle', 'Prioridad'],
                [$w * 0.15, $w * 0.25, $w * 0.45, $w * 0.15],
                array_map(static fn (array $p): array => [
                    $categories[$p['category']] ?? (string) $p['category'],
                    (string) $p['title'],
                    (string) ($p['detail'] ?? ''),
                    $priorities[(int) $p['priority']] ?? '',
                ], $preferences)
            );
        }

        // Dato obtenido de la API externa
        if ($fx !== null) {
            $pdf->spacer(14);
            $pdf->heading('Referencia de cotización');
            $pdf->paragraph(sprintf(
                'Tipo de cambio USD/MXN: $%s (fuente: %s, fecha del dato: %s).',
                number_format($fx['rate'], 4), $fx['source'], date('d/m/Y', strtotime($fx['date']))
            ), 9.5);
        }

        return $pdf->output();
    }

    /**
     * Listado de clientes activos, respeta el filtro de búsqueda
     */
    public static function clientList(array $clients, string $generatedBy, string $filter = ''): string
    {
        $pdf = new ReportPdf(
            (string) Config::get('company.name', 'RECAM LASER'),
            'Listado de clientes',
            'LC-' . date('Ymd-His'),
            date('d/m/Y H:i'),
            $generatedBy,
            (string) Config::get('company.tagline', '')
        );

        $pdf->heading('Resumen');
        $pdf->keyValues([
            'Total de clientes' => (string) count($clients),
            'Filtro aplicado'   => $filter !== '' ? $filter : 'Ninguno (todos los clientes activos)',
        ], 110);

        $pdf->spacer(6);
        $pdf->heading('Clientes');
        if ($clients === []) {
            $pdf->paragraph('No hay clientes que coincidan con el filtro.');
        } else {
            $w = ReportPdf::usableWidth();
            $pdf->table(
                ['Cliente', 'Empresa', 'Correo / Teléfono', 'Ciudad', 'Prefs.'],
                [$w * 0.22, $w * 0.22, $w * 0.31, $w * 0.15, $w * 0.10],
                array_map(static fn (array $c): array => [
                    (string) $c['name'],
                    (string) ($c['company'] ?? ''),
                    trim(($c['email'] ?? '') . "\n" . ($c['phone'] ?? '')),
                    (string) ($c['city'] ?? ''),
                    (string) $c['pref_count'],
                ], $clients)
            );
        }

        return $pdf->output();
    }
}
