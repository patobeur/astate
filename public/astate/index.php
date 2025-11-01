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


// 4. contrôle du code envoyé (facultatif)
if ($USE_CODE_CHECK) {
    $code_recu = $_POST['code'] ?? $_GET['code'] ?? '';
    if ($code_recu !== $EXPECTED_PASSWORD) {
        http_response_code(404);
        exit;
    }
}


// 5. données à envoyer
$donnees = [
    "user"    => "toto",
    "role"    => "admin",
    "expires" => date('Y-m-d H:i:s', time() + 3600),
    "json" => json_encode(array('test' => "ok"),
];


// 6. fonction de chiffrement
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
    $donnees_chiffrees[$k] = chiffrer_valeur((string)$v, $ENCRYPT_KEY, $CIPHER);
}


// 8. réponse
header('Content-Type: application/json; charset=utf-8');
echo json_encode($donnees_chiffrees);
