<?php
require __DIR__ . '/config.php';
$u = current_user(); // may be null: listings are public

$st = db()->prepare("SELECT l.*, o.name AS owner_name, o.phone AS owner_phone, o.email AS owner_email
                     FROM listings l JOIN users o ON o.id = l.owner_id WHERE l.id = ?");
$st->execute([(int) ($_GET['id'] ?? 0)]);
$l = $st->fetch();

$canSee = $l && (
    $l['status'] === 'active'
    || ($u && $u['role'] === 'admin')
    || ($u && $u['role'] === 'owner' && (int)$l['owner_id'] === (int)$u['id'])
);
if (!$canSee) {
    flash('Listing not found or unavailable.', 'warn');
    redirect($u ? role_home($u['role']) : 'index.php');
}
$loginNext = 'index.php?m=login&next=' . urlencode('listing.php?id=' . (int) $l['id']);

/* ---- report this listing ---- */
$reportReasons = ['Scam or fraud suspicion', 'Misleading photos or price', 'Property does not exist',
                  'Discriminatory or offensive content', 'Duplicate or spam listing', 'Other'];
$alreadyReported = false;
if ($u) {
    $st = db()->prepare("SELECT 1 FROM reports WHERE listing_id = ? AND reporter_id = ?");
    $st->execute([(int) $l['id'], (int) $u['id']]);
    $alreadyReported = (bool) $st->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report') {
    csrf_check();
    if (!$u) redirect($loginNext);
    if ((int) $l['owner_id'] === (int) $u['id']) { flash('You cannot report your own listing.', 'warn'); redirect('listing.php?id=' . (int) $l['id']); }
    $reason  = in_array($_POST['reason'] ?? '', $reportReasons, true) ? $_POST['reason'] : 'Other';
    $details = substr(trim($_POST['details'] ?? ''), 0, 500);
    if ($alreadyReported) {
        flash('You have already reported this listing — the team will review it.', 'warn');
    } else {
        try {
            db()->prepare("INSERT INTO reports (listing_id, reporter_id, reason, details, created_at) VALUES (?,?,?,?,?)")
               ->execute([(int) $l['id'], (int) $u['id'], $reason, $details, db_now()]);
            $cnt = db()->prepare("SELECT COUNT(*) FROM reports WHERE listing_id = ?");
            $cnt->execute([(int) $l['id']]);
            $n = (int) $cnt->fetchColumn();
            if ($n >= report_threshold() && $l['status'] === 'active') {
                db()->prepare("UPDATE listings SET status = 'suspended' WHERE id = ?")->execute([(int) $l['id']]);
                flash('Thanks — your report was filed. This listing has been suspended pending review.');
            } else {
                flash('Thanks — your report was filed. The team will review this listing.');
            }
        } catch (Throwable $e) {
            flash('You have already reported this listing.', 'warn');
        }
    }
    redirect('listing.php?id=' . (int) $l['id']);
}

$myLike = null;
if ($u && $u['role'] === 'tenant') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $dir = ($_POST['direction'] ?? '') === 'like' ? 'like' : 'pass';
        swipe_upsert((int)$u['id'], (int)$l['id'], $dir);
        flash($dir === 'like' ? 'Added to your shortlist.' : 'Skipped.');
        redirect('listing.php?id=' . (int)$l['id']);
    }
    $st = db()->prepare("SELECT direction FROM swipes WHERE tenant_id = ? AND listing_id = ?");
    $st->execute([$u['id'], $l['id']]);
    $myLike = $st->fetch()['direction'] ?? null;
}

$likes = db()->prepare("SELECT COUNT(*) c FROM swipes WHERE listing_id = ? AND direction = 'like'");
$likes->execute([$l['id']]);
$likeCount = (int) $likes->fetch()['c'];

$photos = listing_photos((int) $l['id'], html_entity_decode(listing_image($l)));

/* ---- SEO: title pattern + structured data, modeled on the major MY portals ---- */
$verb = $l['listing_type'] === 'sale' ? 'for Sale' : 'for Rent';
$seoTitle = $l['title'] . ' – ' . $l['room_type'] . ' ' . $verb . ' in ' . $l['area'] . ', ' . $l['city']
          . ' | ' . price_label($l, false) . ' | ' . APP_NAME;
$seoDesc = $l['room_type'] . ' ' . strtolower($verb) . ' in ' . $l['area'] . ', ' . $l['city'] . ' at ' . price_label($l, false) . '. '
         . ($l['furnishing'] ? $l['furnishing'] . '. ' : '')
         . ($l['listing_type'] === 'rent' && (int)$l['min_tenure'] > 0 ? 'Min ' . (int)$l['min_tenure'] . ' months. ' : '')
         . ($l['amenities'] ? 'Amenities: ' . implode(', ', array_slice(array_filter(array_map('trim', explode(',', $l['amenities']))), 0, 5)) . '. ' : '')
         . 'View photos, map & move-in costs on ' . APP_NAME . '.';
