/* BilikGo landing — Three.js firefly field + immersive scroll engine.
   Degrades gracefully: if Three.js/WebGL is unavailable, the CSS gradient
   fallback on #bg3d remains and everything else still works. */
(function () {
  'use strict';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------- Three.js animated background ---------------- */
  function initThree() {
    if (reduced || typeof THREE === 'undefined') return;
    var host = document.getElementById('bg3d');
    if (!host) return;

    var scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0xfaf7f2, 0.055);
    var camera = new THREE.PerspectiveCamera(60, innerWidth / innerHeight, 0.1, 100);
    camera.position.set(0, 0.6, 9);

    var renderer;
    try { renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true }); }
    catch (e) { return; }
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.setSize(innerWidth, innerHeight);
    host.appendChild(renderer.domElement);

    // soft round glow sprite drawn on a canvas (no asset downloads)
    function glowTexture(hex) {
      var c = document.createElement('canvas'); c.width = c.height = 64;
      var x = c.getContext('2d');
      var g = x.createRadialGradient(32, 32, 0, 32, 32, 32);
      g.addColorStop(0, hex); g.addColorStop(0.35, hex + 'AA'); g.addColorStop(1, 'transparent');
      x.fillStyle = g; x.fillRect(0, 0, 64, 64);
      return new THREE.CanvasTexture(c);
    }

    // three drifting "firefly" layers — kampung lights at night
    var layers = [];
    [['#1e6e5c', 200, 0.13, 0.9],
     ['#b97e2f', 140, 0.10, 0.65],
     ['#a59c91', 120, 0.09, 0.5]].forEach(function (cfg, li) {
      var color = cfg[0], count = cfg[1], size = cfg[2], spread = cfg[3];
      var geo = new THREE.BufferGeometry();
      var pos = new Float32Array(count * 3);
      var seed = new Float32Array(count);
      for (var i = 0; i < count; i++) {
        pos[i * 3]     = (Math.random() - 0.5) * 26;
        pos[i * 3 + 1] = (Math.random() - 0.5) * 12 * spread;
        pos[i * 3 + 2] = (Math.random() - 0.5) * 16 - 2;
        seed[i] = Math.random() * Math.PI * 2;
      }
      geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
      var mat = new THREE.PointsMaterial({
        size: size, map: glowTexture(color), transparent: true, opacity: 0.35,
        depthWrite: false, blending: THREE.NormalBlending
      });
      var pts = new THREE.Points(geo, mat);
      pts.userData = { seed: seed, speed: 0.18 + li * 0.07, base: pos.slice() };
      scene.add(pts);
      layers.push(pts);
    });

    // distant "city grid" — faint wireframe plane sliding below
    var grid = new THREE.GridHelper(60, 60, 0xe3dccf, 0xefe9df);
    grid.position.y = -4.2;
    grid.material.transparent = true;
    grid.material.opacity = 0.5;
    scene.add(grid);

    var mouseX = 0, mouseY = 0, scrollY = 0;
    addEventListener('pointermove', function (e) {
      mouseX = (e.clientX / innerWidth - 0.5) * 2;
      mouseY = (e.clientY / innerHeight - 0.5) * 2;
    }, { passive: true });
    addEventListener('scroll', function () { scrollY = window.scrollY; }, { passive: true });
    addEventListener('resize', function () {
      camera.aspect = innerWidth / innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(innerWidth, innerHeight);
    });

    var clock = new THREE.Clock();
    var visible = true;
    document.addEventListener('visibilitychange', function () { visible = !document.hidden; });

    (function tick() {
      requestAnimationFrame(tick);
      if (!visible) return;
      var t = clock.getElapsedTime();
      layers.forEach(function (pts, li) {
        var p = pts.geometry.attributes.position.array;
        var base = pts.userData.base, seed = pts.userData.seed, sp = pts.userData.speed;
        for (var i = 0; i < seed.length; i++) {
          p[i * 3 + 1] = base[i * 3 + 1] + Math.sin(t * sp + seed[i]) * 0.55;
          p[i * 3]     = base[i * 3]     + Math.cos(t * sp * 0.6 + seed[i]) * 0.35;
        }
        pts.geometry.attributes.position.needsUpdate = true;
        pts.rotation.y = t * 0.012 * (li + 1);
      });
      grid.position.z = (t * 0.4) % 1;
      // camera: mouse parallax + scroll descent into the scene
      var sy = Math.min(scrollY / innerHeight, 2.2);
      camera.position.x += (mouseX * 0.9 - camera.position.x) * 0.04;
      camera.position.y += ((0.6 - mouseY * 0.5 - sy * 1.1) - camera.position.y) * 0.05;
      camera.position.z = 9 - sy * 1.4;
      camera.lookAt(0, 0.2 - sy * 0.8, 0);
      renderer.render(scene, camera);
    })();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initThree);
  else initThree();

  /* ---------------- nav state ---------------- */
  var nav = document.querySelector('.lnav');
  function navState() { if (nav) nav.classList.toggle('scrolled', scrollYNow() > 24); }
  function scrollYNow() { return window.scrollY || document.documentElement.scrollTop; }
  addEventListener('scroll', navState, { passive: true });
  navState();

  /* ---------------- reveal on scroll ---------------- */
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    });
  }, { threshold: 0.18, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll('.rv').forEach(function (el) { io.observe(el); });

  /* ---------------- animated counters ---------------- */
  function animateCount(el) {
    var target = parseInt(el.dataset.count, 10) || 0;
    var dur = 1300, t0 = null;
    function frame(ts) {
      if (!t0) t0 = ts;
      var k = Math.min((ts - t0) / dur, 1);
      k = 1 - Math.pow(1 - k, 3); // ease-out
      el.textContent = Math.round(target * k).toLocaleString();
      if (k < 1) requestAnimationFrame(frame);
    }
    if (reduced) { el.textContent = target.toLocaleString(); return; }
    requestAnimationFrame(frame);
  }
  var cio = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { animateCount(en.target); cio.unobserve(en.target); }
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('[data-count]').forEach(function (el) { cio.observe(el); });

  /* ---------------- hero card mouse tilt ---------------- */
  var hc = document.querySelector('.hero-card');
  if (hc && !reduced && matchMedia('(pointer:fine)').matches) {
    addEventListener('pointermove', function (e) {
      var rx = (e.clientY / innerHeight - 0.5) * -8;
      var ry = (e.clientX / innerWidth - 0.5) * 10;
      hc.style.transform = 'perspective(900px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
    }, { passive: true });
  }
})();
