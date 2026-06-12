<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

$u = current_user();
if (!$u || $u['role'] !== 'tenant') { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals(csrf_token(), $body['csrf'] ?? '')) { http_response_code(419); echo json_encode(['ok' => false, 'error' => 'csrf']); exit; }

$listingId = (int) ($body['listing_id'] ?? 0);
$dirRaw    = $body['direction'] ?? '';
$direction = $dirRaw === 'like' ? 'like' : ($dirRaw === 'undo' ? 'undo' : 'pass');

$st = db()->prepare("SELECT id FROM listings WHERE id = ? AND status = 'active'");
$st->execute([$listingId]);
if (!$st->fetch()) { echo json_encode(['ok' => false, 'error' => 'listing not found']); exit; }

if ($direction === 'undo') {
    db()->prepare("DELETE FROM swipes WHERE tenant_id = ? AND listing_id = ?")
        ->execute([$u['id'], $listingId]);
} else {
    swipe_upsert((int)$u['id'], $listingId, $direction);
}

$c = db()->prepare("SELECT COUNT(*) c FROM swipes WHERE tenant_id = ? AND direction = 'like'");
$c->execute([$u['id']]);
echo json_encode(['ok' => true, 'likes' => (int) $c->fetch()['c']]);
