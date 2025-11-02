<?php
require("../../private_astate/config.php");
include("../../private_astate/functions.php");
include("../../private_astate/checks.php");




// 5. Authentification : on déchiffre le code avec le mot de passe
// et on le compare à une valeur attendue.
$code_clair = dechiffrer_valeur($code_chiffre, $password, CIPHER);

// if ($code_clair !== $EXPECTED_PASSWORD) {
//     http_response_code(403); // Forbidden
//     exit;
// }


$DB_CHECK = false;
// 5. Connexion à la base de données
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    $DB_CHECK = true;
} catch (\PDOException $e) {
    // Log l'erreur réelle côté serveur si possible, mais ne l'exposez pas au client
    // error_log($e->getMessage()); 
    // send_json_error(500, 'Internal Server Error: Database connection failed.');
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
    // send_json_error(500, 'Internal Server Error: Error querying the database.');
    http_response_code(404); // Bad Request
    exit;
}


// Vérification de l'utilisateur et du mot de passe
$response = false;
if ($user && $password && password_verify($password, $user['password_hash'])) {
    $response  = true;
}


// 6. données à envoyer (uniquement si l'authentification a réussi)
// en phase de testes. 
$donnees = [
    "USE_HEADER_CHECK"    => $USE_HEADER_CHECK_datas,
    "USE_ORIGIN_CHECK"    => $USE_ORIGIN_CHECK_datas,
    "response"    => $response,
    "user"    => json_encode($user),
    "DB_CHECK"    => $DB_CHECK,
    "login" => $login,
    "password" => $password,
    "code" => $code_chiffre,
    "code_claire" => $code_clair,
    "expires" => date('Y-m-d H:i:s', time() + 3600)
];

// 7. on chiffre chaque valeur
$donnees_chiffrees = chiffrer_array($donnees, $password, CIPHER);

// 8. réponse
header('Content-Type: application/json; charset=utf-8');
echo json_encode($donnees_chiffrees);
