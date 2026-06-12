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
        "CREATE TABLE IF NOT EXISTS images (
            id $id,
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
        [2,'Cozy medium room near MRT Cheras','Cheras','Kuala Lumpur',650,'Medium Room','Condominium','Fully furnished','Any','Wi-Fi,Aircond,Washing machine,Near MRT,Pool,Gym','5 min walk to MRT Taman Mutiara. Utilities included, friendly housemates, move-in ready.','seed1.svg'],
        [2,'Master room with bathroom, Bangsar South','Bangsar South','Kuala Lumpur',1200,'Master Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Pool,Gym,Parking','Attached bathroom, KL skyline view, walking distance to LRT Kerinchi and offices.','seed2.svg'],
        [2,'Budget single room, SS15 Subang Jaya','SS15, Subang Jaya','Selangor',480,'Single Room','Apartment','Partially furnished','Female','Wi-Fi,Washing machine,Near LRT,Near college','Perfect for students — Taylor\'s and INTI nearby. Quiet female-only unit.','seed3.svg'],
        [2,'Big master room, Mont Kiara expat area','Mont Kiara','Kuala Lumpur',1500,'Master Room','Condominium','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Pool,Gym,Parking,Security','Premium condo with full facilities, balcony, covered parking included.','seed4.svg'],
        [3,'Medium room in landed house, Petaling Jaya','SS2, Petaling Jaya','Selangor',600,'Medium Room','Terrace House','Partially furnished','Male','Wi-Fi,Washing machine,Parking,Near food court','Quiet neighbourhood, famous SS2 food nearby, car porch parking available.','seed5.svg'],
        [3,'Single room walk to Cyberjaya offices','Cyberjaya','Selangor',520,'Single Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Aircond,Pool,Gym,Shuttle','Ideal for tech workers. Shuttle to major offices, utilities capped at RM50.','seed6.svg'],
        [3,'Master room, Setia Alam with parking','Setia Alam','Selangor',850,'Master Room','Double Storey House','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Parking,Garden','Spacious landed home near Setia City Mall. Includes one car park bay.','seed7.svg'],
        [2,'Studio-style room, KLCC fringe','Kampung Baru','Kuala Lumpur',980,'Studio','Apartment','Fully furnished','Any','Wi-Fi,Aircond,Kitchenette,Near LRT','Own kitchenette and entrance. 10 min to KLCC, halal eateries downstairs.','seed8.svg'],
        [3,'Female unit medium room, Wangsa Maju','Wangsa Maju','Kuala Lumpur',580,'Medium Room','Condominium','Fully furnished','Female','Wi-Fi,Aircond,Pool,Near LRT,Security','Female-only unit, 7 min walk to LRT Wangsa Maju, near AEON Big.','seed9.svg'],
        [2,'Low-deposit single room, Kepong','Kepong','Kuala Lumpur',450,'Single Room','Flat','Partially furnished','Male','Wi-Fi,Washing machine,Near market','One month deposit only. Near Kepong market and bus routes.','seed10.svg'],
    ];
    $li = $pdo->prepare("INSERT INTO listings
        (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($rooms as $r) {
        $r[11] = 'assets/img/' . $r[11];
        $li->execute($r);
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
    if (!$u) { flash('Please sign in first.', 'warn'); redirect('login.php'); }
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

function page_top(string $title, ?array $u = null): void {
    $u = $u ?? current_user();
    $flash = take_flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%23D7263D'/><path d='M8 17l8-8 8 8v7H8z' fill='%23F7F6F2'/></svg>">
</head>
<body>
<div id="bg3d" aria-hidden="true"></div>
<header class="topbar">
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
      <a href="login.php">Sign in</a>
      <a href="register.php" class="nav-cta">Create account</a>
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
  <span><?= APP_NAME ?> — find your bilik, swipe by swipe.</span>
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

function handle_image_upload(string $field): ?string {
    if (empty($_FILES[$field]['name'])) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (error ' . $f['error'] . ').');
    if ($f['size'] > 3 * 1024 * 1024) throw new RuntimeException('Image must be 3 MB or smaller.');
    $info = @getimagesize($f['tmp_name']);
    $mime = $info['mime'] ?? '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Only JPG, PNG or WebP images are accepted.');
    }
    $st = db()->prepare("INSERT INTO images (mime, data, created_at) VALUES (?,?,?)");
    $st->bindValue(1, $mime);
    $st->bindValue(2, file_get_contents($f['tmp_name']), PDO::PARAM_LOB);
    $st->bindValue(3, db_now());
    $st->execute();
    return 'image.php?id=' . (int) db()->lastInsertId();
}

/** Delete the stored photo referenced by a listing's image value (if any). */
function delete_listing_image(?string $imageVal): void {
    if ($imageVal && preg_match('/^image\.php\?id=(\d+)$/', $imageVal, $m)) {
        db()->prepare("DELETE FROM images WHERE id = ?")->execute([(int) $m[1]]);
    }
}

function listing_image(array $l): string {
    $img = $l['image'] ?: 'assets/img/seed1.svg';
    return e($img);
}
