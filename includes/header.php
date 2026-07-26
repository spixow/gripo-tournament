<?php
require_once __DIR__ . '/functions.php';
$currentUser = current_user();
$page = $page ?? '';
$deadlineDT = new DateTime(APP_DEADLINE . ' ' . (defined('APP_DEADLINE_TIME') ? APP_DEADLINE_TIME : '00:00:00'));
$daysLeft = (new DateTime())->diff($deadlineDT)->days;
$deadlinePassed = new DateTime() > $deadlineDT;
$navPending = ($currentUser && empty($currentUser['is_admin']))
    ? pending_matches_count((int)$currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#eef4f0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Rajdhani:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<canvas id="bg-canvas"></canvas>

<nav class="navbar navbar-expand-lg navbar-fifa sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">⚽ GRIPO TOURNAMENT</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= $page==='home'?'active':'' ?>" href="index.php">Accueil</a></li>
                <li class="nav-item"><a class="nav-link <?= $page==='standings'?'active':'' ?>" href="standings.php">Classement</a></li>
                <li class="nav-item"><a class="nav-link <?= $page==='matches'?'active':'' ?>" href="matches.php">Matchs</a></li>
                <li class="nav-item"><a class="nav-link <?= $page==='bracket'?'active':'' ?>" href="bracket.php">Phase finale</a></li>
                <?php if ($currentUser): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative <?= $page==='profile'?'active':'' ?>" href="profile.php">
                            Mes matchs
                            <?php if ($navPending > 0): ?>
                                <span class="nav-pending-badge" title="<?= (int)$navPending ?> match(s) à jouer"><?= (int)$navPending ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative <?= $page==='claims'?'active':'' ?>" href="claims.php">
                            Réclamations
                            <?php if ($currentUser['is_admin']): $oc = open_claims_count(); if ($oc > 0): ?>
                                <span class="nav-pending-badge" title="<?= (int)$oc ?> réclamation(s) active(s)"><?= (int)$oc ?></span>
                            <?php endif; endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($currentUser && $currentUser['is_admin']): ?>
                    <li class="nav-item"><a class="nav-link <?= $page==='admin'?'active':'' ?>" href="admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <span class="deadline-chip">
                    ⏳ <?= $deadlinePassed ? 'Deadline dépassée' : $daysLeft.' j restants' ?>
                </span>
                <?php if ($currentUser): ?>
                    <span class="avatar" style="background:<?= e($currentUser['avatar_color']) ?>">
                        <?= e(mb_strtoupper(mb_substr($currentUser['display_name'],0,1))) ?>
                    </span>
                    <span class="text-light small d-none d-md-inline"><?= e($currentUser['display_name']) ?></span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">Déconnexion</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-fifa">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
