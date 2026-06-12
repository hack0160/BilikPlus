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

$q = trim($_GET['q'] ?? '');
$f = $_GET['f'] ?? 'all';
$sql = "SELECT l.*,
        (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes
        FROM listings l WHERE l.owner_id = ?";
$args = [$u['id']];
if ($q !== '') { $sql .= " AND (l.title LIKE ? OR l.area LIKE ?)"; $args[] = "%$q%"; $args[] = "%$q%"; }
if (in_array($f, ['active','hidden','suspended'], true)) { $sql .= " AND l.status = ?"; $args[] = $f; }
$st = db()->prepare($sql . " ORDER BY l.created_at DESC");
$st->execute($args);
$rows = $st->fetchAll();

$cn = db()->prepare("SELECT status, COUNT(*) c FROM listings WHERE owner_id = ? GROUP BY status");
$cn->execute([$u['id']]);
$counts = ['active'=>0,'hidden'=>0,'suspended'=>0];
foreach ($cn->fetchAll() as $r) $counts[$r['status']] = (int)$r['c'];
$total = array_sum($counts);

page_top('My listings', $u);
?>
<section class="page-head split">
  <div>
    <h1>My listings</h1>
    <p class="muted">Everything you've uploaded. Edit, hide or delete anytime.</p>
  </div>
  <form method="get" class="search-form">
    <input type="search" name="q" placeholder="Search title or area…" value="<?= e($q) ?>">
    <?php if ($f !== 'all'): ?><input type="hidden" name="f" value="<?= e($f) ?>"><?php endif; ?>
    <button class="btn btn-outline btn-sm">Search</button>
  </form>
</section>

<div class="chip-row">
  <?php $base = $q !== '' ? '&q=' . urlencode($q) : '';
        foreach (['all' => "All <span class=\"chip-n\">$total</span>",
                  'active' => "Live <span class=\"chip-n\">{$counts['active']}</span>",
                  'hidden' => "Hidden <span class=\"chip-n\">{$counts['hidden']}</span>",
                  'suspended' => "Suspended <span class=\"chip-n\">{$counts['suspended']}</span>"] as $k => $label): ?>
    <a class="chip <?= $f === $k ? 'on' : '' ?>" href="?f=<?= $k . $base ?>"><?= $label ?></a>
  <?php endforeach; ?>
  <a class="btn btn-primary btn-sm" href="owner_edit.php" style="margin-left:auto">+ New listing</a>
</div>

<?php if (!$rows): ?>
  <div class="card empty-state">
    <?php if ($q !== '' || $f !== 'all'): ?>
      <h2>No matches 🔍</h2>
      <p class="muted">Nothing fits that search or filter. Try clearing it.</p>
      <a class="btn btn-ghost" href="owner_listings.php">Show all listings</a>
    <?php else: ?>
      <h2>No listings yet</h2>
      <p class="muted">Post your first room — it takes about two minutes.</p>
      <a class="btn btn-primary" href="owner_edit.php">Post a room</a>
    <?php endif; ?>
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
            <form method="post" data-confirm="Delete this listing permanently? Tenant shortlists will lose it too.">
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
