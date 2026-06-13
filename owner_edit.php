<?php
require __DIR__ . '/config.php';
$u = require_role('owner');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$l = [
    'title' => '', 'area' => '', 'city' => 'Kuala Lumpur', 'address' => '', 'price' => '',
    'listing_type' => 'rent', 'min_tenure' => 0, 'dep_months' => 1, 'util_months' => 0.5, 'fee_rm' => 0,
    'lat' => null, 'lng' => null,
    'room_type' => 'Medium Room', 'property_type' => 'Condominium', 'furnishing' => 'Fully furnished',
    'gender_pref' => 'Any', 'amenities' => '', 'description' => '', 'image' => '', 'status' => 'active',
];
if ($id) {
    $st = db()->prepare("SELECT * FROM listings WHERE id = ? AND owner_id = ?");
    $st->execute([$id, $u['id']]);
    $l = $st->fetch();
    if (!$l) { flash('Listing not found.', 'warn'); redirect('owner_listings.php'); }
}

/* photo management actions from the edit form thumbnails */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'photo' && $id) {
    csrf_check();
    $pid = (int) ($_POST['photo_id'] ?? 0);
    $own = db()->prepare("SELECT id, sort FROM images WHERE id = ? AND listing_id = ?");
    $own->execute([$pid, $id]);
    $photo = $own->fetch();
    if ($photo) {
        switch ($_POST['op'] ?? '') {
            case 'delete':
                db()->prepare("DELETE FROM images WHERE id = ?")->execute([$pid]);
                flash('Photo removed.');
                break;
            case 'cover':
                $min = db()->prepare("SELECT MIN(sort) FROM images WHERE listing_id = ?");
                $min->execute([$id]);
                db()->prepare("UPDATE images SET sort = ? WHERE id = ?")
                   ->execute([(int) $min->fetchColumn() - 1, $pid]);
                flash('Cover photo updated.');
                break;
            case 'left':
            case 'right':
                $dir = ($_POST['op'] === 'left') ? '<' : '>';
                $ord = ($_POST['op'] === 'left') ? 'DESC' : 'ASC';
                $nb = db()->prepare("SELECT id, sort FROM images WHERE listing_id = ? AND (sort $dir ? OR (sort = ? AND id $dir ?)) ORDER BY sort $ord, id $ord LIMIT 1");
                $nb->execute([$id, $photo['sort'], $photo['sort'], $pid]);
                $other = $nb->fetch();
                if ($other) {
                    // swap; if sorts equal, nudge to force distinct order
                    $a = (int) $photo['sort']; $b = (int) $other['sort'];
                    if ($a === $b) { $b = $a + ($_POST['op'] === 'left' ? -1 : 1); }
                    db()->prepare("UPDATE images SET sort = ? WHERE id = ?")->execute([$b, $pid]);
                    db()->prepare("UPDATE images SET sort = ? WHERE id = ?")->execute([$a, (int) $other['id']]);
                    flash('Photo order updated.');
                }
                break;
        }
        refresh_cover($id);
    }
    redirect('owner_edit.php?id=' . $id);
}

