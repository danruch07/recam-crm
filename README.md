# RECAM LASER CRM

**Requisitos** · PHP 8.1+ (`pdo_mysql`, `curl`, `mbstring`, `gd`, `zlib`), Composer y MySQL 5.7+ o MariaDB 10.3+.

Para el desarrollo se utilizó Laragon para ejecutar el servidor web local y la base de datos.

## Arranque local

Desde la raíz del repositorio, con MySQL o MariaDB ejecutandose:

```sh
composer install
mysql -u root -p -e "SOURCE database/schema.sql"
php database/seed.php
php -S localhost:8000 -t public
```

El esquema crea la base `recam_crm`. La conexión predeterminada es `root` sin contraseña en `127.0.0.1:3306`. Si necesitas otros valores, configura `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` antes de ejecutar el seed.

Abre <http://localhost:8000>. Usuarios de prueba: `admin` / `Admin#2026` y `operador` / `Operador#2026`.
