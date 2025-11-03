<?php

declare(strict_types=1);
/**
 * install.php
 * - Crée les tables ast_users, ast_roles, ast_user_roles
 * - Insère les rôles de base
 * - Crée un compte admin
 */

// 4) Compte admin Patobeur
$email    = 'patobeur@astate.pat';
$username = 'Patobeur';
$pass     = 'test';
$roleUser = 'sysadmin';



// Sécurité minimale des headers si exécuté via HTTP
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline';");
}

require("../../private_astate/config.php");

function out($msg)
{
    if (php_sapi_name() === 'cli') {
        echo strip_tags($msg) . PHP_EOL;
    } else {
        echo '<div style="font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;color:#e5e7eb;background:#0f172a;padding:8px 12px;border-left:4px solid #6366f1;margin:8px 0;border-radius:6px">'
            . $msg . '</div>';
    }
}

function ok($msg)
{
    out("✅ <strong>$msg</strong>");
}
function info($msg)
{
    out("ℹ️ $msg");
}
function warn($msg)
{
    out("⚠️ $msg");
}
function err($msg)
{
    out("❌ <strong>$msg</strong>");
}

try {
    // --------------------------------------------
    // 1) Connexion PDO (essaie d'abord la DB, sinon la crée)
    $dsnWithDb = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    try {
        $pdo = new PDO($dsnWithDb, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        ok("Connecté à la base « " . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . " ».");
    } catch (PDOException $e) {
        // Si la base n'existe pas : SQLSTATE[HY000] [1049] Unknown database
        if ($e->getCode() === '1049' || str_contains($e->getMessage(), 'Unknown database')) {
            info("Base « " . DB_NAME . " » introuvable. Tentative de création…");
            $pdoNoDb = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', DB_NAME) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            ok("Base « " . DB_NAME . " » créée.");
            // Reconnexion sur la DB nouvellement créée
            $pdo = new PDO($dsnWithDb, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            throw $e;
        }
    }

    // --------------------------------------------
    // 2) Schéma : tables
    $sqlUsers = <<<SQL
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
SQL;

    $sqlRoles = <<<SQL
CREATE TABLE IF NOT EXISTS ast_roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $sqlPivot = <<<SQL
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
SQL;

    $pdo->exec($sqlUsers);
    ok("Table « ast_users » ok.");
    $pdo->exec($sqlRoles);
    ok("Table « ast_roles » ok.");
    $pdo->exec($sqlPivot);
    ok("Table « ast_user_roles » ok.");

    $sqlDevices = <<<SQL
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
SQL;
    $pdo->exec($sqlDevices);
    ok("Table « ast_user_devices » ok.");

    // --------------------------------------------
    // 3) Rôles de base
    $pdo->exec("
        INSERT IGNORE INTO ast_roles (name, display_name) VALUES
        ('user', 'Utilisateur'),
        ('agent', 'Agent'),
        ('admin', 'Administrateur'),
        ('sysadmin', 'Super Administrateur')
    ");
    ok("Rôles de base vérifiés/insérés.");

    $hash     = password_hash($pass, PASSWORD_DEFAULT);

    // --------------------------------------------
    // 4) On n'écrase pas le mot de passe si l'utilisateur existe déjà
    $stmt = $pdo->prepare("
        INSERT INTO ast_users (username, email, password_hash, token, is_active)
        VALUES (:u, :e, :p, NULL, 1)
        ON DUPLICATE KEY UPDATE
            username = VALUES(username)  -- idempotent, ne modifie pas le mdp existant
    ");
    $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);
    ok("Utilisateur « {$username} » présent.");

    // --------------------------------------------
    // 5) Lier le rôle admin
    $roleUser = 'admin'; // le rôle voulu

    // Récup id du rôle (requête préparée)
    $stmtRole = $pdo->prepare("SELECT id FROM ast_roles WHERE name = :name LIMIT 1");
    $stmtRole->execute([':name' => $roleUser]);
    $roleId = (int)$stmtRole->fetchColumn();
    if (!$roleId) {
        throw new RuntimeException("Rôle '{$roleUser}' introuvable.");
    }

    // Récup id de l'utilisateur par email (préparée aussi)
    $stmtUserId = $pdo->prepare("SELECT id FROM ast_users WHERE email = :email LIMIT 1");
    $stmtUserId->execute([':email' => $email]);
    $userId = (int)$stmtUserId->fetchColumn();
    if (!$userId) {
        throw new RuntimeException("Utilisateur '{$email}' introuvable après insertion.");
    }

    // Insertion pivot idempotente
    $pivot = $pdo->prepare("INSERT IGNORE INTO ast_user_roles (user_id, role_id) VALUES (:uid, :rid)");
    $pivot->execute([':uid' => $userId, ':rid' => $roleId]);

    ok("Rôle « {$roleUser} » attribué à « {$username} ».");

    // --------------------------------------------
    // 6) Récap
    info("Installation terminée ✅");
    info("Identifiant: <code>{$email}</code> — Mot de passe: <code>{$pass}</code> (hashé et stocké)");
    ok("Fichier d'installation supprimé... ✅");


    $src = 'install.php';
    $dst = 'install_old.php';
    if (is_file($src)) {
        @rename($src, $dst);
    }
} catch (Throwable $e) {
    err("Erreur: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
