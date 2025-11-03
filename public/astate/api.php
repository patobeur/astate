<?php
require("../../private_astate/config.php");
include("../../private_astate/functions.php");
include("../../private_astate/checks.php");

// 4. récupération des paramètres
$user_mail = $_POST['user_mail'] ?? '';
$user_key = $_POST['user_key'] ?? '';
$user_password_chiffre = $_POST['user_password'] ?? '';

if (empty($user_key) || empty($user_password_chiffre) || empty($user_mail)) {
    http_response_code(400); // Bad Request
    exit;
}

// 5. Authentification : on déchiffre le mot de passe avec la clé de chiffrement
$user_password_clair = dechiffrer_valeur($user_password_chiffre, $user_key, CIPHER);

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
    $stmt->execute([$user_mail]);
    $user = $stmt->fetch();
} catch (\PDOException $e) {
    http_response_code(404); // Bad Request
    exit;
}


// 7. Vérification de l'utilisateur et du mot de passe
$response = false;
if ($user && $user_password_clair && password_verify($user_password_clair, $user['password_hash'])) {
    $response  = true;
}

// 8. Génération et stockage du token
// if ($user && $response) {
//     $token = bin2hex(random_bytes(32));
//     try {
//         $stmt = $pdo->prepare("UPDATE ast_users SET token = ? WHERE id = ?");
//         $stmt->execute([$token, $user['id']]);
//     } catch (\PDOException $e) {
//         http_response_code(404); // Bad Request
//         exit;
//     }
// }

if ($user && $response) {
    $token = bin2hex(random_bytes(32));
    $expireAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+1 day')
        ->format('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("
            UPDATE ast_users
               SET token = :token,
                   expire_at = :expire_at
             WHERE id = :id
        ");
        $stmt->execute([
            ':token'     => $token,
            ':expire_at' => $expireAt,
            ':id'        => $user['id'],
        ]);
    } catch (PDOException $e) {
        http_response_code(400);
        exit;
    }
}


// 9. données à envoyer (uniquement si l'authentification a réussi)
// en phase de testes. 

include("../../private_astate/message.php");

if ($user && $response) {
    $donnees = [
        "username" => $user['username'],
        "token" => $token,
        "expires" => $expireAt,
        "message" => json_encode($message)
    ];
} else {
    $donnees = [
        "erreur" => "erreur"
    ];
}


// 10. on chiffre chaque valeur
$donnees_chiffrees = chiffrer_array($donnees, $user_key, CIPHER);

// 11. réponse
header('Content-Type: application/json; charset=utf-8');
echo json_encode($donnees_chiffrees);
