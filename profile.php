<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$page = 'profile';

// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash('danger', 'Jeton de sécurité invalide.');
    } else {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($old, $user['password_hash'])) {
            flash('danger', 'Ancien mot de passe incorrect.');
        } elseif (strlen($new) < 6) {
            flash('danger', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
        } else {
            db()->prepare('UPDATE players SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            log_activity('password_change', 'A changé son mot de passe', $user);
            flash('success', 'Mot de passe mis à jour.');
        }
    }
    redirect('profile.php');
}

$myMatches = matches_of_player($user['id']);
$stats = ['played'=>0,'win'=>0,'draw'=>0,'loss'=>0,'gf'=>0,'ga'=>0,'points'=>0];
foreach ($myMatches as $m) {
    if ($m['status'] !== 'completed') continue;
    $isHome = $m['home_id'] == $user['id'];
    $mine = $isHome ? (int)$m['home_score'] : (int)$m['away_score'];
    $opp  = $isHome ? (int)$m['away_score'] : (int)$m['home_score'];
    $stats['played']++; $stats['gf'] += $mine; $stats['ga'] += $opp;
    if ($mine > $opp) { $stats['win']++; $stats['points'] += 3; }
    elseif ($mine < $opp) { $stats['loss']++; }
    else { $stats['draw']++; $stats['points']++; }
}

require __DIR__ . '/includes/header.php';
?>
<div class="glass mb-4">
    <div class="card-header-fifa">
        <span class="avatar" style="background:<?= e($user['avatar_color']) ?>">
            <?= e(mb_strtoupper(mb_substr($user['display_name'],0,1))) ?>
        </span>
        <?= e($user['display_name']) ?>
        <?php if (!empty($user['team'])): ?>
            <span class="badge text-bg-info ms-2" style="text-transform:none;letter-spacing:0">🎽 <?= e($user['team']) ?></span>
        <?php endif; ?>
    </div>
    <div class="p-3">
        <div class="row text-center g-2">
            <?php
            $cards = [
                ['J', $stats['played'], 'var(--txt)'],
                ['G', $stats['win'], 'var(--green)'],
                ['N', $stats['draw'], 'var(--gold)'],
                ['P', $stats['loss'], 'var(--pink)'],
                ['BP', $stats['gf'], 'var(--cyan)'],
                ['BC', $stats['ga'], 'var(--muted)'],
                ['Pts', $stats['points'], 'var(--cyan)'],
            ];
            foreach ($cards as [$lbl,$val,$col]): ?>
                <div class="col">
                    <div class="match-card py-2">
                        <div class="h4 mb-0" style="color:<?= $col ?>"><?= $val ?></div>
                        <div class="small text-secondary"><?= $lbl ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass">
            <div class="card-header-fifa">⚔️ Mes matchs</div>
            <div class="table-responsive">
                <table class="table table-fifa mb-0">
                    <thead><tr><th>Round</th><th>Adversaire</th><th class="text-center">Score</th><th class="text-center">Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($myMatches as $m):
                        $isHome = $m['home_id'] == $user['id'];
                        $oppName = $isHome ? $m['away_name'] : $m['home_name'];
                        [$lbl,$col] = status_label($m['status']);
                        $scoreTxt = $m['status']==='completed'
                            ? ($isHome ? $m['home_score'].' : '.$m['away_score'] : $m['away_score'].' : '.$m['home_score'])
                            : '—';
                        ?>
                        <tr>
                            <td>R<?= (int)$m['round'] ?></td>
                            <td><?= e($oppName) ?> <?= $isHome?'<span class="badge text-bg-dark">Dom.</span>':'<span class="badge text-bg-dark">Ext.</span>' ?></td>
                            <td class="text-center fw-bold"><?= e($scoreTxt) ?></td>
                            <td class="text-center"><span class="badge text-bg-<?= $col ?>"><?= e($lbl) ?></span></td>
                            <td class="text-end"><a href="match.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-info">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass">
            <div class="card-header-fifa">🔐 Sécurité</div>
            <div class="p-3">
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="password">
                    <div class="mb-2">
                        <label class="form-label small">Ancien mot de passe</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <button class="btn btn-fifa w-100">Mettre à jour</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
