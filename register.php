<?php
require __DIR__ . '/config.php';
if (current_user()) redirect(role_home(current_user()['role']));

$err = '';
$old = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'tenant'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim(strtolower($_POST['email'] ?? ''));
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['role']  = ($_POST['role'] ?? '') === 'owner' ? 'owner' : 'tenant'; // admin cannot self-register
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($old['name'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $err = 'Please fill in a valid name and email.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $err = 'Passwords do not match.';
    } else {
        $st = db()->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $err = 'That email is already registered. Try signing in instead.';
        } else {
            $st = db()->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?)");
            $st->execute([$old['name'], $old['email'], $old['phone'], password_hash($pass, PASSWORD_DEFAULT), $old['role']]);
            $_SESSION['uid'] = (int) db()->lastInsertId();
            session_regenerate_id(true);
            flash('Account created. Welcome to ' . APP_NAME . '!');
            redirect(role_home($old['role']));
        }
    }
}

page_top('Create account');
?>
<div class="auth-wrap">
  <form method="post" class="card auth-card">
    <h1>Create your account</h1>
    <p class="muted">Looking for a room, or renting one out? Pick your side.</p>
    <?php if ($err): ?><div class="form-error"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>

    <div class="role-pick">
      <label class="role-opt">
        <input type="radio" name="role" value="tenant" <?= $old['role'] === 'tenant' ? 'checked' : '' ?>>
        <span><strong>I'm a tenant</strong><small>Swipe rooms &amp; build a shortlist</small></span>
      </label>
      <label class="role-opt">
        <input type="radio" name="role" value="owner" <?= $old['role'] === 'owner' ? 'checked' : '' ?>>
        <span><strong>I'm an owner</strong><small>List &amp; manage my rooms</small></span>
      </label>
    </div>

    <label>Full name
      <input type="text" name="name" required value="<?= e($old['name']) ?>">
    </label>
    <label>Email
      <input type="email" name="email" required autocomplete="email" value="<?= e($old['email']) ?>">
    </label>
    <label>Phone / WhatsApp <span class="opt">(shown to tenants if you're an owner)</span>
      <input type="tel" name="phone" placeholder="012-3456789" value="<?= e($old['phone']) ?>">
    </label>
    <label>Password <span class="opt">(min 8 characters)</span>
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label>Confirm password
      <input type="password" name="password2" required minlength="8" autocomplete="new-password">
    </label>
    <button class="btn btn-primary btn-block">Create account</button>
    <p class="auth-links"><span>Already registered? <a href="login.php">Sign in</a></span></p>
  </form>
</div>
<?php page_bottom();
