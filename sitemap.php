<?php
/* BilikGo — dynamic XML sitemap (submit this URL in Google Search Console) */
require __DIR__ . '/config.php';
header('Content-Type: application/xml; charset=utf-8');

$urls = [
    ['loc' => abs_url('index.php'), 'priority' => '1.0', 'changefreq' => 'hourly'],
    ['loc' => abs_url('index.php?type=rent'), 'priority' => '0.9', 'changefreq' => 'hourly'],
    ['loc' => abs_url('index.php?type=sale'), 'priority' => '0.9', 'changefreq' => 'hourly'],
    ['loc' => abs_url('terms.php'), 'priority' => '0.3', 'changefreq' => 'monthly'],
];
$st = db()->query("SELECT l.id, l.title, l.created_at FROM listings l
                   JOIN users o ON o.id = l.owner_id
                   WHERE l.status = 'active' AND o.status = 'active'
                   ORDER BY l.id");
foreach ($st as $l) {
    $urls[] = [
        'loc' => abs_url(listing_url($l)),
        'priority' => '0.8',
        'changefreq' => 'daily',
        'lastmod' => date('Y-m-d', strtotime($l['created_at'] ?: 'now')),
    ];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url><loc>" . e($u['loc']) . "</loc>"
       . (isset($u['lastmod']) ? "<lastmod>{$u['lastmod']}</lastmod>" : '')
       . "<changefreq>{$u['changefreq']}</changefreq><priority>{$u['priority']}</priority></url>\n";
}
echo '</urlset>';
