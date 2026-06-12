<?php
/** Serves a listing photo stored in the database. Public (photos are shown
 *  on listing cards anyway); sends long-lived cache headers since images
 *  are immutable — a replaced photo gets a new id. */
require __DIR__ . '/config.php';

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare("SELECT mime, data FROM images WHERE id = ?");
$st->execute([$id]);
$img = $st->fetch();

if (!$img) {
    http_response_code(404);
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600"><rect width="800" height="600" fill="#1a2238"/><text x="400" y="310" fill="#fff" font-family="Arial" font-size="28" text-anchor="middle">No photo</text></svg>';
    exit;
}

$data = is_resource($img['data']) ? stream_get_contents($img['data']) : $img['data'];
header('Content-Type: ' . $img['mime']);
header('Content-Length: ' . strlen($data));
header('Cache-Control: public, max-age=31536000, immutable');
echo $data;
