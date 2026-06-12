<?php
require __DIR__ . '/config.php';
$u = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['listing_id'] ?? 0);
    // Owners may only act on their own listings
    $st = db()->prepare("SELECT * FROM listings WHERE id = ? AND owner_id = ?");
    $st->execute([$id, $u['id']]);
    $l = $st->fetch();
    if (!$l) { flash('Listing not found.', 'warn'); redirect('owner_listings.php'); }

    switch ($_POST['action'] ?? '') {
        case 'hide':
            if ($l['status'] === 'suspended') { flash('This listing was suspended by an admin and cannot be changed.', 'warn'); break; }
            db()->prepare("UPDATE listings SET status = 'hidden' WHERE id = ?")->execute([$id]);
            flash('Listing hidden — tenants can no longer see it.');
            break;
        case 'show':
            if ($l['status'] === 'suspended') { flash('This listing was suspended by an admin and cannot be changed.', 'warn'); break; }
            db()->prepare("UPDATE listings SET status = 'active' WHERE id = ?")->execute([$id]);
            flash('Listing is live again.');
            break;
        case 'delete':
            delete_listing_image($l['image']);
            db()->prepare("DELETE FROM listings WHERE id = ?")->execute([$id]);
            flash('Listing deleted.');
            break;
    }
    redirect('owner_listings.php');
}

$st = db()->prepare("SELECT l.*,
        (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes
        FROM listings l WHERE l.owner_id = ? ORDER BY l.created_at DESC");
$st->execute([$u['id']]);
$rows = $st->fetchAll();

page_top('My listings', $u);
?>
<section class="page-head split">
  <div>
    <h1>My listings</h1>
    <p class="muted">Everything you've uploaded. Edit, hide or delete anytime.</p>
  </div>
  <a class="btn btn-primary" href="owner_edit.php">+ New listing</a>
</section>

<?php if (!$rows): ?>
  <div class="card empty-state">
    <h2>No listings yet</h2>
    <p class="muted">Post your first room — it takes about two minutes.</p>
    <a class="btn btn-primary" href="owner_edit.php">Post a room</a>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($rows as $l): ?>
      <article class="card listing-card <?= $l['status'] !== 'active' ? 'is-dimmed' : '' ?>">
        <a class="listing-photo" href="listing.php?id=<?= (int)$l['id'] ?>" style="background-image:url('<?= listing_image($l) ?>')">
          <span class="price-chip">RM <?= number_format((int)$l['price']) ?><small>/mo</small></span>
          <span class="pill pill-<?= e($l['status']) ?> pill-on-photo"><?= e($l['status']) ?></span>
        </a>
        <div class="listing-body">
          <h3><a href="listing.php?id=<?= (int)$l['id'] ?>"><?= e($l['title']) ?></a></h3>
          <p class="muted">📍 <?= e($l['area']) ?> · <?= e($l['room_type']) ?> · ♥ <?= (int)$l['likes'] ?> like<?= (int)$l['likes'] === 1 ? '' : 's' ?></p>
          <div class="inline-form">
            <a class="btn btn-outline btn-sm" href="owner_edit.php?id=<?= (int)$l['id'] ?>">Edit</a>
            <?php if ($l['status'] === 'active'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                <button name="action" value="hide" class="btn btn-ghost btn-sm">Hide</button></form>
            <?php elseif ($l['status'] === 'hidden'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                <button name="action" value="show" class="btn btn-ghost btn-sm">Make live</button></form>
            <?php else: ?>
              <span class="muted small">Suspended by admin</span>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this listing permanently? Tenant shortlists will lose it too.')">
              <?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
              <button name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif;
page_bottom();
