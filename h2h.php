<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'h2h';

$players = all_players();
$aId = (int)($_GET['a'] ?? 0);
$bId = (int)($_GET['b'] ?? 0);

$a = $aId ? get_player($aId) : null;
$b = $bId ? get_player($bId) : null;
$data = ($a && $b && $aId !== $bId) ? h2h_data($aId, $bId) : null;

require __DIR__ . '/includes/header.php';
?>
<section class="text-center py-2">
    <div class="hero-sub mb-2">EA SPORTS FC 26 · HEAD TO HEAD</div>
    <h1 class="hero-title mb-0" style="font-size:2rem">⚔️ Confrontation directe</h1>
</section>

<div class="glass mb-4">
    <div class="p-3">
        <form method="get" class="row g-2 justify-content-center align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label small">Joueur A</label>
                <select name="a" class="form-select">
                    <option value="0">— Choisir —</option>
                    <?php foreach ($players as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $aId===(int)$p['id']?'selected':'' ?>><?= e($p['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto pb-2"><span class="vs-badge">VS</span></div>
            <div class="col-6 col-md-4">
                <label class="form-label small">Joueur B</label>
                <select name="b" class="form-select">
                    <option value="0">— Choisir —</option>
                    <?php foreach ($players as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $bId===(int)$p['id']?'selected':'' ?>><?= e($p['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-fifa w-100">Comparer →</button>
            </div>
        </form>
    </div>
</div>

<?php if ($a && $b && $aId === $bId): ?>
    <div class="alert alert-warning">Choisis deux joueurs différents.</div>
<?php elseif ($data): ?>
    <?php $t = $data['tally']; ?>
    <div class="glass mb-4">
        <div class="card-header-fifa">🥊 Bilan des confrontations</div>
        <div class="p-4">
            <div class="row align-items-center text-center g-3">
                <div class="col-4">
                    <div class="avatar mx-auto mb-2" style="background:<?= e($a['avatar_color']) ?>;width:64px;height:64px;font-size:1.6rem">
                        <?= e(mb_strtoupper(mb_substr($a['display_name'],0,1))) ?>
                    </div>
                    <div class="h5 mb-0"><?= e($a['display_name']) ?></div>
                    <div class="small text-secondary"><?= (int)$t['a_win'] ?> victoire(s)</div>
                </div>
                <div class="col-4">
                    <div class="score-pill d-inline-block" style="font-size:1.8rem">
                        <?= (int)$t['a_win'] ?> - <?= (int)$t['b_win'] ?>
                    </div>
                    <div class="small text-secondary mt-2"><?= (int)$t['draw'] ?> nul(s)</div>
                    <div class="small text-secondary">Buts : <?= (int)$t['a_goals'] ?> - <?= (int)$t['b_goals'] ?></div>
                </div>
                <div class="col-4">
                    <div class="avatar mx-auto mb-2" style="background:<?= e($b['avatar_color']) ?>;width:64px;height:64px;font-size:1.6rem">
                        <?= e(mb_strtoupper(mb_substr($b['display_name'],0,1))) ?>
                    </div>
                    <div class="h5 mb-0"><?= e($b['display_name']) ?></div>
                    <div class="small text-secondary"><?= (int)$t['b_win'] ?> victoire(s)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="glass">
        <div class="card-header-fifa">📜 Historique (<?= count($data['matches']) ?> match(s))</div>
        <div class="p-3">
            <?php if (empty($data['matches'])): ?>
                <p class="text-secondary mb-0">Aucun match terminé entre ces deux joueurs pour le moment.</p>
            <?php else: foreach ($data['matches'] as $m): ?>
                <div class="match-card p-3 mb-2 d-flex align-items-center justify-content-between">
                    <span class="small text-secondary">Round <?= (int)$m['round'] ?></span>
                    <span>
                        <strong><?= e($m['home_name']) ?></strong>
                        <span class="score-pill mx-2"><?= (int)$m['home_score'] ?> : <?= (int)$m['away_score'] ?></span>
                        <strong><?= e($m['away_name']) ?></strong>
                    </span>
                    <a href="match.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-info">Voir</a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="glass p-5 text-center">
        <div class="display-6 mb-2">⚔️</div>
        <h5>Sélectionne deux joueurs</h5>
        <p class="text-secondary mb-0">Compare leur historique, le nombre de victoires et les buts marqués.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
