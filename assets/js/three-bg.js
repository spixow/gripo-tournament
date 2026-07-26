// Fond animé Three.js — Pelouse de stade EA SPORTS FC (terrain de football en perspective)
(function () {
    if (!window.THREE) return;
    const canvas = document.getElementById('bg-canvas');
    if (!canvas) return;

    const scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0xeef4f0, 0.05);

    const camera = new THREE.PerspectiveCamera(
        60, window.innerWidth / window.innerHeight, 0.1, 200
    );
    camera.position.set(0, 3.2, 9);

    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(window.innerWidth, window.innerHeight);

    // ---- Pelouse (plane avec texture de terrain) ----
    const pitchTex = makePitchTexture();
    pitchTex.wrapS = pitchTex.wrapT = THREE.RepeatWrapping;
    pitchTex.repeat.set(1, 2);
    pitchTex.anisotropy = renderer.capabilities.getMaxAnisotropy();

    const pitchMat = new THREE.MeshStandardMaterial({
        map: pitchTex,
        roughness: 0.95,
        metalness: 0.0,
    });
    const pitch = new THREE.Mesh(new THREE.PlaneGeometry(48, 96), pitchMat);
    pitch.rotation.x = -Math.PI / 2;
    pitch.position.y = -2.4;
    scene.add(pitch);

    // ---- Particules d'ambiance (atmosphère stade) ----
    const COUNT = 500;
    const positions = new Float32Array(COUNT * 3);
    const speeds = new Float32Array(COUNT);
    for (let i = 0; i < COUNT; i++) {
        positions[i * 3]     = (Math.random() - 0.5) * 60;
        positions[i * 3 + 1] = Math.random() * 22 - 2;
        positions[i * 3 + 2] = (Math.random() - 0.5) * 60;
        speeds[i] = 0.004 + Math.random() * 0.014;
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const mat = new THREE.PointsMaterial({
        size: 0.12,
        map: makeGlowTexture(),
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        color: 0x9dffcf,
        opacity: 0.7,
    });
    const points = new THREE.Points(geo, mat);
    scene.add(points);

    // ---- Éclairage stade (projecteurs) ----
    scene.add(new THREE.AmbientLight(0x2a4a3a, 0.9));
    const spot1 = new THREE.PointLight(0x00ff87, 1.3, 80);
    spot1.position.set(-14, 16, 6);
    scene.add(spot1);
    const spot2 = new THREE.PointLight(0xffffff, 0.9, 80);
    spot2.position.set(14, 18, -2);
    scene.add(spot2);
    const spot3 = new THREE.PointLight(0x00e0ff, 0.7, 70);
    spot3.position.set(0, 12, 14);
    scene.add(spot3);

    // ---- Interaction souris (léger parallax) ----
    let mouseX = 0, mouseY = 0;
    window.addEventListener('mousemove', (e) => {
        mouseX = (e.clientX / window.innerWidth - 0.5);
        mouseY = (e.clientY / window.innerHeight - 0.5);
    });

    // ---- Animation ----
    const clock = new THREE.Clock();
    function animate() {
        const t = clock.getElapsedTime();

        // Défilement de la pelouse (effet de caméra qui avance)
        pitchTex.offset.y = (t * 0.02) % 1;

        // Particules qui montent
        const pos = geo.attributes.position.array;
        for (let i = 0; i < COUNT; i++) {
            pos[i * 3 + 1] += speeds[i];
            if (pos[i * 3 + 1] > 20) pos[i * 3 + 1] = -2;
        }
        geo.attributes.position.needsUpdate = true;

        camera.position.x += (mouseX * 3 - camera.position.x) * 0.03;
        camera.position.y += (3.2 - mouseY * 1.6 - camera.position.y) * 0.03;
        camera.lookAt(0, -1, -6);

        renderer.render(scene, camera);
        requestAnimationFrame(animate);
    }
    animate();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });

    // ============ Génération de la texture de terrain ============
    function makePitchTexture() {
        const W = 768, H = 1152;           // ratio ~ terrain de foot (68 x 105)
        const c = document.createElement('canvas');
        c.width = W; c.height = H;
        const ctx = c.getContext('2d');

        // Bandes de tonte alternées
        const stripes = 12;
        const sh = H / stripes;
        for (let i = 0; i < stripes; i++) {
            ctx.fillStyle = (i % 2 === 0) ? '#0f7a3a' : '#0c6a33';
            ctx.fillRect(0, i * sh, W, sh);
        }
        // Vignettage doux pour fondre dans le fond clair
        const grd = ctx.createRadialGradient(W/2, H/2, H*0.15, W/2, H/2, H*0.7);
        grd.addColorStop(0, 'rgba(255,255,255,0)');
        grd.addColorStop(1, 'rgba(210,230,218,0.55)');
        ctx.fillStyle = grd;
        ctx.fillRect(0, 0, W, H);

        // Marquages blancs
        ctx.strokeStyle = 'rgba(235,255,245,0.8)';
        ctx.fillStyle = 'rgba(235,255,245,0.8)';
        ctx.lineWidth = 5;

        const m = 60; // marge
        // Contour du terrain
        ctx.strokeRect(m, m, W - 2 * m, H - 2 * m);

        // Ligne médiane
        ctx.beginPath();
        ctx.moveTo(m, H / 2);
        ctx.lineTo(W - m, H / 2);
        ctx.stroke();

        // Rond central
        ctx.beginPath();
        ctx.arc(W / 2, H / 2, 95, 0, Math.PI * 2);
        ctx.stroke();
        // Point central
        ctx.beginPath();
        ctx.arc(W / 2, H / 2, 6, 0, Math.PI * 2);
        ctx.fill();

        // Surfaces de réparation (haut et bas)
        const boxW = 300, boxH = 150;
        const gboxW = 150, gboxH = 60;
        // Haut
        ctx.strokeRect((W - boxW) / 2, m, boxW, boxH);
        ctx.strokeRect((W - gboxW) / 2, m, gboxW, gboxH);
        // Bas
        ctx.strokeRect((W - boxW) / 2, H - m - boxH, boxW, boxH);
        ctx.strokeRect((W - gboxW) / 2, H - m - gboxH, gboxW, gboxH);
        // Points de penalty
        ctx.beginPath(); ctx.arc(W / 2, m + boxH - 40, 6, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(W / 2, H - m - boxH + 40, 6, 0, Math.PI * 2); ctx.fill();
        // Arcs de surface
        ctx.beginPath(); ctx.arc(W / 2, m + boxH - 40, 70, 0.15 * Math.PI, 0.85 * Math.PI); ctx.stroke();
        ctx.beginPath(); ctx.arc(W / 2, H - m - boxH + 40, 70, 1.15 * Math.PI, 1.85 * Math.PI); ctx.stroke();

        return new THREE.CanvasTexture(c);
    }

    function makeGlowTexture() {
        const size = 64;
        const c = document.createElement('canvas');
        c.width = c.height = size;
        const ctx = c.getContext('2d');
        const g = ctx.createRadialGradient(size/2, size/2, 0, size/2, size/2, size/2);
        g.addColorStop(0, 'rgba(255,255,255,1)');
        g.addColorStop(0.3, 'rgba(140,255,200,0.85)');
        g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, size, size);
        return new THREE.CanvasTexture(c);
    }
})();
