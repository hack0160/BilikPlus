<?php
require __DIR__ . '/config.php';
$u = require_role('tenant');

// Listings not yet swiped by this tenant
$st = db()->prepare("SELECT l.*, o.name AS owner_name FROM listings l
                     JOIN users o ON o.id = l.owner_id
                     WHERE l.status = 'active' AND o.status = 'active'
                       AND l.id NOT IN (SELECT listing_id FROM swipes WHERE tenant_id = ?)
                     ORDER BY l.created_at DESC LIMIT 30");
$st->execute([$u['id']]);
$cards = $st->fetchAll();

/* all photos for the deck in one query */
$photoMap = [];
if ($cards) {
    $ids = implode(',', array_map(fn($c) => (int) $c['id'], $cards));
    foreach (db()->query("SELECT id, listing_id FROM images WHERE listing_id IN ($ids) ORDER BY sort, id") as $r) {
        $photoMap[(int) $r['listing_id']][] = 'image.php?id=' . (int) $r['id'];
    }
}

$likes = db()->prepare("SELECT COUNT(*) c FROM swipes WHERE tenant_id = ? AND direction = 'like'");
$likes->execute([$u['id']]);
$likeCount = (int) $likes->fetch()['c'];

page_top('Swipe rooms', $u);
?>
<section class="swipe-page" data-csrf="<?= e(csrf_token()) ?>">
  <div class="swipe-head">
    <h1>Find your bilik</h1>
    <p class="muted">Swipe right kalau suka 💚 left kalau tak nak · buttons and arrow keys work too · U = undo.</p>
  </div>

  <div class="deck" id="deck">
    <?php foreach (array_reverse($cards) as $l): ?>
      <?php $photos = $photoMap[(int)$l['id']] ?? [html_entity_decode(listing_image($l))]; ?>
      <article class="swipe-card" data-id="<?= (int)$l['id'] ?>">
        <div class="swipe-photo gallery" data-photos='<?= e(json_encode($photos)) ?>'
             style="background-image:url('<?= e($photos[0]) ?>')">
          <?php if (count($photos) > 1): ?>
          <div class="seg-row">
            <?php foreach ($photos as $i => $unused): ?><span class="seg <?= $i === 0 ? 'on' : '' ?>"></span><?php endforeach; ?>
          </div>
          <span class="g-zone g-prev" aria-hidden="true"></span>
          <span class="g-zone g-next" aria-hidden="true"></span>
          <?php endif; ?>
          <span class="price-chip"><?= price_label($l) ?></span>
          <span class="stamp stamp-like">SUKA ✓</span>
          <span class="stamp stamp-nope">TAK NAK ✕</span>
        </div>
        <div class="swipe-body">
          <h2><?= e($l['title']) ?></h2>
          <p class="swipe-loc">📍 <?= e($l['area']) ?>, <?= e($l['city']) ?></p>
          <p class="tag-row">
            <span class="tag"><?= e($l['room_type']) ?></span>
            <span class="tag"><?= e($l['property_type']) ?></span>
            <span class="tag"><?= e($l['furnishing']) ?></span>
            <?php if ($l['gender_pref'] !== 'Any'): ?><span class="tag tag-alt"><?= e($l['gender_pref']) ?> only</span><?php endif; ?>
          </p>
          <p class="amen">
            <?php foreach (array_slice(array_filter(array_map('trim', explode(',', $l['amenities']))), 0, 4) as $a): ?>
              <span>· <?= e($a) ?></span>
            <?php endforeach; ?>
          </p>
          <a class="detail-link" href="listing.php?id=<?= (int)$l['id'] ?>">View full details →</a>
        </div>
      </article>
    <?php endforeach; ?>
    <div class="deck-empty" id="deckEmpty" <?= $cards ? 'hidden' : '' ?>>
      <h2>That's everything for now 🎉</h2>
      <p class="muted">You've seen every available room. Check your shortlist, or come back later for new listings.</p>
      <a class="btn btn-primary" href="shortlist.php">Open my shortlist</a>
    </div>
  </div>

  <div class="swipe-actions" <?= $cards ? '' : 'hidden' ?> id="swipeActions">
    <button class="fab fab-undo" id="btnUndo" aria-label="Undo last swipe" title="Undo (U)">↺</button>
    <button class="fab fab-nope" id="btnNope" aria-label="Skip this room">✕</button>
    <a class="fab fab-mid" href="shortlist.php" aria-label="Open shortlist">♥<span class="fab-count" id="likeCount"><?= $likeCount ?></span></a>
    <button class="fab fab-like" id="btnLike" aria-label="Shortlist this room">✓</button>
  </div>

  <div class="match-pop" id="matchPop" hidden>
    <div class="match-card">
      <span class="match-emoji">🎉</span>
      <h2><span>Shortlisted!</span></h2>
      <p id="matchTitle"></p>
      <div class="match-actions">
        <button class="btn btn-ghost btn-sm" id="matchKeep">Keep swiping</button>
        <a class="btn btn-primary btn-sm" href="shortlist.php">See shortlist</a>
      </div>
    </div>
  </div>
</section>
<?php page_bottom();
