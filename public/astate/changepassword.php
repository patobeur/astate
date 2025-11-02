<?php
require("../../private_astate/config.php");
$hash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password !== '') {
        // hash sécurisé
        $hash = password_hash($password, PASSWORD_DEFAULT);
    } else {
        http_response_code(404); // Bad Request
        exit;
    }
}

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
    http_response_code(404); // Bad Request
    exit;
}


// 7. enregistrement du nouveua mot de passe hashé
try {
    $stmt = $pdo->prepare("UPDATE ast_users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, 1]);
} catch (\PDOException $e) {
    http_response_code(404); // Bad Request
    exit;
}



?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Hasher un mot de passe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .box {
            background: #fff;
            padding: 20px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 360px;
        }

        h1 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: .4rem;
        }

        input[type="password"] {
            width: 100%;
            padding: .5rem .6rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            margin-top: 1rem;
            width: 100%;
            padding: .6rem;
            border: none;
            background: #007bff;
            color: #fff;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #0069d9;
        }

        .result {
            margin-top: 1rem;
            background: #eef;
            padding: .6rem .7rem;
            border-radius: 4px;
            word-break: break-all;
            font-family: monospace;
        }

        .hint {
            font-size: .75rem;
            color: #555;
            margin-top: .3rem;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>Hasher un mot de passe</h1>
        <form method="post" action="">
            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required>
            <p class="hint">Le hash sera généré avec <code>password_hash()</code>.</p>
            <button type="submit">Générer le hash</button>
        </form>

        <?php if ($hash): ?>
            <div class="result">
                <?php echo htmlspecialchars($hash, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>