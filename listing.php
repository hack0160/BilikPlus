<?php
require __DIR__ . '/config.php';
$u = require_login();

$st = db()->prepare("SELECT l.*, o.name AS owner_name, o.phone AS owner_phone, o.email AS owner_email
                     FROM listings l JOIN users o ON o.id = l.owner_id WHERE l.id = ?");
$st->execute([(int) ($_GET['id'] ?? 0)]);
$l = $st->fetch();

$canSee = $l && (
    $u['role'] === 'admin'
    || ($u['role'] === 'owner' && (int)$l['owner_id'] === (int)$u['id'])
    || ($u['role'] === 'tenant' && $l['status'] === 'active')
);
if (!$canSee) { flash('Listing not found or unavailable.', 'warn'); redirect(role_home($u['role'])); }

$myLike = null;
if ($u['role'] === 'tenant') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $dir = ($_POST['direction'] ?? '') === 'like' ? 'like' : 'pass';
        swipe_upsert((int)$u['id'], (int)$l['id'], $dir);
        flash($dir === 'like' ? 'Added to your shortlist.' : 'Skipped.');
        redirect('listing.php?id=' . (int)$l['id']);
    }
    $st = db()->prepare("SELECT direction FROM swipes WHERE tenant_id = ? AND listing_id = ?");
    $st->execute([$u['id'], $l['id']]);
    $myLike = $st->fetch()['direction'] ?? null;
}

$likes = db()->prepare("SELECT COUNT(*) c FROM swipes WHERE listing_id = ? AND direction = 'like'");
$likes->execute([$l['id']]);
$likeCount = (int) $likes->fetch()['c'];

page_top($l['title'], $u);
?>
<article class="detail">
  <div class="detail-photo" style="background-image:url('<?= listing_image($l) ?>')">
    <span class="price-chip price-chip-lg">RM <?= number_format((int)$l['price']) ?><small>/month</small></span>
    <?php if ($l['status'] !== 'active'): ?><span class="status-chip">Status: <?= e($l['status']) ?></span><?php endif; ?>
  </div>
  <div class="detail-main">
    <h1><?= e($l['title']) ?></h1>
    <p class="muted">📍 <?= e($l['area']) ?>, <?= e($l['city']) ?><?= $l['address'] ? ' · ' . e($l['address']) : '' ?></p>

    <p class="tag-row">
      <span class="tag"><?= e($l['room_type']) ?></span>
      <span class="tag"><?= e($l['property_type']) ?></span>
      <span class="tag"><?= e($l['furnishing']) ?></span>
      <span class="tag tag-alt"><?= $l['gender_pref'] === 'Any' ? 'Any gender' : e($l['gender_pref']) . ' only' ?></span>
    </p>

    <?php $am = array_filter(array_map('trim', explode(',', $l['amenities']))); if ($am): ?>
      <h3>Amenities</h3>
      <p class="tag-row"><?php foreach ($am as $a): ?><span class="tag tag-soft"><?= e($a) ?></span><?php endforeach; ?></p>
    <?php endif; ?>

    <?php if (trim($l['description'])): ?>
      <h3>About this room</h3>
      <p class="desc"><?= nl2br(e($l['description'])) ?></p>
    <?php endif; ?>

    <div class="card contact-card">
      <h3>Owner</h3>
      <p><strong><?= e($l['owner_name']) ?></strong></p>
      <?php if ($l['owner_phone']): ?><p>📞 <a href="tel:<?= e($l['owner_phone']) ?>"><?= e($l['owner_phone']) ?></a></p><?php endif; ?>
      <p>✉️ <a href="mailto:<?= e($l['owner_email']) ?>"><?= e($l['owner_email']) ?></a></p>
      <p class="muted">♥ <?= $likeCount ?> tenant<?= $likeCount === 1 ? '' : 's' ?> shortlisted this room</p>
    </div>

    <?php if ($u['role'] === 'tenant'): ?>
      <form method="post" class="detail-actions">
        <?= csrf_field() ?>
        <?php if ($myLike === 'like'): ?>
          <span class="liked-pill">♥ In your shortlist</span>
          <button name="direction" value="pass" class="btn btn-ghost">Remove &amp; skip</button>
        <?php else: ?>
          <button name="direction" value="like" class="btn btn-primary">✓ Add to shortlist</button>
          <button name="direction" value="pass" class="btn btn-ghost">✕ Skip</button>
        <?php endif; ?>
        <a class="btn btn-outline" href="swipe.php">Back to deck</a>
      </form>
    <?php elseif ($u['role'] === 'owner' && (int)$l['owner_id'] === (int)$u['id']): ?>
      <div class="detail-actions">
        <a class="btn btn-primary" href="owner_edit.php?id=<?= (int)$l['id'] ?>">Edit listing</a>
        <a class="btn btn-outline" href="owner_listings.php">Back to my listings</a>
      </div>
    <?php elseif ($u['role'] === 'admin'): ?>
      <div class="detail-actions">
        <a class="btn btn-outline" href="admin_listings.php">Back to all listings</a>
      </div>
    <?php endif; ?>
  </div>
</article>
<?php page_bottom();
