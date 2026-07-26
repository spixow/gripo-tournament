<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'matches';

$matchId = (int)($_GET['id'] ?? 0);
$match = get_match($matchId);
if (!$match) {
    flash('danger', 'Match introuvable.');
    redirect('matches.php');
}

$user = current_user();
$isParticipant = $user && !$user['is_admin'] && ($user['id'] == $match['home_id'] || $user['id'] == $match['away_id']);
$submissions = submissions_of_match($matchId);
$mySubmission = $user ? ($submissions[$user['id']] ?? null) : null;

/* -------------------- Traitement du formulaire -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isParticipant && $match['status'] !== 'completed') {
    try {
        if (!csrf_check($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Jeton de sécurité invalide.');
        }
        $hs = filter_input(INPUT_POST, 'home_score', FILTER_VALIDATE_INT);
        $as = filter_input(INPUT_POST, 'away_score', FILTER_VALIDATE_INT);
        if ($hs === false || $hs === null || $as === false || $as === null || $hs < 0 || $as < 0 || $hs > 99 || $as > 99) {
            throw new RuntimeException('Scores invalides (entiers entre 0 et 99).');
        }

        // Preuve image : facultative
        $proof = handle_proof_upload('proof');
        if (!$proof && $mySubmission) {
            $proof = $mySubmission['proof_image']; // conserver l'ancienne si non renvoyée
        }

        $pdo = db();
        // Insérer ou mettre à jour la soumission du joueur
        $stmt = $pdo->prepare(
            'INSERT INTO match_submissions (match_id, player_id, home_score, away_score, proof_image)
             VALUES (:mid, :pid, :hs, :as, :proof)
             ON DUPLICATE KEY UPDATE home_score = :hs2, away_score = :as2, proof_image = :proof2'
        );
        $stmt->execute([
            ':mid' => $matchId, ':pid' => $user['id'],
            ':hs' => $hs, ':as' => $as, ':proof' => $proof,
            ':hs2' => $hs, ':as2' => $as, ':proof2' => $proof,
        ]);

        // Recharger les soumissions
        $submissions = submissions_of_match($matchId);
        $homeSub = $submissions[$match['home_id']] ?? null;
        $awaySub = $submissions[$match['away_id']] ?? null;

        if ($homeSub && $awaySub) {
            // Les deux joueurs ont soumis → comparer
            if ((int)$homeSub['home_score'] === (int)$awaySub['home_score']
                && (int)$homeSub['away_score'] === (int)$awaySub['away_score']) {
                // Concordance → match validé
                $upd = $pdo->prepare(
                    "UPDATE matches SET home_score = ?, away_score = ?, status = 'completed',
                     completed_at = NOW() WHERE id = ?"
                );
                $upd->execute([(int)$homeSub['home_score'], (int)$homeSub['away_score'], $matchId]);
                flash('success', '✅ Scores concordants — match validé et classement mis à jour !');
            } else {
                // Désaccord → litige
                $pdo->prepare("UPDATE matches SET status = 'disputed', home_score = NULL, away_score = NULL WHERE id = ?")
                    ->execute([$matchId]);
                flash('warning', '⚠️ Les scores saisis ne concordent pas. Le match est en litige : vérifiez avec votre adversaire ou contactez l\'admin.');
            }
        } else {
            $pdo->prepare("UPDATE matches SET status = 'awaiting' WHERE id = ?")->execute([$matchId]);
            flash('info', 'Score enregistré ! En attente de la confirmation de votre adversaire.');
        }
        redirect('match.php?id=' . $matchId);
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
        redirect('match.php?id=' . $matchId);
    }
}

$match = get_match($matchId);
$submissions = submissions_of_match($matchId);
$homeSub = $submissions[$match['home_id']] ?? null;
$awaySub = $submissions[$match['away_id']] ?? null;
$mySubmission = $user ? ($submissions[$user['id']] ?? null) : null;
[$statusLbl, $statusCol] = status_label($match['status']);

require __DIR__ . '/includes/header.php';
?>

<a href="matches.php" class="btn btn-sm btn-outline-light mb-3">← Retour aux matchs</a>

<div class="glass mb-4">
    <div class="card-header-fifa">
        🎮 Round <?= (int)$match['round'] ?> · Match <?= (int)$match['match_number'] ?>
        <span class="badge text-bg-<?= $statusCol ?> ms-auto"><?= e($statusLbl) ?></span>
    </div>
    <div class="p-4">
        <div class="row align-items-center text-center">
            <div class="col-5">
                <div class="avatar mx-auto mb-2" style="background:<?= e($match['home_color']) ?>; width:64px;height:64px;font-size:1.6rem">
                    <?= e(mb_strtoupper(mb_substr($match['home_name'],0,1))) ?>
                </div>
                <div class="h5 mb-0"><?= e($match['home_name']) ?></div>
                <div class="small text-secondary">Domicile</div>
            </div>
            <div class="col-2">
                <?php if ($match['status']==='completed'): ?>
                    <div class="score-pill d-inline-block" style="font-size:1.5rem">
                        <?= (int)$match['home_score'] ?> : <?= (int)$match['away_score'] ?>
                    </div>
                <?php else: ?>
                    <div class="vs-badge" style="font-size:1.6rem">VS</div>
                <?php endif; ?>
            </div>
            <div class="col-5">
                <div class="avatar mx-auto mb-2" style="background:<?= e($match['away_color']) ?>; width:64px;height:64px;font-size:1.6rem">
                    <?= e(mb_strtoupper(mb_substr($match['away_name'],0,1))) ?>
                </div>
                <div class="h5 mb-0"><?= e($match['away_name']) ?></div>
                <div class="small text-secondary">Extérieur</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Saisie du score -->
    <div class="col-lg-6">
        <?php if ($isParticipant && $match['status'] !== 'completed'): ?>
            <div class="glass">
                <div class="card-header-fifa">📝 Saisir le score</div>
                <div class="p-4">
                    <?php if ($mySubmission): ?>
                        <div class="alert alert-info py-2 small">
                            Vous avez déjà soumis <strong><?= (int)$mySubmission['home_score'] ?> : <?= (int)$mySubmission['away_score'] ?></strong>.
                            Vous pouvez corriger votre saisie ci-dessous.
                        </div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col">
                                <label class="form-label small"><?= e($match['home_name']) ?></label>
                                <input type="number" min="0" max="99" name="home_score" class="form-control form-control-lg text-center"
                                       value="<?= $mySubmission ? (int)$mySubmission['home_score'] : '' ?>" required>
                            </div>
                            <div class="col-auto pb-2"><span class="vs-badge">–</span></div>
                            <div class="col">
                                <label class="form-label small"><?= e($match['away_name']) ?></label>
                                <input type="number" min="0" max="99" name="away_score" class="form-control form-control-lg text-center"
                                       value="<?= $mySubmission ? (int)$mySubmission['away_score'] : '' ?>" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label small">Preuve image (capture du score final)
                                <span class="text-secondary">(facultatif)</span>
                            </label>
                            <input type="file" name="proof" accept="image/png,image/jpeg,image/webp" class="form-control">
                            <div class="form-text">JPG, PNG ou WEBP — 5 Mo max. Recommandé en cas de litige.</div>
                        </div>
                        <button class="btn btn-fifa w-100 mt-3 py-2">Envoyer mon score →</button>
                    </form>
                </div>
            </div>
        <?php elseif ($match['status'] === 'completed'): ?>
            <div class="glass p-4 text-center">
                <div class="display-6 mb-2">🏁</div>
                <h4>Match terminé</h4>
                <p class="text-secondary mb-0">Résultat validé par les deux joueurs.</p>
            </div>
        <?php else: ?>
            <div class="glass p-4 text-center">
                <div class="display-6 mb-2">🔒</div>
                <h5>Saisie réservée aux 2 joueurs</h5>
                <p class="text-secondary mb-0">Seuls <?= e($match['home_name']) ?> et <?= e($match['away_name']) ?> peuvent saisir le score.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- État des soumissions -->
    <div class="col-lg-6">
        <div class="glass">
            <div class="card-header-fifa">🔍 État des confirmations</div>
            <div class="p-3">
                <?php
                $render = function ($playerName, $sub) use ($user) {
                    ?>
                    <div class="match-card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><?= e($playerName) ?></strong>
                            <?php if ($sub): ?>
                                <span class="badge text-bg-success">Saisi</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">En attente</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($sub): ?>
                            <div class="mb-2">Score déclaré :
                                <span class="score-pill"><?= (int)$sub['home_score'] ?> : <?= (int)$sub['away_score'] ?></span>
                            </div>
                            <?php if ($sub['proof_image']): ?>
                                <a href="<?= UPLOAD_URL . e($sub['proof_image']) ?>" target="_blank">
                                    <img src="<?= UPLOAD_URL . e($sub['proof_image']) ?>" class="proof-thumb" alt="Preuve" style="max-height:180px">
                                </a>
                            <?php else: ?>
                                <span class="text-secondary small">Aucune preuve jointe.</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-secondary small">Ce joueur n'a pas encore saisi le score.</span>
                        <?php endif; ?>
                    </div>
                    <?php
                };
                $render($match['home_name'], $homeSub);
                $render($match['away_name'], $awaySub);
                ?>

                <?php if ($match['status'] === 'disputed'): ?>
                    <div class="alert alert-danger mb-0">
                        ⚠️ <strong>Litige :</strong> les deux scores ne concordent pas.
                        Corrigez votre saisie ou contactez l'administrateur pour trancher.
                    </div>
                <?php elseif ($match['status'] === 'awaiting'): ?>
                    <div class="alert alert-warning mb-0">
                        ⏳ En attente de la saisie du second joueur.
                    </div>
                <?php elseif ($match['status'] === 'completed'): ?>
                    <div class="alert alert-success mb-0">
                        ✅ Résultat confirmé par les deux joueurs.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
