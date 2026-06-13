<?php
require __DIR__ . '/config.php';
$u = require_role('owner');

$st = db()->prepare("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
    SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) AS hidden,
    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended
    FROM listings WHERE owner_id = ?");
$st->execute([$u['id']]);
$stats = $st->fetch();

$lk = db()->prepare("SELECT COUNT(*) c FROM swipes s JOIN listings l ON l.id = s.listing_id
                     WHERE l.owner_id = ? AND s.direction = 'like'");
$lk->execute([$u['id']]);
$totalLikes = (int) $lk->fetch()['c'];

$top = db()->prepare("SELECT l.id, l.title, l.area, l.price, l.status,
        (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes
        FROM listings l WHERE l.owner_id = ? ORDER BY likes DESC, l.created_at DESC LIMIT 5");
$top->execute([$u['id']]);
$topRows = $top->fetchAll();

page_top('Owner dashboard', $u, ['noindex' => true]);
?>
<section class="page-head">
  <h1>Hello, <?= e($u['name']) ?> 👋</h1>
  <p class="muted">Here's how your rooms are doing.</p>
</section>

<div class="stat-row">
  <div class="stat"><strong><?= (int)$stats['total'] ?></strong><span>Total listings</span></div>
  <div class="stat"><strong><?= (int)$stats['active'] ?></strong><span>Live now</span></div>
  <div class="stat"><strong><?= $totalLikes ?></strong><span>Tenant likes ♥</span></div>
  <div class="stat"><strong><?= (int)$stats['hidden'] + (int)$stats['suspended'] ?></strong><span>Hidden / suspended</span></div>
</div>

<section class="card table-card">
  <div class="table-head">
    <h2>Most-liked listings</h2>
    <a class="btn btn-primary btn-sm" href="owner_edit.php">+ New listing</a>
  </div>
  <?php if (!$topRows): ?>
    <div class="empty-state">
      <p class="muted">You haven't listed any rooms yet.</p>
      <a class="btn btn-primary" href="owner_edit.php">Post your first room</a>
    </div>
  <?php else: ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Listing</th><th>Area</th><th>Rent</th><th>Status</th><th>♥ Likes</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($topRows as $r): ?>
        <tr>
          <td data-th="Listing"><a href="listing.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></td>
          <td data-th="Area"><?= e($r['area']) ?></td>
          <td data-th="Rent">RM <?= number_format((int)$r['price']) ?></td>
          <td data-th="Status"><span class="pill pill-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td data-th="Likes"><?= (int)$r['likes'] ?></td>
          <td data-th=""><a class="btn btn-outline btn-sm" href="owner_edit.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="table-foot"><a href="owner_listings.php">Manage all listings →</a></p>
  <?php endif; ?>
</section>
<?php page_bottom();
