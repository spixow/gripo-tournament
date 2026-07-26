<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'home';

$standings = compute_standings();
$total = db()->query("SELECT COUNT(*) c FROM matches")->fetch()['c'];
$played = db()->query("SELECT COUNT(*) c FROM matches WHERE status='completed'")->fetch()['c'];
$user = current_user();

$myMatches = [];
if ($user && !$user['is_admin']) {
    $myMatches = array_filter(matches_of_player($user['id']), fn($m) => $m['status'] !== 'completed');
}

require __DIR__ . '/includes/header.php';
?>

<section class="text-center py-4">
    <div class="hero-sub mb-2">EA SPORTS FC 26 · 1v1 · LEAGUE PHASE · 12 JOUEURS · 5 ROUNDS</div>
    <h1 class="hero-title display-5 mb-3">⚽ GRIPO <span style="color:var(--gold)">TOURNAMENT</span></h1>
    <p class="text-secondary mb-4">
        Chaque joueur affronte les autres. Les deux joueurs saisissent le score
        et peuvent joindre une preuve image (facultative). Le match est validé quand les scores concordent.
    </p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
        <div class="glass px-4 py-3">
            <div class="h3 mb-0" style="color:var(--cyan)"><?= (int)$played ?>/<?= (int)$total ?></div>
            <div class="small text-secondary">Matchs joués</div>
        </div>
        <div class="glass px-4 py-3">
            <div class="h3 mb-0" style="color:var(--green)"><?= count($standings) ?></div>
            <div class="small text-secondary">Joueurs</div>
        </div>
        <div class="glass px-4 py-3">
            <div class="h3 mb-0" style="color:var(--gold)">5</div>
            <div class="small text-secondary">Rounds</div>
        </div>
    </div>
</section>

<div class="row g-4 mt-1">
    <!-- Classement rapide -->
    <div class="col-lg-7">
        <div class="glass h-100">
            <div class="card-header-fifa">🏆 Classement <a href="standings.php" class="ms-auto small text-decoration-none" style="color:var(--cyan)">Voir tout →</a></div>
            <div class="table-responsive">
                <table class="table table-fifa standings-table align-middle">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Joueur</th>
                            <th class="d-none d-sm-table-cell">Forme</th>
                            <th class="text-center">J</th>
                            <th class="text-center">Diff</th>
                            <th class="text-center">Pts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($standings as $row):
                            $rank = (int)$row['rank'];
                            $zone = $rank <= 2 ? 'qz-direct' : ($rank <= 6 ? 'qz-playoff' : 'qz');
                            $medalCls = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                            $gd = (int)$row['gd'];
                            $gdCls = $gd > 0 ? 'gd-pos' : ($gd < 0 ? 'gd-neg' : 'gd-zero');
                            $form = player_form((int)$row['id'], 5);
                            ?>
                            <tr class="<?= $zone ?> <?= $rank <= 3 ? 'row-'.$rank : '' ?>">
                                <td><span class="rank-medal <?= $medalCls ?>"><?= $rank ?></span></td>
                                <td>
                                    <span class="avatar me-2" style="background:<?= e($row['color']) ?>; width:28px;height:28px;font-size:.8rem">
                                        <?= e(mb_strtoupper(mb_substr($row['name'],0,1))) ?>
                                    </span>
                                    <span class="fw-semibold"><?= e($row['name']) ?></span>
                                </td>
                                <td class="d-none d-sm-table-cell">
                                    <?php if (empty($form)): ?>
                                        <span class="text-secondary small">—</span>
                                    <?php else: foreach (array_reverse($form) as $f):
                                        $fc = $f === 'W' ? 'fd-w' : ($f === 'D' ? 'fd-d' : 'fd-l');
                                        $fl = $f === 'W' ? 'V' : ($f === 'D' ? 'N' : 'D'); ?>
                                        <span class="fd <?= $fc ?>"><?= $fl ?></span>
                                    <?php endforeach; endif; ?>
                                </td>
                                <td class="text-center"><?= (int)$row['played'] ?></td>
                                <td class="text-center"><span class="gd-chip <?= $gdCls ?>"><?= ($gd>0?'+':'').$gd ?></span></td>
                                <td class="text-center"><span class="pts-big" style="font-size:1.2rem"><?= (int)$row['points'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-2 px-3 small text-secondary d-flex gap-3 flex-wrap">
                <span><span class="legend-dot" style="background:var(--gold)"></span> 1<sup>er</sup>–2<sup>e</sup> : demi-finales</span>
                <span><span class="legend-dot" style="background:var(--blue)"></span> 3<sup>e</sup>–6<sup>e</sup> : barrages</span>
            </div>
        </div>
    </div>

    <!-- Mes prochains matchs / infos -->
    <div class="col-lg-5">
        <?php if ($user && !$user['is_admin']): ?>
            <div class="glass mb-4">
                <div class="card-header-fifa">⚔️ Mes matchs à jouer</div>
                <div class="p-3">
                    <?php if (empty($myMatches)): ?>
                        <p class="text-secondary mb-0">Aucun match en attente. Beau travail ! 🎉</p>
                    <?php else: foreach ($myMatches as $m):
                        [$lbl, $col] = status_label($m['status']); ?>
                        <a href="match.php?id=<?= (int)$m['id'] ?>" class="text-decoration-none">
                            <div class="match-card p-2 px-3 mb-2 d-flex align-items-center justify-content-between">
                                <span class="text-light"><?= e($m['home_name']) ?> <span class="vs-badge">vs</span> <?= e($m['away_name']) ?></span>
                                <span class="badge text-bg-<?= $col ?>"><?= e($lbl) ?></span>
                            </div>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="glass">
            <div class="card-header-fifa">📜 Règlement</div>
            <div class="p-3 small">
                <ul class="mb-0" style="color:var(--muted)">
                    <li>Victoire = <strong style="color:var(--txt)">3 pts</strong>, Nul = <strong style="color:var(--txt)">1 pt</strong>, Défaite = <strong style="color:var(--txt)">0 pt</strong>.</li>
                    <li>Départage : points → différence de buts → buts marqués → confrontation directe.</li>
                    <li>Après le match, <strong style="color:var(--txt)">les deux joueurs</strong> saisissent le score ; la <strong style="color:var(--txt)">preuve image</strong> est facultative (recommandée en cas de litige).</li>
                    <li>Le match est validé automatiquement si les deux scores concordent, sinon il passe en <span class="text-danger">litige</span>.</li>
                    <li>Deadline de la phase de ligue : <strong style="color:var(--gold)"><?= e(APP_DEADLINE) ?></strong>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
