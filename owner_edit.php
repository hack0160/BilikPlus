<?php
require __DIR__ . '/config.php';
$u = require_role('owner');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$l = [
    'title' => '', 'area' => '', 'city' => 'Kuala Lumpur', 'address' => '', 'price' => '',
    'room_type' => 'Medium Room', 'property_type' => 'Condominium', 'furnishing' => 'Fully furnished',
    'gender_pref' => 'Any', 'amenities' => '', 'description' => '', 'image' => '', 'status' => 'active',
];
if ($id) {
    $st = db()->prepare("SELECT * FROM listings WHERE id = ? AND owner_id = ?");
    $st->execute([$id, $u['id']]);
    $l = $st->fetch();
    if (!$l) { flash('Listing not found.', 'warn'); redirect('owner_listings.php'); }
}

$roomTypes = ['Single Room', 'Medium Room', 'Master Room', 'Studio', 'Whole Unit'];
$propTypes = ['Condominium', 'Apartment', 'Serviced Residence', 'Flat', 'Terrace House', 'Double Storey House'];
$furnish   = ['Fully furnished', 'Partially furnished', 'Unfurnished'];
$genders   = ['Any', 'Male', 'Female'];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['title','area','city','address','amenities','description'] as $f) $l[$f] = trim($_POST[$f] ?? '');
    $l['price']         = (int) ($_POST['price'] ?? 0);
    $l['room_type']     = in_array($_POST['room_type'] ?? '', $roomTypes, true) ? $_POST['room_type'] : 'Medium Room';
    $l['property_type'] = in_array($_POST['property_type'] ?? '', $propTypes, true) ? $_POST['property_type'] : 'Condominium';
    $l['furnishing']    = in_array($_POST['furnishing'] ?? '', $furnish, true) ? $_POST['furnishing'] : 'Fully furnished';
    $l['gender_pref']   = in_array($_POST['gender_pref'] ?? '', $genders, true) ? $_POST['gender_pref'] : 'Any';
    if ($l['status'] !== 'suspended') {
        $l['status'] = ($_POST['status'] ?? '') === 'hidden' ? 'hidden' : 'active';
    }

    if ($l['title'] === '' || $l['area'] === '' || $l['city'] === '') {
        $err = 'Title, area and city are required.';
    } elseif ($l['price'] < 50 || $l['price'] > 50000) {
        $err = 'Monthly rent must be between RM 50 and RM 50,000.';
    } else {
        try {
            $newImg = handle_image_upload('photo');
            if ($newImg) {
                delete_listing_image($l['image']);   // free the old stored photo
                $l['image'] = $newImg;
            }
            if ($id) {
                db()->prepare("UPDATE listings SET title=?,area=?,city=?,address=?,price=?,room_type=?,property_type=?,
                               furnishing=?,gender_pref=?,amenities=?,description=?,image=?,status=?
                               WHERE id=? AND owner_id=?")
                   ->execute([$l['title'],$l['area'],$l['city'],$l['address'],$l['price'],$l['room_type'],$l['property_type'],
                              $l['furnishing'],$l['gender_pref'],$l['amenities'],$l['description'],$l['image'],$l['status'],$id,$u['id']]);
                flash('Listing updated.');
            } else {
                db()->prepare("INSERT INTO listings (owner_id,title,area,city,address,price,room_type,property_type,
                               furnishing,gender_pref,amenities,description,image,status)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$u['id'],$l['title'],$l['area'],$l['city'],$l['address'],$l['price'],$l['room_type'],$l['property_type'],
                              $l['furnishing'],$l['gender_pref'],$l['amenities'],$l['description'],$l['image'] ?: 'assets/img/seed1.svg',$l['status']]);
                flash('Listing published. Tenants can swipe on it now.');
            }
            redirect('owner_listings.php');
        } catch (RuntimeException $ex) {
            $err = $ex->getMessage();
        }
    }
}

page_top($id ? 'Edit listing' : 'New listing', $u);
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

    <label>Address <span class="opt">(optional, shown on detail page)</span>
      <input type="text" name="address" value="<?= e($l['address']) ?>">
    </label>

    <div class="form-grid">
      <label>Monthly rent (RM)
        <input type="number" name="price" required min="50" max="50000" value="<?= e((string)$l['price']) ?>">
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

    <label>Amenities <span class="opt">(comma-separated)</span>
      <input type="text" name="amenities" placeholder="Wi-Fi, Aircond, Pool, Near MRT" value="<?= e($l['amenities']) ?>">
    </label>

    <label>Description
      <textarea name="description" rows="5" placeholder="Deposit terms, housemates, nearby transport, anything tenants should know."><?= e($l['description']) ?></textarea>
    </label>

    <label>Photo <span class="opt">(JPG, PNG or WebP, max 3 MB<?= $l['image'] ? ' — leave empty to keep current photo' : '' ?>)</span>
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
    </label>
    <?php if ($l['image']): ?>
      <img class="form-preview" src="<?= listing_image($l) ?>" alt="Current listing photo">
    <?php endif; ?>

    <div class="detail-actions">
      <button class="btn btn-primary"><?= $id ? 'Save changes' : 'Publish listing' ?></button>
      <a class="btn btn-ghost" href="owner_listings.php">Cancel</a>
    </div>
  </form>
</div>
<?php page_bottom();
