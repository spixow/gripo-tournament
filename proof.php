<?php
/**
 * Sert l'image de preuve d'un match, stockée en base (persistant).
 * Repli sur l'ancien fichier disque si présent (compatibilité).
 *   proof.php?id=<match_submission_id>
 */
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Requête invalide.');
}

try {
    $stmt = db()->prepare('SELECT proof_image, proof_data, proof_mime FROM match_submissions WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
} catch (Throwable $e) {
    $row = null;
}

if (!$row) {
    http_response_code(404);
    exit('Preuve introuvable.');
}

$mime = $row['proof_mime'] ?: 'image/jpeg';

// 1) Image stockée en base (cas normal)
if (!empty($row['proof_data'])) {
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . strlen($row['proof_data']));
    echo $row['proof_data'];
    exit;
}

// 2) Repli : ancien fichier sur disque
if (!empty($row['proof_image'])) {
    $path = UPLOAD_DIR . basename($row['proof_image']);
    if (is_file($path)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        header('Content-Type: ' . ($finfo->file($path) ?: 'image/jpeg'));
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

http_response_code(404);
exit('Image indisponible.');
