<?php
require __DIR__ . '/config.php';
$u = current_user();
if ($u) redirect(role_home($u['role']));

page_top('Find your room');
?>
<section class="hero">
  <div class="hero-copy">
    <p class="eyebrow">AAAAAAAAAAAAAAAAAARoom rental · Klang Valley &amp; beyond</p>
    <h1>Finding a bilik shouldn't feel like homework.</h1>
    <p class="lede">Swipe through real rooms the way you'd browse anything else — right to shortlist, left to skip. Owners list in minutes; tenants decide in seconds.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="register.php">Create a free account</a>
      <a class="btn btn-ghost" href="login.php">Sign in</a>
    </div>
    <div class="demo-box">
      <strong>Try the demo accounts</strong> (password <code>Demo@123</code>):
      <ul>
        <li><code>tenant@bilikgo.test</code> — swipe &amp; shortlist rooms</li>
        <li><code>owner@bilikgo.test</code> — manage listings</li>
        <li><code>admin@bilikgo.test</code> — moderate the whole site</li>
      </ul>
    </div>
  </div>
  <div class="hero-card-stack" aria-hidden="true">
    <div class="mini-card mc-back"></div>
    <div class="mini-card mc-mid"></div>
    <div class="mini-card mc-front">
      <img src="assets/img/seed2.svg" alt="">
      <div class="mini-card-body">
        <span class="price-chip">RM 1,200<small>/mo</small></span>
        <strong>Master room · Bangsar South</strong>
        <span class="mini-meta">Fully furnished · Near LRT</span>
      </div>
      <span class="stamp stamp-like">SUKA ✓</span>
    </div>
  </div>
</section>

<section class="how">
  <div class="how-step"><span class="how-n">Tenants</span><h3>Swipe to shortlist</h3><p>Right means "suka", left means "tak nak". Your shortlist keeps every room you liked, with the owner's contact one tap away.</p></div>
  <div class="how-step"><span class="how-n">Owners</span><h3>List and manage</h3><p>Post a room with photo, price and amenities. Edit, hide or delete your listings anytime, and watch the likes roll in.</p></div>
  <div class="how-step"><span class="how-n">Admins</span><h3>Keep it clean</h3><p>Moderate every listing, manage user accounts and roles, and suspend anything that breaks the rules.</p></div>
</section>
<?php page_bottom();
