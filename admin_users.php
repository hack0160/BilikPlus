<?php
require __DIR__ . '/config.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['user_id'] ?? 0);
    if ($id === (int)$u['id']) {
        flash('You cannot modify your own account here.', 'warn');
        redirect('admin_users.php');
    }
    $st = db()->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$id]);
    $target = $st->fetch();
    if (!$target) { flash('User not found.', 'warn'); redirect('admin_users.php'); }

    switch ($_POST['action'] ?? '') {
        case 'role':
            $role = $_POST['role'] ?? '';
            if (in_array($role, ['admin', 'owner', 'tenant'], true)) {
                db()->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);
                flash("{$target['name']} is now a {$role}.");
            }
            break;
        case 'suspend':
            db()->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$id]);
            flash("{$target['name']} suspended. Their listings are hidden from tenants.");
            break;
        case 'activate':
            db()->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
            flash("{$target['name']} reactivated.");
            break;
        case 'delete':
            $imgs = db()->prepare("SELECT id, image FROM listings WHERE owner_id = ?");
            $imgs->execute([$id]);
            foreach ($imgs->fetchAll() as $row) {
                delete_listing_images((int) $row['id']);
                delete_listing_image($row['image']);
            }
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]); // cascades to listings & swipes
            flash("{$target['name']} deleted along with their listings and swipes.");
            break;
    }
    redirect('admin_users.php');
}

$q = trim($_GET['q'] ?? '');
$f = $_GET['f'] ?? 'all';
$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM listings l WHERE l.owner_id = u.id) AS listing_count
        FROM users u WHERE 1=1";
$args = [];
if ($q !== '') { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $args[] = "%$q%"; $args[] = "%$q%"; }
if (in_array($f, ['admin','owner','tenant'], true)) { $sql .= " AND u.role = ?"; $args[] = $f; }
if ($f === 'suspended') $sql .= " AND u.status = 'suspended'";
$st = db()->prepare($sql . " ORDER BY u.created_at DESC");
$st->execute($args);
$rows = $st->fetchAll();
$counts = ['admin'=>0,'owner'=>0,'tenant'=>0,'suspended'=>0,'all'=>0];
foreach (db()->query("SELECT role, status, COUNT(*) c FROM users GROUP BY role, status") as $r) {
    $counts[$r['role']] += (int)$r['c'];
    $counts['all'] += (int)$r['c'];
    if ($r['status'] === 'suspended') $counts['suspended'] += (int)$r['c'];
}

page_top('Manage users', $u);
?>
<section class="page-head split">
  <div>
    <h1>Users</h1>
    <p class="muted">Change roles, suspend accounts, or remove them entirely.</p>
  </div>
  <form method="get" class="search-form">
    <input type="search" name="q" placeholder="Search name or email…" value="<?= e($q) ?>">
    <?php if ($f !== 'all'): ?><input type="hidden" name="f" value="<?= e($f) ?>"><?php endif; ?>
    <button class="btn btn-outline btn-sm">Search</button>
  </form>
</section>

<div class="chip-row">
  <?php $base = $q !== '' ? '&q=' . urlencode($q) : '';
        foreach (['all' => "Everyone <span class=\"chip-n\">{$counts['all']}</span>",
                  'tenant' => "Tenants <span class=\"chip-n\">{$counts['tenant']}</span>",
                  'owner' => "Owners <span class=\"chip-n\">{$counts['owner']}</span>",
                  'admin' => "Admins <span class=\"chip-n\">{$counts['admin']}</span>",
                  'suspended' => "Suspended <span class=\"chip-n\">{$counts['suspended']}</span>"] as $k => $label): ?>
    <a class="chip <?= $f === $k ? 'on' : '' ?>" href="?f=<?= $k . $base ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<section class="card table-card">
  <div class="table-scroll"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Listings</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $self = (int)$r['id'] === (int)$u['id']; ?>
      <tr class="<?= $r['status'] === 'suspended' ? 'row-dim' : '' ?>">
        <td data-th="Name"><?= e($r['name']) ?><?= $self ? ' <em>(you)</em>' : '' ?></td>
        <td data-th="Email"><?= e($r['email']) ?></td>
        <td data-th="Role">
          <?php if ($self): ?>
            <span class="pill pill-active"><?= e($r['role']) ?></span>
          <?php else: ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="role">
              <select name="role" onchange="this.form.submit()">
                <?php foreach (['tenant','owner','admin'] as $role): ?>
                  <option value="<?= $role ?>" <?= $r['role'] === $role ? 'selected' : '' ?>><?= $role ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php endif; ?>
        </td>
        <td data-th="Status"><span class="pill pill-<?= $r['status'] === 'active' ? 'active' : 'suspended' ?>"><?= e($r['status']) ?></span></td>
        <td data-th="Listings"><?= (int)$r['listing_count'] ?></td>
        <td data-th="Actions">
          <?php if (!$self): ?>
          <div class="inline-form">
            <?php if ($r['status'] === 'active'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                <button name="action" value="suspend" class="btn btn-ghost btn-sm">Suspend</button></form>
            <?php else: ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                <button name="action" value="activate" class="btn btn-ghost btn-sm">Activate</button></form>
            <?php endif; ?>
            <form method="post" data-confirm="Delete <?= e($r['name']) ?> and all their listings? This cannot be undone.">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
              <button name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php page_bottom();
