<?php
require __DIR__ . '/config.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'moderation') {
    csrf_check();
    $t = max(1, min(50, (int) ($_POST['report_threshold'] ?? 3)));
    setting_set('report_threshold', (string) $t);
    flash("Saved — listings now auto-suspend at $t report" . ($t > 1 ? 's' : '') . '.');
    redirect('admin_dashboard.php');
}
$openReports = (int) db()->query("SELECT COUNT(DISTINCT listing_id) FROM reports")->fetchColumn();

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

page_top('Admin dashboard', $u, ['noindex' => true]);
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

<section class="card table-card mod-settings">
  <div class="table-head">
    <h2>Moderation</h2>
    <a class="btn btn-outline btn-sm" href="admin_listings.php">Review reported listings<?= $openReports ? " ($openReports)" : '' ?></a>
  </div>
  <form method="post" class="mod-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="moderation">
    <label for="thresh">Auto-suspend a listing after</label>
    <input id="thresh" type="number" name="report_threshold" min="1" max="50" value="<?= report_threshold() ?>">
    <span>report<?= report_threshold() > 1 ? 's' : '' ?> from different users</span>
    <button class="btn btn-primary btn-sm">Save</button>
  </form>
  <p class="muted small">Suspended listings disappear from search and the swipe deck until you restore them. Restoring a listing clears its reports.</p>
</section>

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
