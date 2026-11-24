<?php
declare(strict_types=1);

namespace Recam\Repository;

use Recam\Core\Database;

final class ClientRepository extends AuditedRepository
{
    protected function table(): string   { return 'clients'; }
    protected function entity(): string  { return 'client'; }
    protected function columns(): array  { return ['name', 'company', 'email', 'phone', 'city', 'notes']; }
    protected function label(array $row): string
    {
        return 'cliente "' . ($row['name'] ?? '?') . '"';
    }

    /**
     * Listado paginado con búsqueda por nombre / empresa / correo / teléfono
     */
    public function search(string $q, bool $trash, int $limit, int $offset): array
    {
        $pdo    = Database::pdo();
        $where  = 'c.deleted_at IS ' . ($trash ? 'NOT NULL' : 'NULL');
        $params = [];

        if ($q !== '') {
            $where .= ' AND (c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            $like   = '%' . $q . '%';
            $params = [$like, $like, $like, $like];
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM clients c WHERE $where");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $limit  = max(1, $limit);
        $offset = max(0, $offset);
        $stmt = $pdo->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM preferences p
                      WHERE p.client_id = c.id AND p.deleted_at IS NULL) AS pref_count
               FROM clients c
              WHERE $where
              ORDER BY c.name ASC, c.id ASC
              LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function validate(array $input): array
    {
        $errors = [];
        $get = static fn (string $k): string => trim((string) ($input[$k] ?? ''));
        // Los campos opcionales vacíos se guardan como NULL
        $nullable = static fn (string $v): ?string => $v === '' ? null : $v;

        $name    = $get('name');
        $company = $get('company');
        $email   = $get('email');
        $phone   = $get('phone');
        $city    = $get('city');
        $notes   = $get('notes');

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'El nombre es obligatorio (entre 2 y 120 caracteres).';
        }
        if (mb_strlen($company) > 120) {
            $errors['company'] = 'La empresa no puede exceder 120 caracteres.';
        }
        if ($email !== '' && (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors['email'] = 'Escribe un correo electrónico válido.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
            $errors['phone'] = 'El teléfono solo puede llevar números, espacios, +, - y paréntesis (7 a 20 caracteres).';
        }
        if (mb_strlen($city) > 80) {
            $errors['city'] = 'La ciudad no puede exceder 80 caracteres.';
        }
        if (mb_strlen($notes) > 1000) {
            $errors['notes'] = 'Las notas no pueden exceder 1000 caracteres.';
        }

        return [
            'data' => [
                'name'    => $name,
                'company' => $nullable($company),
                'email'   => $nullable($email),
                'phone'   => $nullable($phone),
                'city'    => $nullable($city),
                'notes'   => $nullable($notes),
            ],
            'errors' => $errors,
        ];
    }
}
