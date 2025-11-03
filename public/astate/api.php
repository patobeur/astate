<?php
require("../../private_astate/config.php");
include("../../private_astate/functions.php");
include("../../private_astate/checks.php");

// 4. récupération des paramètres
$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';
$code_chiffre = $_POST['code'] ?? '';

if (empty($password) || empty($code_chiffre) || empty($login)) {
    http_response_code(400); // Bad Request
    exit;
}

// 5. Authentification : on déchiffre le mot de passe avec la clé de chiffrement
$motdepasse_clair = dechiffrer_valeur($code_chiffre, $password, CIPHER);

// 5. Connexion à la base de données
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (\PDOException $e) {
    http_response_code(404); // Bad Request
    exit;
}


// 6. Authentification via la base de données
$user = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM ast_users WHERE email = ? AND is_active = 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
} catch (\PDOException $e) {
    http_response_code(404); // Bad Request
    exit;
}


// 7. Vérification de l'utilisateur et du mot de passe
$response = false;
if ($user && $motdepasse_clair && password_verify($motdepasse_clair, $user['password_hash'])) {
    $response  = true;
}

// 8. Génération et stockage du token
if ($user && $response) {
    $token = bin2hex(random_bytes(32));
    try {
        $stmt = $pdo->prepare("UPDATE ast_users SET token = ? WHERE id = ?");
        $stmt->execute([$token, $user['id']]);
    } catch (\PDOException $e) {
        http_response_code(404); // Bad Request
        exit;
    }
}

// 9. données à envoyer (uniquement si l'authentification a réussi)
// en phase de testes. 

if ($response) {
    $donnees = [
        "username" => $user['username'],
        "token" => $token,
        "expires" => date('Y-m-d H:i:s', time() + 3600)
    ];
} else {
    $donnees = [
        "erreur" => "erreur"
    ];
}


// 7. on chiffre chaque valeur
$donnees_chiffrees = chiffrer_array($donnees, $password, CIPHER);

// 8. réponse
header('Content-Type: application/json; charset=utf-8');
echo json_encode($donnees_chiffrees);
