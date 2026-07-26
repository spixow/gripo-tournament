<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'matches';
$byRound = matches_by_round();
$user = current_user();
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

<?php foreach ($byRound as $round => $games): ?>
    <div id="round-<?= (int)$round ?>" class="glass mb-4">
        <div class="card-header-fifa">🎮 Round <?= (int)$round ?></div>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
