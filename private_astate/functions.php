<?php

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
function chiffrer_array(array $donnees, $password, $CIPHER)
{
    // 7. on chiffre chaque valeur
    $donnees_chiffrees = [];
    foreach ($donnees as $k => $v) {
        $donnees_chiffrees[$k] = chiffrer_valeur((string)$v, $password, $CIPHER);
    }
    return $donnees_chiffrees;
}

// --- Fonctions pour le chiffrement/déchiffrement avec la MASTER_KEY ---

/**
 * Chiffre une valeur avec la MASTER_KEY.
 */
function master_encrypt(string $value, string $cipher): string
{
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($value, $cipher, MASTER_KEY, OPENSSL_RAW_DATA, $iv);

    return base64_encode($iv . $encrypted);
}

/**
 * Déchiffre une valeur avec la MASTER_KEY.
 */
function master_decrypt(string $base64, string $cipher): string|false
{
    $data = base64_decode($base64);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivlen);
    $encrypted = substr($data, $ivlen);

    return openssl_decrypt($encrypted, $cipher, MASTER_KEY, OPENSSL_RAW_DATA, $iv);
}