$roomTypes = ['Single Room', 'Medium Room', 'Master Room', 'Studio', 'Whole Unit'];
$propTypes = ['Condominium', 'Apartment', 'Serviced Residence', 'Flat', 'Terrace House', 'Double Storey House'];
$furnish   = ['Fully furnished', 'Partially furnished', 'Unfurnished'];
$genders   = ['Any', 'Male', 'Female'];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delphoto') {
    csrf_check();
    foreach (['title','area','city','address','description'] as $f) $l[$f] = trim($_POST[$f] ?? '');
    $picked = array_values(array_intersect((array) ($_POST['amenities'] ?? []), amenity_options()));
    $other  = trim($_POST['amenities_other'] ?? '');
    if ($other !== '') {
        foreach (array_filter(array_map('trim', explode(',', $other))) as $extra) $picked[] = $extra;
    }
    $l['amenities'] = implode(',', array_unique($picked));
    $l['min_tenure']  = max(0, min(60, (int) ($_POST['min_tenure'] ?? 0)));
    $l['dep_months']  = max(0, min(6, round((float) ($_POST['dep_months'] ?? 0) * 2) / 2));
    $l['util_months'] = max(0, min(3, round((float) ($_POST['util_months'] ?? 0) * 2) / 2));
    $l['fee_rm']      = max(0, min(10000, (int) ($_POST['fee_rm'] ?? 0)));
    if ($l['listing_type'] === 'sale') { $l['min_tenure'] = 0; $l['dep_months'] = 0; $l['util_months'] = 0; $l['fee_rm'] = 0; }
    $lat = trim($_POST['lat'] ?? ''); $lng = trim($_POST['lng'] ?? '');
    if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)
        && (float)$lat >= -90 && (float)$lat <= 90 && (float)$lng >= -180 && (float)$lng <= 180) {
        $l['lat'] = (float) $lat; $l['lng'] = (float) $lng;
    } else { $l['lat'] = null; $l['lng'] = null; }
    $l['price']         = (int) ($_POST['price'] ?? 0);
    $l['listing_type']  = ($_POST['listing_type'] ?? '') === 'sale' ? 'sale' : 'rent';
    $l['room_type']     = in_array($_POST['room_type'] ?? '', $roomTypes, true) ? $_POST['room_type'] : 'Medium Room';
    $l['property_type'] = in_array($_POST['property_type'] ?? '', $propTypes, true) ? $_POST['property_type'] : 'Condominium';
    $l['furnishing']    = in_array($_POST['furnishing'] ?? '', $furnish, true) ? $_POST['furnishing'] : 'Fully furnished';
    $l['gender_pref']   = in_array($_POST['gender_pref'] ?? '', $genders, true) ? $_POST['gender_pref'] : 'Any';
    if ($l['status'] !== 'suspended') {
        $l['status'] = ($_POST['status'] ?? '') === 'hidden' ? 'hidden' : 'active';
    }

    if ($l['title'] === '' || $l['area'] === '' || $l['city'] === '') {
        $err = 'Title, area and city are required.';
    } elseif ($l['listing_type'] === 'rent' && ($l['price'] < 50 || $l['price'] > 50000)) {
        $err = 'Monthly rent must be between RM 50 and RM 50,000.';
    } elseif ($l['listing_type'] === 'sale' && ($l['price'] < 10000 || $l['price'] > 50000000) ) {
        $err = 'Sale price must be between RM 10,000 and RM 50,000,000.';
    } else {
        try {
            if ($id) {
                db()->prepare("UPDATE listings SET title=?,area=?,city=?,address=?,price=?,listing_type=?,room_type=?,property_type=?,
                               furnishing=?,gender_pref=?,amenities=?,description=?,image=?,status=?,
                               min_tenure=?,dep_months=?,util_months=?,fee_rm=?,lat=?,lng=?
                               WHERE id=? AND owner_id=?")
                   ->execute([$l['title'],$l['area'],$l['city'],$l['address'],$l['price'],$l['listing_type'],$l['room_type'],$l['property_type'],
                              $l['furnishing'],$l['gender_pref'],$l['amenities'],$l['description'],$l['image'],$l['status'],
                              $l['min_tenure'],$l['dep_months'],$l['util_months'],$l['fee_rm'],$l['lat'],$l['lng'],$id,$u['id']]);
                $added = handle_images_upload('photos', $id);
                refresh_cover($id);
                flash($added ? "Listing updated — $added photo(s) added." : 'Listing updated.');
            } else {
                db()->prepare("INSERT INTO listings (owner_id,title,area,city,address,price,listing_type,room_type,property_type,
                               furnishing,gender_pref,amenities,description,image,status,
                               min_tenure,dep_months,util_months,fee_rm,lat,lng)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$u['id'],$l['title'],$l['area'],$l['city'],$l['address'],$l['price'],$l['listing_type'],$l['room_type'],$l['property_type'],
                              $l['furnishing'],$l['gender_pref'],$l['amenities'],$l['description'],$l['image'] ?: 'assets/img/seed1.svg',$l['status'],
                              $l['min_tenure'],$l['dep_months'],$l['util_months'],$l['fee_rm'],$l['lat'],$l['lng']]);
                $newId = (int) db()->lastInsertId();
                handle_images_upload('photos', $newId);
                refresh_cover($newId);
                flash('Listing published. Tenants can swipe on it now.');
            }
            redirect('owner_listings.php');
        } catch (RuntimeException $ex) {
            $err = $ex->getMessage();
        }
    }
}

