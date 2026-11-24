<?php
declare(strict_types=1);

namespace Recam\Repository;

use PDO;
use Recam\Core\Audit;
use Recam\Core\Database;

/**
 * Los nombres de tabla y columnas vienen de métodos fijos de
 * las clases hijas (nunca de la entrada del usuario) y los valores siempre
 * se envían como parámetros preparados
 */
abstract class AuditedRepository
{
    /** Nombre de la tabla en MySQL */
    abstract protected function table(): string;

    /** Etiqueta de la entidad para el historial */
    abstract protected function entity(): string;

    /** Columnas que el usuario puede escribir */
    abstract protected function columns(): array;

    /** Texto para la descripción del historial*/
    abstract protected function label(array $row): string;

    // ------------------------------------------------------------------
    // CONSULTAR
    // ------------------------------------------------------------------

    /** Busca un registro por id*/
    public function find(int $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE id = ?';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // REGISTRAR
    // ------------------------------------------------------------------

    /**
     * Inserta un registro y escribe en el historial
     */
    public function create(array $data, int $userId): int
    {
        $data = $this->pick($data);
        $now  = now();

        return Database::transaction(function (PDO $pdo) use ($data, $userId, $now): int {
            $cols = array_merge(array_keys($data), ['created_at', 'created_by', 'updated_at', 'updated_by']);
            $vals = array_merge(array_values($data), [$now, $userId, $now, $userId]);

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->table(),
                implode(', ', $cols),
                implode(', ', array_fill(0, count($cols), '?'))
            );
            $pdo->prepare($sql)->execute($vals);
            $id = (int) $pdo->lastInsertId();

            Audit::log('CREATE', $this->entity(), $id, null, $data,
                'Alta: ' . $this->label($data), $userId, $this->username($userId));
            return $id;
        });
    }

    // ------------------------------------------------------------------
    // ACTUALIZAR
    // ------------------------------------------------------------------

    /**
     * Actualiza un registro. Solo se escriben los campos que
     * realmente cambiaron. Devuelve false si no existe o no hubo cambios
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $data = $this->pick($data);
        $now  = now();

        return Database::transaction(function (PDO $pdo) use ($id, $data, $userId, $now): bool {
            $current = $this->find($id);
            if ($current === null) {
                return false;
            }

            // Se guarda únicamente lo que cambió 
            $old = [];
            $new = [];
            foreach ($data as $col => $value) {
                if ((string) ($current[$col] ?? '') !== (string) ($value ?? '')) {
                    $old[$col] = $current[$col];
                    $new[$col] = $value;
                }
            }
            if ($new === []) {
                return false; 
            }

            $sets = [];
            foreach (array_keys($new) as $col) {
                $sets[] = "$col = ?";
            }
            $sets[] = 'updated_at = ?';
            $sets[] = 'updated_by = ?';

            $sql = 'UPDATE ' . $this->table() . ' SET ' . implode(', ', $sets) . ' WHERE id = ? AND deleted_at IS NULL';
            $pdo->prepare($sql)->execute(array_merge(array_values($new), [$now, $userId, $id]));

            Audit::log('UPDATE', $this->entity(), $id, $old, $new,
                'Edición: ' . $this->label($current) . ' (campos: ' . implode(', ', array_keys($new)) . ')',
                $userId, $this->username($userId));
            return true;
        });
    }

    // ------------------------------------------------------------------
    // ELIMINAR y RESTAURAR
    // ------------------------------------------------------------------

    /**
     * ELIMINACIÓN LÓGICA: la fila NO se borra; solo se marca con la fecha y el
     * usuario que la eliminó. Todas las consultas normales filtran
     * "deleted_at IS NULL", por lo que desaparece de la interfaz pero se conserva
     * para auditoría y para poder restaurarla
     */
    public function softDelete(int $id, int $userId): bool
    {
        $now = now();

        return Database::transaction(function (PDO $pdo) use ($id, $userId, $now): bool {
            $current = $this->find($id);
            if ($current === null) {
                return false; // no existe o ya estaba eliminado
            }

            $pdo->prepare(
                'UPDATE ' . $this->table() .
                ' SET deleted_at = ?, deleted_by = ?, updated_at = ?, updated_by = ? WHERE id = ? AND deleted_at IS NULL'
            )->execute([$now, $userId, $now, $userId, $id]);

            Audit::log('DELETE', $this->entity(), $id, $this->pick($current), ['deleted_at' => $now],
                'Eliminación lógica: ' . $this->label($current), $userId, $this->username($userId));
            return true;
        });
    }

    /** Revierte una eliminación lógica (vuelve a dejar deleted_at en NULL) */
    public function restore(int $id, int $userId): bool
    {
        $now = now();

        return Database::transaction(function (PDO $pdo) use ($id, $userId, $now): bool {
            $current = $this->find($id, true);
            if ($current === null || $current['deleted_at'] === null) {
                return false; // no existe o no estaba eliminado
            }

            $pdo->prepare(
                'UPDATE ' . $this->table() .
                ' SET deleted_at = NULL, deleted_by = NULL, updated_at = ?, updated_by = ? WHERE id = ? AND deleted_at IS NOT NULL'
            )->execute([$now, $userId, $id]);

            Audit::log('RESTORE', $this->entity(), $id, ['deleted_at' => $current['deleted_at']], ['deleted_at' => null],
                'Restauración: ' . $this->label($current), $userId, $this->username($userId));
            return true;
        });
    }

    // ------------------------------------------------------------------
    // Utilidades internas
    // ------------------------------------------------------------------

    /** Cuenta registros activos o eliminados */
    public function countByState(bool $deleted): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE deleted_at IS ' . ($deleted ? 'NOT NULL' : 'NULL');
        return (int) Database::pdo()->query($sql)->fetchColumn();
    }

    /** Filtra el arreglo */
    protected function pick(array $data): array
    {
        return array_intersect_key($data, array_flip($this->columns()));
    }

    /** Nombre de usuario para el historial*/
    private function username(int $userId): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        return $name === false ? null : (string) $name;
    }
}
