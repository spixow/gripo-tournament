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
            log_activity('admin_score', "Match #$matchId foré à $hs:$as");
            flash('success', "Score forcé pour le match #$matchId.");
        }
    } elseif ($action === 'reset') {
        db()->prepare("UPDATE matches SET home_score=NULL, away_score=NULL, status='pending', completed_at=NULL WHERE id=?")
            ->execute([$matchId]);
        db()->prepare("DELETE FROM match_submissions WHERE match_id=?")->execute([$matchId]);
        log_activity('admin_reset', "Match #$matchId réinitialisé");
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
            log_activity('admin_password', 'Mot de passe modifié pour ' . $target['display_name'] . ' (' . $target['username'] . ')');
            flash('success', 'Mot de passe mis à jour pour ' . $target['display_name'] . '.');
        }
    } elseif ($action === 'toggle_admin') {
        $pid = (int)($_POST['player_id'] ?? 0);
        $target = get_player($pid);
        if (!$target) {
            flash('danger', 'Compte introuvable.');
        } elseif ($pid === (int)$admin['id']) {
            flash('danger', 'Vous ne pouvez pas modifier votre propre statut administrateur.');
        } else {
            $makeAdmin = empty($target['is_admin']) ? 1 : 0;
            if (!$makeAdmin) {
                $adminCount = (int)db()->query('SELECT COUNT(*) FROM players WHERE is_admin = 1')->fetchColumn();
                if ($adminCount <= 1) {
                    flash('danger', 'Impossible : au moins un administrateur doit rester.');
                    redirect('admin.php');
                }
            }
            db()->prepare('UPDATE players SET is_admin = ? WHERE id = ?')->execute([$makeAdmin, $pid]);
            log_activity('admin_role', ($makeAdmin ? 'Promu administrateur : ' : 'Rétrogradé joueur : ')
                . $target['display_name'] . ' (' . $target['username'] . ')');
            flash('success', $target['display_name'] . ($makeAdmin ? ' est désormais administrateur.' : ' est redevenu joueur.'));
        }
    } elseif ($action === 'save_announcement') {
        $text   = trim($_POST['announcement'] ?? '');
        $active = isset($_POST['announcement_active']) ? '1' : '0';
        set_setting('announcement', mb_substr($text, 0, 500));
        set_setting('announcement_active', $active);
        log_activity('settings', 'Annonce ' . ($active === '1' && $text !== '' ? 'publiée' : 'masquée'));
        flash('success', 'Annonce enregistrée.');
    } elseif ($action === 'save_deadline') {
        $d = trim($_POST['deadline_date'] ?? '');
        $t = trim($_POST['deadline_time'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !preg_match('/^\d{2}:\d{2}$/', $t)) {
            flash('danger', 'Date ou heure invalide.');
        } else {
            set_setting('deadline_date', $d);
            set_setting('deadline_time', $t . ':00');
            log_activity('settings', "Deadline modifiée : $d $t");
            flash('success', 'Deadline mise à jour.');
        }
    } elseif ($action === 'reset_tournament') {
        reset_tournament_results();
        log_activity('tournament_reset', 'Tournoi réinitialisé (résultats effacés)');
        flash('warning', 'Tournoi réinitialisé : tous les matchs sont de nouveau « à jouer ».');
    } elseif ($action === 'regenerate_matches') {
        try {
            $n = regenerate_group_matches();
            log_activity('tournament_reset', "Matchs de groupe régénérés ($n matchs)");
            flash('success', "Matchs de la phase de groupe régénérés ($n rencontres).");
        } catch (Throwable $ex) {
            flash('danger', 'Erreur : ' . $ex->getMessage());
        }
    } elseif ($action === 'set_team') {
        ensure_team_column();
        $pid  = (int)($_POST['player_id'] ?? 0);
        $team = trim($_POST['team'] ?? '');
        $target = get_player($pid);
        if (!$target) {
            flash('danger', 'Joueur introuvable.');
        } else {
            db()->prepare('UPDATE players SET team = ? WHERE id = ?')
                ->execute([$team !== '' ? mb_substr($team, 0, 60) : null, $pid]);
            log_activity('set_team', $target['display_name'] . ' → ' . ($team !== '' ? $team : '(aucune)'));
            flash('success', 'Équipe mise à jour pour ' . $target['display_name'] . '.');
        }
    } elseif ($action === 'gen_bracket') {
        try {
            generate_bracket();
            log_activity('bracket_generate', 'Phase finale générée');
            flash('success', 'Phase finale générée depuis le classement actuel.');
        } catch (Throwable $ex) {
            flash('danger', $ex->getMessage());
        }
    } elseif ($action === 'clear_bracket') {
        clear_bracket();
        log_activity('bracket_clear', 'Phase finale supprimée');
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
                log_activity('bracket_score', "$code : $s1:$s2");
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
ensure_team_column();
$accounts = db()->query('SELECT * FROM players ORDER BY is_admin DESC, display_name')->fetchAll();

$activityFilter = (int)($_GET['activity_player'] ?? 0) ?: null;
$activityLog = get_activity_log(120, $activityFilter);

$summary = tournament_summary();
$deadlinePassed = deadline_passed();

require __DIR__ . '/includes/header.php';
?>
<h2 class="hero-title mb-4" style="font-size:1.6rem">🛠️ Panneau administrateur</h2>

<!-- ============ Résumé du tournoi ============ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="glass p-3 text-center h-100">
            <div class="h3 mb-0" style="color:var(--cyan)"><?= $summary['pct'] ?>%</div>
            <div class="small text-secondary"><?= $summary['played'] ?>/<?= $summary['total'] ?> matchs joués</div>
            <div class="progress mt-2" style="height:6px;background:rgba(0,0,0,.08)">
                <div class="progress-bar" style="width:<?= $summary['pct'] ?>%;background:var(--grad)"></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="glass p-3 text-center h-100">
            <div class="h3 mb-0" style="color:var(--gold)"><?= $summary['remaining'] ?></div>
            <div class="small text-secondary">Matchs restants</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="glass p-3 text-center h-100">
            <div class="h3 mb-0" style="color:<?= $summary['disputed']?'var(--pink)':'var(--green)' ?>"><?= $summary['disputed'] ?></div>
            <div class="small text-secondary">Litige(s)<?= $summary['awaiting'] ? ' · '.$summary['awaiting'].' en attente' : '' ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="glass p-3 text-center h-100">
            <div class="h3 mb-0" style="color:<?= $deadlinePassed?'var(--pink)':'var(--cyan)' ?>"><?= e(app_deadline_date()) ?></div>
            <div class="small text-secondary"><?= $deadlinePassed ? '⚠️ Deadline dépassée' : 'Échéance' ?></div>
        </div>
    </div>
</div>

<!-- ============ Réglages : annonce & deadline ============ -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="glass h-100">
            <div class="card-header-fifa">📢 Annonce (bannière visible par tous)</div>
            <div class="p-3">
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_announcement">
                    <textarea name="announcement" class="form-control mb-2" rows="3" maxlength="500"
                              placeholder="Ex : Rappel — merci de jouer vos matchs avant la deadline !"><?= e(get_setting('announcement', '') ?? '') ?></textarea>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="announcement_active" id="annact"
                               <?= get_setting('announcement_active','0')==='1' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="annact">Afficher l'annonce sur tout le site</label>
                    </div>
                    <button class="btn btn-sm btn-fifa">Enregistrer l'annonce</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="glass h-100">
            <div class="card-header-fifa">🗓️ Deadline du tournoi</div>
            <div class="p-3">
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_deadline">
                    <div class="col-7">
                        <label class="form-label small mb-1">Date</label>
                        <input type="date" name="deadline_date" class="form-control" value="<?= e(app_deadline_date()) ?>" required>
                    </div>
                    <div class="col-5">
                        <label class="form-label small mb-1">Heure</label>
                        <input type="time" name="deadline_time" class="form-control" value="<?= e(substr(app_deadline_time(),0,5)) ?>" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-sm btn-fifa w-100">Mettre à jour la deadline</button>
                    </div>
                    <div class="col-12">
                        <span class="small text-secondary">Actuelle : <strong style="color:var(--txt)"><?= e(app_deadline_date()) ?> à <?= e(substr(app_deadline_time(),0,5)) ?></strong></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============ Gestion du tournoi ============ -->
<div class="glass mb-4">
    <div class="card-header-fifa">🔄 Gestion du tournoi</div>
    <div class="p-3 d-flex flex-wrap gap-2 align-items-center">
        <form method="post" onsubmit="return confirm('Réinitialiser le tournoi ? Tous les scores, soumissions, réclamations et la phase finale seront effacés. Les matchs repassent « à jouer ».');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reset_tournament">
            <button class="btn btn-sm btn-outline-danger">♻️ Réinitialiser le tournoi</button>
        </form>
        <form method="post" onsubmit="return confirm('Régénérer TOUS les matchs de la phase de groupe ? Les matchs actuels et leurs données seront supprimés puis recréés.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="regenerate_matches">
            <button class="btn btn-sm btn-fifa">🔁 Régénérer les matchs du groupe</button>
        </form>
        <span class="text-secondary small ms-auto">
            « Réinitialiser » efface les résultats (garde les matchs). « Régénérer » recrée les 30 rencontres du programme officiel.
        </span>
    </div>
</div>

<!-- ============ Surveillance de l'activité ============ -->
<div class="glass mb-4">
    <div class="card-header-fifa">
        🕵️ Surveillance de l'activité
        <form method="get" class="ms-auto d-flex align-items-center gap-2">
            <select name="activity_player" class="form-select form-select-sm" style="min-width:170px" onchange="this.form.submit()">
                <option value="0">Tous les utilisateurs</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int)$acc['id'] ?>" <?= $activityFilter === (int)$acc['id'] ? 'selected' : '' ?>>
                        <?= e($acc['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="p-3">
        <?php if (empty($activityLog)): ?>
            <p class="text-secondary mb-0">Aucune activité enregistrée pour le moment.</p>
        <?php else: ?>
            <div class="table-responsive" style="max-height:460px;overflow:auto">
                <table class="table table-fifa mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="white-space:nowrap">Date / heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Détails</th>
                            <th class="d-none d-md-table-cell">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLog as $a):
                            [$lbl, $col] = activity_label($a['action']);
                            $when = date('d/m/Y H:i', strtotime($a['created_at']));
                            $uname = $a['username'] ?? '—';
                            $color = $a['avatar_color'] ?? '#889';
                            ?>
                            <tr>
                                <td class="small text-secondary" style="white-space:nowrap"><?= e($when) ?></td>
                                <td style="white-space:nowrap">
                                    <span class="avatar me-1" style="background:<?= e($color) ?>;width:24px;height:24px;font-size:.7rem">
                                        <?= e(mb_strtoupper(mb_substr($uname, 0, 1))) ?>
                                    </span>
                                    <?= e($uname) ?>
                                </td>
                                <td><span class="badge text-bg-<?= $col ?>"><?= e($lbl) ?></span></td>
                                <td class="small"><?= e($a['details'] ?? '') ?></td>
                                <td class="small text-secondary d-none d-md-table-cell"><?= e($a['ip'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="small text-secondary mt-2">
                <?= count($activityLog) ?> dernière(s) action(s) affichée(s)<?= $activityFilter ? ' (filtré)' : '' ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

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
                                <a href="proof.php?id=<?= (int)$hSub['id'] ?>" target="_blank" class="d-block mt-1">
                                    <img src="proof.php?id=<?= (int)$hSub['id'] ?>" class="proof-thumb" style="max-height:120px" loading="lazy">
                                </a>
                            <?php endif; ?>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </div>
                    <div class="col-6">
                        <div class="text-secondary"><?= e($m['away_name']) ?> déclare :</div>
                        <?php if ($aSub): ?>
                            <span class="score-pill"><?= (int)$aSub['home_score'] ?> : <?= (int)$aSub['away_score'] ?></span>
                            <?php if ($aSub['proof_image']): ?>
                                <a href="proof.php?id=<?= (int)$aSub['id'] ?>" target="_blank" class="d-block mt-1">
                                    <img src="proof.php?id=<?= (int)$aSub['id'] ?>" class="proof-thumb" style="max-height:120px" loading="lazy">
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

<div class="glass" id="matchs">
    <?php
    $mstatus = $_GET['mstatus'] ?? '';
    $validStatuses = ['pending', 'awaiting', 'disputed', 'completed'];
    if (!in_array($mstatus, $validStatuses, true)) { $mstatus = ''; }
    // Comptes par statut
    $counts = ['' => count($allMatches), 'pending' => 0, 'awaiting' => 0, 'disputed' => 0, 'completed' => 0];
    foreach ($allMatches as $mm) { $counts[$mm['status']] = ($counts[$mm['status']] ?? 0) + 1; }
    $filteredMatches = $mstatus ? array_values(array_filter($allMatches, fn($m) => $m['status'] === $mstatus)) : $allMatches;
    $qsActivity = $activityFilter ? '&activity_player=' . (int)$activityFilter : '';
    $filters = ['' => 'Tous', 'pending' => 'À jouer', 'awaiting' => 'En attente', 'disputed' => 'Litige', 'completed' => 'Terminé'];
    ?>
    <div class="card-header-fifa">
        📋 Tous les matchs — saisie directe
        <div class="btn-group btn-group-sm ms-auto flex-wrap" role="group">
            <?php foreach ($filters as $val => $lbl): ?>
                <a href="admin.php?mstatus=<?= e($val) ?><?= $qsActivity ?>#matchs"
                   class="btn <?= $mstatus === $val ? 'btn-fifa' : 'btn-outline-info' ?>">
                    <?= e($lbl) ?> <span class="badge text-bg-dark"><?= (int)($counts[$val] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-fifa mb-0 align-middle">
            <thead><tr><th>R</th><th>Match</th><th class="text-center">Saisir le score</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($filteredMatches)): ?>
                <tr><td colspan="5" class="text-center text-secondary py-3">Aucun match avec ce statut.</td></tr>
            <?php else: foreach ($filteredMatches as $m): [$lbl,$col]=status_label($m['status']); ?>
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
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ Affectation des équipes ============ -->
<div class="glass mt-4">
    <div class="card-header-fifa">🎽 Affectation des équipes (FIFA)</div>
    <div class="p-3">
        <p class="text-secondary small">
            Attribue une équipe à chaque joueur. Cette information n'est <strong>pas modifiable par le joueur</strong>.
        </p>
        <div class="table-responsive">
            <table class="table table-fifa mb-0 align-middle">
                <thead><tr><th>Joueur</th><th>Équipe</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $acc): if (!empty($acc['is_admin'])) continue; ?>
                    <tr>
                        <td class="fw-semibold" style="white-space:nowrap">
                            <span class="avatar me-1" style="background:<?= e($acc['avatar_color']) ?>;width:26px;height:26px;font-size:.75rem">
                                <?= e(mb_strtoupper(mb_substr($acc['display_name'],0,1))) ?>
                            </span>
                            <?= e($acc['display_name']) ?>
                        </td>
                        <td style="min-width:280px">
                            <form method="post" class="d-flex gap-1">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_team">
                                <input type="hidden" name="player_id" value="<?= (int)$acc['id'] ?>">
                                <input type="text" name="team" class="form-control form-control-sm" maxlength="60"
                                       placeholder="ex : Real Madrid, PSG…" value="<?= e($acc['team'] ?? '') ?>">
                                <button class="btn btn-sm btn-fifa">Enregistrer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ Gestion des comptes ============ -->
<div class="glass mt-4">
    <div class="card-header-fifa">🔑 Comptes — rôles &amp; mots de passe</div>
    <div class="p-3">
        <p class="text-secondary small">
            Réinitialisez le mot de passe de n'importe quel compte (6 caractères min.)
            et accordez ou retirez les droits <strong>administrateur</strong>.
        </p>
        <div class="table-responsive">
            <table class="table table-fifa mb-0 align-middle">
                <thead><tr><th>Compte</th><th>Nom d'utilisateur</th><th>Rôle</th><th>Nouveau mot de passe</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $acc):
                    $isSelf = (int)$acc['id'] === (int)$admin['id']; ?>
                    <tr>
                        <td class="fw-semibold" style="white-space:nowrap">
                            <span class="avatar me-1" style="background:<?= e($acc['avatar_color']) ?>;width:26px;height:26px;font-size:.75rem">
                                <?= e(mb_strtoupper(mb_substr($acc['display_name'],0,1))) ?>
                            </span>
                            <?= e($acc['display_name']) ?>
                        </td>
                        <td><code><?= e($acc['username']) ?></code></td>
                        <td style="white-space:nowrap">
                            <?php if ($acc['is_admin']): ?>
                                <span class="badge text-bg-warning">Admin</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Joueur</span>
                            <?php endif; ?>
                            <?php if ($isSelf): ?>
                                <span class="text-secondary small ms-1">(vous)</span>
                            <?php else: ?>
                                <form method="post" class="d-inline"
                                      onsubmit="return confirm('<?= $acc['is_admin'] ? 'Retirer les droits admin à ' : 'Donner les droits admin à ' ?><?= e($acc['display_name']) ?> ?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="player_id" value="<?= (int)$acc['id'] ?>">
                                    <button class="btn btn-sm <?= $acc['is_admin'] ? 'btn-outline-danger' : 'btn-outline-info' ?> ms-1">
                                        <?= $acc['is_admin'] ? 'Retirer admin' : 'Rendre admin' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
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

<!-- ============ Sauvegarde / export ============ -->
<div class="glass mt-4">
    <div class="card-header-fifa">💾 Sauvegarde &amp; export</div>
    <div class="p-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="export.php?type=sql" class="btn btn-sm btn-fifa">⬇️ Sauvegarde SQL complète</a>
        <a href="export.php?type=standings" class="btn btn-sm btn-outline-info">📊 Classement (CSV)</a>
        <a href="export.php?type=matches" class="btn btn-sm btn-outline-info">📅 Matchs (CSV)</a>
        <span class="text-secondary small ms-auto">La sauvegarde SQL contient toutes les données (joueurs, matchs, preuves, bracket, journal).</span>
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
