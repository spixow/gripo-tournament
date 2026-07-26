<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$page = 'claims';

/* -------------------- Création d'une réclamation -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash('danger', 'Jeton de sécurité invalide.');
        redirect('matches.php');
    }
    $matchId = (int)($_POST['match_id'] ?? 0);
    $reason  = trim($_POST['reason'] ?? '');
    $match   = get_match($matchId);
    if (!$match) {
        flash('danger', 'Match introuvable.');
        redirect('matches.php');
    }
    // Seuls les deux joueurs du match peuvent ouvrir une réclamation
    $isParticipant = in_array((int)$user['id'], [(int)$match['home_id'], (int)$match['away_id']], true);
    if (!$isParticipant) {
        flash('danger', "Seuls les joueurs de ce match peuvent ouvrir une réclamation.");
        redirect('match.php?id=' . $matchId);
    }
    if ($reason === '') {
        flash('danger', 'Merci d’indiquer un motif.');
        redirect('match.php?id=' . $matchId);
    }
    // Réclamation déjà ouverte ? on y renvoie
    $existing = open_claim_for_match($matchId);
    if ($existing) {
        redirect('claim.php?id=' . $existing['id']);
    }
    $cid = create_claim($matchId, (int)$user['id'], $reason);
    log_activity('claim_open', "Match {$match['home_name']} vs {$match['away_name']} — motif : " . mb_substr($reason, 0, 120));
    flash('success', 'Réclamation ouverte.');
    redirect('claim.php?id=' . $cid);
}

/* -------------------- Chargement de la réclamation -------------------- */
$claimId = (int)($_GET['id'] ?? ($_POST['claim_id'] ?? 0));
$claim = get_claim($claimId);
if (!$claim) {
    flash('danger', 'Réclamation introuvable.');
    redirect('claims.php');
}
if (!user_can_access_claim($claim, $user)) {
    flash('danger', "Accès refusé : cette discussion est réservée aux joueurs du match et aux administrateurs.");
    redirect('claims.php');
}
$isClosed = $claim['status'] === 'closed';

/* -------------------- Envoi d'un message -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'message') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash('danger', 'Jeton de sécurité invalide.');
    } elseif ($isClosed) {
        flash('warning', 'Cette réclamation est fermée : impossible d’écrire.');
    } else {
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            add_claim_message($claimId, (int)$user['id'], $body, false);
            // Réactive un ticket résolu si un joueur relance
            if ($claim['status'] === 'resolved' && empty($user['is_admin'])) {
                set_claim_status($claimId, 'in_progress');
            }
        }
    }
    redirect('claim.php?id=' . $claimId);
}

/* -------------------- Changement de statut (admin) -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    if (!$user['is_admin']) {
        flash('danger', 'Action réservée aux administrateurs.');
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        flash('danger', 'Jeton de sécurité invalide.');
    } else {
        $new = $_POST['status'] ?? '';
        [$lbl] = claim_status_label($new);
        set_claim_status($claimId, $new);
        add_claim_message($claimId, null, "Statut de la réclamation : " . $lbl . " (par " . $user['display_name'] . ").", true);
        log_activity('claim_status', "Réclamation #$claimId → $lbl");
        flash('success', 'Statut mis à jour : ' . $lbl);
    }
    redirect('claim.php?id=' . $claimId);
}

$messages = claim_messages($claimId);
[$statusLbl, $statusCol] = claim_status_label($claim['status']);
require __DIR__ . '/includes/header.php';
?>
<a href="claims.php" class="btn btn-sm btn-outline-light mb-3">← Toutes les réclamations</a>

<div class="glass mb-3">
    <div class="card-header-fifa">
        🚩 Réclamation #<?= (int)$claim['id'] ?>
        <span class="badge text-bg-<?= $statusCol ?> ms-auto"><?= e($statusLbl) ?></span>
    </div>
    <div class="p-3">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold">
                    Round <?= (int)$claim['round'] ?> ·
                    <?= e($claim['home_name']) ?> <span class="vs-badge">vs</span> <?= e($claim['away_name']) ?>
                </div>
                <div class="small text-secondary">
                    Ouverte par <?= e($claim['opener_name'] ?? '—') ?> ·
                    <?= e(date('d/m/Y H:i', strtotime($claim['created_at']))) ?>
                </div>
            </div>
            <a href="match.php?id=<?= (int)$claim['match_id'] ?>" class="btn btn-sm btn-outline-info align-self-start">Voir le match</a>
        </div>

        <?php if ($user['is_admin']): ?>
            <hr>
            <form method="post" class="d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                <span class="small text-secondary">Cycle de vie :</span>
                <?php foreach (['open'=>'Ouverte','in_progress'=>'En cours','resolved'=>'Résolue','closed'=>'Fermée'] as $val=>$lbl):
                    [$l,$c] = claim_status_label($val); ?>
                    <button name="status" value="<?= $val ?>"
                            class="btn btn-sm <?= $claim['status']===$val ? 'btn-fifa' : 'btn-outline-info' ?>"
                            <?= $claim['status']===$val ? 'disabled' : '' ?>><?= e($lbl) ?></button>
                <?php endforeach; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Chat -->
<div class="glass">
    <div class="card-header-fifa">💬 Discussion — joueurs du match &amp; administrateurs</div>
    <div class="p-3">
        <div class="chat-thread" id="chat-thread">
            <?php foreach ($messages as $m):
                $mine = !$m['is_system'] && (int)$m['sender_id'] === (int)$user['id'];
                if ($m['is_system']): ?>
                    <div class="chat-system"><?= e($m['body']) ?>
                        <span class="chat-time"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></span>
                    </div>
                <?php else: ?>
                    <div class="chat-msg <?= $mine ? 'mine' : '' ?>">
                        <span class="avatar" style="background:<?= e($m['avatar_color'] ?? '#889') ?>;width:30px;height:30px;font-size:.8rem">
                            <?= e(mb_strtoupper(mb_substr($m['sender_name'] ?? '?', 0, 1))) ?>
                        </span>
                        <div class="chat-bubble">
                            <div class="chat-name">
                                <?= e($m['sender_name'] ?? 'Utilisateur supprimé') ?>
                                <?php if (!empty($m['is_admin'])): ?><span class="badge text-bg-warning ms-1">Admin</span><?php endif; ?>
                            </div>
                            <div class="chat-body"><?= nl2br(e($m['body'])) ?></div>
                            <div class="chat-time"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($isClosed): ?>
            <div class="alert alert-secondary mt-3 mb-0">🔒 Réclamation fermée. La discussion est en lecture seule.</div>
        <?php else: ?>
            <form method="post" class="mt-3 d-flex gap-2 align-items-end">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="message">
                <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                <textarea name="body" class="form-control" rows="1" required
                          placeholder="Écris un message…" style="resize:vertical"></textarea>
                <button class="btn btn-fifa">Envoyer</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    var t = document.getElementById('chat-thread');
    if (t) t.scrollTop = t.scrollHeight;
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
