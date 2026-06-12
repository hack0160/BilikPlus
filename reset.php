<?php
require __DIR__ . '/config.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$st = db()->prepare("SELECT pr.*, u.email, u.name FROM password_resets pr
                     JOIN users u ON u.id = pr.user_id
                     WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > ?");
$st->execute([$token, db_now()]);
$reset = $st->fetch();

$err = '';
if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if (strlen($pass) < 8)      $err = 'Password must be at least 8 characters.';
    elseif ($pass !== $pass2)   $err = 'Passwords do not match.';
    else {
        db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $reset['user_id']]);
        db()->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$reset['id']]);
        flash('Password updated. You can sign in now.');
        redirect('login.php');
    }
}

page_top('Reset password');
?>
<div class="auth-wrap">
  <?php if (!$reset): ?>
    <div class="card auth-card">
      <h1>Link expired</h1>
      <p class="muted">This reset link is invalid or has already been used.</p>
      <a class="btn btn-primary btn-block" href="forgot.php">Request a new link</a>
    </div>
  <?php else: ?>
    <form method="post" class="card auth-card">
      <h1>Choose a new password</h1>
      <p class="muted">Resetting password for <strong><?= e($reset['email']) ?></strong></p>
      <?php if ($err): ?><div class="form-error"><?= e($err) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label>New password <span class="opt">(min 8 characters)</span>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </label>
      <label>Confirm new password
        <input type="password" name="password2" required minlength="8" autocomplete="new-password">
      </label>
      <button class="btn btn-primary btn-block">Update password</button>
    </form>
  <?php endif; ?>
</div>
<?php page_bottom();
