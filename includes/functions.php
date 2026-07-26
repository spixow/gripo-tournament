<?php
/**
 * Fonctions utilitaires de la plateforme
 */
require_once __DIR__ . '/../config/database.php';

/* -------------------- Sécurité / helpers -------------------- */

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/* -------------------- Authentification -------------------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM players WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (empty($u['is_admin'])) {
        flash('danger', "Accès réservé à l'administrateur.");
        redirect('index.php');
    }
    return $u;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/* -------------------- Requêtes données -------------------- */

function get_player(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM players WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function all_players(): array
{
    // Participants au tournoi = joueurs figurant dans au moins un match.
    // (indépendant du statut admin : un participant promu admin reste dans le classement)
    $sql = 'SELECT DISTINCT p.* FROM players p
            JOIN matches m ON p.id = m.home_id OR p.id = m.away_id
            ORDER BY p.display_name';
    $rows = db()->query($sql)->fetchAll();
    if ($rows) {
        return $rows;
    }
    // Repli (aucun match encore créé) : tous les comptes non-admin
    return db()->query('SELECT * FROM players WHERE is_admin = 0 ORDER BY display_name')->fetchAll();
}

function get_match(int $id): ?array
{
    $sql = 'SELECT m.*,
                   h.display_name AS home_name, h.avatar_color AS home_color,
                   a.display_name AS away_name, a.avatar_color AS away_color
            FROM matches m
            JOIN players h ON h.id = m.home_id
            JOIN players a ON a.id = m.away_id
            WHERE m.id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function matches_by_round(): array
{
    $sql = 'SELECT m.*,
                   h.display_name AS home_name, h.avatar_color AS home_color,
                   a.display_name AS away_name, a.avatar_color AS away_color
            FROM matches m
            JOIN players h ON h.id = m.home_id
            JOIN players a ON a.id = m.away_id
            ORDER BY m.round, m.match_number';
    $rows = db()->query($sql)->fetchAll();
    $byRound = [];
    foreach ($rows as $r) {
        $byRound[$r['round']][] = $r;
    }
    return $byRound;
}

function matches_of_player(int $playerId): array
{
    $sql = 'SELECT m.*,
                   h.display_name AS home_name, h.avatar_color AS home_color,
                   a.display_name AS away_name, a.avatar_color AS away_color
            FROM matches m
            JOIN players h ON h.id = m.home_id
            JOIN players a ON a.id = m.away_id
            WHERE m.home_id = ? OR m.away_id = ?
            ORDER BY m.round, m.match_number';
    $stmt = db()->prepare($sql);
    $stmt->execute([$playerId, $playerId]);
    return $stmt->fetchAll();
}

function submissions_of_match(int $matchId): array
{
    $stmt = db()->prepare('SELECT * FROM match_submissions WHERE match_id = ?');
    $stmt->execute([$matchId]);
    $out = [];
    foreach ($stmt->fetchAll() as $s) {
        $out[$s['player_id']] = $s;
    }
    return $out;
}

/* -------------------- Classement (standings) -------------------- */

/**
 * Calcule le classement complet avec départage :
 * 1) Points  2) Diff. buts  3) Buts marqués  4) Confrontation directe
 */
function compute_standings(): array
{
    $players = all_players();
    $table = [];
    foreach ($players as $p) {
        $table[$p['id']] = [
            'id'      => $p['id'],
            'name'    => $p['display_name'],
            'color'   => $p['avatar_color'],
            'played'  => 0,
            'win'     => 0,
            'draw'    => 0,
            'loss'    => 0,
            'gf'      => 0, // buts pour
            'ga'      => 0, // buts contre
            'gd'      => 0, // diff
            'points'  => 0,
        ];
    }

    $completed = db()->query(
        "SELECT * FROM matches WHERE status = 'completed' AND home_score IS NOT NULL"
    )->fetchAll();

    // Pour la confrontation directe
    $h2h = []; // $h2h[a][b] = points de a contre b

    foreach ($completed as $m) {
        $h = $m['home_id'];
        $a = $m['away_id'];
        $hs = (int)$m['home_score'];
        $as = (int)$m['away_score'];
        if (!isset($table[$h]) || !isset($table[$a])) {
            continue;
        }

        $table[$h]['played']++; $table[$a]['played']++;
        $table[$h]['gf'] += $hs; $table[$h]['ga'] += $as;
        $table[$a]['gf'] += $as; $table[$a]['ga'] += $hs;

        if ($hs > $as) {
            $table[$h]['win']++; $table[$h]['points'] += 3;
            $table[$a]['loss']++;
            $h2h[$h][$a] = ($h2h[$h][$a] ?? 0) + 3;
        } elseif ($hs < $as) {
            $table[$a]['win']++; $table[$a]['points'] += 3;
            $table[$h]['loss']++;
            $h2h[$a][$h] = ($h2h[$a][$h] ?? 0) + 3;
        } else {
            $table[$h]['draw']++; $table[$a]['draw']++;
            $table[$h]['points']++; $table[$a]['points']++;
            $h2h[$h][$a] = ($h2h[$h][$a] ?? 0) + 1;
            $h2h[$a][$h] = ($h2h[$a][$h] ?? 0) + 1;
        }
    }

    foreach ($table as &$row) {
        $row['gd'] = $row['gf'] - $row['ga'];
    }
    unset($row);

    $rows = array_values($table);
    usort($rows, function ($x, $y) use ($h2h) {
        if ($x['points'] !== $y['points']) return $y['points'] - $x['points'];
        if ($x['gd'] !== $y['gd'])         return $y['gd'] - $x['gd'];
        if ($x['gf'] !== $y['gf'])         return $y['gf'] - $x['gf'];
        // Confrontation directe
        $xa = $h2h[$x['id']][$y['id']] ?? 0;
        $ya = $h2h[$y['id']][$x['id']] ?? 0;
        if ($xa !== $ya) return $ya - $xa;
        return strcmp($x['name'], $y['name']);
    });

    // Ajouter la position
    $pos = 1;
    foreach ($rows as &$r) {
        $r['rank'] = $pos++;
    }
    unset($r);

    return $rows;
}

/* -------------------- Uploads -------------------- */

function handle_proof_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erreur lors de l'upload de l'image.");
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Image trop volumineuse (max 5 Mo).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format non autorisé (JPG, PNG ou WEBP uniquement).');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = 'proof_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = UPLOAD_DIR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException("Impossible d'enregistrer l'image.");
    }
    return $name;
}

