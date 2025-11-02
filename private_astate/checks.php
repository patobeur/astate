<?php

// 1. méthode obligatoire
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    exit;
}

// 2. contrôle de l’origin (facultatif)
$USE_ORIGIN_CHECK_datas = false;
if (USE_ORIGIN_CHECK) {
    if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === ALLOWED_ORIGIN) {
        header("Access-Control-Allow-Origin: {ALLOWED_ORIGIN}");
        $USE_ORIGIN_CHECK_datas = true;
    } else {
        http_response_code(403);
        exit;
    }
}


// 3. contrôle du header (facultatif)
$USE_HEADER_CHECK_datas = false;
if (USE_HEADER_CHECK) {
    // on récupère tous les headers en minuscules pour ne pas se battre avec la casse
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);

    // le header qu'on attend
    $recv = isset($headers['x-addon-key']) ? trim($headers['x-addon-key']) : '';

    if ($recv !== EXPECTED_HEADER) {
        http_response_code(404);
        exit;
    } else {
        $USE_HEADER_CHECK_datas = true;
    }
}

// 4. récupération des paramètres
$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';
$code_chiffre = $_POST['code'] ?? '';

if (empty($password) || empty($code_chiffre)) {
    http_response_code(400); // Bad Request
    exit;
}
