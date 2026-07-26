<?php
/**
 * Export / sauvegarde des données — réservé à l'administrateur.
 *   export.php?type=sql        → dump SQL (INSERT) de toutes les tables
 *   export.php?type=standings  → classement au format CSV
 *   export.php?type=matches    → liste des matchs au format CSV
 */
require_once __DIR__ . '/includes/functions.php';
require_admin();

$type = $_GET['type'] ?? 'sql';
$date = date('Ymd-His');

/** Dump des lignes d'une table sous forme d'INSERT. */
function dump_table(PDO $pdo, string $table): string
{
    $out = "\n-- ----- Données : $table -----\n";
    try {
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return "-- (table $table absente)\n";
    }
    if (!$rows) {
        return $out . "-- (aucune donnée)\n";
    }
    $cols = array_keys($rows[0]);
    $colList = '`' . implode('`,`', $cols) . '`';
    foreach ($rows as $r) {
        $vals = array_map(
            fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
            array_values($r)
        );
        $out .= "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $vals) . ");\n";
    }
    return $out;
}

/** Envoie un CSV téléchargeable. */
function send_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
    fputcsv($out, $header, ';');
    foreach ($rows as $r) {
        fputcsv($out, $r, ';');
    }
    fclose($out);
    exit;
}

if ($type === 'sql') {
    $pdo = db();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="gripo-backup-' . $date . '.sql"');
    echo "-- Sauvegarde GRIPO TOURNAMENT — $date\n";
    echo "-- Restauration : créez d'abord les tables (sql/schema.sql) puis importez ce fichier.\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach (['players', 'matches', 'match_submissions', 'bracket_matches', 'activity_log'] as $t) {
        echo dump_table($pdo, $t);
    }
    echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

if ($type === 'standings') {
    $rows = [];
    foreach (compute_standings() as $s) {
        $rows[] = [
            $s['rank'], $s['name'], $s['played'], $s['win'], $s['draw'], $s['loss'],
            $s['gf'], $s['ga'], $s['gd'], $s['points'],
        ];
    }
    send_csv(
        'classement-' . $date . '.csv',
        ['Rang', 'Joueur', 'J', 'V', 'N', 'D', 'BP', 'BC', 'Diff', 'Pts'],
        $rows
    );
}

if ($type === 'matches') {
    $rows = [];
    foreach (matches_by_round() as $games) {
        foreach ($games as $m) {
            [$lbl] = status_label($m['status']);
            $rows[] = [
                $m['round'], $m['match_number'], $m['home_name'], $m['away_name'],
                $m['status'] === 'completed' ? $m['home_score'] : '',
                $m['status'] === 'completed' ? $m['away_score'] : '',
                $lbl,
            ];
        }
    }
    send_csv(
        'matchs-' . $date . '.csv',
        ['Round', 'Match', 'Domicile', 'Extérieur', 'Score dom.', 'Score ext.', 'Statut'],
        $rows
    );
}

// Type inconnu
flash('danger', 'Type d\'export inconnu.');
redirect('admin.php');
