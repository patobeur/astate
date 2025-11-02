<?php
// 0. CONFIG

$EXPECTED_PASSWORD = "MON_CODE_SECRET"; // doit être le même que dans l'extension (user password)
$EXPECTED_LOGIN = "patobeur@astate.pat"; // doit être le même que dans l'extension (user mail)
$ENCRYPT_KEY = "ma_cle_super_secrete"; // doit être le même que dans l'extension (encrypt clé)

// 1. CONFIG des tests à Faire

define('USE_ORIGIN_CHECK', true); // contrôle de l'origine (extension seulement)
define('USE_HEADER_CHECK', true); // contrôle du header X-Addon-Key
define('USE_CODE_CHECK', false); // contrôle du code envoyé dans le body

// 2. CONFIG données fixes

define('EXPECTED_HEADER', 'ASTATE-EXT-2025-01'); // doit être le même que dans l'extension
define('CIPHER', 'AES-256-CBC');
define('ALLOWED_ORIGIN', 'chrome-extension://abcdefghijklmnopqrstuvwxyzabcdef');

// 3. CONFIGDBDATAS

define('DB_HOST', 'localhost');
define('DB_NAME', 'asate_database');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
