-- =====================================================================
--  Esquema de base de datos (MySQL 5.7+ / MariaDB 10.3+)
--
--   * Eliminación LÓGICA: las tablas de negocio (clients, preferences)
--     tienen deleted_at / deleted_by. Un registro con deleted_at NOT NULL
--     se considera borrado, pero la fila sigue existiendo y se puede
--     restaurar. Nunca se ejecuta DELETE sobre estas tablas
--   * Bitácora (audit_log) de solo inserción: guarda quién, cuándo, qué
--     acción, sobre qué registro y los valores anteriores/nuevos
--     'username' se duplica a propósito: si el usuario cambia o se
--     desactiva, el historial conserva el nombre que tenía en ese momento
-- =====================================================================

CREATE DATABASE IF NOT EXISTS recam_crm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE recam_crm;

-- ---------------------------------------------------------------------
-- Usuarios del sistema
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(50)  NOT NULL,
    -- Hash generado con password_hash()
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    role            ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    -- Control de ataques defuerza bruta: se bloquea la cuenta tras 5 intentos fallidos
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Clientes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120) NOT NULL,
    company     VARCHAR(120) NULL,
    email       VARCHAR(150) NULL,
    phone       VARCHAR(20)  NULL,
    city        VARCHAR(80)  NULL,
    notes       TEXT NULL,
    created_at  DATETIME NOT NULL,
    created_by  INT UNSIGNED NOT NULL,
    updated_at  DATETIME NOT NULL,
    updated_by  INT UNSIGNED NOT NULL,
    -- Eliminación lógica: NULL = activo; fecha = eliminado en ese momento.
    deleted_at  DATETIME NULL,
    deleted_by  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_clients_deleted (deleted_at),
    KEY idx_clients_name (name),
    CONSTRAINT fk_clients_created_by FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT fk_clients_updated_by FOREIGN KEY (updated_by) REFERENCES users (id),
    CONSTRAINT fk_clients_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Preferencias de cada cliente (material, acabado, contacto, entrega...)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS preferences (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id   INT UNSIGNED NOT NULL,
    category    ENUM('material','servicio','acabado','contacto','entrega','facturacion','otro')
                NOT NULL DEFAULT 'otro',
    title       VARCHAR(120) NOT NULL,
    detail      TEXT NULL,
    -- 1 = baja, 2 = media, 3 = alta
    priority    TINYINT UNSIGNED NOT NULL DEFAULT 2,
    created_at  DATETIME NOT NULL,
    created_by  INT UNSIGNED NOT NULL,
    updated_at  DATETIME NOT NULL,
    updated_by  INT UNSIGNED NOT NULL,
    deleted_at  DATETIME NULL,
    deleted_by  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_pref_client (client_id, deleted_at),
    CONSTRAINT fk_pref_client     FOREIGN KEY (client_id)  REFERENCES clients (id),
    CONSTRAINT fk_pref_created_by FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT fk_pref_updated_by FOREIGN KEY (updated_by) REFERENCES users (id),
    CONSTRAINT fk_pref_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Historial solo se inserta, nunca se edita
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- NULL cuando el usuario no está identificado
    user_id     INT UNSIGNED NULL,
    username    VARCHAR(50)  NULL,
    action      ENUM('CREATE','UPDATE','DELETE','RESTORE','LOGIN','LOGIN_FAILED','LOGOUT','EXPORT')
                NOT NULL,
    entity      VARCHAR(30)  NOT NULL,         
    entity_id   INT UNSIGNED NULL,
    old_values  JSON NULL,                      -- valores antes del cambio
    new_values  JSON NULL,                      -- valores después del cambio
    description VARCHAR(255) NULL,
    ip_address  VARCHAR(45)  NULL,              
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL,              -- fecha y hora exacta de la acción
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity, entity_id),
    KEY idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
