-- Schema MySQL - Messagerie IA Conciergerie
-- Importez ce fichier depuis cPanel > phpMyAdmin.
-- Encodage utf8mb4 pour gerer toutes les langues et les emojis.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- Logements (un chatbot + une instruction + une cle API par logement)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS properties (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lodgify_property_id  BIGINT NOT NULL,
    lodgify_room_id      BIGINT NULL,
    name                 VARCHAR(255) NOT NULL,
    is_active            TINYINT(1) NOT NULL DEFAULT 0,
    auto_send            TINYINT(1) NOT NULL DEFAULT 0,
    ai_provider          VARCHAR(32) NOT NULL DEFAULT 'anthropic',
    ai_model             VARCHAR(64) NOT NULL DEFAULT 'claude-haiku-4-5-20251001',
    ai_key_enc           TEXT NULL,
    instruction          MEDIUMTEXT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lodgify_property (lodgify_property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Conversations (un fil = un thread_uid Lodgify)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_uid           VARCHAR(64) NOT NULL,
    lodgify_property_id  BIGINT NOT NULL,
    booking_id           BIGINT NULL,
    arrival_date         DATETIME NULL,
    departure_date       DATETIME NULL,
    guest_name           VARCHAR(255) NULL,
    guest_email          VARCHAR(255) NULL,
    channel              VARCHAR(64) NULL,
    language             VARCHAR(8) NULL,
    last_message_date    DATETIME NULL,
    last_guest_msg_id    BIGINT NULL,
    last_replied_msg_id  BIGINT NULL,
    read_at              DATETIME NULL,
    status               VARCHAR(24) NOT NULL DEFAULT 'new',
    ai_draft             MEDIUMTEXT NULL,
    ai_mode              VARCHAR(16) NULL,
    ai_reason            VARCHAR(255) NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_thread (thread_uid),
    KEY idx_status (status),
    KEY idx_read (read_at),
    KEY idx_property (lodgify_property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Messages (voyageur + hote), miroir local des echanges
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id      INT UNSIGNED NOT NULL,
    lodgify_message_id   BIGINT NULL,
    direction            VARCHAR(8) NOT NULL,
    body                 MEDIUMTEXT NULL,
    created_at_remote    DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conv (conversation_id),
    KEY idx_remote_msg (lodgify_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Historique des actions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS history (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id      INT UNSIGNED NULL,
    thread_uid           VARCHAR(64) NULL,
    lodgify_property_id  BIGINT NULL,
    booking_id           BIGINT NULL,
    guest_name           VARCHAR(255) NULL,
    action               VARCHAR(24) NOT NULL,
    body                 MEDIUMTEXT NULL,
    actor                VARCHAR(16) NOT NULL DEFAULT 'ia',
    error_text           TEXT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conv (conversation_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Reglages divers (cle/valeur)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    skey   VARCHAR(64) NOT NULL,
    sval   TEXT NULL,
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
