<?php
require __DIR__ . '/config.php';
if (current_user()) redirect(role_home(current_user()['role']));

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $st = db()->prepare("SELECT * FROM users WHERE email = ?");
    $st->execute([$email]);
    $u = $st->fetch();
    $fromLanding = ($_POST['from'] ?? '') === 'landing';
    if (!$u || !password_verify($pass, $u['password_hash'])) {
        $err = 'Email or password is incorrect.';
        if ($fromLanding) redirect('index.php?m=login&err=' . urlencode($err) . '&email=' . urlencode($email));
    } elseif ($u['status'] !== 'active') {
        $err = 'This account is suspended. Contact the site admin.';
        if ($fromLanding) redirect('index.php?m=login&err=' . urlencode($err) . '&email=' . urlencode($email));
    } else {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        flash('Welcome back, ' . $u['name'] . '!');
        redirect(role_home($u['role']));
    }
}

page_top('Sign in');
?>
<div class="auth-wrap">
  <form method="post" class="card auth-card">
    <h1>Sign in</h1>
    <p class="muted">Welcome back. Your shortlist missed you.</p>
    <?php if ($err): ?><div class="form-error"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
    </label>
    <label>Password
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button class="btn btn-primary btn-block">Sign in</button>
    <p class="auth-links">
      <a href="forgot.php">Forgot password?</a>
      <span>New here? <a href="register.php">Create an account</a></span>
    </p>
  </form>
</div>
<?php page_bottom();
