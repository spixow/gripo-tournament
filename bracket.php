<?php
require_once __DIR__ . '/includes/functions.php';
$page = 'bracket';

$generated = bracket_generated();
$bracket = $generated ? get_bracket() : [];
$champion = bracket_champion();

/** Rendu d'un bloc de match du bracket. */
function bracket_block(array $b): string
{
    $render_side = function ($name, $color, $score, $isWinner, $done) {
        $ini = $name ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
        $cls = $isWinner ? 'bk-side bk-win' : 'bk-side';
        $sc  = $done ? (int)$score : '–';
        $bg  = $color ?: '#1c3a2a';
        return '<div class="' . $cls . '">'
            . '<span class="avatar" style="background:' . e($bg) . ';width:24px;height:24px;font-size:.7rem">' . e($ini) . '</span>'
            . '<span class="bk-name">' . e($name ?: '—') . '</span>'
            . '<span class="bk-score">' . e((string)$sc) . '</span>'
            . '</div>';
    };
    $done = $b['status'] === 'done';
    $w = $done ? (int)$b['winner_id'] : 0;
    $p1win = $done && $w === (int)$b['player1_id'];
    $p2win = $done && $w === (int)$b['player2_id'];

    $n1 = $b['p1_name'] ?? ($b['seed1'] ?? null);
    $n2 = $b['p2_name'] ?? ($b['seed2'] ?? null);

    $html  = '<div class="bk-match">';
    $html .= '<div class="bk-label">' . e($b['label']) . '</div>';
    $html .= $render_side($n1, $b['p1_color'] ?? null, $b['score1'], $p1win, $done && $b['player1_id']);
    $html .= $render_side($n2, $b['p2_color'] ?? null, $b['score2'], $p2win, $done && $b['player2_id']);
    $html .= '</div>';
    return $html;
}

require __DIR__ . '/includes/header.php';
?>

<section class="text-center py-3">
    <div class="hero-sub mb-2">EA SPORTS FC 26 · KNOCKOUT STAGE</div>
    <h1 class="hero-title mb-2" style="font-size:2rem">🏆 Phase finale</h1>
    <p class="text-secondary mb-0">
        1<sup>er</sup> &amp; 2<sup>e</sup> qualifiés directement en demi-finales — 3<sup>e</sup> à 6<sup>e</sup> passent par les barrages.
    </p>
</section>

<?php if (!$generated): ?>
    <div class="glass p-5 text-center">
        <div class="display-6 mb-3">⏳</div>
        <h4>La phase finale n'a pas encore été générée</h4>
        <p class="text-secondary mb-0">Elle sera disponible une fois la phase de ligue terminée et validée par l'administrateur.</p>
    </div>
<?php else: ?>

    <div class="text-center mb-3">
        <button id="btn-export-bracket" class="btn btn-fifa btn-sm btn-export-hide">🖼️ Exporter le bracket (PNG)</button>
    </div>

    <div id="bracket-capture">
    <?php if ($champion): ?>
        <div class="glass p-4 mb-4 text-center champion-banner">
            <div class="hero-sub mb-2" style="color:var(--gold)">🏅 CHAMPION — GRIPO TOURNAMENT</div>
            <div class="champion-avatar mx-auto my-3" style="background:<?= e($champion['avatar_color']) ?>">
                <?= e(mb_strtoupper(mb_substr($champion['display_name'],0,1))) ?>
            </div>
            <h2 class="hero-title mb-0" style="color:var(--gold)"><?= e($champion['display_name']) ?></h2>
        </div>
    <?php endif; ?>

    <div class="glass p-3 p-md-4">
        <div class="bracket-wrap">
            <div class="bracket-col">
                <div class="bracket-col-title">Barrages</div>
                <?= isset($bracket['PO1']) ? bracket_block($bracket['PO1']) : '' ?>
                <?= isset($bracket['PO2']) ? bracket_block($bracket['PO2']) : '' ?>
            </div>
            <div class="bracket-col">
                <div class="bracket-col-title">Demi-finales</div>
                <?= isset($bracket['SF1']) ? bracket_block($bracket['SF1']) : '' ?>
                <?= isset($bracket['SF2']) ? bracket_block($bracket['SF2']) : '' ?>
            </div>
            <div class="bracket-col">
                <div class="bracket-col-title">Finale</div>
                <?= isset($bracket['FINAL']) ? bracket_block($bracket['FINAL']) : '' ?>
            </div>
        </div>
    </div>
    </div><!-- /#bracket-capture -->

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="assets/js/export-png.js"></script>
    <script>
    document.getElementById('btn-export-bracket').addEventListener('click', function () {
        window.exportNodePng(document.getElementById('bracket-capture'), 'bracket-gripo-tournament.png', this);
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
