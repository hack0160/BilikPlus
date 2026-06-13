/* BilikGo — ambient firefly background for app pages (lighter than landing) */
(function () {
  'use strict';
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (typeof THREE === 'undefined') return;
  var host = document.getElementById('bg3d');
  if (!host) return;

  var scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0xfaf7f2, 0.06);
  var camera = new THREE.PerspectiveCamera(60, innerWidth / innerHeight, 0.1, 80);
  camera.position.set(0, 0.4, 9);
  var renderer;
  try { renderer = new THREE.WebGLRenderer({ antialias: false, alpha: true }); }
  catch (e) { return; }
  renderer.setPixelRatio(Math.min(devicePixelRatio, 1.5));
  renderer.setSize(innerWidth, innerHeight);
  host.appendChild(renderer.domElement);

  function glow(hex) {
    var c = document.createElement('canvas'); c.width = c.height = 64;
    var x = c.getContext('2d');
    var g = x.createRadialGradient(32, 32, 0, 32, 32, 32);
    g.addColorStop(0, hex); g.addColorStop(0.35, hex + 'AA'); g.addColorStop(1, 'transparent');
    x.fillStyle = g; x.fillRect(0, 0, 64, 64);
    return new THREE.CanvasTexture(c);
  }
  var layers = [];
  [['#1e6e5c', 90, 0.11], ['#b97e2f', 60, 0.09], ['#a59c91', 50, 0.08]].forEach(function (cfg, li) {
    var count = cfg[1];
    var geo = new THREE.BufferGeometry();
    var pos = new Float32Array(count * 3), seed = new Float32Array(count);
    for (var i = 0; i < count; i++) {
      pos[i * 3] = (Math.random() - 0.5) * 24;
      pos[i * 3 + 1] = (Math.random() - 0.5) * 10;
      pos[i * 3 + 2] = (Math.random() - 0.5) * 14 - 2;
      seed[i] = Math.random() * Math.PI * 2;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    var pts = new THREE.Points(geo, new THREE.PointsMaterial({
      size: cfg[2], map: glow(cfg[0]), transparent: true, opacity: 0.28,
      depthWrite: false, blending: THREE.NormalBlending
    }));
    pts.userData = { seed: seed, speed: 0.14 + li * 0.05, base: pos.slice() };
    scene.add(pts); layers.push(pts);
  });

  var mx = 0, my = 0, visible = true;
  addEventListener('pointermove', function (e) {
    mx = (e.clientX / innerWidth - 0.5) * 2;
    my = (e.clientY / innerHeight - 0.5) * 2;
  }, { passive: true });
  addEventListener('resize', function () {
    camera.aspect = innerWidth / innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(innerWidth, innerHeight);
  });
  document.addEventListener('visibilitychange', function () { visible = !document.hidden; });

  var clock = new THREE.Clock();
  (function tick() {
    requestAnimationFrame(tick);
    if (!visible) return;
    var t = clock.getElapsedTime();
    layers.forEach(function (pts) {
      var p = pts.geometry.attributes.position.array;
      var base = pts.userData.base, seed = pts.userData.seed, sp = pts.userData.speed;
      for (var i = 0; i < seed.length; i++) {
        p[i * 3 + 1] = base[i * 3 + 1] + Math.sin(t * sp + seed[i]) * 0.5;
      }
      pts.geometry.attributes.position.needsUpdate = true;
    });
    camera.position.x += (mx * 0.6 - camera.position.x) * 0.04;
    camera.position.y += ((0.4 - my * 0.35) - camera.position.y) * 0.04;
    camera.lookAt(0, 0.1, 0);
    renderer.render(scene, camera);
  })();
})();
