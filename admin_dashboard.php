<?php
require __DIR__ . '/config.php';
$u = require_role('admin');

$s = db()->query("SELECT
    (SELECT COUNT(*) FROM users) AS users,
    (SELECT COUNT(*) FROM users WHERE role = 'owner') AS owners,
    (SELECT COUNT(*) FROM users WHERE role = 'tenant') AS tenants,
    (SELECT COUNT(*) FROM users WHERE status = 'suspended') AS suspended_users,
    (SELECT COUNT(*) FROM listings) AS listings,
    (SELECT COUNT(*) FROM listings WHERE status = 'active') AS live,
    (SELECT COUNT(*) FROM listings WHERE status = 'suspended') AS suspended_listings,
    (SELECT COUNT(*) FROM swipes WHERE direction = 'like') AS likes,
    (SELECT COUNT(*) FROM swipes) AS swipes")->fetch();

$recent = db()->query("SELECT l.id, l.title, l.area, l.price, l.status, l.created_at, o.name AS owner_name
                       FROM listings l JOIN users o ON o.id = l.owner_id
                       ORDER BY l.created_at DESC LIMIT 6")->fetchAll();

page_top('Admin dashboard', $u);
?>
<section class="page-head">
  <h1>Site overview</h1>
  <p class="muted">Everything happening on <?= APP_NAME ?> right now.</p>
</section>

<div class="stat-row">
  <div class="stat"><strong><?= (int)$s['users'] ?></strong><span><?= (int)$s['owners'] ?> owners · <?= (int)$s['tenants'] ?> tenants</span></div>
  <div class="stat"><strong><?= (int)$s['live'] ?>/<?= (int)$s['listings'] ?></strong><span>Listings live</span></div>
  <div class="stat"><strong><?= (int)$s['swipes'] ?></strong><span>Swipes · <?= (int)$s['likes'] ?> likes ♥</span></div>
  <div class="stat"><strong><?= (int)$s['suspended_users'] + (int)$s['suspended_listings'] ?></strong><span>Suspensions in force</span></div>
</div>

<section class="card table-card">
  <div class="table-head">
    <h2>Newest listings</h2>
    <a class="btn btn-outline btn-sm" href="admin_listings.php">Moderate all</a>
  </div>
  <div class="table-scroll"><table>
    <thead><tr><th>Listing</th><th>Owner</th><th>Area</th><th>Rent</th><th>Status</th><th>Posted</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td data-th="Listing"><a href="listing.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></td>
        <td data-th="Owner"><?= e($r['owner_name']) ?></td>
        <td data-th="Area"><?= e($r['area']) ?></td>
        <td data-th="Rent">RM <?= number_format((int)$r['price']) ?></td>
        <td data-th="Status"><span class="pill pill-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td data-th="Posted"><?= e(substr($r['created_at'], 0, 10)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php page_bottom();
