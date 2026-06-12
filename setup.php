<?php
/**
 * BilikGo one-time installer / diagnostic.
 * Open this file in your browser AFTER uploading the site:
 *     https://yourdomain.com/setup.php
 * It checks the PHP environment, creates all tables, seeds demo data,
 * and reports exactly what is wrong if something fails.
 * DELETE THIS FILE once the site is running.
 */
require __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>BilikGo setup</title>
<body style='font-family:system-ui;max-width:680px;margin:2rem auto;padding:0 1rem;line-height:1.6'>
<h1>BilikGo setup</h1><ol>";

function row(string $label, bool $ok, string $extra = ''): void {
    echo "<li style='color:" . ($ok ? 'green' : '#c00') . "'>"
       . ($ok ? '✔ ' : '✘ ') . htmlspecialchars($label)
       . ($extra ? " <small style='color:#555'>— " . htmlspecialchars($extra) . "</small>" : '')
       . "</li>";
}

/* 1. environment */
row('PHP version ' . PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>='), 'need 8.0+');
$drv = DB_DRIVER;
row("Configured driver: $drv", in_array($drv, ['sqlite', 'mysql'], true));
if ($drv === 'sqlite') {
    row('pdo_sqlite extension', extension_loaded('pdo_sqlite'));
    $dataDir = dirname(DB_FILE);
    if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);
    row("data/ folder writable", is_writable($dataDir), $dataDir);
} else {
    row('pdo_mysql extension', extension_loaded('pdo_mysql'));
    row('MySQL credentials filled in', !str_contains(MYSQL_HOST, 'XXX') && !str_contains(MYSQL_NAME, 'XXXX'),
        'edit the MYSQL_* constants in config.php with the values from your hosting control panel');
}

/* 2. connect, create tables, seed */
try {
    $pdo = db();   // runs migrate() and seeds automatically if empty
    row('Database connection', true, $drv === 'sqlite' ? DB_FILE : MYSQL_HOST . ' / ' . MYSQL_NAME);
    foreach (['users', 'listings', 'swipes', 'password_resets', 'images'] as $t) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        row("Table `$t`", true, "$n rows");
    }
    $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    row('Admin account exists', $admins > 0, $admins > 0 ? 'admin@bilikgo.test / Demo@123' : 'seed failed?');
    echo "</ol><p><strong style='color:green'>Setup complete.</strong>
          <a href='index.php'>Open the site →</a></p>
          <p style='background:#fff3cd;padding:.7rem;border-radius:8px'>
          ⚠ Now <strong>delete setup.php</strong> from the server, and change the
          demo passwords before sharing the site.</p>";
} catch (Throwable $ex) {
    row('Database connection', false, $ex->getMessage());
    echo "</ol><p style='color:#c00'><strong>Fix the item marked ✘ above, then reload this page.</strong></p>";
    if ($drv === 'mysql') {
        echo "<p>On InfinityFree: control panel → <em>MySQL Databases</em> → create a database,
              then copy the <em>MySQL Host Name</em>, <em>database name</em>, <em>username</em> and your
              vPanel password into the MYSQL_* constants in config.php.</p>";
    } else {
        echo "<p>If SQLite keeps failing on your host, switch to MySQL: create a database in the
              control panel and set DB_DRIVER to 'mysql' in config.php — the tables will be
              created automatically. Or import database/install_mysql.sql via phpMyAdmin.</p>";
    }
}
echo "</body>";
