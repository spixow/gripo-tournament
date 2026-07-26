<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'standings';
$standings = compute_standings();

// Podium (top 3)
$top = array_slice($standings, 0, 3);
$medalEmoji = ['🥇', '🥈', '🥉'];
require __DIR__ . '/includes/header.php';
?>

<section class="text-center py-2">
    <div class="hero-sub mb-2">EA SPORTS FC 26 · LEAGUE PHASE</div>
    <h1 class="hero-title mb-0" style="font-size:2rem">🏆 Classement général</h1>
    <button id="btn-export-png" class="btn btn-fifa btn-sm mt-3 btn-export-hide">🖼️ Exporter en image (PNG)</button>
</section>

<div id="standings-capture" class="p-2">
<?php if (count($top) === 3):
    $order = [1, 0, 2]; // 2e à gauche, 1er au centre, 3e à droite
    ?>
<div class="podium">
    <?php foreach ($order as $idx):
        $r = $top[$idx];
        $rank = $idx + 1; ?>
        <div class="podium-item podium-<?= $rank ?>">
            <div class="podium-medal"><?= $medalEmoji[$idx] ?></div>
            <div class="podium-avatar" style="background:<?= e($r['color']) ?>">
                <?= e(mb_strtoupper(mb_substr($r['name'],0,1))) ?>
            </div>
            <div class="podium-name"><?= e($r['name']) ?></div>
            <div class="podium-pts mt-1"><?= (int)$r['points'] ?> <small>PTS</small></div>
            <div class="podium-base"><?= $rank ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="glass">
    <div class="card-header-fifa">📊 Classement complet</div>
    <div class="table-responsive">
        <table class="table table-fifa standings-table align-middle">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>Joueur</th>
                    <th class="d-none d-md-table-cell">Forme</th>
                    <th class="text-center">J</th>
                    <th class="text-center d-none d-sm-table-cell">V·N·D</th>
                    <th class="text-center d-none d-lg-table-cell">Buts</th>
                    <th class="text-center">Diff</th>
                    <th class="text-center">Pts</th>
                    <th class="text-center d-none d-md-table-cell">Qualif.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($standings as $row):
                    $rank = (int)$row['rank'];
                    $zone = $rank <= 2 ? 'qz-direct' : ($rank <= 6 ? 'qz-playoff' : 'qz');
                    $medalCls = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                    if ($rank <= 2)      { $q = ['Demi-finale', 'warning']; }
                    elseif ($rank <= 6)  { $q = ['Barrage', 'primary']; }
                    else                 { $q = ['Éliminé', 'secondary']; }
                    $gd = (int)$row['gd'];
                    $gdCls = $gd > 0 ? 'gd-pos' : ($gd < 0 ? 'gd-neg' : 'gd-zero');
                    $form = player_form((int)$row['id'], 5);
                    ?>
                    <tr class="<?= $zone ?> <?= $rank <= 3 ? 'row-'.$rank : '' ?>">
                        <td><span class="rank-medal <?= $medalCls ?>"><?= $rank ?></span></td>
                        <td>
                            <span class="avatar me-2" style="background:<?= e($row['color']) ?>">
                                <?= e(mb_strtoupper(mb_substr($row['name'],0,1))) ?>
                            </span>
                            <span class="fw-semibold"><?= e($row['name']) ?></span>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if (empty($form)): ?>
                                <span class="text-secondary small">—</span>
                            <?php else:
                                foreach (array_reverse($form) as $f):
                                    $fc = $f === 'W' ? 'fd-w' : ($f === 'D' ? 'fd-d' : 'fd-l');
                                    $fl = $f === 'W' ? 'V' : ($f === 'D' ? 'N' : 'D'); ?>
                                    <span class="fd <?= $fc ?>" title="<?= $fl ?>"><?= $fl ?></span>
                                <?php endforeach; endif; ?>
                        </td>
                        <td class="text-center"><?= (int)$row['played'] ?></td>
                        <td class="text-center d-none d-sm-table-cell">
                            <span class="wdl">
                                <b class="w"><?= (int)$row['win'] ?></b>
                                <b class="d"><?= (int)$row['draw'] ?></b>
                                <b class="l"><?= (int)$row['loss'] ?></b>
                            </span>
                        </td>
                        <td class="text-center d-none d-lg-table-cell">
                            <span class="text-secondary"><?= (int)$row['gf'] ?>:<?= (int)$row['ga'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="gd-chip <?= $gdCls ?>"><?= ($gd>0?'+':'').$gd ?></span>
                        </td>
                        <td class="text-center"><span class="pts-big"><?= (int)$row['points'] ?></span></td>
                        <td class="text-center d-none d-md-table-cell">
                            <span class="badge text-bg-<?= $q[1] ?>"><?= $q[0] ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 small text-secondary d-flex flex-wrap gap-3 align-items-center">
        <span><span class="legend-dot" style="background:var(--gold)"></span> 1<sup>er</sup>–2<sup>e</sup> : demi-finales</span>
        <span><span class="legend-dot" style="background:var(--blue)"></span> 3<sup>e</sup>–6<sup>e</sup> : barrages</span>
        <span class="ms-auto"><strong>Départage :</strong> Pts → Diff. buts → Buts marqués → Confrontation directe</span>
    </div>
</div>
</div><!-- /#standings-capture -->

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="assets/js/export-png.js"></script>
<script>
document.getElementById('btn-export-png').addEventListener('click', function () {
    window.exportNodePng(document.getElementById('standings-capture'), 'classement-gripo-tournament.png', this);
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