/* -------------------- Étiquettes de statut -------------------- */

function status_label(string $status): array
{
    return match ($status) {
        'completed' => ['Terminé', 'success'],
        'awaiting'  => ['En attente de confirmation', 'warning'],
        'disputed'  => ['Litige', 'danger'],
        default     => ['À jouer', 'secondary'],
    };
}

/* -------------------- Forme récente d'un joueur -------------------- */

/**
 * Retourne les derniers résultats d'un joueur ('W' | 'D' | 'L'),
 * du plus récent au plus ancien.
 */
function player_form(int $playerId, int $limit = 5): array
{
    $sql = "SELECT * FROM matches
            WHERE status = 'completed' AND home_score IS NOT NULL
              AND (home_id = :p1 OR away_id = :p2)
            ORDER BY completed_at DESC, round DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute([':p1' => $playerId, ':p2' => $playerId]);

    $form = [];
    foreach ($stmt->fetchAll() as $m) {
        $isHome = (int)$m['home_id'] === $playerId;
        $mine = $isHome ? (int)$m['home_score'] : (int)$m['away_score'];
        $opp  = $isHome ? (int)$m['away_score'] : (int)$m['home_score'];
        if ($mine > $opp)      $form[] = 'W';
        elseif ($mine < $opp)  $form[] = 'L';
        else                   $form[] = 'D';
        if (count($form) >= $limit) break;
    }
    return $form;
}

/* -------------------- Phase finale (bracket) -------------------- */

