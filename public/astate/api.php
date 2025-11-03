<?php
require("../../private_astate/config.php");
include("../../private_astate/functions.php");
include("../../private_astate/checks.php");

// --- Connexion à la base de données ---
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    exit;
}

// --- Routeur d'action ---
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'register_device':
        handle_register_device($pdo);
        break;
    case 'login':
        handle_login($pdo);
        break;
    default:
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Action non valide']);
        exit;
}

// --- Logique pour l'enregistrement d'un appareil ---
function handle_register_device($pdo) {
    $user_mail = $_POST['user_mail'] ?? '';
    $user_password_chiffre = $_POST['user_password'] ?? '';
    $user_key = $_POST['user_key'] ?? '';
    $device_id = $_POST['device_id'] ?? '';

    if (empty($user_mail) || empty($user_password_chiffre) || empty($user_key) || empty($device_id)) {
        http_response_code(400);
        exit;
    }

    // 1. Authentifier l'utilisateur
    $user_password_clair = dechiffrer_valeur($user_password_chiffre, $user_key, CIPHER);
    $stmt = $pdo->prepare("SELECT * FROM ast_users WHERE email = ? AND is_active = 1");
    $stmt->execute([$user_mail]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($user_password_clair, $user['password_hash'])) {
        http_response_code(401); // Unauthorized
        exit;
    }

    // 2. Chiffrer la user_key avec la MASTER_KEY
    $encrypted_user_key = master_encrypt($user_key, CIPHER);

    // 3. Sauvegarder l'appareil
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ast_user_devices (user_id, device_id, encrypted_user_key)
            VALUES (:user_id, :device_id, :encrypted_user_key)
            ON DUPLICATE KEY UPDATE encrypted_user_key = :encrypted_user_key
        ");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':device_id' => $device_id,
            ':encrypted_user_key' => $encrypted_user_key,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Appareil enregistré.']);
}

// --- Logique pour la connexion ---
function handle_login($pdo) {
    $user_mail = $_POST['user_mail'] ?? '';
    $user_password_chiffre = $_POST['user_password'] ?? '';
    $device_id = $_POST['device_id'] ?? '';

    if (empty($user_mail) || empty($user_password_chiffre) || empty($device_id)) {
        http_response_code(400);
        exit;
    }

    // 1. Récupérer la user_key chiffrée via le device_id
    $stmt = $pdo->prepare("
        SELECT ud.encrypted_user_key
        FROM ast_user_devices ud
        JOIN ast_users u ON u.id = ud.user_id
        WHERE ud.device_id = ? AND u.email = ?
    ");
    $stmt->execute([$device_id, $user_mail]);
    $device = $stmt->fetch();

    if (!$device) {
        http_response_code(401); // Unauthorized
        exit;
    }

    // 2. Déchiffrer la user_key avec la MASTER_KEY
    $user_key = master_decrypt($device['encrypted_user_key'], CIPHER);
    if (!$user_key) {
        http_response_code(500);
        exit;
    }

    // 3. Le reste du processus d'authentification est identique
    $user_password_clair = dechiffrer_valeur($user_password_chiffre, $user_key, CIPHER);

    $stmt = $pdo->prepare("SELECT * FROM ast_users WHERE email = ? AND is_active = 1");
    $stmt->execute([$user_mail]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($user_password_clair, $user['password_hash'])) {
        http_response_code(401);
        exit;
    }

    // Génération et stockage du token
    $token = bin2hex(random_bytes(32));
    $expireAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+1 day')
        ->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE ast_users SET token = :token, expire_at = :expire_at WHERE id = :id");
    $stmt->execute([':token' => $token, ':expire_at' => $expireAt, ':id' => $user['id']]);

    // Préparation de la réponse chiffrée
    include("../../private_astate/message.php");
    $donnees = [
        "username" => $user['username'],
        "token" => $token,
        "expires" => $expireAt,
        "message" => json_encode($message)
    ];

    $donnees_chiffrees = chiffrer_array($donnees, $user_key, CIPHER);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees_chiffrees);
}
