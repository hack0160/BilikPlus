<?php
require __DIR__ . '/config.php';
if (current_user()) redirect(role_home(current_user()['role']));

$sent = false; $demoLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $st = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $st->execute([$email]);
    $u = $st->fetch();
    $sent = true; // always claim success to avoid leaking which emails exist
    if ($u) {
        $token = bin2hex(random_bytes(24));
        $st = db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
        $st->execute([$u['id'], $token, db_now(3600)]);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/reset.php?token=' . $token;
        @mail($email, APP_NAME . ' password reset',
              "Hi {$u['name']},\n\nReset your password using this link (valid 1 hour):\n$link\n\nIf you didn't request this, ignore this email.");
        if (DEMO_MODE) $demoLink = $link;
    }
}

page_top('Forgot password');
?>
<div class="auth-wrap">
  <form method="post" class="card auth-card">
    <h1>Forgot your password?</h1>
    <p class="muted">Enter your email and we'll send a reset link valid for one hour.</p>
    <?php if ($sent): ?>
      <div class="form-ok">If that email is registered, a reset link has been sent.</div>
      <?php if ($demoLink): ?>
        <div class="demo-box">
          <strong>Demo mode:</strong> mail isn't configured on this server, so here's your reset link directly:<br>
          <a href="<?= e($demoLink) ?>"><?= e($demoLink) ?></a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" required autocomplete="email">
    </label>
    <button class="btn btn-primary btn-block">Send reset link</button>
    <p class="auth-links"><a href="login.php">Back to sign in</a></p>
  </form>
</div>
<?php page_bottom();
