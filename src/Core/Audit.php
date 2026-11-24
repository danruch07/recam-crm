<?php
declare(strict_types=1);

namespace Recam\Core;

use PDO;

/**
 * Historial / Auditoría
 * La tabla es de solo inserción: la aplicación no tiene función para
 * modificar ni borrar registros del historial
 */
final class Audit
{

    public static function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
        ?int $userId = null,
        ?string $username = null
    ): void {
        // Si no indica actor, se toma el usuario de la sesión actual
        if ($userId === null && $username === null) {
            $current  = Auth::user();
            $userId   = $current['id'] ?? null;
            $username = $current['username'] ?? null;
        }

        $json = static fn (?array $data): ?string =>
            $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_log
                (user_id, username, action, entity, entity_id, old_values, new_values,
                 description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $username,
            $action,
            $entity,
            $entityId,
            $json($old),
            $json($new),
            $description,
            client_ip() ?: null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            now(),
        ]);
    }

    public static function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::buildWhere($filters);
        $pdo = Database::pdo();

        $count = $pdo->prepare("SELECT COUNT(*) FROM audit_log a $where");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $limit  = max(1, $limit);
        $offset = max(0, $offset);
        $stmt = $pdo->prepare(
            "SELECT a.* FROM audit_log a $where ORDER BY a.created_at DESC, a.id DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** Últimas acciones (para el tablero principal) */
    public static function latest(int $n = 8): array
    {
        return self::search([], $n, 0)['rows'];
    }

    /** Cantidad de acciones registradas desde cierta fecha/hora */
    public static function countSince(string $datetime): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM audit_log WHERE created_at >= ?');
        $stmt->execute([$datetime]);
        return (int) $stmt->fetchColumn();
    }


    private static function buildWhere(array $f): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($f['q'])) {
            $conditions[] = '(a.username LIKE ? OR a.description LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like);
        }
        if (!empty($f['entity'])) {
            $conditions[] = 'a.entity = ?';
            $params[] = $f['entity'];
        }
        if (!empty($f['action'])) {
            $conditions[] = 'a.action = ?';
            $params[] = $f['action'];
        }
        if (!empty($f['date_from'])) {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $f['date_from'] . ' 00:00:00';
        }
        if (!empty($f['date_to'])) {
            $conditions[] = 'a.created_at <= ?';
            $params[] = $f['date_to'] . ' 23:59:59';
        }

        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }
}
