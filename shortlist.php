<?php
require __DIR__ . '/config.php';
$u = require_role('tenant');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    csrf_check();
    db()->prepare("DELETE FROM swipes WHERE tenant_id = ? AND listing_id = ? AND direction = 'like'")
        ->execute([$u['id'], (int) $_POST['listing_id']]);
    flash('Removed from your shortlist — it may show up again in your deck.');
    redirect('shortlist.php');
}

$sort = $_GET['sort'] ?? 'newest';
$order = match ($sort) {
    'cheap' => 'l.price ASC',
    'pricey' => 'l.price DESC',
    default => 's.created_at DESC',
};
$st = db()->prepare("SELECT l.*, o.name AS owner_name, o.phone AS owner_phone, o.email AS owner_email, s.created_at AS liked_at
                     FROM swipes s
                     JOIN listings l ON l.id = s.listing_id
                     JOIN users o ON o.id = l.owner_id
                     WHERE s.tenant_id = ? AND s.direction = 'like'
                     ORDER BY $order");
$st->execute([$u['id']]);
$rows = $st->fetchAll();

page_top('My shortlist', $u);
?>
<section class="page-head">
  <h1>My shortlist</h1>
  <p class="muted"><?= count($rows) ?> room<?= count($rows) === 1 ? '' : 's' ?> you've liked. Owner contacts are unlocked here.</p>
</section>

<?php if ($rows): ?>
<div class="chip-row">
  <?php foreach (['newest' => 'Newest liked', 'cheap' => 'Price: low → high', 'pricey' => 'Price: high → low'] as $k => $label): ?>
    <a class="chip <?= $sort === $k ? 'on' : '' ?>" href="?sort=<?= $k ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="card empty-state">
    <h2>Nothing here yet</h2>
    <p class="muted">Swipe right on rooms you like and they'll land here.</p>
    <a class="btn btn-primary" href="swipe.php">Start swiping</a>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($rows as $l): ?>
      <article class="card listing-card <?= $l['status'] !== 'active' ? 'is-dimmed' : '' ?>">
        <a class="listing-photo" href="listing.php?id=<?= (int)$l['id'] ?>" style="background-image:url('<?= listing_image($l) ?>')">
          <span class="price-chip">RM <?= number_format((int)$l['price']) ?><small>/mo</small></span>
          <?php if ($l['status'] !== 'active'): ?><span class="status-chip">No longer available</span><?php endif; ?>
        </a>
        <div class="listing-body">
          <h3><a href="listing.php?id=<?= (int)$l['id'] ?>"><?= e($l['title']) ?></a></h3>
          <p class="muted">📍 <?= e($l['area']) ?>, <?= e($l['city']) ?> · <?= e($l['room_type']) ?></p>
          <p class="contact-line">👤 <?= e($l['owner_name']) ?>
            <?php if ($l['owner_phone']): ?> · <a href="tel:<?= e($l['owner_phone']) ?>"><?= e($l['owner_phone']) ?></a><?php endif; ?>
            · <a href="mailto:<?= e($l['owner_email']) ?>">email</a>
          </p>
          <form method="post" class="inline-form" data-confirm="Remove this room from your shortlist?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
            <button class="btn btn-ghost btn-sm">Remove</button>
            <a class="btn btn-outline btn-sm" href="listing.php?id=<?= (int)$l['id'] ?>">Details</a>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif;
page_bottom();
