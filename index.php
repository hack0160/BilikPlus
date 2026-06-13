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
        if (($_POST['tos'] ?? '') !== '1') $err = 'You must read and agree to the Terms & Disclaimer to create an account.';
        elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please fill in a valid name and email.';
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
        db()->prepare("INSERT INTO users (name,email,phone,password_hash,role,tos_accepted_at) VALUES (?,?,?,?,?,?)")
           ->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), $role, db_now()]);
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
$propTypes = ['Condominium', 'Apartment', 'Serviced Residence', 'Flat', 'Terrace House', 'Double Storey House'];
$furnTypes = ['Fully furnished', 'Partially furnished', 'Unfurnished'];
$f['prop']    = in_array($_GET['prop'] ?? '', $propTypes, true) ? $_GET['prop'] : '';
$f['furn']    = in_array($_GET['furn'] ?? '', $furnTypes, true) ? $_GET['furn'] : '';
$f['gender']  = in_array($_GET['gender'] ?? '', ['Any', 'Male', 'Female'], true) ? $_GET['gender'] : '';
$f['tenure']  = max(0, min(60, (int) ($_GET['tenure'] ?? 0)));   // "I can commit up to X months"
$f['amen']    = array_values(array_intersect((array) ($_GET['amen'] ?? []), amenity_options()));

$where = "l.status = 'active' AND o.status = 'active' AND l.listing_type = ?";
$args = [$f['type']];
if ($f['q'] !== '')   { $where .= " AND (l.area LIKE ? OR l.title LIKE ? OR l.city LIKE ?)"; $like = '%' . $f['q'] . '%'; array_push($args, $like, $like, $like); }
if ($f['min'] > 0)    { $where .= " AND l.price >= ?"; $args[] = $f['min']; }
if ($f['max'] > 0)    { $where .= " AND l.price <= ?"; $args[] = $f['max']; }
if (in_array($f['room'], $roomTypes, true)) { $where .= " AND l.room_type = ?"; $args[] = $f['room']; }
if ($f['prop'] !== '')   { $where .= " AND l.property_type = ?"; $args[] = $f['prop']; }
if ($f['furn'] !== '')   { $where .= " AND l.furnishing = ?"; $args[] = $f['furn']; }
if ($f['gender'] !== '') { // 'Male' shows Male-only + Any; 'Any' shows open-to-anyone units
    if ($f['gender'] === 'Any') { $where .= " AND l.gender_pref = 'Any'"; }
    else { $where .= " AND l.gender_pref IN ('Any', ?)"; $args[] = $f['gender']; }
}
if ($f['tenure'] > 0) { $where .= " AND l.min_tenure <= ?"; $args[] = $f['tenure']; }
foreach ($f['amen'] as $a) { $where .= " AND (',' || l.amenities || ',') LIKE ?"; $args[] = '%,' . $a . ',%'; }
if (DB_DRIVER === 'mysql') { $where = str_replace("(',' || l.amenities || ',')", "CONCAT(',', l.amenities, ',')", $where); }
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

/* photos for quick-view popups, one query */
$gridPhotos = [];
if ($results) {
    $ids = implode(',', array_map(fn($r) => (int) $r['id'], $results));
    foreach (db()->query("SELECT id, listing_id FROM images WHERE listing_id IN ($ids) ORDER BY sort, id") as $r) {
        $gridPhotos[(int) $r['listing_id']][] = 'image.php?id=' . (int) $r['id'];
    }
}
$searching = $f['q'] !== '' || $f['min'] || $f['max'] || $f['room'] !== '' || $f['prop'] !== ''
          || $f['furn'] !== '' || $f['gender'] !== '' || $f['tenure'] > 0 || $f['amen'] || isset($_GET['type']);
