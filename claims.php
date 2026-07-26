<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$page = 'claims';
$claims = claims_for_user($user);
require __DIR__ . '/includes/header.php';
?>
<section class="text-center py-2">
    <div class="hero-sub mb-2">SUPPORT · TICKETS</div>
    <h1 class="hero-title mb-0" style="font-size:2rem">🚩 Réclamations</h1>
</section>

<div class="glass">
    <div class="card-header-fifa">
        <?= $user['is_admin'] ? 'Toutes les réclamations' : 'Mes réclamations' ?>
        <span class="ms-auto small text-secondary"><?= count($claims) ?> ticket(s)</span>
    </div>
    <div class="p-3">
        <?php if (empty($claims)): ?>
            <p class="text-secondary mb-0">
                Aucune réclamation.
                <?= $user['is_admin'] ? '' : 'Tu peux en ouvrir une depuis la page d’un de tes matchs.' ?>
            </p>
        <?php else: foreach ($claims as $c):
            [$lbl, $col] = claim_status_label($c['status']); ?>
            <a href="claim.php?id=<?= (int)$c['id'] ?>" class="text-decoration-none">
                <div class="match-card p-3 mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <span class="badge text-bg-<?= $col ?> me-2"><?= e($lbl) ?></span>
                        <strong class="text-light">R<?= (int)$c['round'] ?> · <?= e($c['home_name']) ?> vs <?= e($c['away_name']) ?></strong>
                        <div class="small text-secondary mt-1"><?= e($c['reason']) ?></div>
                    </div>
                    <div class="text-end small text-secondary">
                        💬 <?= (int)$c['msg_count'] ?> ·
                        <?= e(date('d/m H:i', strtotime($c['updated_at']))) ?>
                    </div>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
