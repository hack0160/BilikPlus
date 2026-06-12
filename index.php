<?php
require __DIR__ . '/config.php';
$u = current_user();

/* ============ auth actions (modals post here; no standalone pages) ============ */
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    csrf_check();

    if ($action === 'login') {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        $st = db()->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$email]);
        $acc = $st->fetch();
        if (!$acc || !password_verify($pass, $acc['password_hash'])) {
            redirect('index.php?m=login&err=' . urlencode('Email or password is incorrect.') . '&email=' . urlencode($email)
                   . (!empty($_POST['next']) ? '&next=' . urlencode($_POST['next']) : ''));
        }
        if ($acc['status'] !== 'active') {
            redirect('index.php?m=login&err=' . urlencode('This account is suspended. Contact the site admin.') . '&email=' . urlencode($email));
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = $acc['id'];
        flash('Welcome back, ' . $acc['name'] . '!');
        $next = $_POST['next'] ?? '';
        if ($next !== '' && preg_match('#^[a-z_]+\.php(\?[A-Za-z0-9_=&%-]*)?$#', $next)) redirect($next);
        redirect(role_home($acc['role']));
    }

    if ($action === 'register') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $role  = ($_POST['role'] ?? '') === 'owner' ? 'owner' : 'tenant';
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        $err = '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please fill in a valid name and email.';
        elseif (strlen($pass) < 8)  $err = 'Password must be at least 8 characters.';
        elseif ($pass !== $pass2)   $err = 'Passwords do not match.';
        else {
            $st = db()->prepare("SELECT id FROM users WHERE email = ?");
            $st->execute([$email]);
            if ($st->fetch()) $err = 'That email is already registered. Try signing in instead.';
        }
        if ($err !== '') {
            redirect('index.php?m=register&err=' . urlencode($err)
                   . '&name=' . urlencode($name) . '&email=' . urlencode($email)
                   . '&phone=' . urlencode($phone) . '&role=' . $role);
        }
        db()->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?)")
           ->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), $role]);
        $_SESSION['uid'] = (int) db()->lastInsertId();
        session_regenerate_id(true);
        flash('Account created. Welcome to ' . APP_NAME . '!');
        redirect(role_home($role));
    }

    if ($action === 'forgot') {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $st = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $st->execute([$email]);
        $acc = $st->fetch();
        $demoLink = '';
        if ($acc) {
            $token = bin2hex(random_bytes(24));
            db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)")
               ->execute([$acc['id'], $token, db_now(3600)]);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                  . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/index.php?m=reset&token=' . $token;
            @mail($email, APP_NAME . ' password reset',
                  "Hi {$acc['name']},\n\nReset your password using this link (valid 1 hour):\n$link\n\nIf you didn't request this, ignore this email.");
            if (DEMO_MODE) $demoLink = $link;
        }
        redirect('index.php?m=forgot&sent=1' . ($demoLink ? '&link=' . urlencode($demoLink) : ''));
    }

    if ($action === 'reset') {
        $token = $_POST['token'] ?? '';
        $st = db()->prepare("SELECT pr.* FROM password_resets pr
                             WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > ?");
        $st->execute([$token, db_now()]);
        $pr = $st->fetch();
        if (!$pr) redirect('index.php?m=reset&token=&err=' . urlencode('This reset link is invalid or has expired. Request a new one.'));
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        $err = strlen($pass) < 8 ? 'Password must be at least 8 characters.'
             : ($pass !== $pass2 ? 'Passwords do not match.' : '');
        if ($err !== '') redirect('index.php?m=reset&token=' . urlencode($token) . '&err=' . urlencode($err));
        db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($pass, PASSWORD_DEFAULT), $pr['user_id']]);
        db()->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$pr['id']]);
        redirect('index.php?m=login&ok=' . urlencode('Password updated. Sign in with your new password.'));
    }
}

if ($u) redirect(role_home($u['role']));

