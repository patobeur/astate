-- Migration pour ajouter la table de liaison entre les utilisateurs et leurs appareils.
-- Cette table stockera la clé de chiffrement de l'utilisateur de manière sécurisée,
-- chiffrée avec une clé maître propre au serveur.

CREATE TABLE IF NOT EXISTS ast_user_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    device_id VARCHAR(255) NOT NULL UNIQUE,
    encrypted_user_key VARCHAR(512) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_devices_user FOREIGN KEY (user_id)
        REFERENCES ast_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
