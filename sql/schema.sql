-- =========================================================
--  TOURNOI FIFA 1v1 — Schéma de base de données
-- =========================================================

CREATE DATABASE IF NOT EXISTS `tournoi_fifa`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tournoi_fifa`;

-- ---------- Joueurs / Comptes ----------
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `claim_messages`;
DROP TABLE IF EXISTS `claims`;
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `bracket_matches`;
DROP TABLE IF EXISTS `match_submissions`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `players`;

CREATE TABLE `players` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `username`     VARCHAR(50)  NOT NULL UNIQUE,
  `display_name` VARCHAR(80)  NOT NULL,
  `password_hash`VARCHAR(255) NOT NULL,
  `avatar_color` VARCHAR(7)   NOT NULL DEFAULT '#00e5ff',
  `team`         VARCHAR(60)  NULL,
  `photo_url`    VARCHAR(255) NULL,
  `position`     VARCHAR(5)   NULL,
  `ovr_override` INT          NULL,
  `is_admin`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Matchs ----------
-- status: pending | awaiting | completed | disputed
CREATE TABLE `matches` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `round`        INT NOT NULL,
  `match_number` INT NOT NULL,
  `home_id`      INT NOT NULL,
  `away_id`      INT NOT NULL,
  `home_score`   INT NULL,
  `away_score`   INT NULL,
  `status`       ENUM('pending','awaiting','completed','disputed') NOT NULL DEFAULT 'pending',
  `completed_at` TIMESTAMP NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`home_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`away_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Soumissions de score (une par joueur / match) ----------
CREATE TABLE `match_submissions` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `match_id`      INT NOT NULL,
  `player_id`     INT NOT NULL,
  `home_score`    INT NOT NULL,
  `away_score`    INT NOT NULL,
  `proof_image`   VARCHAR(255) NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_match_player` (`match_id`, `player_id`),
  FOREIGN KEY (`match_id`)  REFERENCES `matches`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`player_id`) REFERENCES `players`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Phase finale (bracket) ----------
-- code: PO1, PO2, SF1, SF2, FINAL   |   stage: playoff | semi | final
CREATE TABLE `bracket_matches` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Journal d'activité (surveillance admin) ----------
CREATE TABLE `activity_log` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `player_id`  INT NULL,
  `username`   VARCHAR(80) NULL,
  `action`     VARCHAR(50) NOT NULL,
  `details`    VARCHAR(255) NULL,
  `ip`         VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_player` (`player_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Réclamations (tickets) ----------
-- status: open | in_progress | resolved | closed
CREATE TABLE `claims` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Messages des réclamations (chat) ----------
CREATE TABLE `claim_messages` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `claim_id`   INT NOT NULL,
  `sender_id`  INT NULL,
  `body`       TEXT NOT NULL,
  `is_system`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`claim_id`)  REFERENCES `claims`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `players`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Paramètres dynamiques (annonce, deadline…) ----------
CREATE TABLE `settings` (
  `k` VARCHAR(50) PRIMARY KEY,
  `v` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
