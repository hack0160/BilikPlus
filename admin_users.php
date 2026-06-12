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
            $imgs = db()->prepare("SELECT image FROM listings WHERE owner_id = ?");
            $imgs->execute([$id]);
            foreach ($imgs->fetchAll() as $row) delete_listing_image($row['image']);
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]); // cascades to listings & swipes
            flash("{$target['name']} deleted along with their listings and swipes.");
            break;
    }
    redirect('admin_users.php');
}

$rows = db()->query("SELECT u.*,
        (SELECT COUNT(*) FROM listings l WHERE l.owner_id = u.id) AS listing_count
        FROM users u ORDER BY u.created_at DESC")->fetchAll();

page_top('Manage users', $u);
?>
<section class="page-head">
  <h1>Users</h1>
  <p class="muted">Change roles, suspend accounts, or remove them entirely.</p>
</section>

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
            <form method="post" onsubmit="return confirm('Delete <?= e($r['name']) ?> and all their listings? This cannot be undone.')">
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