/** Définition statique du tableau final. */
function bracket_definition(): array
{
    return [
        'PO1'   => ['stage' => 'playoff', 'label' => 'Barrage 1',     'seed1' => '3ᵉ',              'seed2' => '6ᵉ'],
        'PO2'   => ['stage' => 'playoff', 'label' => 'Barrage 2',     'seed1' => '4ᵉ',              'seed2' => '5ᵉ'],
        'SF1'   => ['stage' => 'semi',    'label' => 'Demi-finale 1', 'seed1' => '1ᵉʳ',             'seed2' => 'Vainqueur Barrage 2'],
        'SF2'   => ['stage' => 'semi',    'label' => 'Demi-finale 2', 'seed1' => '2ᵉ',              'seed2' => 'Vainqueur Barrage 1'],
        'FINAL' => ['stage' => 'final',   'label' => 'Finale',        'seed1' => 'Vainqueur DF 1',  'seed2' => 'Vainqueur DF 2'],
    ];
}

function bracket_generated(): bool
{
    try {
        return (int)db()->query('SELECT COUNT(*) FROM bracket_matches')->fetchColumn() > 0;
    } catch (PDOException $e) {
        // Table absente : la mise à niveau (upgrade.php) n'a pas encore été exécutée
        return false;
    }
}

/** Retourne les matchs du bracket indexés par code, avec noms/couleurs des joueurs. */
function get_bracket(): array
{
    $sql = 'SELECT b.*,
                   p1.display_name AS p1_name, p1.avatar_color AS p1_color, p1.username AS p1_user, p1.photo_url AS p1_photo, p1.position AS p1_pos,
                   p2.display_name AS p2_name, p2.avatar_color AS p2_color, p2.username AS p2_user, p2.photo_url AS p2_photo, p2.position AS p2_pos,
                   w.display_name  AS w_name
            FROM bracket_matches b
            LEFT JOIN players p1 ON p1.id = b.player1_id
            LEFT JOIN players p2 ON p2.id = b.player2_id
            LEFT JOIN players w  ON w.id  = b.winner_id';
    $rows = db()->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['code']] = $r;
    }
    return $out;
}

