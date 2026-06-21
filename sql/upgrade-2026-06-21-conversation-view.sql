-- Upgrade pour les bases deja deployees avant la vue conversation.
-- A executer une seule fois dans phpMyAdmin si la base existe deja.

ALTER TABLE properties
    MODIFY auto_send TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE conversations
    ADD COLUMN arrival_date DATETIME NULL AFTER booking_id,
    ADD COLUMN departure_date DATETIME NULL AFTER arrival_date,
    ADD COLUMN read_at DATETIME NULL AFTER last_replied_msg_id,
    ADD KEY idx_read (read_at);
