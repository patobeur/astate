<?php
require("../../private_astate/config.php");


// 1. méthode obligatoire
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    exit;
}


// 2. contrôle de l’origin (facultatif)
if ($USE_ORIGIN_CHECK) {
    if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $ALLOWED_ORIGIN) {
        header("Access-Control-Allow-Origin: {$ALLOWED_ORIGIN}");
    } else {
        http_response_code(403);
        exit;
    }
}


// 3. contrôle du header (facultatif)
if ($USE_HEADER_CHECK) {
    // on récupère tous les headers en minuscules pour ne pas se battre avec la casse
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);

    // le header qu'on attend
    $recv = isset($headers['x-addon-key']) ? trim($headers['x-addon-key']) : '';

    if ($recv !== $EXPECTED_HEADER) {
        http_response_code(404);
        exit;
    }
}


// 4. récupération des paramètres
$password = $_POST['password'] ?? '';
$code_chiffre = $_POST['code'] ?? '';

if (empty($password) || empty($code_chiffre)) {
	http_response_code(400); // Bad Request
	exit;
}

// 5. Authentification : on déchiffre le code avec le mot de passe
// et on le compare à une valeur attendue.
$code_clair = dechiffrer_valeur($code_chiffre, $password, $CIPHER);

if ($code_clair !== $EXPECTED_PASSWORD) {
    http_response_code(403); // Forbidden
    exit;
}

// 6. données à envoyer (uniquement si l'authentification a réussi)
$donnees = [
    "user"    => "toto",
    "role"    => "admin",
    "expires" => date('Y-m-d H:i:s', time() + 3600),
    "json" => json_encode(array("test" => "ok"))
];


// 6. fonctions de chiffrement/déchiffrement
function dechiffrer_valeur(string $base64, string $key, string $cipher): string|false
{
    // ⚠️ même dérivation que côté JS : SHA-256 sur la clé texte
    $realKey = hash('sha256', $key, true); // 32 octets

    $data = base64_decode($base64);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivlen);
    $crypt = substr($data, $ivlen);

    return openssl_decrypt($crypt, $cipher, $realKey, OPENSSL_RAW_DATA, $iv);
}

function chiffrer_valeur(string $valeur, string $key, string $cipher): string
{
    // ⚠️ même dérivation que côté JS : SHA-256 sur la clé texte
    $realKey = hash('sha256', $key, true); // 32 octets

    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $crypt = openssl_encrypt($valeur, $cipher, $realKey, OPENSSL_RAW_DATA, $iv);

    // on renvoie base64(iv + crypt)
    return base64_encode($iv . $crypt);
}


// 7. on chiffre chaque valeur
$donnees_chiffrees = [];
foreach ($donnees as $k => $v) {
    $donnees_chiffrees[$k] = chiffrer_valeur((string)$v, $password, $CIPHER);
}


// 8. réponse
header('Content-Type: application/json; charset=utf-8');
echo json_encode($donnees_chiffrees);