/* ---------------- public search ---------------- */
$f = [
    'type' => in_array($_GET['type'] ?? '', ['rent', 'sale'], true) ? $_GET['type'] : 'rent',
    'q'    => trim($_GET['q'] ?? ''),
    'min'  => max(0, (int) ($_GET['min'] ?? 0)),
    'max'  => max(0, (int) ($_GET['max'] ?? 0)),
    'room' => trim($_GET['room'] ?? ''),
    'sort' => in_array($_GET['sort'] ?? '', ['cheap', 'pricey', 'liked'], true) ? $_GET['sort'] : 'new',
];
$roomTypes = ['Single Room', 'Medium Room', 'Master Room', 'Studio', 'Whole Unit'];
$where = "l.status = 'active' AND o.status = 'active' AND l.listing_type = ?";
$args = [$f['type']];
if ($f['q'] !== '')   { $where .= " AND (l.area LIKE ? OR l.title LIKE ? OR l.city LIKE ?)"; $like = '%' . $f['q'] . '%'; array_push($args, $like, $like, $like); }
if ($f['min'] > 0)    { $where .= " AND l.price >= ?"; $args[] = $f['min']; }
if ($f['max'] > 0)    { $where .= " AND l.price <= ?"; $args[] = $f['max']; }
if (in_array($f['room'], $roomTypes, true)) { $where .= " AND l.room_type = ?"; $args[] = $f['room']; }
$order = match ($f['sort']) {
    'cheap'  => 'l.price ASC',
    'pricey' => 'l.price DESC',
    'liked'  => 'likes DESC, l.created_at DESC',
    default  => 'l.created_at DESC',
};
$st = db()->prepare("SELECT l.*, 
        (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes
        FROM listings l JOIN users o ON o.id = l.owner_id
        WHERE $where ORDER BY $order LIMIT 24");
$st->execute($args);
$results = $st->fetchAll();
$searching = $f['q'] !== '' || $f['min'] || $f['max'] || $f['room'] !== '' || isset($_GET['type']);

/* modal state passed back by auth endpoints on error/success */
$m      = $_GET['m'] ?? '';
$mErr   = trim($_GET['err'] ?? '');
$mOk    = trim($_GET['ok'] ?? '');
$mSent  = isset($_GET['sent']);
$mLink  = $_GET['link'] ?? '';
$mToken = $_GET['token'] ?? '';
$mNext  = $_GET['next'] ?? '';
if (!preg_match('#^[a-z_]+\.php(\?[A-Za-z0-9_=&%-]*)?$#', $mNext)) $mNext = '';
$mTokenValid = false;
if ($m === 'reset' && $mToken !== '') {
    $st = db()->prepare("SELECT 1 FROM password_resets WHERE token = ? AND used = 0 AND expires_at > ?");
    $st->execute([$mToken, db_now()]);
    $mTokenValid = (bool) $st->fetch();
}
$pre    = ['email' => trim($_GET['email'] ?? ''), 'name' => trim($_GET['name'] ?? ''),
           'phone' => trim($_GET['phone'] ?? ''), 'role' => ($_GET['role'] ?? '') === 'owner' ? 'owner' : 'tenant'];

$areas = db()->query("SELECT DISTINCT area FROM listings WHERE status = 'active' ORDER BY area LIMIT 12")->fetchAll(PDO::FETCH_COLUMN);
if (!$areas) $areas = ['Cheras', 'Bangsar South', 'Subang Jaya', 'Mont Kiara', 'Cyberjaya', 'Setia Alam'];
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
    <a class="lbtn lbtn-ghost" href="index.php?m=login" data-modal="login">Sign in</a>
    <a class="lbtn lbtn-hot" href="index.php?m=register" data-modal="register">Get started</a>
  </nav>
</header>

<main>
  <!-- ================= compact hero + search ================= -->
  <section class="hero hero-compact">
    <h1>Find a place. <span class="glow">Fast.</span></h1>
    <form class="search-panel" method="get" action="index.php#results">
      <?php $qs = $_GET; unset($qs['type']);
            $toggleUrl = fn($t) => 'index.php?' . http_build_query(array_merge($qs, ['type' => $t])) . '#results'; ?>
      <div class="sp-toggle" role="tablist">
        <a href="<?= e($toggleUrl('rent')) ?>" class="<?= $f['type'] === 'rent' ? 'on' : '' ?>">Rent</a>
        <a href="<?= e($toggleUrl('sale')) ?>" class="<?= $f['type'] === 'sale' ? 'on' : '' ?>">Buy</a>
      </div>
      <input type="hidden" name="type" value="<?= e($f['type']) ?>">
      <div class="sp-fields">
        <input type="search" name="q" list="areaList" placeholder="Area, city or keyword — e.g. Cheras"
               value="<?= e($f['q']) ?>" aria-label="Search area or keyword">
        <datalist id="areaList">
          <?php foreach ($areas as $a): ?><option value="<?= e($a) ?>"><?php endforeach; ?>
        </datalist>
        <input type="number" name="min" min="0" placeholder="Min RM" value="<?= $f['min'] ?: '' ?>" aria-label="Minimum price">
        <input type="number" name="max" min="0" placeholder="Max RM" value="<?= $f['max'] ?: '' ?>" aria-label="Maximum price">
        <select name="room" aria-label="Room type">
          <option value="">Any type</option>
          <?php foreach ($roomTypes as $t): ?>
            <option <?= $f['room'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="sort" aria-label="Sort">
          <option value="new"    <?= $f['sort'] === 'new' ? 'selected' : '' ?>>Newest</option>
          <option value="cheap"  <?= $f['sort'] === 'cheap' ? 'selected' : '' ?>>Price ↑</option>
          <option value="pricey" <?= $f['sort'] === 'pricey' ? 'selected' : '' ?>>Price ↓</option>
          <option value="liked"  <?= $f['sort'] === 'liked' ? 'selected' : '' ?>>Most liked</option>
        </select>
        <button class="lbtn lbtn-hot">Search</button>
      </div>
    </form>
    <p class="hero-sub">Browse free, no account needed. <button class="linkish" data-modal="register">Tenants who sign up</button> get the swipe deck too.</p>
  </section>

  <!-- ================= results grid ================= -->
  <section class="sec sec-results" id="results">
    <div class="results-head">
      <h2><?= count($results) ?> unit<?= count($results) === 1 ? '' : 's' ?> <?= $f['type'] === 'sale' ? 'for sale' : 'for rent' ?><?= $f['q'] !== '' ? ' · "' . e($f['q']) . '"' : '' ?></h2>
      <?php if ($searching): ?><a class="linkish" href="index.php#results">Clear filters</a><?php endif; ?>
    </div>
    <?php if (!$results): ?>
      <div class="no-results">
        <p>No units match. Try widening the price range or clearing the area.</p>
        <a class="lbtn lbtn-ghost" href="index.php#results">Show everything</a>
      </div>
    <?php else: ?>
    <div class="unit-grid">
      <?php foreach ($results as $i => $l): ?>
      <a class="room rv in" href="listing.php?id=<?= (int)$l['id'] ?>">
        <div class="r-photo" style="background-image:url('<?= listing_image($l) ?>')">
          <span class="r-price"><?= price_label($l) ?></span>
          <?php if ($l['listing_type'] === 'sale'): ?><span class="r-type">FOR SALE</span><?php endif; ?>
        </div>
        <div class="r-body">
          <strong><?= e($l['title']) ?></strong>
          <span>📍 <?= e($l['area']) ?> · <?= e($l['room_type']) ?><?= (int)$l['likes'] ? ' · ♥ ' . (int)$l['likes'] : '' ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="results-foot">
      <button class="linkish" data-modal="register">List your unit free</button> ·
      <button class="linkish" data-modal="login">Sign in</button> — needed only to contact owners or swipe.
    </p>
  </section>
</main>

<footer class="lfoot">
  <span>BilikGo — find your bilik, swipe by swipe.</span>
  <span>Klang Valley, Malaysia</span>
</footer>


<!-- ============================ AUTH MODALS ============================ -->
<div class="lmodal" id="lm-login" <?= $m === 'login' ? '' : 'hidden' ?>>
  <div class="lmodal-card" role="dialog" aria-modal="true" aria-labelledby="lmlT">
    <button class="lmodal-close" type="button" data-close aria-label="Close">✕</button>
    <h2 id="lmlT">Welcome back</h2>
    <p class="sub">Your shortlist missed you.</p>
    <?php if ($m === 'login' && $mErr): ?><div class="lmodal-err"><?= e($mErr) ?></div><?php endif; ?>
    <?php if ($m === 'login' && $mOk): ?><div class="lmodal-ok"><?= e($mOk) ?></div><?php endif; ?>
    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <?php if ($mNext): ?><input type="hidden" name="next" value="<?= e($mNext) ?>"><?php endif; ?>
      <label>Email <input type="email" name="email" required autocomplete="email" value="<?= $m === 'login' ? e($pre['email']) : '' ?>"></label>
      <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
      <button class="lbtn lbtn-hot lbtn-block">Sign in</button>
    </form>
    <div class="lmodal-links">
      <button type="button" data-swap="forgot">Forgot password?</button>
      <span>New here? <button type="button" data-swap="register">Create an account</button></span>
    </div>
  </div>
</div>

<div class="lmodal" id="lm-register" <?= $m === 'register' ? '' : 'hidden' ?>>
  <div class="lmodal-card" role="dialog" aria-modal="true" aria-labelledby="lmrT">
    <button class="lmodal-close" type="button" data-close aria-label="Close">✕</button>
    <h2 id="lmrT">Create your account</h2>
    <p class="sub">Looking for a room, or renting one out?</p>
    <?php if ($m === 'register' && $mErr): ?><div class="lmodal-err"><?= e($mErr) ?></div><?php endif; ?>
    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="register">
      <div class="lm-roles">
        <label><input type="radio" name="role" value="tenant" <?= $pre['role'] === 'tenant' ? 'checked' : '' ?>>
          <span><strong>I'm a tenant</strong><small>Swipe &amp; shortlist rooms</small></span></label>
        <label><input type="radio" name="role" value="owner" <?= $pre['role'] === 'owner' ? 'checked' : '' ?>>
          <span><strong>I'm an owner</strong><small>List &amp; manage rooms</small></span></label>
      </div>
      <label>Full name <input type="text" name="name" required value="<?= $m === 'register' ? e($pre['name']) : '' ?>"></label>
      <label>Email <input type="email" name="email" required autocomplete="email" value="<?= $m === 'register' ? e($pre['email']) : '' ?>"></label>
      <label>Phone / WhatsApp <span class="opt">(optional)</span> <input type="tel" name="phone" placeholder="012-3456789" value="<?= $m === 'register' ? e($pre['phone']) : '' ?>"></label>
      <label>Password <span class="opt">(min 8 characters)</span> <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label>Confirm password <input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
      <button class="lbtn lbtn-hot lbtn-block">Create account</button>
    </form>
    <div class="lmodal-links">
      <span>Already registered? <button type="button" data-swap="login">Sign in</button></span>
    </div>
  </div>
</div>

<div class="lmodal" id="lm-forgot" <?= $m === 'forgot' ? '' : 'hidden' ?>>
  <div class="lmodal-card" role="dialog" aria-modal="true" aria-labelledby="lmfT">
    <button class="lmodal-close" type="button" data-close aria-label="Close">✕</button>
    <h2 id="lmfT">Forgot your password?</h2>
    <p class="sub">We'll send a reset link, valid for one hour.</p>
    <?php if ($m === 'forgot' && $mSent): ?>
      <div class="lmodal-ok">If that email is registered, a reset link has been sent.
        <?php if ($mLink): ?><br><br><strong>Demo mode</strong> — your link:<br><a href="<?= e($mLink) ?>"><?= e($mLink) ?></a><?php endif; ?>
      </div>
    <?php endif; ?>
    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="forgot">
      <label>Email <input type="email" name="email" required autocomplete="email"></label>
      <button class="lbtn lbtn-hot lbtn-block">Send reset link</button>
    </form>
    <div class="lmodal-links">
      <button type="button" data-swap="login">Back to sign in</button>
    </div>
  </div>
</div>

<div class="lmodal" id="lm-reset" <?= $m === 'reset' ? '' : 'hidden' ?>>
  <div class="lmodal-card" role="dialog" aria-modal="true" aria-labelledby="lmxT">
    <button class="lmodal-close" type="button" data-close aria-label="Close">✕</button>
    <?php if ($m === 'reset' && !$mTokenValid): ?>
      <h2 id="lmxT">Link expired</h2>
      <p class="sub">This reset link is invalid or has already been used.</p>
      <?php if ($mErr): ?><div class="lmodal-err"><?= e($mErr) ?></div><?php endif; ?>
      <button class="lbtn lbtn-hot lbtn-block" type="button" data-swap="forgot">Request a new link</button>
    <?php else: ?>
      <h2 id="lmxT">Choose a new password</h2>
      <p class="sub">Make it at least 8 characters.</p>
      <?php if ($m === 'reset' && $mErr): ?><div class="lmodal-err"><?= e($mErr) ?></div><?php endif; ?>
      <form method="post" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="token" value="<?= e($mToken) ?>">
        <label>New password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Confirm new password <input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
        <button class="lbtn lbtn-hot lbtn-block">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
/* auth modal controller */
(function () {
  function open(name) {
    document.querySelectorAll('.lmodal').forEach(function (m) { m.hidden = true; });
    var m = document.getElementById('lm-' + name);
    if (m) { m.hidden = false; var f = m.querySelector('input:not([type=hidden]):not([type=radio])'); if (f) f.focus(); }
    document.body.style.overflow = m && !m.hidden ? 'hidden' : '';
  }
  function closeAll() {
    document.querySelectorAll('.lmodal').forEach(function (m) { m.hidden = true; });
    document.body.style.overflow = '';
    if (location.search) history.replaceState(null, '', location.pathname);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-modal]');
    if (t) { e.preventDefault(); open(t.dataset.modal); return; }
    var sw = e.target.closest('[data-swap]');
    if (sw) { open(sw.dataset.swap); return; }
    if (e.target.closest('[data-close]')) { closeAll(); return; }
    if (e.target.classList && e.target.classList.contains('lmodal')) closeAll();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
  // auto-open if the server bounced us back with state
  var visible = document.querySelector('.lmodal:not([hidden])');
  if (visible) { document.body.style.overflow = 'hidden'; var f = visible.querySelector('.lmodal-err, .lmodal-ok'); if (f) f.scrollIntoView({ block: 'center' }); }
})();
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="assets/landing.js"></script>
</body>
</html>
