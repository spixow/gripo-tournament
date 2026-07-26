<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Jeton de sécurité invalide, réessayez.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT * FROM players WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            flash('success', 'Bienvenue ' . $user['display_name'] . ' !');
            redirect('index.php');
        } else {
            $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
        }
    }
}
$page = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="text-center mb-3 mt-4">
            <div class="hero-sub mb-2">EA SPORTS FC 26 · LEAGUE PHASE</div>
            <h2 class="hero-title mb-0" style="font-size:1.9rem">⚽ GRIPO TOURNAMENT</h2>
        </div>
        <div class="glass p-4">
            <h3 class="text-center mb-1" style="font-size:1.4rem">Connexion</h3>
            <p class="text-center text-secondary mb-4 small">Accédez à votre espace joueur</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Nom d'utilisateur</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           placeholder="ex : smock">
                </div>
                <div class="mb-4">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="password" class="form-control" required
                           placeholder="••••••••">
                </div>
                <button class="btn btn-fifa w-100 py-2">Se connecter →</button>
            </form>
            <p class="text-secondary small mt-3 mb-0 text-center">
                Identifiants oubliés ? Contactez l'administrateur du tournoi.
            </p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
