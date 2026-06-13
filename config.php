<?php
/**
 * BilikGo — room rental platform (improved iBilik concept)
 * Single-file core: database, auth, helpers, layout.
 * Requirements: PHP 8.0+ with pdo_sqlite (standard on most shared hosting).
 */

declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

define('APP_NAME', 'BilikGo');
define('BASE_DIR', __DIR__);
define('UPLOAD_DIR', BASE_DIR . '/uploads');
// Demo mode: password-reset links are shown on screen instead of relying on a
// configured mail server. Set to false once mail() works on your host.
define('DEMO_MODE', true);

/* ------------------------------------------------------- database settings
 * DB_DRIVER: 'sqlite' (zero-config, file based) or 'mysql' (recommended on
 * InfinityFree — create a database in the control panel under "MySQL
 * Databases", then copy the credentials it shows you into the constants
 * below. InfinityFree names look like: host sqlXXX.infinityfree.com,
 * database epiz_12345678_bilikgo, user epiz_12345678).
 */
define('DB_DRIVER', 'sqlite');                       // 'sqlite' or 'mysql'
define('DB_FILE',   BASE_DIR . '/data/bilikgo.sqlite'); // used by sqlite
define('MYSQL_HOST', 'sqlXXX.infinityfree.com');     // used by mysql
define('MYSQL_NAME', 'epiz_XXXXXXXX_bilikgo');
define('MYSQL_USER', 'epiz_XXXXXXXX');
define('MYSQL_PASS', 'your-vpanel-password');