/** Crée la table du bracket si elle n'existe pas encore. */
function ensure_bracket_table(): void
{
    db()->exec(
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
}

/**
 * (Re)génère le tableau final à partir du classement actuel.
 * Nécessite au moins 6 joueurs classés.
 */
function generate_bracket(): void
{
    ensure_bracket_table();
    $standings = compute_standings();
    if (count($standings) < 6) {
        throw new RuntimeException('Il faut au moins 6 joueurs classés pour générer la phase finale.');
    }
    // Seeds 1..6 (index 0..5)
    $s = [];
    for ($i = 1; $i <= 6; $i++) {
        $s[$i] = (int)$standings[$i - 1]['id'];
    }

    $def = bracket_definition();
    $pdo = db();
    $pdo->exec('DELETE FROM bracket_matches');

    // Joueurs de départ connus
    $players = [
        'PO1'   => [$s[3], $s[6]],
        'PO2'   => [$s[4], $s[5]],
        'SF1'   => [$s[1], null],
        'SF2'   => [$s[2], null],
        'FINAL' => [null, null],
    ];

    $ins = $pdo->prepare(
        'INSERT INTO bracket_matches (code, stage, label, player1_id, player2_id, seed1, seed2, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
    );
    foreach ($def as $code => $d) {
        $ins->execute([
            $code, $d['stage'], $d['label'],
            $players[$code][0], $players[$code][1],
            $d['seed1'], $d['seed2'],
        ]);
    }
}

/** Enregistre le résultat d'un match du bracket et fait avancer le vainqueur. */
function save_bracket_result(string $code, int $s1, int $s2): void
{
    $bracket = get_bracket();
    if (!isset($bracket[$code])) {
        throw new RuntimeException('Match de bracket inconnu.');
    }
    $m = $bracket[$code];
    if (!$m['player1_id'] || !$m['player2_id']) {
        throw new RuntimeException('Les deux joueurs de ce match ne sont pas encore connus.');
    }
    if ($s1 < 0 || $s2 < 0 || $s1 === $s2) {
        throw new RuntimeException('Un match à élimination directe ne peut pas se terminer sur une égalité.');
    }
    $winner = $s1 > $s2 ? (int)$m['player1_id'] : (int)$m['player2_id'];

    $pdo = db();
    $pdo->prepare('UPDATE bracket_matches SET score1=?, score2=?, winner_id=?, status="done" WHERE code=?')
        ->execute([$s1, $s2, $winner, $code]);

    // Avancement du vainqueur
    $advance = [
        'PO1' => ['SF2', 2],  // vainqueur barrage 1 -> place 2 de la DF 2
        'PO2' => ['SF1', 2],  // vainqueur barrage 2 -> place 2 de la DF 1
        'SF1' => ['FINAL', 1],
        'SF2' => ['FINAL', 2],
    ];
    if (isset($advance[$code])) {
        [$next, $slot] = $advance[$code];
        $col = $slot === 1 ? 'player1_id' : 'player2_id';
        // Réinitialise le match suivant s'il avait déjà un résultat (cohérence)
        $pdo->prepare("UPDATE bracket_matches SET {$col}=?, score1=NULL, score2=NULL, winner_id=NULL, status='pending' WHERE code=?")
            ->execute([$winner, $next]);
    }
}

function clear_bracket(): void
{
    db()->exec('DELETE FROM bracket_matches');
}

/** Retourne le nom du champion si la finale est jouée, sinon null. */
function bracket_champion(): ?array
{
    $b = get_bracket();
    if (isset($b['FINAL']) && $b['FINAL']['status'] === 'done' && $b['FINAL']['winner_id']) {
        return get_player((int)$b['FINAL']['winner_id']);
    }
    return null;
}

/* -------------------- Journal d'activité (surveillance) -------------------- */

/** Crée la table du journal si elle n'existe pas. */
function ensure_activity_table(): void
{
    db()->exec(
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
}

/**
 * Enregistre une action utilisateur dans le journal d'activité.
 * Ne lève jamais d'exception (la journalisation ne doit pas casser l'app).
 */
function log_activity(string $action, string $details = '', ?array $user = null): void
{
    $u  = $user ?? current_user();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $params = [
        $u['id'] ?? null,
        $u['username'] ?? ($u['display_name'] ?? null),
        $action,
        mb_substr($details, 0, 255),
        $ip,
    ];
    $sql = 'INSERT INTO activity_log (player_id, username, action, details, ip) VALUES (?,?,?,?,?)';
    try {
        db()->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        // Table absente : on la crée puis on réessaie une fois
        try {
            ensure_activity_table();
            db()->prepare($sql)->execute($params);
        } catch (Throwable $ignore) { /* silencieux */ }
    }
}

/** Récupère les dernières entrées du journal (optionnellement filtrées par joueur). */
function get_activity_log(int $limit = 100, ?int $playerId = null): array
{
    try {
        if ($playerId) {
            $stmt = db()->prepare(
                'SELECT a.*, p.avatar_color FROM activity_log a
                 LEFT JOIN players p ON p.id = a.player_id
                 WHERE a.player_id = ? ORDER BY a.id DESC LIMIT ?'
            );
            $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        } else {
            $stmt = db()->prepare(
                'SELECT a.*, p.avatar_color FROM activity_log a
                 LEFT JOIN players p ON p.id = a.player_id
                 ORDER BY a.id DESC LIMIT ?'
            );
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Libellé + couleur d'un type d'action pour l'affichage. */
function activity_label(string $action): array
{
    return match ($action) {
        'login'           => ['Connexion', 'success'],
        'login_failed'    => ['Échec connexion', 'danger'],
        'logout'          => ['Déconnexion', 'secondary'],
        'score_submit'    => ['Saisie de score', 'info'],
        'match_validated' => ['Match validé', 'success'],
        'match_disputed'  => ['Litige', 'warning'],
        'password_change' => ['Mot de passe', 'primary'],
        'admin_score'     => ['Score forcé (admin)', 'info'],
        'admin_reset'     => ['Reset match (admin)', 'warning'],
        'admin_password'  => ['MDP modifié (admin)', 'primary'],
        'admin_role'      => ['Rôle modifié (admin)', 'danger'],
        'bracket_generate'=> ['Bracket généré', 'info'],
        'bracket_clear'   => ['Bracket supprimé', 'warning'],
        'bracket_score'   => ['Score bracket', 'info'],
        default           => [ucfirst($action), 'secondary'],
    };
}
