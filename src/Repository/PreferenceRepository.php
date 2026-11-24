<?php
declare(strict_types=1);

namespace Recam\Repository;

use Recam\Core\Database;

/**
 * Preferencias de cada cliente (material favorito, acabado, medio de contacto)
 * CRUD, eliminación lógica e historial se heredan de AuditedRepository
 */
final class PreferenceRepository extends AuditedRepository
{
    protected function table(): string   { return 'preferences'; }
    protected function entity(): string  { return 'preference'; }
    protected function columns(): array  { return ['client_id', 'category', 'title', 'detail', 'priority']; }
    protected function label(array $row): string
    {
        return 'preferencia "' . ($row['title'] ?? '?') . '" (cliente #' . ($row['client_id'] ?? '?') . ')';
    }

    /**
     * Preferencias de un cliente, de mayor a menor prioridad.
     */
    public function byClient(int $clientId, bool $deleted = false): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, u.username AS updated_by_name
               FROM preferences p
               JOIN users u ON u.id = p.updated_by
              WHERE p.client_id = ? AND p.deleted_at IS ' . ($deleted ? 'NOT NULL' : 'NULL') . '
              ORDER BY p.priority DESC, p.category ASC, p.id ASC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function countActiveVisible(): int
    {
        return (int) Database::pdo()->query(
            'SELECT COUNT(*) FROM preferences p
               JOIN clients c ON c.id = p.client_id
              WHERE p.deleted_at IS NULL AND c.deleted_at IS NULL'
        )->fetchColumn();
    }

    public static function validate(array $input): array
    {
        $errors = [];
        $get = static fn (string $k): string => trim((string) ($input[$k] ?? ''));

        $category = $get('category');
        $title    = $get('title');
        $detail   = $get('detail');
        $priority = (int) $get('priority');

        if (!array_key_exists($category, preference_categories())) {
            $errors['category'] = 'Selecciona una categoría válida.';
        }
        if ($title === '' || mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            $errors['title'] = 'El título es obligatorio (entre 2 y 120 caracteres).';
        }
        if (mb_strlen($detail) > 1000) {
            $errors['detail'] = 'El detalle no puede exceder 1000 caracteres.';
        }
        if (!array_key_exists($priority, priority_labels())) {
            $errors['priority'] = 'Selecciona una prioridad válida.';
        }

        return [
            'data' => [
                'category' => $category,
                'title'    => $title,
                'detail'   => $detail === '' ? null : $detail,
                'priority' => $priority,
            ],
            'errors' => $errors,
        ];
    }
}