/* ---------------------------------------------------------------- database */

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (DB_DRIVER === 'mysql') {
            $pdo = new PDO(
                'mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_NAME . ';charset=utf8mb4',
                MYSQL_USER, MYSQL_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $fresh = !$pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        } else {
            $fresh = !file_exists(DB_FILE);
            if (!is_dir(dirname(DB_FILE))) mkdir(dirname(DB_FILE), 0775, true);
            $pdo = new PDO('sqlite:' . DB_FILE, null, null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        migrate($pdo);
        if ($fresh) seed($pdo);
    }
    return $pdo;
}

/** Current UTC timestamp as a string — computed in PHP so it is identical
 *  for both database drivers. $offsetSeconds shifts it (e.g. +3600). */
function db_now(int $offsetSeconds = 0): string {
    return gmdate('Y-m-d H:i:s', time() + $offsetSeconds);
}

/** Insert or update a tenant's swipe — driver-specific UPSERT syntax. */
function swipe_upsert(int $tenantId, int $listingId, string $direction): void {
    $now = db_now();
    if (DB_DRIVER === 'mysql') {
        db()->prepare("INSERT INTO swipes (tenant_id, listing_id, direction, created_at)
                       VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE direction = VALUES(direction), created_at = VALUES(created_at)")
            ->execute([$tenantId, $listingId, $direction, $now]);
    } else {
        db()->prepare("INSERT INTO swipes (tenant_id, listing_id, direction, created_at)
                       VALUES (?,?,?,?)
                       ON CONFLICT(tenant_id, listing_id) DO UPDATE
                       SET direction = excluded.direction, created_at = excluded.created_at")
            ->execute([$tenantId, $listingId, $direction, $now]);
    }
}

function migrate(PDO $pdo): void {
    foreach (schema_statements(DB_DRIVER) as $sql) $pdo->exec($sql);
    // upgrade older installs: images gains listing_id + sort
    foreach (["ALTER TABLE images ADD COLUMN listing_id INTEGER",
              "ALTER TABLE images ADD COLUMN sort INTEGER NOT NULL DEFAULT 0",
              "ALTER TABLE listings ADD COLUMN listing_type VARCHAR(6) NOT NULL DEFAULT 'rent'",
              "ALTER TABLE listings ADD COLUMN min_tenure INTEGER NOT NULL DEFAULT 0",
              "ALTER TABLE listings ADD COLUMN dep_months DECIMAL(4,1) NOT NULL DEFAULT 0",
              "ALTER TABLE listings ADD COLUMN util_months DECIMAL(4,1) NOT NULL DEFAULT 0",
              "ALTER TABLE listings ADD COLUMN fee_rm INTEGER NOT NULL DEFAULT 0",
              "ALTER TABLE listings ADD COLUMN lat DOUBLE PRECISION",
              "ALTER TABLE listings ADD COLUMN lng DOUBLE PRECISION",
              "ALTER TABLE users ADD COLUMN tos_accepted_at DATETIME"] as $alter) {
        try { $pdo->exec($alter); } catch (Throwable $e) { /* column already exists */ }
    }
}

/** Full schema for either driver. Also used by setup.php and to generate
 *  the standalone .sql files in database/. */
function schema_statements(string $driver): array {
    if ($driver === 'mysql') {
        $id  = 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $fk  = 'INT UNSIGNED NOT NULL';
        $opt = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $txt = 'TEXT'; $vc = 'VARCHAR(255)'; $dt = 'DATETIME'; $blob = 'MEDIUMBLOB';
    } else {
        $id  = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $fk  = 'INTEGER NOT NULL';
        $opt = ')';
        $txt = 'TEXT'; $vc = 'TEXT'; $dt = 'TEXT'; $blob = 'BLOB';
    }
    return [
        "CREATE TABLE IF NOT EXISTS users (
            id $id,
            name $vc NOT NULL,
            email $vc NOT NULL UNIQUE,
            phone $vc DEFAULT '',
            password_hash $vc NOT NULL,
            role VARCHAR(10) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            tos_accepted_at $dt,
            created_at $dt NOT NULL DEFAULT CURRENT_TIMESTAMP
        $opt",
        "CREATE TABLE IF NOT EXISTS listings (
            id $id,
            owner_id $fk,
            title $vc NOT NULL,
            area $vc NOT NULL,
            city $vc NOT NULL,
            address $vc DEFAULT '',
            price INTEGER NOT NULL,
            listing_type VARCHAR(6) NOT NULL DEFAULT 'rent',
            min_tenure INTEGER NOT NULL DEFAULT 0,
            dep_months DECIMAL(4,1) NOT NULL DEFAULT 0,
            util_months DECIMAL(4,1) NOT NULL DEFAULT 0,
            fee_rm INTEGER NOT NULL DEFAULT 0,
            lat DOUBLE PRECISION,
            lng DOUBLE PRECISION,
            room_type $vc NOT NULL,
            property_type $vc NOT NULL,
            furnishing $vc NOT NULL,
            gender_pref VARCHAR(10) NOT NULL DEFAULT 'Any',
            amenities $txt,
            description $txt,
            image $vc DEFAULT '',
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            created_at $dt NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_listing_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
        $opt",
        "CREATE TABLE IF NOT EXISTS swipes (
            id $id,
            tenant_id $fk,
            listing_id $fk,
            direction VARCHAR(5) NOT NULL,
            created_at $dt NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uq_swipe UNIQUE (tenant_id, listing_id),
            CONSTRAINT fk_swipe_tenant  FOREIGN KEY (tenant_id)  REFERENCES users(id)    ON DELETE CASCADE,
            CONSTRAINT fk_swipe_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
        $opt",
        "CREATE TABLE IF NOT EXISTS password_resets (
            id $id,
            user_id $fk,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at $dt NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        $opt",
        "CREATE TABLE IF NOT EXISTS settings (
            name VARCHAR(50) NOT NULL PRIMARY KEY,
            value VARCHAR(255) NOT NULL
        $opt",
        "CREATE TABLE IF NOT EXISTS reports (
            id $id,
            listing_id $fk,
            reporter_id $fk,
            reason VARCHAR(50) NOT NULL,
            details $txt,
            created_at $dt NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uq_report UNIQUE (listing_id, reporter_id),
            CONSTRAINT fk_report_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
            CONSTRAINT fk_report_user FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
        $opt",
        "CREATE TABLE IF NOT EXISTS images (
            id $id,
            listing_id INTEGER,
            sort INTEGER NOT NULL DEFAULT 0,
            mime VARCHAR(30) NOT NULL,
            data $blob NOT NULL,
            created_at $dt NOT NULL DEFAULT CURRENT_TIMESTAMP
        $opt",
    ];
}

function seed(PDO $pdo): void {
    $hash = password_hash('Demo@123', PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?)");
    $ins->execute(['Site Admin', 'admin@bilikgo.test', '012-0000001', $hash, 'admin']);
    $ins->execute(['Aiman Properties', 'owner@bilikgo.test', '012-0000002', $hash, 'owner']);
    $ins->execute(['Mei Ling Homes', 'owner2@bilikgo.test', '012-0000003', $hash, 'owner']);
    $ins->execute(['Demo Tenant', 'tenant@bilikgo.test', '012-0000004', $hash, 'tenant']);

    $rooms = [
        // owner_id, title, area, city, price, room_type, property, furnishing, gender, amenities, desc, img
        [2,'Cozy medium room near MRT Cheras','Cheras','Kuala Lumpur',650,'Medium Room','Condominium','Fully furnished','Any','Wi-Fi,Air-conditioning,Washing machine,Near MRT/LRT,Pool,Gym','5 min walk to MRT Taman Mutiara. Utilities included, friendly housemates, move-in ready.','seed1.svg'],
        [2,'Master room with bathroom, Bangsar South','Bangsar South','Kuala Lumpur',1200,'Master Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Air-conditioning,Private bathroom,Pool,Gym,Parking','Attached bathroom, KL skyline view, walking distance to LRT Kerinchi and offices.','seed2.svg'],
        [2,'Budget single room, SS15 Subang Jaya','SS15, Subang Jaya','Selangor',480,'Single Room','Apartment','Partially furnished','Female','Wi-Fi,Washing machine,Near MRT/LRT,Near university','Perfect for students — Taylor\'s and INTI nearby. Quiet female-only unit.','seed3.svg'],
        [2,'Big master room, Mont Kiara expat area','Mont Kiara','Kuala Lumpur',1500,'Master Room','Condominium','Fully furnished','Any','Wi-Fi,Air-conditioning,Private bathroom,Pool,Gym,Parking,Security / guarded','Premium condo with full facilities, balcony, covered parking included.','seed4.svg'],
        [3,'Medium room in landed house, Petaling Jaya','SS2, Petaling Jaya','Selangor',600,'Medium Room','Terrace House','Partially furnished','Male','Wi-Fi,Washing machine,Parking,Near food court','Quiet neighbourhood, famous SS2 food nearby, car porch parking available.','seed5.svg'],
        [3,'Single room walk to Cyberjaya offices','Cyberjaya','Selangor',520,'Single Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Air-conditioning,Pool,Gym,Shuttle','Ideal for tech workers. Shuttle to major offices, utilities capped at RM50.','seed6.svg'],
        [3,'Master room, Setia Alam with parking','Setia Alam','Selangor',850,'Master Room','Double Storey House','Fully furnished','Any','Wi-Fi,Air-conditioning,Private bathroom,Parking,Garden','Spacious landed home near Setia City Mall. Includes one car park bay.','seed7.svg'],
        [2,'Studio-style room, KLCC fringe','Kampung Baru','Kuala Lumpur',980,'Studio','Apartment','Fully furnished','Any','Wi-Fi,Air-conditioning,Kitchenette,Near MRT/LRT','Own kitchenette and entrance. 10 min to KLCC, halal eateries downstairs.','seed8.svg'],
        [3,'Female unit medium room, Wangsa Maju','Wangsa Maju','Kuala Lumpur',580,'Medium Room','Condominium','Fully furnished','Female','Wi-Fi,Air-conditioning,Pool,Near MRT/LRT,Security / guarded','Female-only unit, 7 min walk to LRT Wangsa Maju, near AEON Big.','seed9.svg'],
        [2,'Low-deposit single room, Kepong','Kepong','Kuala Lumpur',450,'Single Room','Flat','Partially furnished','Male','Wi-Fi,Washing machine,Near bus stop','One month deposit only. Near Kepong market and bus routes.','seed10.svg'],
    ];
    $li = $pdo->prepare("INSERT INTO listings
        (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image,
         min_tenure,dep_months,util_months,fee_rm)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $terms = [[12, 2, 0.5, 200], [12, 2, 0.5, 250], [6, 1, 0.5, 150], [12, 2.5, 0.5, 300], [6, 1, 0.5, 100],
              [3, 1, 0.5, 100], [12, 2, 1, 250], [6, 1.5, 0.5, 200], [12, 2, 0.5, 150], [1, 1, 0, 50]];
    foreach ($rooms as $i => $r) {
        $r[11] = 'assets/img/' . $r[11];
        $li->execute(array_merge($r, $terms[$i % count($terms)]));
    }

    $sales = [
        [2,'Renovated 3R2B condo, Cheras','Cheras','Kuala Lumpur',438000,'Whole Unit','Condominium','Partially furnished','Any','Pool,Gym,Security / guarded,Parking,Near MRT/LRT','Freehold 1,012 sqft, new kitchen cabinets, 2 covered car parks.','seed1.svg'],
        [3,'Corner-lot double storey, Setia Alam','Setia Alam','Selangor',795000,'Whole Unit','Double Storey House','Unfurnished','Any','Garden,Parking,Gated guarded','22x75 corner with extra land, move-in condition.','seed7.svg'],
        [2,'Compact studio, KLCC fringe (investor unit)','Kampung Baru','Kuala Lumpur',365000,'Studio','Serviced Residence','Fully furnished','Any','Pool,Gym,Near MRT/LRT,Airbnb friendly','Tenanted at RM1.9k/mo, 5.9% gross yield, walk to LRT.','seed8.svg'],
        [3,'Family apartment, Wangsa Maju','Wangsa Maju','Kuala Lumpur',420000,'Whole Unit','Apartment','Partially furnished','Any','Near MRT/LRT,Security / guarded,Playground','3 rooms 2 baths, 950 sqft, near AEON Big and LRT.','seed9.svg'],
    ];
    $ls = $pdo->prepare("INSERT INTO listings
        (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image)
        VALUES (?,?,?,?,?,'sale',?,?,?,?,?,?,?)");
    foreach ($sales as $r) {
        $r[11] = 'assets/img/' . $r[11];
        $ls->execute($r);
    }

    /* give every demo listing 3 photos so carousels/galleries show out of the box */
    $listingIds = $pdo->query("SELECT id FROM listings ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $imgIns = $pdo->prepare("INSERT INTO images (listing_id, sort, mime, data, created_at) VALUES (?,?,?,?,?)");
    $now = gmdate('Y-m-d H:i:s');
    foreach ($listingIds as $n => $lid) {
        for ($k = 0; $k < 3; $k++) {
            $svgFile = BASE_DIR . '/assets/img/seed' . ((($n + $k) % 10) + 1) . '.svg';
            if (!is_file($svgFile)) continue;
            $imgIns->bindValue(1, (int) $lid, PDO::PARAM_INT);
            $imgIns->bindValue(2, $k, PDO::PARAM_INT);
            $imgIns->bindValue(3, 'image/svg+xml');
            $imgIns->bindValue(4, file_get_contents($svgFile), PDO::PARAM_LOB);
            $imgIns->bindValue(5, $now);
            $imgIns->execute();
        }
    }
    /* approximate map pins for the demo areas */
    $coords = [
        'Cheras' => [3.0850, 101.7480], 'Bangsar South' => [3.1110, 101.6650],
        'SS15, Subang Jaya' => [3.0760, 101.5860], 'Mont Kiara' => [3.1660, 101.6510],
        'SS2, Petaling Jaya' => [3.1170, 101.6240], 'Cyberjaya' => [2.9220, 101.6500],
        'Setia Alam' => [3.1030, 101.4570], 'Kampung Baru' => [3.1660, 101.7050],
        'Wangsa Maju' => [3.2050, 101.7320], 'Kepong' => [3.2100, 101.6380],
    ];
    $geo = $pdo->prepare("UPDATE listings SET lat = ?, lng = ? WHERE area = ?");
    foreach ($coords as $area => $c) $geo->execute([$c[0], $c[1], $area]);

    $coverSel = $pdo->prepare("SELECT MIN(id) FROM images WHERE listing_id = ?");
    $coverUpd = $pdo->prepare("UPDATE listings SET image = ? WHERE id = ?");
    foreach ($listingIds as $lid) {
        $coverSel->execute([(int) $lid]);
        $imgId = $coverSel->fetchColumn();
        if ($imgId) $coverUpd->execute(['image.php?id=' . (int) $imgId, (int) $lid]);
    }
}

/* ------------------------------------------------------------------- utils */

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): never { header('Location: ' . $path); exit; }

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }

function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Session expired. Go back and try again.');
    }
}

/* -------------------------------------------------------------------- auth */

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $u = false;
    if ($u === false) {
        $st = db()->prepare("SELECT * FROM users WHERE id = ?");
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if ($u && $u['status'] !== 'active') { session_destroy(); $u = null; }
    }
    return $u;
}

function require_login(): array {
    $u = current_user();
    if (!$u) { flash('Please sign in first.', 'warn'); redirect('index.php?m=login'); }
    return $u;
}

function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        flash('You do not have access to that page.', 'warn');
        redirect(role_home($u['role']));
    }
    return $u;
}

