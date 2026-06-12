/* BilikGo v2 — toasts, sticker modals, swipe engine with match popup & undo */
(function () {
  'use strict';

  /* ---------- toast lifetime (flash messages auto-dismiss) ---------- */
  document.querySelectorAll('.flash').forEach(function (t) {
    setTimeout(function () { t.classList.add('toast-out'); }, 3400);
    setTimeout(function () { t.remove(); }, 3800);
    t.addEventListener('click', function () { t.remove(); });
  });

  /* ---------- custom confirm modal (replaces browser confirm) ----------
     Any form with data-confirm="message" gets a sticker-style dialog. */
  var pendingForm = null;
  function buildModal() {
    var veil = document.createElement('div');
    veil.className = 'modal-veil'; veil.hidden = true;
    veil.innerHTML = '<div class="modal-box" role="dialog" aria-modal="true">'
      + '<h3 id="mTitle">Just checking 👀</h3><p id="mMsg"></p>'
      + '<div class="modal-actions">'
      + '<button type="button" class="btn btn-ghost" id="mNo">Keep it</button>'
      + '<button type="button" class="btn btn-primary" id="mYes">Yes, do it</button>'
      + '</div></div>';
    document.body.appendChild(veil);
    veil.querySelector('#mNo').addEventListener('click', close);
    veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
    veil.querySelector('#mYes').addEventListener('click', function () {
      var f = pendingForm; close();
      if (f) { f.dataset.ok = '1'; f.requestSubmit ? f.requestSubmit(f._btn || undefined) : f.submit(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    function close() { veil.hidden = true; pendingForm = null; }
    return veil;
  }
  var modal = null;
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.dataset || !f.dataset.confirm || f.dataset.ok === '1') { if (f.dataset) delete f.dataset.ok; return; }
    e.preventDefault();
    modal = modal || buildModal();
    pendingForm = f;
    f._btn = e.submitter || null;
    modal.querySelector('#mMsg').textContent = f.dataset.confirm;
    modal.hidden = false;
    modal.querySelector('#mYes').focus();
  }, true);


  /* ---------- photo galleries: tap right half = next, left half = prev ---------- */
  (function () {
    function setPhoto(g, idx) {
      var photos = JSON.parse(g.dataset.photos || '[]');
      if (!photos.length) return;
      idx = (idx + photos.length) % photos.length;
      g.dataset.idx = idx;
      g.style.backgroundImage = "url('" + photos[idx] + "')";
      var segs = g.querySelectorAll('.seg');
      segs.forEach(function (sg, i) { sg.classList.toggle('on', i === idx); });
      g.classList.remove('flip-anim');
      void g.offsetWidth; /* restart animation */
      g.classList.add('flip-anim');
    }
    /* taps on g-zones flip; on swipe cards we must NOT flip when the user
       was dragging, so track pointer travel and only flip on true taps. */
    var down = null;
    document.addEventListener('pointerdown', function (e) {
      var z = e.target.closest('.g-zone');
      if (z) down = { z: z, x: e.clientX, y: e.clientY };
    }, true);
    document.addEventListener('pointerup', function (e) {
      if (!down) return;
      var z = down.z, moved = Math.hypot(e.clientX - down.x, e.clientY - down.y);
      down = null;
      if (moved > 8) return; /* was a drag, not a tap */
      var g = z.closest('.gallery');
      if (!g) return;
      var idx = parseInt(g.dataset.idx || '0', 10);
      setPhoto(g, idx + (z.classList.contains('g-next') ? 1 : -1));
    }, true);
    /* keyboard: when a detail gallery exists, [ and ] flip photos */
    document.addEventListener('keydown', function (e) {
      if (e.target.matches('input, textarea, select')) return;
      var g = document.querySelector('.detail-photo.gallery');
      if (!g) return;
      var idx = parseInt(g.dataset.idx || '0', 10);
      if (e.key === ']') setPhoto(g, idx + 1);
      if (e.key === '[') setPhoto(g, idx - 1);
    });
  })();

  /* ---------- swipe deck ---------- */
  var page = document.querySelector('.swipe-page');
  if (!page) return;

  var csrf = page.dataset.csrf;
  var deck = document.getElementById('deck');
  var emptyEl = document.getElementById('deckEmpty');
  var actions = document.getElementById('swipeActions');
  var likeCountEl = document.getElementById('likeCount');
  var btnUndo = document.getElementById('btnUndo');
  var matchPop = document.getElementById('matchPop');
  var THRESHOLD = 90;
  var busy = false, lastSwipe = null; // {card, direction}

  function topCard() {
    var cards = deck.querySelectorAll('.swipe-card:not(.gone)');
    return cards.length ? cards[cards.length - 1] : null;
  }
  function send(listingId, direction) {
    return fetch('api_swipe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: csrf, listing_id: listingId, direction: direction })
    }).then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.ok && likeCountEl) likeCountEl.textContent = d.likes; return d; })
      .catch(function () {});
  }
  function hearts(x, y) {
    ['💚', '💛', '💚'].forEach(function (h, i) {
      var s = document.createElement('span');
      s.className = 'heart-float'; s.textContent = h;
      s.style.left = (x + (i - 1) * 28) + 'px';
      s.style.top = y + 'px';
      s.style.animationDelay = (i * 80) + 'ms';
      document.body.appendChild(s);
      setTimeout(function () { s.remove(); }, 1300);
    });
  }
  function showMatch(card) {
    if (!matchPop) return;
    matchPop.querySelector('#matchTitle').textContent = card.querySelector('h2').textContent;
    matchPop.hidden = false;
    clearTimeout(showMatch._t);
    showMatch._t = setTimeout(hideMatch, 2200);
  }
  function hideMatch() { if (matchPop) matchPop.hidden = true; }
  if (matchPop) {
    matchPop.addEventListener('click', function (e) { if (e.target === matchPop) hideMatch(); });
    var keep = document.getElementById('matchKeep');
    if (keep) keep.addEventListener('click', hideMatch);
  }

  function setStamps(card, dx) {
    var like = card.querySelector('.stamp-like'), nope = card.querySelector('.stamp-nope');
    var t = Math.min(Math.abs(dx) / THRESHOLD, 1);
    if (like) { like.style.opacity = dx > 0 ? t : 0; like.style.transform = 'rotate(-14deg) scale(' + (0.9 + t * 0.2) + ')'; }
    if (nope) { nope.style.opacity = dx < 0 ? t : 0; nope.style.transform = 'rotate(14deg) scale(' + (0.9 + t * 0.2) + ')'; }
  }
  function refreshUndo() { if (btnUndo) btnUndo.disabled = !lastSwipe; }
  function deckEmptyCheck() {
    if (!topCard()) { emptyEl.hidden = false; if (actions) actions.hidden = true; }
    else { emptyEl.hidden = true; if (actions) actions.hidden = false; }
  }
  function flyOut(card, direction, ev) {
    if (busy) return;
    busy = true;
    var sign = direction === 'like' ? 1 : -1;
    card.classList.remove('dragging');
    card.style.transition = 'transform .35s ease, opacity .35s ease';
    card.style.transform = 'translate(' + sign * (window.innerWidth * 0.9) + 'px,-40px) rotate(' + sign * 22 + 'deg)';
    card.style.opacity = '0';
    send(parseInt(card.dataset.id, 10), direction);
    if (direction === 'like') {
      var r = card.getBoundingClientRect();
      hearts(ev && ev.clientX ? ev.clientX : r.left + r.width / 2, ev && ev.clientY ? ev.clientY : r.top + r.height / 2);
      showMatch(card);
    }
    lastSwipe = { card: card, direction: direction };
    setTimeout(function () {
      card.classList.add('gone');
      busy = false;
      refreshUndo();
      deckEmptyCheck();
    }, 320);
  }
  function undo() {
    if (!lastSwipe || busy) return;
    var card = lastSwipe.card;
    lastSwipe = null;
    hideMatch();
    send(parseInt(card.dataset.id, 10), 'undo');
    card.classList.remove('gone');
    card.style.opacity = '1';
    card.style.transform = '';
    setStamps(card, 0);
    refreshUndo();
    deckEmptyCheck();
  }
  if (btnUndo) { btnUndo.addEventListener('click', undo); refreshUndo(); }

  var drag = null;
  deck.addEventListener('pointerdown', function (e) {
    if (e.target.closest('a, button')) return;
    var card = topCard();
    if (!card || busy || !card.contains(e.target)) return;
    drag = { card: card, startX: e.clientX, startY: e.clientY, dx: 0 };
    card.classList.add('dragging');
    deck.setPointerCapture(e.pointerId);
  });
  deck.addEventListener('pointermove', function (e) {
    if (!drag) return;
    drag.dx = e.clientX - drag.startX;
    var dy = (e.clientY - drag.startY) * 0.25;
    drag.card.style.transform = 'translate(' + drag.dx + 'px,' + dy + 'px) rotate(' + drag.dx / 16 + 'deg)';
    setStamps(drag.card, drag.dx);
  });
  function endDrag(e) {
    if (!drag) return;
    var card = drag.card, dx = drag.dx;
    drag = null;
    if (Math.abs(dx) > THRESHOLD) flyOut(card, dx > 0 ? 'like' : 'pass', e);
    else { card.classList.remove('dragging'); card.style.transform = ''; setStamps(card, 0); }
  }
  deck.addEventListener('pointerup', endDrag);
  deck.addEventListener('pointercancel', endDrag);

  var btnLike = document.getElementById('btnLike');
  var btnNope = document.getElementById('btnNope');
  if (btnLike) btnLike.addEventListener('click', function (e) { var c = topCard(); if (c) { setStamps(c, THRESHOLD); flyOut(c, 'like', e); } });
  if (btnNope) btnNope.addEventListener('click', function () { var c = topCard(); if (c) { setStamps(c, -THRESHOLD); flyOut(c, 'pass'); } });

  document.addEventListener('keydown', function (e) {
    if (e.target.matches('input, textarea, select')) return;
    if (e.key === 'ArrowRight') { var c = topCard(); if (c) { setStamps(c, THRESHOLD); flyOut(c, 'like'); } }
    if (e.key === 'ArrowLeft') { var c2 = topCard(); if (c2) { setStamps(c2, -THRESHOLD); flyOut(c2, 'pass'); } }
    if (e.key.toLowerCase() === 'u') undo();
  });
})();
