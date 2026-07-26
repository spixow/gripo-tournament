<?php
require_once __DIR__ . '/includes/functions.php';
$admin = require_admin();
$page = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash('danger', 'Jeton de sécurité invalide.');
        redirect('admin.php');
    }
    $action = $_POST['action'] ?? '';
    $matchId = (int)($_POST['match_id'] ?? 0);

    if ($action === 'force_score') {
        $hs = filter_input(INPUT_POST, 'home_score', FILTER_VALIDATE_INT);
        $as = filter_input(INPUT_POST, 'away_score', FILTER_VALIDATE_INT);
        if ($hs === false || $as === false || $hs === null || $as === null || $hs < 0 || $as < 0) {
            flash('danger', 'Scores invalides.');
        } else {
            db()->prepare("UPDATE matches SET home_score=?, away_score=?, status='completed', completed_at=NOW() WHERE id=?")
                ->execute([$hs, $as, $matchId]);
            flash('success', "Score forcé pour le match #$matchId.");
        }
    } elseif ($action === 'reset') {
        db()->prepare("UPDATE matches SET home_score=NULL, away_score=NULL, status='pending', completed_at=NULL WHERE id=?")
            ->execute([$matchId]);
        db()->prepare("DELETE FROM match_submissions WHERE match_id=?")->execute([$matchId]);
        flash('warning', "Match #$matchId réinitialisé.");
    } elseif ($action === 'set_password') {
        $pid = (int)($_POST['player_id'] ?? 0);
        $new = $_POST['new_password'] ?? '';
        $target = get_player($pid);
        if (!$target) {
            flash('danger', 'Compte introuvable.');
        } elseif (strlen($new) < 6) {
            flash('danger', 'Le mot de passe doit contenir au moins 6 caractères.');
        } else {
            db()->prepare('UPDATE players SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $pid]);
            flash('success', 'Mot de passe mis à jour pour ' . $target['display_name'] . '.');
        }
    } elseif ($action === 'gen_bracket') {
        try {
            generate_bracket();
            flash('success', 'Phase finale générée depuis le classement actuel.');
        } catch (Throwable $ex) {
            flash('danger', $ex->getMessage());
        }
    } elseif ($action === 'clear_bracket') {
        clear_bracket();
        flash('warning', 'Phase finale réinitialisée.');
    } elseif ($action === 'bracket_score') {
        $code = $_POST['code'] ?? '';
        $s1 = filter_input(INPUT_POST, 'score1', FILTER_VALIDATE_INT);
        $s2 = filter_input(INPUT_POST, 'score2', FILTER_VALIDATE_INT);
        if ($s1 === false || $s2 === false || $s1 === null || $s2 === null) {
            flash('danger', 'Scores invalides.');
        } else {
            try {
                save_bracket_result($code, $s1, $s2);
                flash('success', "Résultat enregistré ($code).");
            } catch (Throwable $ex) {
                flash('danger', $ex->getMessage());
            }
        }
    }
    redirect('admin.php');
}

$disputed = array_filter(
    call_user_func(function () {
        $all = [];
        foreach (matches_by_round() as $games) { foreach ($games as $g) $all[] = $g; }
        return $all;
    }),
    fn($m) => in_array($m['status'], ['disputed','awaiting'], true)
);

$allMatches = [];
foreach (matches_by_round() as $games) { foreach ($games as $g) $allMatches[] = $g; }

$bracket = bracket_generated() ? get_bracket() : [];
$accounts = db()->query('SELECT * FROM players ORDER BY is_admin DESC, display_name')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h2 class="hero-title mb-4" style="font-size:1.6rem">🛠️ Panneau administrateur</h2>

