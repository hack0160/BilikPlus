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
            db()->prepare("DELETE FROM reports WHERE listing_id = ?")->execute([$id]);
            flash('Listing restored and live. Its reports were cleared.');
            break;
        case 'clear_reports':
            db()->prepare("DELETE FROM reports WHERE listing_id = ?")->execute([$id]);
            flash('Reports cleared for this listing.');
            break;
        case 'delete':
            delete_listing_images($id);
            delete_listing_image($l['image']);
            db()->prepare("DELETE FROM listings WHERE id = ?")->execute([$id]);
            flash('Listing deleted permanently.');
            break;
    }
    redirect('admin_listings.php');
}

$q = trim($_GET['q'] ?? '');
$f = $_GET['f'] ?? 'all';
$where = in_array($f, ['active','hidden','suspended'], true) ? " AND l.status = '$f'" : '';
if ($q !== '') {
    $st = db()->prepare("SELECT l.*, o.name AS owner_name,
            (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes,
            (SELECT COUNT(*) FROM reports r WHERE r.listing_id = l.id) AS reports
            FROM listings l JOIN users o ON o.id = l.owner_id
            WHERE (l.title LIKE ? OR l.area LIKE ? OR o.name LIKE ?)$where
            ORDER BY reports DESC, l.created_at DESC");
    $like = "%$q%";
    $st->execute([$like, $like, $like]);
} else {
    $st = db()->query("SELECT l.*, o.name AS owner_name,
            (SELECT COUNT(*) FROM swipes s WHERE s.listing_id = l.id AND s.direction = 'like') AS likes,
            (SELECT COUNT(*) FROM reports r WHERE r.listing_id = l.id) AS reports
            FROM listings l JOIN users o ON o.id = l.owner_id
            WHERE 1=1$where
            ORDER BY reports DESC, l.created_at DESC");
}
$rows = $st->fetchAll();

/* expanded report details for one listing */
$openReports = (int) ($_GET['reports'] ?? 0);
$reportRows = [];
if ($openReports) {
    $rs = db()->prepare("SELECT r.*, ru.name AS reporter_name, ru.role AS reporter_role
                         FROM reports r JOIN users ru ON ru.id = r.reporter_id
                         WHERE r.listing_id = ? ORDER BY r.created_at DESC");
    $rs->execute([$openReports]);
    $reportRows = $rs->fetchAll();
}

page_top('Moderate listings', $u, ['noindex' => true]);
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

<div class="chip-row">
  <?php $counts = ['active'=>0,'hidden'=>0,'suspended'=>0];
        foreach (db()->query("SELECT status, COUNT(*) c FROM listings GROUP BY status") as $r) $counts[$r['status']] = (int)$r['c'];
        $total = array_sum($counts);
        $base = $q !== '' ? '&q=' . urlencode($q) : '';
        foreach (['all' => "All <span class=\"chip-n\">$total</span>",
                  'active' => "Live <span class=\"chip-n\">{$counts['active']}</span>",
                  'hidden' => "Hidden <span class=\"chip-n\">{$counts['hidden']}</span>",
                  'suspended' => "Suspended <span class=\"chip-n\">{$counts['suspended']}</span>"] as $k => $label): ?>
    <a class="chip <?= $f === $k ? 'on' : '' ?>" href="?f=<?= $k . $base ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php if ($openReports && $reportRows): ?>
<section class="card table-card report-panel">
  <div class="table-head">
    <h2>⚑ Reports for listing #<?= $openReports ?></h2>
    <div class="inline-form">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= $openReports ?>">
        <button name="action" value="clear_reports" class="btn btn-ghost btn-sm">Clear reports</button></form>
      <a class="btn btn-outline btn-sm" href="listing.php?id=<?= $openReports ?>">View listing</a>
    </div>
  </div>
  <div class="table-scroll"><table>
    <thead><tr><th>When</th><th>Reporter</th><th>Reason</th><th>Details</th></tr></thead>
    <tbody>
      <?php foreach ($reportRows as $r): ?>
      <tr>
        <td data-th="When"><?= e(substr($r['created_at'], 0, 16)) ?></td>
        <td data-th="Reporter"><?= e($r['reporter_name']) ?> <span class="muted">(<?= e($r['reporter_role']) ?>)</span></td>
        <td data-th="Reason"><strong><?= e($r['reason']) ?></strong></td>
        <td data-th="Details"><?= $r['details'] !== '' ? e($r['details']) : '<span class="muted">—</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<section class="card table-card">
  <div class="table-scroll"><table>
    <thead><tr><th>Listing</th><th>Owner</th><th>Area</th><th>Rent</th><th>♥</th><th>⚑</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="muted">No listings match<?= $q ? ' "' . e($q) . '"' : '' ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $l): ?>
      <tr class="<?= $l['status'] !== 'active' ? 'row-dim' : '' ?>">
        <td data-th="Listing"><a href="listing.php?id=<?= (int)$l['id'] ?>"><?= e($l['title']) ?></a></td>
        <td data-th="Owner"><?= e($l['owner_name']) ?></td>
        <td data-th="Area"><?= e($l['area']) ?></td>
        <td data-th="Rent">RM <?= number_format((int)$l['price']) ?></td>
        <td data-th="Likes"><?= (int)$l['likes'] ?></td>
        <td data-th="Reports">
          <?php if ((int)$l['reports'] > 0): ?>
            <a class="report-count <?= (int)$l['reports'] >= report_threshold() ? 'hot' : '' ?>"
               href="?reports=<?= (int)$l['id'] ?><?= $f !== 'all' ? '&f=' . e($f) : '' ?><?= $base ?>">⚑ <?= (int)$l['reports'] ?></a>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
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
            <form method="post" data-confirm="Delete this listing permanently?">
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
