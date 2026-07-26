// Export d'un élément DOM en image PNG (classement, bracket…)
window.exportNodePng = function (node, filename, btn) {
    if (!window.html2canvas || !node) { alert('Export indisponible.'); return; }
    var original = btn ? btn.textContent : null;
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Génération…'; }

    // Mode export : fonds solides, pas de canvas 3D ni de flou (non gérés par html2canvas)
    document.body.classList.add('exporting');
    var bg = (getComputedStyle(document.body).getPropertyValue('--bg-0') || '#eef4f0').trim();

    setTimeout(function () {
        html2canvas(node, {
            backgroundColor: bg,
            scale: 2,
            useCORS: true,
            logging: false,
            scrollX: 0,
            scrollY: 0,
            windowWidth: Math.max(node.scrollWidth, node.offsetWidth),
            width: node.scrollWidth,
            height: node.scrollHeight
        }).then(function (canvas) {
            var link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            link.click();
        }).catch(function (e) {
            alert("Échec de l'export image.");
        }).then(function () {
            document.body.classList.remove('exporting');
            if (btn) { btn.disabled = false; btn.textContent = original; }
        });
    }, 80);
};