<div class="glass mb-4">
    <div class="card-header-fifa">⚠️ Litiges &amp; matchs en attente (<?= count($disputed) ?>)</div>
    <div class="p-3">
        <?php if (empty($disputed)): ?>
            <p class="text-secondary mb-0">Aucun litige en cours. 🎉</p>
        <?php else: foreach ($disputed as $m):
            $subs = submissions_of_match($m['id']);
            $hSub = $subs[$m['home_id']] ?? null;
            $aSub = $subs[$m['away_id']] ?? null;
            [$lbl,$col] = status_label($m['status']); ?>
            <div class="match-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>R<?= (int)$m['round'] ?> · <?= e($m['home_name']) ?> vs <?= e($m['away_name']) ?></strong>
                    <span class="badge text-bg-<?= $col ?>"><?= e($lbl) ?></span>
                </div>
                <div class="row small mb-3">
                    <div class="col-6">
                        <div class="text-secondary"><?= e($m['home_name']) ?> déclare :</div>
                        <?php if ($hSub): ?>
                            <span class="score-pill"><?= (int)$hSub['home_score'] ?> : <?= (int)$hSub['away_score'] ?></span>
                            <?php if ($hSub['proof_image']): ?>
                                <a href="<?= UPLOAD_URL.e($hSub['proof_image']) ?>" target="_blank" class="d-block mt-1">
                                    <img src="<?= UPLOAD_URL.e($hSub['proof_image']) ?>" class="proof-thumb" style="max-height:120px">
                                </a>
                            <?php endif; ?>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </div>
                    <div class="col-6">
                        <div class="text-secondary"><?= e($m['away_name']) ?> déclare :</div>
                        <?php if ($aSub): ?>
                            <span class="score-pill"><?= (int)$aSub['home_score'] ?> : <?= (int)$aSub['away_score'] ?></span>
                            <?php if ($aSub['proof_image']): ?>
                                <a href="<?= UPLOAD_URL.e($aSub['proof_image']) ?>" target="_blank" class="d-block mt-1">
                                    <img src="<?= UPLOAD_URL.e($aSub['proof_image']) ?>" class="proof-thumb" style="max-height:120px">
                                </a>
                            <?php endif; ?>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </div>
                </div>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="match_id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="action" value="force_score">
                    <div class="col-auto">
                        <label class="form-label small mb-0"><?= e($m['home_name']) ?></label>
                        <input type="number" min="0" name="home_score" class="form-control form-control-sm" style="width:80px" required>
                    </div>
                    <div class="col-auto pb-1">:</div>
                    <div class="col-auto">
                        <label class="form-label small mb-0"><?= e($m['away_name']) ?></label>
                        <input type="number" min="0" name="away_score" class="form-control form-control-sm" style="width:80px" required>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-fifa">Valider ce score</button>
                    </div>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="glass">
    <div class="card-header-fifa">📋 Tous les matchs — saisie directe</div>
    <div class="table-responsive">
        <table class="table table-fifa mb-0 align-middle">
            <thead><tr><th>R</th><th>Match</th><th class="text-center">Saisir le score</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($allMatches as $m): [$lbl,$col]=status_label($m['status']); ?>
                <tr>
                    <td>R<?= (int)$m['round'] ?></td>
                    <td><?= e($m['home_name']) ?> <span class="vs-badge">vs</span> <?= e($m['away_name']) ?></td>
                    <td>
                        <form method="post" class="d-flex align-items-center justify-content-center gap-1">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="match_id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="action" value="force_score">
                            <input type="number" min="0" name="home_score" class="form-control form-control-sm text-center" style="width:58px"
                                   value="<?= $m['home_score'] !== null ? (int)$m['home_score'] : '' ?>" required>
                            <span class="vs-badge">:</span>
                            <input type="number" min="0" name="away_score" class="form-control form-control-sm text-center" style="width:58px"
                                   value="<?= $m['away_score'] !== null ? (int)$m['away_score'] : '' ?>" required>
                            <button class="btn btn-sm btn-fifa">OK</button>
                        </form>
                    </td>
                    <td class="text-center"><span class="badge text-bg-<?= $col ?>"><?= e($lbl) ?></span></td>
                    <td class="text-end">
                        <a href="match.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-info">Voir</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Réinitialiser ce match ? Les soumissions seront supprimées.');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="match_id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="action" value="reset">
                            <button class="btn btn-sm btn-outline-danger">Reset</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ Gestion des comptes ============ -->
