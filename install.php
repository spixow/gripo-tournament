<?php
/**
 * Script d'installation :
 *  - crée la base et les tables
 *  - crée les comptes joueurs + admin
 *  - génère les 30 matchs (5 rounds)
 *  - affiche les identifiants
 *
 *  Lancez ce fichier UNE SEULE FOIS depuis le navigateur : /install.php
 */
require_once __DIR__ . '/config/config.php';

$errors = [];
$done   = false;
$credentials = [];

// Liste des joueurs (nom affiché => couleur d'avatar)
$PLAYERS = [
    'Smock'     => '#00e5ff',
    'Dyxow'     => '#ff3d71',
    'Fuska'     => '#ffaa00',
    'Khalil'    => '#00d68f',
    'Lhaaj'     => '#a16eff',
    'Viking'    => '#0095ff',
    'Hajib'     => '#ff6b00',
    'Adam'      => '#ffd200',
    'Nabil'     => '#2ce69b',
    'No Mercy'  => '#ff1f5a',
    'Araknocci' => '#8a5cff',
    'Imad'      => '#00b8d9',
];

// Programme des matchs : round => [ [home, away], ... ]
$SCHEDULE = [
    1 => [
        ['Smock', 'Dyxow'], ['Fuska', 'Khalil'], ['Lhaaj', 'Viking'],
        ['Hajib', 'Adam'],  ['Nabil', 'No Mercy'], ['Araknocci', 'Imad'],
    ],
    2 => [
        ['Smock', 'Lhaaj'], ['Fuska', 'Hajib'], ['Dyxow', 'Adam'],
        ['Khalil', 'Viking'], ['Nabil', 'Araknocci'], ['No Mercy', 'Imad'],
    ],
    3 => [
        ['Smock', 'Nabil'], ['Fuska', 'No Mercy'], ['Dyxow', 'Araknocci'],
        ['Khalil', 'Imad'], ['Lhaaj', 'Hajib'], ['Viking', 'Adam'],
    ],
    4 => [
        ['Viking', 'Smock'], ['Adam', 'Fuska'], ['Lhaaj', 'Dyxow'],
        ['Hajib', 'Khalil'], ['Imad', 'Nabil'], ['No Mercy', 'Araknocci'],
    ],
    5 => [
        ['Imad', 'Smock'], ['Araknocci', 'Fuska'], ['Dyxow', 'No Mercy'],
        ['Khalil', 'Nabil'], ['Adam', 'Lhaaj'], ['Viking', 'Hajib'],
    ],
];

function slugify_username(string $name): string
{
    $u = strtolower($name);
    $u = str_replace(' ', '', $u);
    $u = preg_replace('/[^a-z0-9]/', '', $u);
    return $u;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $port = defined('DB_PORT') ? DB_PORT : '3306';

        // 1) Connexion au serveur MySQL (sans base d'abord)
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . $port . ';charset=' . DB_CHARSET,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Tenter de créer la base (ignoré si l'hébergeur l'interdit, ex. Railway)
            try {
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`'
                    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (Throwable $ignore) { /* base déjà fournie par l'hébergeur */ }
            $pdo->exec('USE `' . DB_NAME . '`');
        } catch (Throwable $e) {
            // Repli : connexion directe à la base déjà existante
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }

        // 2) Créer les tables (sans CREATE DATABASE / USE, gérés ci-dessus)
        $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
        $schema = preg_replace('/^\s*CREATE DATABASE.*?;/mis', '', $schema);
        $schema = preg_replace('/^\s*USE\s+`?[\w]+`?\s*;/mi', '', $schema);
        $pdo->exec($schema);

        // 3) Créer l'admin
        $adminPass = 'admin2026';
        $stmt = $pdo->prepare(
            'INSERT INTO players (username, display_name, password_hash, avatar_color, is_admin)
             VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute(['admin', 'Administrateur', password_hash($adminPass, PASSWORD_DEFAULT), '#ffd700']);
        $credentials[] = ['Administrateur', 'admin', $adminPass, 'Admin'];

        // 4) Créer les joueurs
        $ids = [];
        $insPlayer = $pdo->prepare(
            'INSERT INTO players (username, display_name, password_hash, avatar_color, is_admin)
             VALUES (?, ?, ?, ?, 0)'
        );
        foreach ($PLAYERS as $name => $color) {
            $username = slugify_username($name);
            $password = $username . '2026';
            $insPlayer->execute([$username, $name, password_hash($password, PASSWORD_DEFAULT), $color]);
            $ids[$name] = (int)$pdo->lastInsertId();
            $credentials[] = [$name, $username, $password, 'Joueur'];
        }

        // 5) Créer les matchs
        $insMatch = $pdo->prepare(
            'INSERT INTO matches (round, match_number, home_id, away_id, status)
             VALUES (?, ?, ?, ?, "pending")'
        );
        foreach ($SCHEDULE as $round => $games) {
            $n = 1;
            foreach ($games as [$home, $away]) {
                if (!isset($ids[$home]) || !isset($ids[$away])) {
                    throw new RuntimeException("Joueur introuvable : $home / $away");
                }
                $insMatch->execute([$round, $n++, $ids[$home], $ids[$away]]);
            }
        }

        // Créer le dossier uploads
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0775, true);
        }

        $done = true;
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation — <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#070b18; color:#e8f0ff; font-family:system-ui, sans-serif; }
        .card { background:#0e1730; border:1px solid #1d2b52; }
        code { color:#7cf; }
        .cred-pass { color:#ffd200; }
    </style>
</head>
<body class="py-5">
<div class="container">
    <h1 class="mb-4 text-center">⚙️ Installation — Tournoi FIFA 1v1</h1>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Erreurs :</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($done): ?>
        <div class="alert alert-success">
            ✅ Installation terminée avec succès ! Voici les identifiants générés.
            <strong>Notez-les puis supprimez <code>install.php</code>.</strong>
        </div>
        <div class="card p-3 mb-4">
            <table class="table table-dark table-striped mb-0">
                <thead>
                    <tr><th>Nom</th><th>Nom d'utilisateur</th><th>Mot de passe</th><th>Rôle</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as [$name, $user, $pass, $role]): ?>
                        <tr>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><code><?= htmlspecialchars($user) ?></code></td>
                            <td class="cred-pass"><code class="cred-pass"><?= htmlspecialchars($pass) ?></code></td>
                            <td><?= htmlspecialchars($role) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-center">
            <a href="index.php" class="btn btn-primary btn-lg">Accéder à la plateforme →</a>
        </div>
    <?php else: ?>
        <div class="card p-4">
            <p>Ce script va :</p>
            <ul>
                <li>Créer la base <code><?= DB_NAME ?></code> et ses tables ;</li>
                <li>Créer <strong>12 comptes joueurs</strong> + 1 compte administrateur ;</li>
                <li>Générer les <strong>30 matchs</strong> (5 rounds).</li>
            </ul>
            <div class="alert alert-warning">
                ⚠️ Toute donnée existante dans ces tables sera <strong>écrasée</strong>.
            </div>
            <p>Paramètres actuels : <code><?= DB_USER ?>@<?= DB_HOST ?></code> → base <code><?= DB_NAME ?></code>
               (modifiables dans <code>config/config.php</code>).</p>
            <form method="post">
                <button class="btn btn-success btn-lg w-100">🚀 Lancer l'installation</button>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
