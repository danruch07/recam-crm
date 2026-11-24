<?php
declare(strict_types=1);

namespace Recam\Core;

use PDO;
use Throwable;

/**
 * Conexión única a la base de datos con PDO
 *    Los valores nunca se concatenan en el SQL, para prevenir inyección SQL
 *  ERRMODE_EXCEPTION: cualquier error de SQL lanza una excepción, ninguna transaccion queda incompleta
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = Config::get('db.dsn');
            if ($dsn === null) {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    Config::get('db.host'),
                    Config::get('db.port'),
                    Config::get('db.name'),
                    Config::get('db.charset')
                );
            }

            self::$pdo = new PDO($dsn, Config::get('db.user'), Config::get('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            if (str_starts_with((string) $dsn, 'sqlite')) {
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            }
        }
        return self::$pdo;
    }

    /**
     * Si $work termina bien se hace COMMIT; si lanza cualquier excepción se
     * hace ROLLBACK y se vuelve a lanzar. Se usa en cada operación que
     * modifica datos para que el cambio y su registro en el historial se
     * guarden juntos o no se guarden
     */
    
    public static function transaction(callable $work): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $work($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