$advancedOn = $f['prop'] !== '' || $f['furn'] !== '' || $f['gender'] !== '' || $f['tenure'] > 0 || (bool) $f['amen'];

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
<title>Rooms for Rent &amp; Property for Sale in Malaysia | BilikGo</title>
<meta name="description" content="Search rooms for rent and homes for sale across Malaysia. Filter by price, area, room type, furnishing and amenities — see photos, maps and full move-in costs. Browse free, no sign-up needed.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= e(abs_url('index.php')) ?>">
<meta property="og:site_name" content="BilikGo">
<meta property="og:type" content="website">
<meta property="og:title" content="Rooms for Rent &amp; Property for Sale in Malaysia | BilikGo">
<meta property="og:description" content="Search rooms and homes across Malaysia with photos, maps and transparent move-in costs.">
<meta property="og:url" content="<?= e(abs_url('index.php')) ?>">
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<script type="application/ld+json"><?= json_encode([
  '@context' => 'https://schema.org', '@type' => 'WebSite',
  'name' => APP_NAME, 'url' => abs_url('index.php'),
  'potentialAction' => ['@type' => 'SearchAction',
    'target' => abs_url('index.php') . '?q={search_term_string}',
    'query-input' => 'required name=search_term_string'],
], JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/ld+json"><?= json_encode([
  '@context' => 'https://schema.org', '@type' => 'Organization',
  'name' => APP_NAME, 'url' => abs_url('index.php'),
], JSON_UNESCAPED_SLASHES) ?></script>
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
  <button class="navburger" type="button" aria-label="Menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <nav class="lnav-links">
    <a href="index.php?type=rent#results">Rent</a>
    <a href="index.php?type=sale#results">Buy</a>
    <a href="terms.php">Terms</a>
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
      <details class="sp-more" <?= $advancedOn ? 'open' : '' ?>>
        <summary>More filters<?= $advancedOn ? ' · active' : '' ?></summary>
        <div class="sp-more-grid">
          <label>Property type
            <select name="prop">
              <option value="">Any property</option>
              <?php foreach ($propTypes as $t): ?><option <?= $f['prop'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>Furnishing
            <select name="furn">
              <option value="">Any furnishing</option>
              <?php foreach ($furnTypes as $t): ?><option <?= $f['furn'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>I am / we are
            <select name="gender">
              <option value="">No preference</option>
              <option value="Male"   <?= $f['gender'] === 'Male' ? 'selected' : '' ?>>Male (incl. open units)</option>
              <option value="Female" <?= $f['gender'] === 'Female' ? 'selected' : '' ?>>Female (incl. open units)</option>
              <option value="Any"    <?= $f['gender'] === 'Any' ? 'selected' : '' ?>>Open-to-anyone units only</option>
            </select>
          </label>
          <label>Max commitment
            <select name="tenure">
              <option value="0">Any minimum stay</option>
              <?php foreach ([1 => 'Up to 1 month', 3 => 'Up to 3 months', 6 => 'Up to 6 months', 12 => 'Up to 12 months'] as $v => $lab): ?>
                <option value="<?= $v ?>" <?= $f['tenure'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="sp-amen">
          <?php foreach (amenity_options() as $a): ?>
            <label class="sp-amen-opt">
              <input type="checkbox" name="amen[]" value="<?= e($a) ?>" <?= in_array($a, $f['amen'], true) ? 'checked' : '' ?>>
              <span><?= e($a) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </details>
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
      <?php
        $qv = [
            'url' => listing_url($l), 'title' => $l['title'],
            'price' => price_label($l, false), 'sale' => $l['listing_type'] === 'sale',
            'area' => $l['area'] . ', ' . $l['city'], 'room' => $l['room_type'],
            'prop' => $l['property_type'], 'furn' => $l['furnishing'],
            'tenure' => (int) $l['min_tenure'],
            'movein' => $l['listing_type'] === 'rent' ? 'RM ' . number_format(movein_costs($l)['total']) : '',
            'amen' => array_slice(array_filter(array_map('trim', explode(',', $l['amenities']))), 0, 6),
            'photos' => $gridPhotos[(int) $l['id']] ?? [html_entity_decode(listing_image($l))],
        ];
      ?>
      <a class="room rv in" href="<?= e(listing_url($l)) ?>" data-qv='<?= e(json_encode($qv, JSON_UNESCAPED_SLASHES)) ?>'>
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
    <?php if ($areas): ?>
    <nav class="area-links" aria-label="Popular areas">
      <h2>Popular areas</h2>
      <p>
        <?php foreach (array_slice($areas, 0, 10) as $a): ?>
          <a href="index.php?type=rent&amp;q=<?= e(urlencode($a)) ?>#results">Rooms for rent in <?= e($a) ?></a>
        <?php endforeach; ?>
        <a href="index.php?type=sale#results">Property for sale in Malaysia</a>
      </p>
    </nav>
    <?php endif; ?>
    <p class="results-foot">
      <button class="linkish" data-modal="register">List your unit free</button> ·
      <button class="linkish" data-modal="login">Sign in</button> — needed only to contact owners or swipe.
    </p>
  </section>
</main>

<footer class="lfoot">
  <span>BilikGo — rooms and homes, made simple. <a href="terms.php">Terms &amp; Disclaimer</a></span>
  <span>Klang Valley, Malaysia</span>
</footer>


<!-- ============================ QUICK VIEW ============================ -->
<div class="lmodal" id="lm-qv" hidden>
  <div class="lmodal-card qv-card" role="dialog" aria-modal="true">
    <button class="lmodal-close" type="button" data-close aria-label="Close">✕</button>
    <div class="qv-photo gallery" id="qvPhoto">
      <div class="seg-row" id="qvSegs"></div>
      <span class="g-zone g-prev"></span><span class="g-zone g-next"></span>
      <span class="r-price" id="qvPrice"></span>
      <span class="r-type" id="qvType" hidden>FOR SALE</span>
    </div>
    <h2 id="qvTitle"></h2>
    <p class="qv-loc" id="qvLoc"></p>
    <div class="qv-facts" id="qvFacts"></div>
    <div class="qv-amen" id="qvAmen"></div>
    <div class="qv-actions">
      <a class="lbtn lbtn-hot" id="qvOpen" href="#">See full details, map &amp; costs</a>
    </div>
  </div>
</div>

<!-- ============================ TERMS MODAL ============================ -->
<div class="lmodal" id="lm-terms" hidden>
  <div class="lmodal-card terms-modal" role="dialog" aria-modal="true" aria-label="Terms and Disclaimer">
    <button class="lmodal-close" type="button" data-close aria-label="Close">✕</button>
    <iframe src="about:blank" data-src="terms.php" title="Terms of Use and Disclaimer"></iframe>
  </div>
</div>

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
    <div class="demo-creds-box">
      <p>Demo accounts — tap to fill, password is <code>Demo@123</code>:</p>
      <div class="demo-cred-row">
        <button type="button" class="demo-cred" data-email="tenant@bilikgo.test">👤 Tenant<small>tenant@bilikgo.test</small></button>
        <button type="button" class="demo-cred" data-email="owner@bilikgo.test">🏠 Owner<small>owner@bilikgo.test</small></button>
        <button type="button" class="demo-cred" data-email="admin@bilikgo.test">🛡 Admin<small>admin@bilikgo.test</small></button>
      </div>
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
      <label class="tos-check">
        <input type="checkbox" name="tos" value="1" required>
        <span>I have read and agree to the <a href="terms.php" target="_blank">Terms of Use &amp; Disclaimer</a>, and I confirm any content I post (including photos) is mine to publish.</span>
      </label>
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
  // quick view: tap a unit card -> popup; direct URL stays for new-tab/SEO
  var qvIdx = 0, qvPhotos = [];
  function qvShow(n) {
    qvIdx = (n + qvPhotos.length) % qvPhotos.length;
    var ph = document.getElementById('qvPhoto');
    ph.style.backgroundImage = "url('" + qvPhotos[qvIdx] + "')";
    document.querySelectorAll('#qvSegs .seg').forEach(function (sg, i) { sg.classList.toggle('on', i === qvIdx); });
  }
  document.addEventListener('click', function (e) {
    var card = e.target.closest('.room[data-qv]');
    if (!card || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
    e.preventDefault();
    var d = JSON.parse(card.dataset.qv);
    qvPhotos = d.photos; qvIdx = 0;
    document.getElementById('qvTitle').textContent = d.title;
    document.getElementById('qvLoc').textContent = '📍 ' + d.area;
    document.getElementById('qvPrice').textContent = d.price;
    document.getElementById('qvType').hidden = !d.sale;
    document.getElementById('qvOpen').href = d.url;
    var facts = [d.room, d.prop, d.furn];
    if (!d.sale && d.tenure > 0) facts.push('Min ' + d.tenure + ' mo');
    if (d.movein) facts.push('Move-in ' + d.movein);
    document.getElementById('qvFacts').innerHTML = facts.filter(Boolean)
      .map(function (f) { return '<span>' + f + '</span>'; }).join('');
    document.getElementById('qvAmen').textContent = d.amen.length ? d.amen.join(' · ') : '';
    var segs = document.getElementById('qvSegs');
    segs.innerHTML = ''; segs.hidden = d.photos.length < 2;
    d.photos.forEach(function (_, i) {
      var sp = document.createElement('span'); sp.className = 'seg' + (i === 0 ? ' on' : ''); segs.appendChild(sp);
    });
    qvShow(0);
    open('qv');
  });
  document.addEventListener('click', function (e) {
    var z = e.target.closest('#qvPhoto .g-zone');
    if (!z) return;
    qvShow(qvIdx + (z.classList.contains('g-next') ? 1 : -1));
  });
  // terms links on the landing open as a popup (page still exists for SEO/direct visits)
  document.addEventListener('click', function (e) {
    var t = e.target.closest('a[href^="terms.php"]');
    if (!t || e.ctrlKey || e.metaKey) return;
    e.preventDefault();
    var m = document.getElementById('lm-terms');
    var fr = m.querySelector('iframe');
    if (fr.src === 'about:blank' || fr.src === '') fr.src = fr.dataset.src;
    else if (!fr.src.includes('terms.php')) fr.src = fr.dataset.src;
    open('terms');
  });
  // tripleline menu toggle
  var burger = document.querySelector('.navburger');
  if (burger) burger.addEventListener('click', function () {
    var open = document.body.classList.toggle('nav-open');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function (e) {
    if (document.body.classList.contains('nav-open')
        && !e.target.closest('.lnav') && !e.target.closest('.topbar')) {
      document.body.classList.remove('nav-open');
      if (burger) burger.setAttribute('aria-expanded', 'false');
    }
  });
  // demo accounts: tap to fill the login form
  document.addEventListener('click', function (e) {
    var d = e.target.closest('.demo-cred');
    if (!d) return;
    var modal = document.getElementById('lm-login');
    modal.querySelector('input[name=email]').value = d.dataset.email;
    modal.querySelector('input[name=password]').value = 'Demo@123';
    modal.querySelector('input[name=password]').focus();
  });
  // auto-open if the server bounced us back with state
  var visible = document.querySelector('.lmodal:not([hidden])');
  if (visible) { document.body.style.overflow = 'hidden'; var f = visible.querySelector('.lmodal-err, .lmodal-ok'); if (f) f.scrollIntoView({ block: 'center' }); }
})();
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="assets/landing.js"></script>
</body>
</html>