function role_home(string $role): string {
    return match ($role) {
        'admin' => 'admin_dashboard.php',
        'owner' => 'owner_dashboard.php',
        default => 'swipe.php',
    };
}

/* ------------------------------------------------------------------ layout */

function page_top(string $title, ?array $u = null, array $meta = []): void {
    $u = $u ?? current_user();
    $flash = take_flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($meta['title'] ?? ($title . ' · ' . APP_NAME)) ?></title>
<?php if (!empty($meta['description'])): ?><meta name="description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
<meta name="robots" content="<?= !empty($meta['noindex']) ? 'noindex, nofollow' : 'index, follow' ?>">
<?php if (!empty($meta['canonical'])): ?><link rel="canonical" href="<?= e($meta['canonical']) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= APP_NAME ?>">
<meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($meta['title'] ?? $title) ?>">
<?php if (!empty($meta['description'])): ?><meta property="og:description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
<?php if (!empty($meta['image'])): ?><meta property="og:image" content="<?= e($meta['image']) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<?php if (!empty($meta['jsonld'])): foreach ((array) $meta['jsonld'] as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endforeach; endif; ?>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%23D7263D'/><path d='M8 17l8-8 8 8v7H8z' fill='%23F7F6F2'/></svg>">
</head>
<body>
<div id="bg3d" aria-hidden="true"></div>
<header class="topbar">
  <button class="navburger" type="button" aria-label="Menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <a class="brand" href="index.php"><span class="brand-mark">⌂</span> Bilik<span>Go</span></a>
  <nav class="nav">
  <?php if ($u): ?>
      <?php if ($u['role'] === 'tenant'): ?>
        <a href="swipe.php">Swipe</a>
        <a href="shortlist.php">Shortlist</a>
      <?php elseif ($u['role'] === 'owner'): ?>
        <a href="owner_dashboard.php">Dashboard</a>
        <a href="owner_listings.php">My listings</a>
        <a href="owner_edit.php" class="nav-cta">+ New listing</a>
      <?php else: ?>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_listings.php">Listings</a>
        <a href="admin_users.php">Users</a>
      <?php endif; ?>
      <span class="nav-user"><?= e($u['name']) ?> · <em><?= e($u['role']) ?></em></span>
      <a href="logout.php">Log out</a>
  <?php else: ?>
      <a href="index.php?m=login">Sign in</a>
      <a href="index.php?m=register" class="nav-cta">Create account</a>
  <?php endif; ?>
  </nav>
</header>
<?php if ($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>
<main class="main">
<?php
}

function page_bottom(): void {
    ?>
</main>
<footer class="footer">
  <span><?= APP_NAME ?> — rooms and homes, made simple. <a href="terms.php">Terms &amp; Disclaimer</a></span>
  <span>Demo build · Klang Valley, Malaysia</span>
</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<script src="assets/bg.js" defer></script>
<script src="assets/app.js"></script>
</body>
</html><?php
}

/* ----------------------------------------------------------------- uploads
 * Photos are stored INSIDE the database (images table) so that a single
 * database backup/export contains everything, and git/FTP deploys can
 * never wipe them. Listing.image holds either:
 *   - 'image.php?id=N'    -> a photo stored in the database, or
 *   - 'assets/img/...svg' -> a bundled seed image (file on disk).
 */

/** Store every file in a multi-file input against a listing.
 *  Returns how many photos were saved. Max 6 photos per listing. */
function handle_images_upload(string $field, int $listingId): int {
    if (empty($_FILES[$field]['name'][0])) return 0;
    $files = $_FILES[$field];
    $st = db()->prepare("SELECT COUNT(*) FROM images WHERE listing_id = ?");
    $st->execute([$listingId]);
    $existing = (int) $st->fetchColumn();
    $sortSt = db()->prepare("SELECT COALESCE(MAX(sort), -1) FROM images WHERE listing_id = ?");
    $sortSt->execute([$listingId]);
    $sort = (int) $sortSt->fetchColumn();
    $saved = 0;
    $n = count($files['name']);
    for ($i = 0; $i < $n; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($existing + $saved >= 6) throw new RuntimeException('Maximum 6 photos per listing.');
        if ($files['size'][$i] > 3 * 1024 * 1024) throw new RuntimeException('Each image must be 3 MB or smaller.');
        $info = @getimagesize($files['tmp_name'][$i]);
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Only JPG, PNG or WebP images are accepted.');
        }
        $sort++;
        $binary = watermark_image(file_get_contents($files['tmp_name'][$i]), $mime);
        $ins = db()->prepare("INSERT INTO images (listing_id, sort, mime, data, created_at) VALUES (?,?,?,?,?)");
        $ins->bindValue(1, $listingId, PDO::PARAM_INT);
        $ins->bindValue(2, $sort, PDO::PARAM_INT);
        $ins->bindValue(3, $mime);
        $ins->bindValue(4, $binary, PDO::PARAM_LOB);
        $ins->bindValue(5, db_now());
        $ins->execute();
        $saved++;
    }
    return $saved;
}

