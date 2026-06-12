<?php
require __DIR__ . '/config.php';
$u = current_user();
if ($u) redirect(role_home($u['role']));

/* real content for the journey: live stats + featured rooms */
$stats = db()->query("SELECT
    (SELECT COUNT(*) FROM listings WHERE status = 'active') AS rooms,
    (SELECT COUNT(DISTINCT area) FROM listings WHERE status = 'active') AS areas,
    (SELECT COUNT(*) FROM swipes WHERE direction = 'like') AS likes,
    (SELECT COUNT(*) FROM users WHERE role = 'owner') AS owners")->fetch();

$featured = db()->query("SELECT l.*,
    (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes
    FROM listings l WHERE l.status = 'active'
    ORDER BY likes DESC, l.created_at DESC LIMIT 8")->fetchAll();

$areas = db()->query("SELECT DISTINCT area FROM listings WHERE status = 'active' ORDER BY area LIMIT 12")->fetchAll(PDO::FETCH_COLUMN);
if (!$areas) $areas = ['Cheras', 'Bangsar South', 'Subang Jaya', 'Mont Kiara', 'Cyberjaya', 'Setia Alam'];
$marquee = '';
foreach ($areas as $a) $marquee .= '<span>' . e($a) . ' <i>✦</i></span>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>BilikGo — your next bilik is one swipe away</title>
<meta name="description" content="Swipe through real rooms across the Klang Valley. Right to shortlist, left to skip. Owners list in minutes.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/landing.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%23FF3D5E'/><path d='M8 17l8-8 8 8v7H8z' fill='%23120D1D'/></svg>">
</head>
<body class="landing">

<div id="bg3d" aria-hidden="true"></div>

<header class="lnav">
  <a class="lbrand" href="index.php"><span class="mark">⌂</span> Bilik<b>Go</b></a>
  <nav>
    <a href="#how">How it works</a>
    <a href="#rooms">Rooms</a>
    <a href="#roles">For owners</a>
    <a class="lbtn lbtn-ghost" href="login.php">Sign in</a>
    <a class="lbtn lbtn-hot" href="register.php">Get started</a>
  </nav>
</header>

<main>
  <!-- =============================== HERO =============================== -->
  <section class="hero">
    <span class="kicker">KL · PJ · Subang · Cyberjaya</span>
    <h1>Your next <span class="stroke">bilik</span> is one <span class="glow">swipe</span> away.</h1>
    <p>House-hunting, minus the hundred open tabs. Swipe through real rooms across the Klang Valley — right kalau suka, left kalau tak nak.</p>
    <div class="hero-cta">
      <a class="lbtn lbtn-hot lbtn-lg" href="register.php">Start swiping — it's free</a>
      <a class="lbtn lbtn-ghost lbtn-lg" href="#how">See how it works</a>
    </div>
    <?php $h = $featured[0] ?? null; if ($h): ?>
    <div class="hero-card" aria-hidden="true">
      <div class="hc-photo" style="background-image:url('<?= listing_image($h) ?>')">
        <span class="hc-stamp">SUKA ✓</span>
        <span class="hc-price">RM <?= number_format((int)$h['price']) ?>/mo</span>
      </div>
      <div class="hc-body">
        <strong><?= e($h['title']) ?></strong>
        <span>📍 <?= e($h['area']) ?> · ♥ <?= (int)$h['likes'] ?> shortlists</span>
      </div>
    </div>
    <?php endif; ?>
    <div class="scroll-cue">Scroll</div>
  </section>

  <!-- neighbourhood marquee -->
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track"><?= $marquee . $marquee ?></div>
  </div>

  <!-- =============================== HOW =============================== -->
  <section class="sec" id="how">
    <div class="sec-head rv">
      <span class="tag">The journey</span>
      <h2>Three steps between you and move-in day.</h2>
      <p>No agents, no fees, no endless forms. The whole flow fits in your thumb.</p>
    </div>
    <div class="steps">
      <article class="step rv">
        <span class="step-n">01</span>
        <div>
          <h3>Swipe</h3>
          <p>Every card is a real room with the price, area and amenities up front. Right to shortlist, left to skip — and an undo button for the oops moments.</p>
        </div>
      </article>
      <article class="step rv rv-d1">
        <span class="step-n">02</span>
        <div>
          <h3>Match</h3>
          <p>Everything you like lands in your shortlist with the owner's phone and email unlocked. Sort by price, compare, and message them directly — no middleman.</p>
        </div>
      </article>
      <article class="step rv rv-d2">
        <span class="step-n">03</span>
        <div>
          <h3>Move in</h3>
          <p>Deal directly with the owner on viewing and deposit. Most rooms on BilikGo are move-in ready with utilities sorted.</p>
        </div>
      </article>
    </div>
  </section>

  <!-- ============================ LIVE STATS ============================ -->
  <section class="stats rv">
    <div><b data-count="<?= (int)$stats['rooms'] ?>">0</b><span>rooms live right now</span></div>
    <div><b data-count="<?= (int)$stats['likes'] ?>">0</b><span>shortlists made</span></div>
    <div><b data-count="<?= (int)$stats['areas'] ?>">0</b><span>neighbourhoods covered</span></div>
    <div><b data-count="<?= (int)$stats['owners'] ?>">0</b><span>owners listing with us</span></div>
  </section>

  <!-- =========================== FEATURED ROOMS ========================= -->
  <section class="sec" id="rooms">
    <div class="sec-head rv">
      <span class="tag">On the deck tonight</span>
      <h2>Rooms tenants are swiping right on.</h2>
      <p>A taste of what's live — sign in to swipe the full deck.</p>
    </div>
    <div class="shelf-wrap rv rv-d1">
      <div class="shelf">
        <?php foreach ($featured as $l): ?>
        <a class="room" href="register.php">
          <div class="r-photo" style="background-image:url('<?= listing_image($l) ?>')">
            <span class="r-price">RM <?= number_format((int)$l['price']) ?>/mo</span>
          </div>
          <div class="r-body">
            <strong><?= e($l['title']) ?></strong>
            <span>📍 <?= e($l['area']) ?> · <?= e($l['room_type']) ?> · ♥ <?= (int)$l['likes'] ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- =============================== ROLES ============================== -->
  <section class="sec" id="roles">
    <div class="sec-head rv">
      <span class="tag">Built for both sides</span>
      <h2>Tenants swipe. Owners list. Admins keep it honest.</h2>
    </div>
    <div class="roles">
      <article class="role rv">
        <span class="r-ic">💚</span>
        <h3>Tenants</h3>
        <p>A deck of rooms tuned to your budget, a shortlist that remembers everything, and owner contacts one tap away.</p>
      </article>
      <article class="role rv rv-d1">
        <span class="r-ic">🏠</span>
        <h3>Owners</h3>
        <p>List a room in two minutes with photos and amenities. Watch the likes roll in, edit or hide anytime, fill your room faster.</p>
      </article>
      <article class="role rv rv-d2">
        <span class="r-ic">🛡️</span>
        <h3>Admins</h3>
        <p>Every listing and account moderated. Anything that breaks the rules gets suspended before it reaches your deck.</p>
      </article>
    </div>
  </section>

  <!-- ================================ CTA =============================== -->
  <section class="cta rv">
    <h2>Jom, find your bilik.</h2>
    <p>Free for tenants. Free for owners. Takes a minute to join.</p>
    <a class="lbtn lbtn-hot lbtn-lg" href="register.php">Create your account</a>
    <p class="demo-creds">Just browsing? Demo logins — password <code>Demo@123</code>:
      <code>tenant@bilikgo.test</code> · <code>owner@bilikgo.test</code> · <code>admin@bilikgo.test</code></p>
  </section>
</main>

<footer class="lfoot">
  <span>BilikGo — find your bilik, swipe by swipe.</span>
  <span>Klang Valley, Malaysia</span>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="assets/landing.js"></script>
</body>
</html>
