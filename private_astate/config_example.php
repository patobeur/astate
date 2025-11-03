<?php
// 0. CONFIG

define('USE_ORIGIN_CHECK', true); // contrôle de l'origine (extension seulement)
define('USE_HEADER_CHECK', true); // contrôle du header X-Addon-Key
define('USE_CODE_CHECK', false); // contrôle du code envoyé dans le body

// 1. CONFIG données fixes

define('EXPECTED_HEADER', 'ASTATE-EXT-2025-01'); // doit être le même que dans l'extension
define('CIPHER', 'AES-256-CBC');
define('ALLOWED_ORIGIN', 'chrome-extension://abcdefghijklmnopqrstuvwxyzabcdef');

// 2. CONFIGDBDATAS

define('DB_HOST', 'localhost');
define('DB_NAME', 'asate_database');
define('DB_USER', 'asate_user');
define('DB_PASSWORD', '');