/** Stamp a small site watermark (logo mark + name + ©year) at the bottom
 *  right of an uploaded photo. Returns the original bytes untouched if GD
 *  is unavailable or the format can't be processed. */
function watermark_image(string $binary, string $mime): string {
    if (!function_exists('imagecreatefromstring')) return $binary;
    $im = @imagecreatefromstring($binary);
    if (!$im) return $binary;
    $w = imagesx($im); $h = imagesy($im);
    if ($w < 120 || $h < 80) { imagedestroy($im); return $binary; }

    imagealphablending($im, true);
    $text = APP_NAME . ' (c) ' . date('Y');
    $font = $w >= 700 ? 3 : 2;
    $tw = imagefontwidth($font) * strlen($text);
    $th = imagefontheight($font);
    $pad = 6; $iconW = $th; // square house mark
    $bw = $tw + $iconW + $pad * 3;
    $bh = $th + $pad * 2;
    $x0 = $w - $bw - 8; $y0 = $h - $bh - 8;

    $bg    = imagecolorallocatealpha($im, 20, 18, 16, 60);   // translucent dark band
    $white = imagecolorallocate($im, 255, 255, 255);
    $green = imagecolorallocate($im, 86, 178, 152);
    imagefilledrectangle($im, $x0, $y0, $x0 + $bw, $y0 + $bh, $bg);
    // tiny house mark: roof triangle + body
    $ix = $x0 + $pad; $iy = $y0 + $pad;
    imagefilledpolygon($im, [$ix, $iy + (int)($iconW * .45), $ix + (int)($iconW / 2), $iy, $ix + $iconW, $iy + (int)($iconW * .45)], $green);
    imagefilledrectangle($im, $ix + (int)($iconW * .15), $iy + (int)($iconW * .45), $ix + (int)($iconW * .85), $iy + $iconW, $green);
    imagestring($im, $font, $ix + $iconW + $pad, $y0 + $pad, $text, $white);

    ob_start();
    $ok = match ($mime) {
        'image/png'  => imagepng($im, null, 8),
        'image/webp' => function_exists('imagewebp') ? imagewebp($im, null, 88) : false,
        default      => imagejpeg($im, null, 88),
    };
    $out = ob_get_clean();
    imagedestroy($im);
    return ($ok && $out !== false && strlen($out) > 0) ? $out : $binary;
}

