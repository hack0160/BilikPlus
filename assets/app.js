/* BilikGo — swipe deck (tenant) */
(function () {
  const page = document.querySelector('.swipe-page');
  if (!page) return;

  const csrf = page.dataset.csrf;
  const deck = document.getElementById('deck');
  const emptyEl = document.getElementById('deckEmpty');
  const actions = document.getElementById('swipeActions');
  const likeCountEl = document.getElementById('likeCount');

  const THRESHOLD = 90;          // px of drag needed to commit a swipe
  let busy = false;

  function topCard() {
    const cards = deck.querySelectorAll('.swipe-card:not(.gone)');
    return cards.length ? cards[cards.length - 1] : null;
  }

  function sendSwipe(listingId, direction) {
    fetch('api_swipe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: csrf, listing_id: listingId, direction: direction })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok && likeCountEl) likeCountEl.textContent = d.likes;
      })
      .catch(function () { /* swipe is recorded again on next page view if it failed */ });
  }

  function flyOut(card, direction) {
    if (busy) return;
    busy = true;
    const sign = direction === 'like' ? 1 : -1;
    card.classList.remove('dragging');
    card.style.transition = 'transform .35s ease, opacity .35s ease';
    card.style.transform = 'translate(' + sign * (window.innerWidth * 0.9) + 'px, -40px) rotate(' + sign * 22 + 'deg)';
    card.style.opacity = '0';
    sendSwipe(parseInt(card.dataset.id, 10), direction);
    setTimeout(function () {
      card.classList.add('gone');
      card.style.display = 'none';
      busy = false;
      if (!topCard()) {
        emptyEl.hidden = false;
        if (actions) actions.hidden = true;
      }
    }, 320);
  }

  function setStamps(card, dx) {
    const like = card.querySelector('.stamp-like');
    const nope = card.querySelector('.stamp-nope');
    const t = Math.min(Math.abs(dx) / THRESHOLD, 1);
    if (like) like.style.opacity = dx > 0 ? t : 0;
    if (nope) nope.style.opacity = dx < 0 ? t : 0;
  }

  /* drag with pointer events */
  let drag = null;
  deck.addEventListener('pointerdown', function (e) {
    if (e.target.closest('a, button')) return; // let links work
    const card = topCard();
    if (!card || busy || !card.contains(e.target)) return;
    drag = { card: card, startX: e.clientX, startY: e.clientY, dx: 0 };
    card.classList.add('dragging');
    card.setPointerCapture && deck.setPointerCapture(e.pointerId);
  });

  deck.addEventListener('pointermove', function (e) {
    if (!drag) return;
    drag.dx = e.clientX - drag.startX;
    const dy = (e.clientY - drag.startY) * 0.25;
    drag.card.style.transform =
      'translate(' + drag.dx + 'px,' + dy + 'px) rotate(' + drag.dx / 16 + 'deg)';
    setStamps(drag.card, drag.dx);
  });

  function endDrag() {
    if (!drag) return;
    const card = drag.card, dx = drag.dx;
    drag = null;
    if (Math.abs(dx) > THRESHOLD) {
      flyOut(card, dx > 0 ? 'like' : 'pass');
    } else {
      card.classList.remove('dragging');
      card.style.transform = '';
      setStamps(card, 0);
    }
  }
  deck.addEventListener('pointerup', endDrag);
  deck.addEventListener('pointercancel', endDrag);

  /* buttons */
  const btnLike = document.getElementById('btnLike');
  const btnNope = document.getElementById('btnNope');
  if (btnLike) btnLike.addEventListener('click', function () {
    const c = topCard(); if (c) { setStamps(c, THRESHOLD); flyOut(c, 'like'); }
  });
  if (btnNope) btnNope.addEventListener('click', function () {
    const c = topCard(); if (c) { setStamps(c, -THRESHOLD); flyOut(c, 'pass'); }
  });

  /* keyboard */
  document.addEventListener('keydown', function (e) {
    if (e.target.matches('input, textarea, select')) return;
    if (e.key === 'ArrowRight') { const c = topCard(); if (c) { setStamps(c, THRESHOLD); flyOut(c, 'like'); } }
    if (e.key === 'ArrowLeft')  { const c = topCard(); if (c) { setStamps(c, -THRESHOLD); flyOut(c, 'pass'); } }
  });
})();
