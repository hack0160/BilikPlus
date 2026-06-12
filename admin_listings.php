<?php
require __DIR__ . '/config.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['listing_id'] ?? 0);
    $st = db()->prepare("SELECT * FROM listings WHERE id = ?");
    $st->execute([$id]);
    $l = $st->fetch();
    if (!$l) { flash('Listing not found.', 'warn'); redirect('admin_listings.php'); }

    switch ($_POST['action'] ?? '') {
        case 'suspend':
            db()->prepare("UPDATE listings SET status = 'suspended' WHERE id = ?")->execute([$id]);
            flash('Listing suspended. The owner cannot re-publish it.');
            break;
        case 'restore':
            db()->prepare("UPDATE listings SET status = 'active' WHERE id = ?")->execute([$id]);
            flash('Listing restored and live.');
            break;
        case 'delete':
            delete_listing_image($l['image']);
            db()->prepare("DELETE FROM listings WHERE id = ?")->execute([$id]);
            flash('Listing deleted permanently.');
            break;
    }
    redirect('admin_listings.php');
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $st = db()->prepare("SELECT l.*, o.name AS owner_name,
            (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes
            FROM listings l JOIN users o ON o.id = l.owner_id
            WHERE l.title LIKE ? OR l.area LIKE ? OR o.name LIKE ?
            ORDER BY l.created_at DESC");
    $like = "%$q%";
    $st->execute([$like, $like, $like]);
} else {
    $st = db()->query("SELECT l.*, o.name AS owner_name,
            (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes
            FROM listings l JOIN users o ON o.id = l.owner_id
            ORDER BY l.created_at DESC");
}
$rows = $st->fetchAll();

page_top('Moderate listings', $u);
?>
<section class="page-head split">
  <div>
    <h1>All listings</h1>
    <p class="muted">Suspend anything that breaks the rules. Suspended listings stay hidden until you restore them.</p>
  </div>
  <form method="get" class="search-form">
    <input type="search" name="q" placeholder="Search title, area or owner…" value="<?= e($q) ?>">
    <button class="btn btn-outline btn-sm">Search</button>
  </form>
</section>

<section class="card table-card">
  <div class="table-scroll"><table>
    <thead><tr><th>Listing</th><th>Owner</th><th>Area</th><th>Rent</th><th>♥</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="muted">No listings match<?= $q ? ' "' . e($q) . '"' : '' ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $l): ?>
      <tr class="<?= $l['status'] !== 'active' ? 'row-dim' : '' ?>">
        <td data-th="Listing"><a href="listing.php?id=<?= (int)$l['id'] ?>"><?= e($l['title']) ?></a></td>
        <td data-th="Owner"><?= e($l['owner_name']) ?></td>
        <td data-th="Area"><?= e($l['area']) ?></td>
        <td data-th="Rent">RM <?= number_format((int)$l['price']) ?></td>
        <td data-th="Likes"><?= (int)$l['likes'] ?></td>
        <td data-th="Status"><span class="pill pill-<?= e($l['status']) ?>"><?= e($l['status']) ?></span></td>
        <td data-th="Actions">
          <div class="inline-form">
            <?php if ($l['status'] !== 'suspended'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                <button name="action" value="suspend" class="btn btn-ghost btn-sm">Suspend</button></form>
            <?php else: ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                <button name="action" value="restore" class="btn btn-ghost btn-sm">Restore</button></form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this listing permanently?')">
              <?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
              <button name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php page_bottom();