/** All photo URLs for a listing, in order. Falls back to the legacy cover
 *  value or a bundled seed image so every listing always has >= 1 photo. */
function listing_photos(int $listingId, ?string $legacy = null): array {
    $st = db()->prepare("SELECT id FROM images WHERE listing_id = ? ORDER BY sort, id");
    $st->execute([$listingId]);
    $urls = array_map(fn($r) => 'image.php?id=' . (int) $r['id'], $st->fetchAll());
    if ($urls) return $urls;
    if ($legacy) return [$legacy];
    return ['assets/img/seed1.svg'];
}

/** Refresh the listing's cover (listings.image) to its first photo. */
function refresh_cover(int $listingId): void {
    $st = db()->prepare("SELECT id FROM images WHERE listing_id = ? ORDER BY sort, id LIMIT 1");
    $st->execute([$listingId]);
    $row = $st->fetch();
    if ($row) {
        db()->prepare("UPDATE listings SET image = ? WHERE id = ?")
            ->execute(['image.php?id=' . (int) $row['id'], $listingId]);
    }
}

/** Remove every stored photo belonging to a listing. */
function delete_listing_images(int $listingId): void {
    db()->prepare("DELETE FROM images WHERE listing_id = ?")->execute([$listingId]);
}

/** Legacy single-cover cleanup (old refs not tied to a listing_id). */
function delete_listing_image(?string $imageVal): void {
    if ($imageVal && preg_match('/^image\.php\?id=(\d+)$/', $imageVal, $m)) {
        db()->prepare("DELETE FROM images WHERE id = ?")->execute([(int) $m[1]]);
    }
}

