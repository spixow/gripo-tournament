<?php
/**
 * Mise à niveau de la base pour les installations existantes.
 *  - ajoute les colonnes de personnalisation joueur (photo, poste, note)
 *  - crée la table du bracket (phase finale)
 *
 * Sûr à exécuter plusieurs fois. Lancez : /upgrade.php
 */
require_once __DIR__ . '/config/database.php';

$log = [];
$err = [];

function column_exists(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $pdo = db();

    // 1) Colonnes players
    $newCols = [
        'photo_url'    => "ADD COLUMN `photo_url` VARCHAR(255) NULL AFTER `avatar_color`",
        'position'     => "ADD COLUMN `position` VARCHAR(5) NULL AFTER `photo_url`",
        'ovr_override' => "ADD COLUMN `ovr_override` INT NULL AFTER `position`",
        'team'         => "ADD COLUMN `team` VARCHAR(60) NULL AFTER `avatar_color`",
    ];
    foreach ($newCols as $col => $ddl) {
        if (!column_exists($pdo, 'players', $col)) {
            $pdo->exec("ALTER TABLE `players` $ddl");
            $log[] = "Colonne players.$col ajoutée.";
        } else {
            $log[] = "Colonne players.$col déjà présente.";
        }
    }

    // 2) Table bracket_matches
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `bracket_matches` (
          `id`         INT AUTO_INCREMENT PRIMARY KEY,
          `code`       VARCHAR(10) NOT NULL UNIQUE,
          `stage`      VARCHAR(10) NOT NULL,
          `label`      VARCHAR(60) NOT NULL,
          `player1_id` INT NULL,
          `player2_id` INT NULL,
          `seed1`      VARCHAR(30) NULL,
          `seed2`      VARCHAR(30) NULL,
          `score1`     INT NULL,
          `score2`     INT NULL,
          `winner_id`  INT NULL,
          `status`     VARCHAR(10) NOT NULL DEFAULT 'pending',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`player1_id`) REFERENCES `players`(`id`) ON DELETE SET NULL,
          FOREIGN KEY (`player2_id`) REFERENCES `players`(`id`) ON DELETE SET NULL,
          FOREIGN KEY (`winner_id`)  REFERENCES `players`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $log[] = "Table bracket_matches prête.";

    // 3) Table du journal d'activité
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `activity_log` (
          `id`         INT AUTO_INCREMENT PRIMARY KEY,
          `player_id`  INT NULL,
          `username`   VARCHAR(80) NULL,
          `action`     VARCHAR(50) NOT NULL,
          `details`    VARCHAR(255) NULL,
          `ip`         VARCHAR(45) NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_player` (`player_id`),
          INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $log[] = "Table activity_log prête.";

    // 4) Tables des réclamations (tickets + chat)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `claims` (
          `id`         INT AUTO_INCREMENT PRIMARY KEY,
          `match_id`   INT NOT NULL,
          `opened_by`  INT NULL,
          `reason`     VARCHAR(255) NOT NULL,
          `status`     VARCHAR(15) NOT NULL DEFAULT 'open',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `closed_at`  TIMESTAMP NULL,
          FOREIGN KEY (`match_id`)  REFERENCES `matches`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`opened_by`) REFERENCES `players`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `claim_messages` (
          `id`         INT AUTO_INCREMENT PRIMARY KEY,
          `claim_id`   INT NOT NULL,
          `sender_id`  INT NULL,
          `body`       TEXT NOT NULL,
          `is_system`  TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`claim_id`)  REFERENCES `claims`(`id`)  ON DELETE CASCADE,
          FOREIGN KEY (`sender_id`) REFERENCES `players`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $log[] = "Tables claims / claim_messages prêtes.";

    // 5) Table des paramètres dynamiques
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `settings` (
          `k` VARCHAR(50) PRIMARY KEY,
          `v` TEXT NULL,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $log[] = "Table settings prête.";
} catch (Throwable $ex) {
    $err[] = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mise à niveau — Gripo Tournament</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#05080a;color:#eafff5;font-family:system-ui,sans-serif;padding:3rem} .card{background:#0b1511;border:1px solid #1c3a2a} code{color:#7dffb8}</style>
</head><body>
<div class="container" style="max-width:720px">
    <h1 class="mb-4">🔧 Mise à niveau de la base</h1>
    <?php if ($err): ?>
        <div class="alert alert-danger"><strong>Erreur :</strong><ul class="mb-0">
            <?php foreach ($err as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul></div>
    <?php else: ?>
        <div class="alert alert-success">✅ Mise à niveau terminée.</div>
        <div class="card p-3"><ul class="mb-0">
            <?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?>
        </ul></div>
        <p class="mt-3">Vous pouvez maintenant <strong>supprimer <code>upgrade.php</code></strong> puis
           <a href="admin.php" class="text-info">aller au panneau admin</a>.</p>
    <?php endif; ?>
</div>
</body></html>