<div class="glass mt-4">
    <div class="card-header-fifa">🔑 Comptes — réinitialiser les mots de passe</div>
    <div class="p-3">
        <p class="text-secondary small">Définissez un nouveau mot de passe pour n'importe quel compte (6 caractères min.).</p>
        <div class="table-responsive">
            <table class="table table-fifa mb-0 align-middle">
                <thead><tr><th>Compte</th><th>Nom d'utilisateur</th><th>Rôle</th><th>Nouveau mot de passe</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $acc): ?>
                    <tr>
                        <td class="fw-semibold" style="white-space:nowrap">
                            <span class="avatar me-1" style="background:<?= e($acc['avatar_color']) ?>;width:26px;height:26px;font-size:.75rem">
                                <?= e(mb_strtoupper(mb_substr($acc['display_name'],0,1))) ?>
                            </span>
                            <?= e($acc['display_name']) ?>
                        </td>
                        <td><code><?= e($acc['username']) ?></code></td>
                        <td><?= $acc['is_admin'] ? '<span class="badge text-bg-warning">Admin</span>' : '<span class="badge text-bg-secondary">Joueur</span>' ?></td>
                        <td style="min-width:280px">
                            <form method="post" class="d-flex gap-1">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_password">
                                <input type="hidden" name="player_id" value="<?= (int)$acc['id'] ?>">
                                <input type="text" name="new_password" class="form-control form-control-sm"
                                       placeholder="Nouveau mot de passe" minlength="6" required>
                                <button class="btn btn-sm btn-fifa">Changer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ Gestion de la phase finale ============ -->
<div class="glass mt-4">
    <div class="card-header-fifa">
        🏆 Phase finale — bracket
        <div class="ms-auto d-flex gap-2">
            <form method="post" onsubmit="return confirm('(Re)générer la phase finale à partir du classement actuel ? Les résultats du bracket existant seront perdus.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="gen_bracket">
                <button class="btn btn-sm btn-fifa"><?= $bracket ? 'Regénérer' : 'Générer' ?></button>
            </form>
            <?php if ($bracket): ?>
                <form method="post" onsubmit="return confirm('Supprimer complètement la phase finale ?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="clear_bracket">
                    <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="p-3">
        <?php if (!$bracket): ?>
            <p class="text-secondary mb-0">Aucune phase finale générée. Cliquez sur « Générer » une fois la phase de ligue suffisamment avancée (6 joueurs classés requis).</p>
        <?php else:
            $order = ['PO1','PO2','SF1','SF2','FINAL'];
            foreach ($order as $code):
                if (!isset($bracket[$code])) continue;
                $b = $bracket[$code];
                $p1 = $b['p1_name'] ?? ($b['seed1'] ?? '—');
                $p2 = $b['p2_name'] ?? ($b['seed2'] ?? '—');
                $ready = $b['player1_id'] && $b['player2_id'];
                ?>
                <div class="match-card p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge text-bg-dark me-2"><?= e($b['label']) ?></span>
                            <strong><?= e($p1) ?></strong> <span class="vs-badge">vs</span> <strong><?= e($p2) ?></strong>
                            <?php if ($b['status']==='done'): ?>
                                <span class="score-pill ms-2"><?= (int)$b['score1'] ?> : <?= (int)$b['score2'] ?></span>
                                <span class="badge text-bg-success ms-1">Vainqueur : <?= e($b['w_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($ready): ?>
                            <form method="post" class="row g-1 align-items-center">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="bracket_score">
                                <input type="hidden" name="code" value="<?= e($code) ?>">
                                <div class="col-auto"><input type="number" min="0" name="score1" class="form-control form-control-sm" style="width:64px" value="<?= $b['score1'] !== null ? (int)$b['score1'] : '' ?>" required></div>
                                <div class="col-auto">:</div>
                                <div class="col-auto"><input type="number" min="0" name="score2" class="form-control form-control-sm" style="width:64px" value="<?= $b['score2'] !== null ? (int)$b['score2'] : '' ?>" required></div>
                                <div class="col-auto"><button class="btn btn-sm btn-fifa">Valider</button></div>
                            </form>
                        <?php else: ?>
                            <span class="text-secondary small">En attente des qualifiés…</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <a href="bracket.php" class="btn btn-sm btn-outline-info mt-2">Voir le tableau visuel →</a>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