page_top($id ? 'Edit listing' : 'New listing', $u, ['noindex' => true]);
?>
<div class="form-wrap">
  <form method="post" enctype="multipart/form-data" class="card form-card">
    <h1><?= $id ? 'Edit listing' : 'Post a new room' ?></h1>
    <?php if ($l['status'] === 'suspended'): ?>
      <div class="form-error">This listing was suspended by an admin. You can edit it, but only an admin can make it live again.</div>
    <?php endif; ?>
    <?php if ($err): ?><div class="form-error"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <label>Listing title
      <input type="text" name="title" required maxlength="120" placeholder="e.g. Cozy medium room near MRT Cheras" value="<?= e($l['title']) ?>">
    </label>

    <div class="form-grid">
      <label>Area / neighbourhood
        <input type="text" name="area" required placeholder="e.g. Cheras" value="<?= e($l['area']) ?>">
      </label>
      <label>City / state
        <input type="text" name="city" required placeholder="e.g. Kuala Lumpur" value="<?= e($l['city']) ?>">
      </label>
    </div>

    <label>Address <span class="opt">(optional — start typing for suggestions)</span>
      <span class="map-search addr-search">
        <input type="text" name="address" id="addrInput" autocomplete="off"
               placeholder="e.g. Jalan Cempaka 5, Taman Cempaka, Cheras" value="<?= e($l['address']) ?>">
        <span class="map-results" id="addrResults" hidden></span>
      </span>
    </label>

    <fieldset class="map-set">
      <legend>Location on map <span class="opt">(helps tenants find you — recommended)</span></legend>
      <div class="map-search">
        <input type="text" id="mapSearch" placeholder="Type the condo or taman name, e.g. Pangsapuri Seri Cempaka"
               autocomplete="off" aria-label="Search for a place">
        <div class="map-results" id="mapResults" hidden></div>
      </div>
      <div id="ownerMap" class="map-canvas" aria-label="Map — click or drag the pin to set the exact location"></div>
      <div class="map-meta">
        <span id="mapStatus" class="muted small"><?= $l['lat'] !== null ? 'Pin set — drag it to fine-tune.' : 'No location yet. Search above, or click the map to drop a pin.' ?></span>
        <button type="button" class="btn btn-ghost btn-sm" id="mapClear">Remove pin</button>
      </div>
      <input type="hidden" name="lat" id="f-lat" value="<?= $l['lat'] !== null ? e((string)$l['lat']) : '' ?>">
      <input type="hidden" name="lng" id="f-lng" value="<?= $l['lng'] !== null ? e((string)$l['lng']) : '' ?>">
    </fieldset>

    <div class="form-grid">
      <label>Listing type
        <select name="listing_type">
          <option value="rent" <?= $l['listing_type'] === 'rent' ? 'selected' : '' ?>>For rent (monthly)</option>
          <option value="sale" <?= $l['listing_type'] === 'sale' ? 'selected' : '' ?>>For sale</option>
        </select>
      </label>
      <label>Price (RM) <span class="opt">monthly rent, or full price for sale</span>
        <input type="number" name="price" required min="50" max="50000000" value="<?= e((string)$l['price']) ?>">
      </label>
      <label>Room type
        <select name="room_type"><?php foreach ($roomTypes as $t): ?><option <?= $l['room_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
      </label>
      <label>Property type
        <select name="property_type"><?php foreach ($propTypes as $t): ?><option <?= $l['property_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
      </label>
      <label>Furnishing
        <select name="furnishing"><?php foreach ($furnish as $t): ?><option <?= $l['furnishing'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
      </label>
      <label>Tenant preference
        <select name="gender_pref"><?php foreach ($genders as $t): ?><option <?= $l['gender_pref'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
      </label>
      <label>Visibility
        <select name="status" <?= $l['status'] === 'suspended' ? 'disabled' : '' ?>>
          <option value="active" <?= $l['status'] === 'active' ? 'selected' : '' ?>>Live — tenants can swipe</option>
          <option value="hidden" <?= $l['status'] === 'hidden' ? 'selected' : '' ?>>Hidden — only you can see it</option>
        </select>
      </label>
    </div>

    <?php
      $picked = array_filter(array_map('trim', explode(',', $l['amenities'])));
      $known  = amenity_options();
      $extras = implode(', ', array_diff($picked, $known));
    ?>
    <fieldset class="amen-set">
      <legend>Amenities <span class="opt">(tick all that apply)</span></legend>
      <div class="amen-grid">
        <?php foreach ($known as $a): ?>
          <label class="amen-opt">
            <input type="checkbox" name="amenities[]" value="<?= e($a) ?>" <?= in_array($a, $picked, true) ? 'checked' : '' ?>>
            <span><?= e($a) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <label>Other amenities <span class="opt">(optional, comma-separated)</span>
        <input type="text" name="amenities_other" placeholder="e.g. Balcony, Smart lock" value="<?= e($extras) ?>">
      </label>
    </fieldset>

    <fieldset class="rent-terms" id="rentTerms">
      <legend>Rental terms <span class="opt">(rent listings only)</span></legend>
      <div class="form-grid">
        <label>Minimum tenure
          <select name="min_tenure" id="t-tenure">
            <?php foreach ([0 => 'Flexible / no minimum', 1 => '1 month', 3 => '3 months', 6 => '6 months', 12 => '12 months', 24 => '24 months'] as $v => $lab): ?>
              <option value="<?= $v ?>" <?= (int)$l['min_tenure'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Security deposit
          <select name="dep_months" id="t-dep">
            <?php foreach ([0, 0.5, 1, 1.5, 2, 2.5, 3] as $v): ?>
              <option value="<?= $v ?>" <?= (float)$l['dep_months'] == $v ? 'selected' : '' ?>><?= $v == 0 ? 'None' : rtrim(rtrim(number_format($v, 1), '0'), '.') . ' month' . ($v > 1 ? 's' : '') ?> rent</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Utility deposit
          <select name="util_months" id="t-util">
            <?php foreach ([0, 0.5, 1] as $v): ?>
              <option value="<?= $v ?>" <?= (float)$l['util_months'] == $v ? 'selected' : '' ?>><?= $v == 0 ? 'None' : rtrim(rtrim(number_format($v, 1), '0'), '.') . ' month' . ($v > 1 ? 's' : '') ?> rent</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>One-time fees (RM) <span class="opt">stamping, processing, etc.</span>
          <input type="number" name="fee_rm" id="t-fee" min="0" max="10000" value="<?= e((string)(int)$l['fee_rm']) ?>">
        </label>
      </div>
      <div class="calc-box" id="calcBox" aria-live="polite">
        <strong>Tenant's estimated move-in cost</strong>
        <table class="calc-table">
          <tr><td>First month's rent</td><td id="c-rent">—</td></tr>
          <tr><td>Security deposit (<span id="c-depm">0</span> mo)</td><td id="c-dep">—</td></tr>
          <tr><td>Utility deposit (<span id="c-utilm">0</span> mo)</td><td id="c-util">—</td></tr>
          <tr><td>One-time fees</td><td id="c-fee">—</td></tr>
          <tr class="calc-total"><td>Total upfront</td><td id="c-total">—</td></tr>
        </table>
      </div>
    </fieldset>

    <label>Description
      <textarea name="description" rows="5" placeholder="Deposit terms, housemates, nearby transport, anything tenants should know."><?= e($l['description']) ?></textarea>
    </label>

    <label>Photos <span class="opt">(up to 6 · JPG, PNG or WebP, max 3 MB each · first photo is the cover)</span>
      <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    </label>
    <?php if ($id):
        $st = db()->prepare("SELECT id FROM images WHERE listing_id = ? ORDER BY sort, id");
        $st->execute([$id]);
        $photos = $st->fetchAll();
        if ($photos): ?>
      <div class="photo-strip">
        <?php $last = count($photos) - 1; foreach ($photos as $i => $ph): $pid = (int)$ph['id']; ?>
          <div class="photo-thumb">
            <img src="image.php?id=<?= $pid ?>" alt="Photo <?= $i + 1 ?>">
            <?php if ($i === 0): ?><span class="photo-cover">Cover</span><?php endif; ?>
            <button type="submit" form="ph<?= $pid ?>del" class="photo-del" aria-label="Remove photo">✕</button>
            <div class="photo-tools">
              <?php if ($i > 0): ?>
                <button type="submit" form="ph<?= $pid ?>left" class="photo-tool" aria-label="Move photo left" title="Move left">◀</button>
                <button type="submit" form="ph<?= $pid ?>cover" class="photo-tool" aria-label="Make cover photo" title="Make cover">★</button>
              <?php endif; ?>
              <?php if ($i < $last): ?>
                <button type="submit" form="ph<?= $pid ?>right" class="photo-tool" aria-label="Move photo right" title="Move right">▶</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php foreach ($photos as $ph): $pid = (int)$ph['id'];
            foreach (['del' => 'delete', 'cover' => 'cover', 'left' => 'left', 'right' => 'right'] as $suffix => $op): ?>
        <form method="post" id="ph<?= $pid . $suffix ?>" <?= $op === 'delete' ? 'data-confirm="Remove this photo?"' : '' ?>>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="photo">
          <input type="hidden" name="op" value="<?= $op ?>">
          <input type="hidden" name="photo_id" value="<?= $pid ?>">
        </form>
      <?php endforeach; endforeach; ?>
    <?php endif; endif; ?>

    <div class="detail-actions">
      <button class="btn btn-primary"><?= $id ? 'Save changes' : 'Publish listing' ?></button>
      <a class="btn btn-ghost" href="owner_listings.php">Cancel</a>
    </div>
  </form>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
/* ---- map tagging: search-as-you-type (Nominatim) + draggable pin ---- */
(function () {
  if (typeof L === 'undefined') { document.querySelector('.map-set').style.display = 'none'; return; }
  var latIn = document.getElementById('f-lat'), lngIn = document.getElementById('f-lng');
  var statusEl = document.getElementById('mapStatus');
  var hasPin = latIn.value !== '' && lngIn.value !== '';
  var start = hasPin ? [parseFloat(latIn.value), parseFloat(lngIn.value)] : [3.139, 101.6869]; // KL
  var map = L.map('ownerMap', { scrollWheelZoom: false }).setView(start, hasPin ? 16 : 11);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var marker = null;
  function setPin(lat, lng, zoomTo) {
    latIn.value = lat.toFixed(6); lngIn.value = lng.toFixed(6);
    if (!marker) {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function () {
        var pos = marker.getLatLng();
        setPin(pos.lat, pos.lng, false);
        statusEl.textContent = 'Pin moved — exact spot saved.';
      });
    } else marker.setLatLng([lat, lng]);
    if (zoomTo) map.setView([lat, lng], Math.max(map.getZoom(), 16));
    statusEl.textContent = 'Pin set — drag it to fine-tune.';
  }
  window.ownerSetPin = setPin;   // shared with the address suggester
  if (hasPin) setPin(start[0], start[1], false);
  map.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng, false); });
  document.getElementById('mapClear').addEventListener('click', function () {
    latIn.value = ''; lngIn.value = '';
    if (marker) { map.removeLayer(marker); marker = null; }
    statusEl.textContent = 'Pin removed. This listing will have no map.';
  });

  /* geocoder: debounced search with picker, biased to Malaysia */
  var box = document.getElementById('mapSearch'), out = document.getElementById('mapResults');
  var timer = null, lastQ = '';
  function geocode(q) {
    fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&countrycodes=my&limit=5&q=' + encodeURIComponent(q),
          { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        if (q !== lastQ) return;
        out.innerHTML = '';
        if (!rows.length) { out.innerHTML = '<div class="map-empty">No places found — click the map to pin manually.</div>'; out.hidden = false; return; }
        rows.forEach(function (r) {
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = r.display_name;
          b.addEventListener('click', function () {
            setPin(parseFloat(r.lat), parseFloat(r.lon), true);
            out.hidden = true; box.value = r.display_name.split(',')[0];
            statusEl.textContent = 'Found it — drag the pin if it is slightly off.';
          });
          out.appendChild(b);
        });
        out.hidden = false;
      })
      .catch(function () { out.hidden = true; });
  }
  box.addEventListener('input', function () {
    clearTimeout(timer);
    var q = box.value.trim();
    if (q.length < 3) { out.hidden = true; return; }
    timer = setTimeout(function () { lastQ = q; geocode(q); }, 450);
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.map-search')) out.hidden = true;
  });
  /* convenience: prefill the search with title/area once if empty */
  var title = document.querySelector('input[name=title]');
  box.addEventListener('focus', function () {
    if (box.value === '' && title && title.value.trim() !== '') box.value = title.value.trim();
  });
})();
</script>
<script>
/* ---- address suggestions: pick one to fill address + area/city + map pin ---- */
(function () {
  var input = document.getElementById('addrInput');
  var out = document.getElementById('addrResults');
  if (!input || !out) return;
  var areaIn = document.querySelector('input[name=area]');
  var cityIn = document.querySelector('input[name=city]');
  var timer = null, lastQ = '';

  function shortAddress(r) {
    // first 3 comma-parts read like a street address; full name is too long
    return r.display_name.split(',').slice(0, 3).map(function (x) { return x.trim(); }).join(', ');
  }
  function pick(r) {
    input.value = shortAddress(r);
    var a = r.address || {};
    var area = a.suburb || a.neighbourhood || a.village || a.quarter || a.city_district || '';
    var city = a.city || a.town || a.state || '';
    if (areaIn && areaIn.value.trim() === '' && area) areaIn.value = area;
    if (cityIn && (cityIn.value.trim() === '' || cityIn.value === 'Kuala Lumpur') && city) cityIn.value = city;
    if (window.ownerSetPin) window.ownerSetPin(parseFloat(r.lat), parseFloat(r.lon), true);
    out.hidden = true;
  }
  function suggest(q) {
    fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&countrycodes=my&addressdetails=1&limit=5&q=' + encodeURIComponent(q),
          { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        if (q !== lastQ) return;
        out.innerHTML = '';
        if (!rows.length) { out.hidden = true; return; }
        rows.forEach(function (r) {
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = r.display_name;
          b.addEventListener('click', function () { pick(r); });
          out.appendChild(b);
        });
        out.hidden = false;
      })
      .catch(function () { out.hidden = true; });
  }
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 4) { out.hidden = true; return; }
    timer = setTimeout(function () { lastQ = q; suggest(q); }, 450);
  });
  input.addEventListener('keydown', function (e) { if (e.key === 'Escape') out.hidden = true; });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.addr-search')) out.hidden = true;
  });
})();
</script>
<script>
/* live move-in calculator + hide rental terms for sale listings */
(function () {
  var price = document.querySelector('input[name=price]');
  var type  = document.querySelector('select[name=listing_type]');
  var dep = document.getElementById('t-dep'), util = document.getElementById('t-util'), fee = document.getElementById('t-fee');
  var terms = document.getElementById('rentTerms');
  if (!price || !terms) return;
  var rm = function (n) { return 'RM ' + Math.round(n).toLocaleString(); };
  function calc() {
    var p = parseFloat(price.value) || 0;
    var d = parseFloat(dep.value) || 0, ut = parseFloat(util.value) || 0, f = parseFloat(fee.value) || 0;
    document.getElementById('c-rent').textContent = rm(p);
    document.getElementById('c-depm').textContent = d;
    document.getElementById('c-dep').textContent = rm(p * d);
    document.getElementById('c-utilm').textContent = ut;
    document.getElementById('c-util').textContent = rm(p * ut);
    document.getElementById('c-fee').textContent = rm(f);
    document.getElementById('c-total').textContent = rm(p + p * d + p * ut + f);
  }
  function toggleTerms() { terms.style.display = (type && type.value === 'sale') ? 'none' : ''; }
  [price, dep, util, fee].forEach(function (el) { el.addEventListener('input', calc); el.addEventListener('change', calc); });
  if (type) type.addEventListener('change', toggleTerms);
  calc(); toggleTerms();
})();
</script>
<?php page_bottom();