$canonical = abs_url(listing_url($l));
$jsonld = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateListing',
        'name' => $l['title'],
        'url' => $canonical,
        'image' => array_map(fn($ph) => abs_url($ph), array_slice($photos, 0, 4)),
        'description' => trim($seoDesc),
        'datePosted' => date('c', strtotime($l['created_at'] ?: 'now')),
        'offers' => [
            '@type' => 'Offer',
            'price' => (int) $l['price'],
            'priceCurrency' => 'MYR',
            'availability' => 'https://schema.org/InStock',
            'businessFunction' => $l['listing_type'] === 'sale'
                ? 'http://purl.org/goodrelations/v1#Sell'
                : 'http://purl.org/goodrelations/v1#LeaseOut',
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $l['area'],
            'addressRegion' => $l['city'],
            'addressCountry' => 'MY',
        ],
    ] + ($l['lat'] !== null ? ['geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float)$l['lat'], 'longitude' => (float)$l['lng']]] : []),
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => abs_url('index.php')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => ($l['listing_type'] === 'sale' ? 'For sale' : 'For rent'), 'item' => abs_url('index.php?type=' . $l['listing_type'])],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $l['area'], 'item' => abs_url('index.php?type=' . $l['listing_type'] . '&q=' . urlencode($l['area']))],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $l['title']],
        ],
    ],
];
page_top($l['title'], $u, [
    'title' => $seoTitle,
    'description' => strlen($seoDesc) > 300 ? substr($seoDesc, 0, 297) . '…' : $seoDesc,
    'canonical' => $canonical,
    'og_type' => 'article',
    'image' => abs_url($photos[0]),
    'jsonld' => $jsonld,
    'noindex' => $l['status'] !== 'active',
]);
?>
<article class="detail">
  <div class="carousel" id="carousel">
    <div class="carousel-main">
      <?php foreach ($photos as $i => $ph): ?>
        <div class="carousel-img <?= $i === 0 ? 'on' : '' ?>" style="background-image:url('<?= e($ph) ?>')"></div>
      <?php endforeach; ?>
      <?php if (count($photos) > 1): ?>
        <button class="car-btn car-prev" type="button" aria-label="Previous photo">‹</button>
        <button class="car-btn car-next" type="button" aria-label="Next photo">›</button>
        <span class="car-count"><span id="carIdx">1</span> / <?= count($photos) ?></span>
      <?php endif; ?>
      <span class="price-chip price-chip-lg"><?= price_label($l) ?></span>
      <?php if ($l['listing_type'] === 'sale'): ?><span class="status-chip">For sale</span><?php endif; ?>
      <?php if ($l['status'] !== 'active'): ?><span class="status-chip">Status: <?= e($l['status']) ?></span><?php endif; ?>
    </div>
    <?php if (count($photos) > 1): ?>
    <div class="carousel-thumbs" role="tablist" aria-label="Listing photos">
      <?php foreach ($photos as $i => $ph): ?>
        <button class="car-thumb <?= $i === 0 ? 'on' : '' ?>" type="button" data-idx="<?= $i ?>"
                style="background-image:url('<?= e($ph) ?>')" aria-label="Photo <?= $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="detail-main">
    <h1><?= e($l['title']) ?></h1>
    <p class="muted">📍 <?= e($l['area']) ?>, <?= e($l['city']) ?><?= $l['address'] ? ' · ' . e($l['address']) : '' ?></p>

    <p class="tag-row">
      <span class="tag"><?= e($l['room_type']) ?></span>
      <span class="tag"><?= e($l['property_type']) ?></span>
      <span class="tag"><?= e($l['furnishing']) ?></span>
      <span class="tag tag-alt"><?= $l['gender_pref'] === 'Any' ? 'Any gender' : e($l['gender_pref']) . ' only' ?></span>
      <?php if ($l['listing_type'] === 'rent' && (int)$l['min_tenure'] > 0): ?>
        <span class="tag tag-soft">Min <?= (int)$l['min_tenure'] ?> mo stay</span>
      <?php endif; ?>
    </p>

    <?php $am = array_filter(array_map('trim', explode(',', $l['amenities']))); if ($am): ?>
      <h3>Amenities</h3>
      <p class="tag-row"><?php foreach ($am as $a): ?><span class="tag tag-soft"><?= e($a) ?></span><?php endforeach; ?></p>
    <?php endif; ?>

    <?php if (trim($l['description'])): ?>
      <h3>About this room</h3>
      <p class="desc"><?= nl2br(e($l['description'])) ?></p>
    <?php endif; ?>

    <?php if ($l['lat'] !== null && $l['lng'] !== null): ?>
      <h3>Location &amp; nearby</h3>
      <div class="map-wrap" id="mapWrap">
        <div id="viewMap" class="map-canvas map-view"
             data-lat="<?= e((string)$l['lat']) ?>" data-lng="<?= e((string)$l['lng']) ?>"
             data-title="<?= e($l['title']) ?>"></div>
        <button type="button" class="map-expand" id="mapExpand" aria-label="Enlarge map">⤢ Enlarge</button>
      </div>
      <div class="poi-legend" id="poiLegend">
        <p class="muted small" id="poiStatus">Finding nearby places…</p>
      </div>
      <p class="map-links">
        📍 <?= e($l['area']) ?>, <?= e($l['city']) ?> ·
        <a href="https://www.google.com/maps?q=<?= e((string)$l['lat']) ?>,<?= e((string)$l['lng']) ?>" target="_blank" rel="noopener">Open in Google Maps</a> ·
        <a href="https://waze.com/ul?ll=<?= e((string)$l['lat']) ?>,<?= e((string)$l['lng']) ?>&navigate=yes" target="_blank" rel="noopener">Navigate with Waze</a>
      </p>
    <?php endif; ?>

    <?php if ($l['listing_type'] === 'rent'): $c = movein_costs($l); ?>
    <div class="card cost-card">
      <h3>Costs</h3>
      <table class="calc-table">
        <tr><td>Monthly rent</td><td>RM <?= number_format($c['rent']) ?></td></tr>
        <?php if ($c['dep_m'] > 0): ?><tr><td>Security deposit (<?= rtrim(rtrim(number_format($c['dep_m'], 1), '0'), '.') ?> mo)</td><td>RM <?= number_format($c['deposit']) ?></td></tr><?php endif; ?>
        <?php if ($c['util_m'] > 0): ?><tr><td>Utility deposit (<?= rtrim(rtrim(number_format($c['util_m'], 1), '0'), '.') ?> mo)</td><td>RM <?= number_format($c['utility']) ?></td></tr><?php endif; ?>
        <?php if ($c['fee'] > 0): ?><tr><td>One-time fees</td><td>RM <?= number_format($c['fee']) ?></td></tr><?php endif; ?>
        <tr class="calc-total"><td>Est. move-in total</td><td>RM <?= number_format($c['total']) ?></td></tr>
      </table>
      <p class="muted small">Move-in total = first month's rent + deposits + fees.
        <?= (int)$l['min_tenure'] > 0 ? 'Minimum stay: ' . (int)$l['min_tenure'] . ' month' . ((int)$l['min_tenure'] > 1 ? 's' : '') . '.' : 'No minimum stay.' ?></p>
    </div>
    <?php endif; ?>

    <div class="card contact-card">
      <h3>Owner</h3>
      <?php if ($u): ?>
        <p><strong><?= e($l['owner_name']) ?></strong></p>
        <?php if ($l['owner_phone']): ?><p>📞 <a href="tel:<?= e($l['owner_phone']) ?>"><?= e($l['owner_phone']) ?></a></p><?php endif; ?>
        <p>✉️ <a href="mailto:<?= e($l['owner_email']) ?>"><?= e($l['owner_email']) ?></a></p>
      <?php else: ?>
        <p class="muted">Sign in to see the owner's phone and email — it takes a few seconds and it's free.</p>
        <a class="btn btn-primary" href="<?= e($loginNext) ?>">Contact owner</a>
      <?php endif; ?>
      <p class="muted">♥ <?= $likeCount ?> tenant<?= $likeCount === 1 ? '' : 's' ?> shortlisted this room</p>
    </div>

    <?php if (!$u || (int) $l['owner_id'] !== (int) $u['id']): ?>
    <div class="report-block">
      <?php if (!$u): ?>
        <p class="muted small">Something wrong with this listing?
          <a href="<?= e($loginNext) ?>">Sign in to report it</a>.</p>
      <?php elseif ($alreadyReported): ?>
        <p class="muted small">🚩 You reported this listing — the team will review it.</p>
      <?php else: ?>
        <details class="report-details">
          <summary>🚩 Report this listing</summary>
          <form method="post" class="report-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="report">
            <label>Reason
              <select name="reason" required>
                <?php foreach ($reportReasons as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label>Details <span class="opt">(optional, helps the review)</span>
              <textarea name="details" rows="2" maxlength="500" placeholder="Tell us what's wrong…"></textarea>
            </label>
            <p class="muted small">Reports are anonymous to the owner. Listings reported by multiple users are suspended automatically pending review. Bad-faith reports breach our <a href="terms.php" target="_blank">Terms</a>.</p>
            <button class="btn btn-danger btn-sm">Submit report</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
      $alreadyReported = false;
      if ($u && (int) $l['owner_id'] !== (int) $u['id']) {
          $rq = db()->prepare("SELECT 1 FROM reports WHERE listing_id = ? AND reporter_id = ?");
          $rq->execute([(int) $l['id'], (int) $u['id']]);
          $alreadyReported = (bool) $rq->fetch();
      }
    ?>
    <?php if (!$u): ?>
      <div class="detail-actions">
        <a class="btn btn-primary" href="<?= e($loginNext) ?>">Contact owner</a>
        <a class="btn btn-ghost" href="index.php#results">← Back to search</a>
        <a class="report-link" href="<?= e($loginNext) ?>">⚑ Report this listing</a>
      </div>
    <?php elseif ($u['role'] === 'tenant'): ?>
      <form method="post" class="detail-actions">
        <?= csrf_field() ?>
        <?php if ($myLike === 'like'): ?>
          <span class="liked-pill">♥ In your shortlist</span>
          <button name="direction" value="pass" class="btn btn-ghost">Remove &amp; skip</button>
        <?php else: ?>
          <button name="direction" value="like" class="btn btn-primary">✓ Add to shortlist</button>
          <button name="direction" value="pass" class="btn btn-ghost">✕ Skip</button>
        <?php endif; ?>
        <a class="btn btn-outline" href="swipe.php">Back to deck</a>
      </form>
    <?php elseif ($u['role'] === 'owner' && (int)$l['owner_id'] === (int)$u['id']): ?>
      <div class="detail-actions">
        <a class="btn btn-primary" href="owner_edit.php?id=<?= (int)$l['id'] ?>">Edit listing</a>
        <a class="btn btn-outline" href="owner_listings.php">Back to my listings</a>
      </div>
    <?php elseif ($u['role'] === 'admin'): ?>
      <div class="detail-actions">
        <a class="btn btn-outline" href="admin_listings.php">Back to all listings</a>
      </div>
    <?php endif; ?>
  </div>
</article>
<?php if ($u && (int) $l['owner_id'] !== (int) $u['id']): ?>
  <div class="report-box card" id="reportBox">
    <?php if ($alreadyReported): ?>
      <p class="muted small">⚑ You reported this listing — it is queued for admin review.</p>
    <?php else: ?>
      <details>
        <summary>⚑ Report this listing</summary>
        <form method="post" class="report-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="report">
          <label>Reason
            <select name="reason" required>
              <option>Scam or fraud</option>
              <option>Wrong or misleading info</option>
              <option>Offensive content</option>
              <option>Duplicate listing</option>
              <option>Property not available</option>
              <option>Other</option>
            </select>
          </label>
          <label>Details <span class="opt">(optional, helps the admin)</span>
            <textarea name="details" rows="2" maxlength="500" placeholder="Tell us what's wrong…"></textarea>
          </label>
          <p class="muted small">Reports are anonymous to the owner. Listings with multiple reports are suspended automatically pending review. False reports breach our <a href="terms.php" target="_blank">terms</a>.</p>
          <button class="btn btn-danger btn-sm">Submit report</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($l['lat'] !== null && $l['lng'] !== null): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function () {
  if (typeof L === 'undefined') return;
  var el = document.getElementById('viewMap');
  var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
  var map = L.map(el, { scrollWheelZoom: false }).setView([lat, lng], 15);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  var home = L.marker([lat, lng]).addTo(map).bindPopup('<b>' + el.dataset.title + '</b>');
  L.circle([lat, lng], { radius: 180, color: '#1e6e5c', weight: 1, fillOpacity: .08 }).addTo(map);

  /* ---- enlarge / fullscreen toggle ---- */
  var wrap = document.getElementById('mapWrap');
  var btn = document.getElementById('mapExpand');
  function setFull(on) {
    wrap.classList.toggle('map-fullscreen', on);
    document.body.style.overflow = on ? 'hidden' : '';
    btn.textContent = on ? '✕ Close map' : '⤢ Enlarge';
    map.scrollWheelZoom[on ? 'enable' : 'disable']();
    setTimeout(function () { map.invalidateSize(); map.panTo([lat, lng]); }, 60);
  }
  btn.addEventListener('click', function () { setFull(!wrap.classList.contains('map-fullscreen')); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && wrap.classList.contains('map-fullscreen')) setFull(false);
  });

  /* ---- nearby amenities via Overpass, with legend + distances ---- */
  var CATS = [
    { key: 'transit',  label: 'Train / LRT / MRT', color: '#7b61c9', match: function (t) { return t.railway === 'station' || t.railway === 'halt' || t.station === 'subway' || t.public_transport === 'station'; } },
    { key: 'bus',      label: 'Bus stops',          color: '#3b82c4', match: function (t) { return t.highway === 'bus_stop'; } },
    { key: 'edu',      label: 'Schools & universities', color: '#b97e2f', match: function (t) { return /^(school|university|college|kindergarten)$/.test(t.amenity || ''); } },
    { key: 'health',   label: 'Clinics & hospitals', color: '#c25450', match: function (t) { return /^(hospital|clinic|pharmacy|doctors)$/.test(t.amenity || ''); } },
    { key: 'shop',     label: 'Groceries & malls',   color: '#1e6e5c', match: function (t) { return /^(supermarket|mall|convenience|department_store)$/.test(t.shop || ''); } }
  ];
  var legend = document.getElementById('poiLegend');
  var statusEl = document.getElementById('poiStatus');

  function dist(aLat, aLng) { // haversine, metres
    var R = 6371000, dLa = (aLat - lat) * Math.PI / 180, dLo = (aLng - lng) * Math.PI / 180;
    var h = Math.sin(dLa / 2) * Math.sin(dLa / 2)
          + Math.cos(lat * Math.PI / 180) * Math.cos(aLat * Math.PI / 180) * Math.sin(dLo / 2) * Math.sin(dLo / 2);
    return 2 * R * Math.asin(Math.sqrt(h));
  }
  function fmt(m) { return m < 1000 ? Math.round(m / 10) * 10 + ' m' : (m / 1000).toFixed(1) + ' km'; }
  function dot(color) {
    return L.divIcon({ className: '', iconSize: [14, 14], iconAnchor: [7, 7],
      html: '<span style="display:block;width:14px;height:14px;border-radius:50%;background:' + color + ';border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></span>' });
  }

  var q = '[out:json][timeout:12];nwr(around:1500,' + lat + ',' + lng + ')'
        + '[~"^(railway|station|public_transport|highway|amenity|shop)$"~"."];out center 150;';
  fetch('https://overpass-api.de/api/interpreter', { method: 'POST', body: 'data=' + encodeURIComponent(q) })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var buckets = {};
      CATS.forEach(function (c) { buckets[c.key] = []; });
      (data.elements || []).forEach(function (e) {
        var t = e.tags || {}, name = t.name;
        if (!name) return;
        var pLat = e.lat || (e.center && e.center.lat), pLng = e.lon || (e.center && e.center.lon);
        if (!pLat || !pLng) return;
        for (var i = 0; i < CATS.length; i++) {
          if (CATS[i].match(t)) {
            buckets[CATS[i].key].push({ name: name, lat: pLat, lng: pLng, d: dist(pLat, pLng) });
            break;
          }
        }
      });
      legend.innerHTML = '';
      var any = false;
      CATS.forEach(function (c) {
        var items = buckets[c.key].sort(function (a, b) { return a.d - b.d; }).slice(0, 4);
        if (!items.length) return;
        any = true;
        var sec = document.createElement('div');
        sec.className = 'poi-cat';
        sec.innerHTML = '<h4><span class="poi-dot" style="background:' + c.color + '"></span>' + c.label + '</h4>';
        var ul = document.createElement('ul');
        items.forEach(function (it) {
          var mk = L.marker([it.lat, it.lng], { icon: dot(c.color) }).addTo(map)
                    .bindPopup('<b>' + it.name + '</b><br>' + c.label + ' · ' + fmt(it.d) + ' away');
          var li = document.createElement('li');
          li.innerHTML = '<button type="button"><span>' + it.name + '</span><em>' + fmt(it.d) + '</em></button>';
          li.querySelector('button').addEventListener('click', function () {
            map.setView([it.lat, it.lng], 17);
            mk.openPopup();
          });
          ul.appendChild(li);
        });
        sec.appendChild(ul);
        legend.appendChild(sec);
      });
      if (!any) legend.innerHTML = '<p class="muted small">No mapped places found within 1.5 km.</p>';
    })
    .catch(function () {
      statusEl.textContent = "Couldn't load nearby places right now — the map above still works.";
    });
})();
</script>
<?php endif; ?>
<?php page_bottom();
