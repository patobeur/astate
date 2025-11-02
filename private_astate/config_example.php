<?php
// 0. CONFIG
$USE_ORIGIN_CHECK = true;  // contrôle de l'origine (extension seulement)
$USE_HEADER_CHECK = true;  // contrôle du header X-Addon-Key
$USE_CODE_CHECK   = true;  // contrôle du code envoyé dans le body

$EXPECTED_HEADER = "............"; // doit être le même que dans l'extension
$EXPECTED_PASSWORD = ".........."; // doit être le même que dans l'extension (Votre code)
$EXPECTED_LOGIN = "............."; // doit être le même que dans l'extension (Votre login)
$ENCRYPT_KEY = "................"; // doit être la même que dans l'extension (Votre mot de passe)

$CIPHER = "AES-256-CBC";
$ALLOWED_ORIGIN = "chrome-extension://abcdefghijklmnopqrstuvwxyzabcdef";


// DBDATAS
define('DB_HOST', 'localhost');
define('DB_NAME', 'asate_database');
define('DB_USER', 'asate');
define('DB_PASSWORD', 'asate_password');
