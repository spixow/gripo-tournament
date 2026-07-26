<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'matches';
$byRound = matches_by_round();
$user = current_user();
$latecomers = players_with_pending();
$deadlinePassed = deadline_passed();
$allPlayers = all_players();
$filterPlayer = (int)($_GET['player'] ?? 0) ?: null;
$filterName = null;
if ($filterPlayer) {
    foreach ($allPlayers as $p) { if ((int)$p['id'] === $filterPlayer) { $filterName = $p['display_name']; break; } }
    if (!$filterName) { $filterPlayer = null; }
}
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="hero-title mb-0" style="font-size:1.6rem">📅 Calendrier des matchs</h2>
    <div class="btn-group btn-group-sm" role="group">
        <?php foreach (array_keys($byRound) as $r): ?>
            <a href="#round-<?= (int)$r ?>" class="btn btn-outline-info">Round <?= (int)$r ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Filtre par joueur -->
<div class="glass p-3 mb-4">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label class="form-label small mb-1">🔎 Filtrer par joueur</label>
            <select name="player" class="form-select" onchange="this.form.submit()">
                <option value="0">— Tous les joueurs —</option>
                <?php foreach ($allPlayers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $filterPlayer===(int)$p['id']?'selected':'' ?>>
                        <?= e($p['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto d-flex gap-2">
            <?php if ($user && !$user['is_admin']): ?>
                <a href="matches.php?player=<?= (int)$user['id'] ?>" class="btn btn-fifa">⚽ Mes matchs</a>
            <?php endif; ?>
            <?php if ($filterPlayer): ?>
                <a href="matches.php" class="btn btn-outline-light">✕ Tout afficher</a>
            <?php endif; ?>
        </div>
        <?php if ($filterPlayer): ?>
            <div class="col-12">
                <span class="small text-secondary">Filtre actif : <strong style="color:var(--txt)"><?= e($filterName) ?></strong></span>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Compte à rebours de la deadline -->
<div class="glass p-3 mb-4 text-center countdown-banner">
    <div class="hero-sub mb-2">⏳ DEADLINE DE LA PHASE DE LIGUE — <?= e(app_deadline_date()) ?> à <?= e(substr(app_deadline_time(),0,5)) ?></div>
    <?php if ($deadlinePassed): ?>
        <div class="h4 mb-0" style="color:var(--pink)">⚠️ Deadline dépassée</div>
    <?php else: ?>
        <div id="countdown" class="countdown-grid" data-deadline="<?= e(app_deadline_date()) ?>T<?= e(app_deadline_time()) ?>">
            <div class="cd-box"><span class="cd-num" data-cd="d">–</span><span class="cd-lbl">jours</span></div>
            <div class="cd-box"><span class="cd-num" data-cd="h">–</span><span class="cd-lbl">heures</span></div>
            <div class="cd-box"><span class="cd-num" data-cd="m">–</span><span class="cd-lbl">min</span></div>
            <div class="cd-box"><span class="cd-num" data-cd="s">–</span><span class="cd-lbl">sec</span></div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($latecomers)): ?>
<div class="glass mb-4">
    <div class="card-header-fifa">🐌 Retardataires — joueurs avec des matchs à jouer</div>
    <div class="p-3 d-flex flex-wrap gap-2">
        <?php foreach ($latecomers as $lc): ?>
            <a href="matches.php?player=<?= (int)$lc['id'] ?>" class="text-decoration-none">
                <span class="latecomer-chip <?= $deadlinePassed ? 'late' : '' ?>">
                    <span class="avatar" style="background:<?= e($lc['avatar_color']) ?>;width:22px;height:22px;font-size:.68rem">
                        <?= e(mb_strtoupper(mb_substr($lc['display_name'],0,1))) ?>
                    </span>
                    <?= e($lc['display_name']) ?>
                    <span class="badge text-bg-danger"><?= (int)$lc['pending'] ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php foreach ($byRound as $round => $allGames):
    $games = $filterPlayer
        ? array_values(array_filter($allGames, fn($m) => (int)$m['home_id'] === $filterPlayer || (int)$m['away_id'] === $filterPlayer))
        : $allGames;
    if (empty($games)) continue;
    $roundPlayed = count(array_filter($games, fn($m) => $m['status']==='completed'));
    $roundTotal = count($games);
    $roundDone = $roundPlayed === $roundTotal;
    ?>
    <div id="round-<?= (int)$round ?>" class="glass mb-4">
        <div class="card-header-fifa">
            🎮 Round <?= (int)$round ?>
            <span class="ms-auto small" style="letter-spacing:0;text-transform:none;font-weight:600">
                <?php if ($roundDone): ?>
                    <span class="badge text-bg-success">Complet</span>
                <?php elseif ($deadlinePassed): ?>
                    <span class="badge text-bg-danger">En retard · <?= $roundPlayed ?>/<?= $roundTotal ?></span>
                <?php else: ?>
                    <span class="text-secondary"><?= $roundPlayed ?>/<?= $roundTotal ?> joués</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="p-3">
            <div class="row g-3">
                <?php foreach ($games as $m):
                    [$lbl, $col] = status_label($m['status']);
                    $isMine = $user && ($user['id']==$m['home_id'] || $user['id']==$m['away_id']);
                    ?>
                    <div class="col-md-6">
                        <a href="match.php?id=<?= (int)$m['id'] ?>" class="text-decoration-none">
                            <div class="match-card p-3 <?= $isMine?'border-info':'' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-secondary">Match <?= (int)$m['match_number'] ?></span>
                                    <span class="badge text-bg-<?= $col ?>"><?= e($lbl) ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2" style="flex:1">
                                        <span class="avatar" style="background:<?= e($m['home_color']) ?>; width:28px;height:28px;font-size:.8rem">
                                            <?= e(mb_strtoupper(mb_substr($m['home_name'],0,1))) ?>
                                        </span>
                                        <span class="text-light fw-semibold"><?= e($m['home_name']) ?></span>
                                    </div>
                                    <?php if ($m['status']==='completed'): ?>
                                        <span class="score-pill mx-2"><?= (int)$m['home_score'] ?> : <?= (int)$m['away_score'] ?></span>
                                    <?php else: ?>
                                        <span class="vs-badge mx-2">VS</span>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center gap-2 justify-content-end" style="flex:1">
                                        <span class="text-light fw-semibold"><?= e($m['away_name']) ?></span>
                                        <span class="avatar" style="background:<?= e($m['away_color']) ?>; width:28px;height:28px;font-size:.8rem">
                                            <?= e(mb_strtoupper(mb_substr($m['away_name'],0,1))) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    var el = document.getElementById('countdown');
    if (!el) return;
    var target = new Date(el.dataset.deadline).getTime();
    var out = {
        d: el.querySelector('[data-cd="d"]'), h: el.querySelector('[data-cd="h"]'),
        m: el.querySelector('[data-cd="m"]'), s: el.querySelector('[data-cd="s"]')
    };
    function tick() {
        var diff = target - Date.now();
        if (diff <= 0) { el.innerHTML = '<div class="h4 mb-0" style="color:var(--pink)">⚠️ Deadline atteinte</div>'; return; }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor(diff % 86400000 / 3600000);
        var m = Math.floor(diff % 3600000 / 60000);
        var s = Math.floor(diff % 60000 / 1000);
        out.d.textContent = d; out.h.textContent = ('0'+h).slice(-2);
        out.m.textContent = ('0'+m).slice(-2); out.s.textContent = ('0'+s).slice(-2);
    }
    tick(); setInterval(tick, 1000);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