/** Site settings stored in the database (admin-configurable). */
function setting_get(string $name, string $default = ''): string {
    $st = db()->prepare("SELECT value FROM settings WHERE name = ?");
    $st->execute([$name]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string) $v;
}
function setting_set(string $name, string $value): void {
    if (DB_DRIVER === 'mysql') {
        db()->prepare("INSERT INTO settings (name, value) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([$name, $value]);
    } else {
        db()->prepare("INSERT INTO settings (name, value) VALUES (?,?)
                       ON CONFLICT(name) DO UPDATE SET value = excluded.value")->execute([$name, $value]);
    }
}
/** Distinct reports needed before a listing auto-suspends. Admin-configurable. */
function report_threshold(): int { return max(1, (int) setting_get('report_threshold', '3')); }

/** Canonical amenity options shown as checkboxes on the listing form. */
function amenity_options(): array {
    return ['Wi-Fi', 'Air-conditioning', 'Washing machine', 'Private bathroom', 'Kitchen access',
            'Fridge', 'Water heater', 'Cooking allowed', 'Parking', 'Pool', 'Gym', 'Security / guarded',
            'Near MRT/LRT', 'Near bus stop', 'Near mall', 'Near university', 'Pets allowed', 'Garden'];
}

/** Move-in cost breakdown for a rental listing. */
function movein_costs(array $l): array {
    $rent = (int) $l['price'];
    $dep  = (float) ($l['dep_months'] ?? 0);
    $util = (float) ($l['util_months'] ?? 0);
    $fee  = (int) ($l['fee_rm'] ?? 0);
    return [
        'rent'     => $rent,
        'deposit'  => (int) round($rent * $dep),
        'dep_m'    => $dep,
        'utility'  => (int) round($rent * $util),
        'util_m'   => $util,
        'fee'      => $fee,
        'total'    => $rent + (int) round($rent * $dep) + (int) round($rent * $util) + $fee,
    ];
}

/** "RM 650<small>/mo</small>" for rentals, "RM 450,000" for sales. */
function price_label(array $l, bool $small = true): string {
    $p = 'RM ' . number_format((int) $l['price']);
    if (($l['listing_type'] ?? 'rent') === 'sale') return $p;
    return $p . ($small ? '<small>/mo</small>' : '/mo');
}

/* ------------------------------------------------------------------- SEO */

function slugify(string $t): string {
    $t = strtolower(trim($t));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim(substr($t, 0, 60), '-') ?: 'unit';
}

/** Pretty per-listing URL (rewritten by .htaccess); falls back transparently. */
function listing_url(array $l): string {
    return 'room/' . (int) $l['id'] . '-' . slugify($l['title'] ?? 'unit');
}

/** Absolute URL for canonical/OG/sitemap use. */
function abs_url(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return $scheme . '://' . $host . $dir . '/' . ltrim($path, '/');
}

function listing_image(array $l): string {
    $img = $l['image'] ?: 'assets/img/seed1.svg';
    return e($img);
}
