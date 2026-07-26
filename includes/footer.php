</main>

<footer class="fifa-footer">
    <div class="container">
        ⚽ <?= e(APP_NAME) ?> &middot; EA SPORTS FC 26 &middot; League Phase &middot; Deadline : <?= e(app_deadline_date()) ?><br>
        Victoire = 3 pts &middot; Nul = 1 pt &middot; Défaite = 0 pt &middot; Équipes 4 étoiles uniques
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
<script src="assets/js/three-bg.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Remplace une preuve image cassée (fichier perdu) par un message clair
function proofBroken(img) {
    var target = img.closest('a') || img;
    var span = document.createElement('span');
    span.className = 'text-secondary small d-inline-block';
    span.innerHTML = '⚠️ Preuve indisponible';
    if (target.parentNode) target.parentNode.replaceChild(span, target);
}
</script>
</body>
</html>
