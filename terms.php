<?php
require __DIR__ . '/config.php';
$u = current_user(); // public page
page_top('Terms & Disclaimer', $u);
?>
<div class="page-head">
  <h1>Terms of Use &amp; <span class="hl">Disclaimer</span></h1>
  <p class="muted">Last updated: <?= date('j F Y') ?> · These terms protect tenants, owners, and <?= e(APP_NAME) ?> alike. By creating an account or using this site you agree to everything below.</p>
</div>

<div class="card form-card terms-doc">

  <h2>1. What <?= e(APP_NAME) ?> is (and is not)</h2>
  <p><?= e(APP_NAME) ?> is an <strong>advertising platform only</strong>. We let property owners publish listings and let tenants and buyers discover them. We are <strong>not</strong> a real-estate agent, broker, property manager, or party to any tenancy or sale agreement. Any deal, viewing, payment, deposit, or contract happens <strong>directly between owner and tenant/buyer</strong>, at their own risk and on their own terms.</p>

  <h2>2. No verification — do your own checks</h2>
  <p>Listings are created by their owners. We do <strong>not</strong> verify ownership, pricing, photos, measurements, availability, or the identity of any user, and we make <strong>no warranty</strong> that any information on this site is accurate, complete, or current. Estimated costs (deposits, move-in totals) are calculated from figures the owner entered and are <strong>indicative only</strong>. Before paying anything, tenants and buyers should view the property in person, verify the owner's identity and right to rent or sell, and use a written agreement (and proper stamping where applicable).</p>

  <h2>3. Account rules &amp; acceptable use</h2>
  <p>You must provide accurate registration details and keep your password safe; you are responsible for all activity under your account. You agree <strong>not</strong> to: post false, misleading, or discriminatory listings; advertise property you have no right to offer; harvest other users' contact details for spam; upload unlawful, obscene, or infringing content; attempt to bypass site security; or use the platform for scams, money laundering, or any illegal purpose. We may suspend or delete accounts and content that break these rules, at our sole discretion and without notice.</p>

  <h2>4. Your content, photos &amp; copyright</h2>
  <p>You keep ownership of the text and photos you upload, and you confirm you have the legal right to publish them (you took the photo, or you have the owner's permission). By uploading, you grant <?= e(APP_NAME) ?> a non-exclusive, royalty-free licence to store, resize, watermark, and display that content on this platform for the purpose of running the service. Uploaded photos are stamped with a small <?= e(APP_NAME) ?> watermark to deter unauthorised reuse; this watermark does not transfer ownership of the photo to us. If you believe content on this site infringes your copyright, contact the site administrator with details and we will review and, where justified, remove it promptly.</p>

  <h2>5. Reporting &amp; moderation</h2>
  <p>Any signed-in user may report a listing they believe is fraudulent, misleading, offensive, or otherwise in breach of these terms. Listings that receive multiple independent reports are <strong>automatically suspended</strong> pending admin review. Reporting in bad faith (to harass a competitor, for example) is itself a breach of these terms. Moderation decisions are made in good faith but are discretionary and final.</p>

  <h2>6. Limitation of liability</h2>
  <p>To the maximum extent permitted by law, <?= e(APP_NAME) ?>, its operator, and contributors are <strong>not liable</strong> for any loss or damage — including lost deposits, rental disputes, fraud by other users, property defects, data loss, or loss of profit — arising from your use of this site or from any dealing between users. The service is provided <em>"as is"</em> and <em>"as available"</em>, without warranties of any kind, and may change or go offline at any time. Where liability cannot be excluded, it is limited to the amount (if any) you paid us to use the service.</p>

  <h2>7. Privacy in brief</h2>
  <p>We store the account details you give us (name, email, phone), your listings, photos, swipes/shortlists, and reports, solely to operate the service. Your contact details are shown only to signed-in users viewing your listing. We do not sell your personal data. You may ask the administrator to delete your account and its data.</p>

  <h2>8. Third-party services, trademarks &amp; attributions</h2>
  <p>Maps are rendered with <a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a> (BSD-2-Clause licence) using map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors (ODbL). Place search uses OSM's Nominatim and nearby places use the Overpass API, both subject to their own usage policies. Links to Google Maps and Waze are provided for convenience; Google Maps™ and Waze™ are trademarks of their respective owners, who do not sponsor or endorse this site. All other product names, logos, brands, and condominium or taman names appearing in listings are the property of their respective owners and are used for identification purposes only. If you operate one of these services or brands and have concerns, contact the administrator.</p>

  <h2>9. General</h2>
  <p>These terms are governed by the laws of Malaysia. If any clause is found unenforceable, the rest still applies. We may update these terms; continued use after an update means you accept the new version. These terms are a general template provided with the site software and do not constitute legal advice — the site operator should have them reviewed by a qualified lawyer before relying on them commercially.</p>

  <div class="detail-actions">
    <a class="btn btn-primary" href="index.php">Back to browsing</a>
    <?php if (!$u): ?><a class="btn btn-ghost" href="index.php?m=register">Create an account</a><?php endif; ?>
  </div>
</div>
<?php page_bottom(); ?>
