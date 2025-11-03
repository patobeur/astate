# installation manuelle

_Le champ `email` est utilisé comme identifiant de connexion par l'extension._

```bash
-- Utiliser la base
USE astate_database;

-- Table utilisateurs
CREATE TABLE IF NOT EXISTS ast_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    token VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    expire_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```bash
-- Table des rôles
CREATE TABLE IF NOT EXISTS ast_roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```bash
-- Table pivot user<->role (many-to-many)
CREATE TABLE IF NOT EXISTS ast_user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id)
        REFERENCES ast_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id)
        REFERENCES ast_roles(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```bash
-- Rôles de base
INSERT IGNORE INTO ast_roles (name, display_name) VALUES
('user', 'Utilisateur'),
('agent', 'Agent'),
('admin', 'Administrateur'),
('sysadmin', 'Super Administrateur');
```

```bash
-- Création du user
INSERT INTO ast_users (username, email, password_hash, token, is_active)
VALUES ('Patobeur', 'patobeur@astate.pat', password_hash("VOTRE_MOT_DE_PASSE",PASSWORD_DEFAULT), NULL, 1);
```

```bash
-- Attribution du rôle admin (idempotent)
INSERT IGNORE INTO ast_user_roles (user_id, role_id)
SELECT u.id, r.id
FROM ast_users u
JOIN ast_roles r ON r.name = 'admin'
WHERE u.email = 'patobeur@astate.pat';
```
