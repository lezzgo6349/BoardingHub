<?php
require __DIR__ . '/../includes/init.php';
$u = require_owner();
$hid = (int)($_GET['id'] ?? 0);
$h = $hid ? one('SELECT * FROM boarding_houses WHERE id=? AND owner_id=?', [$hid, $u['id']]) : null;
if ($hid && !$h) redirect('owner/houses.php');
$err = [];

function next_bed_no($roomId) { return (int)val("SELECT COALESCE(MAX(CAST(SUBSTRING(label,5) AS UNSIGNED)),0) FROM beds WHERE room_id=?", [$roomId]); }
function add_beds($roomId, $n) { $s = next_bed_no($roomId); for ($i = 1; $i <= $n; $i++) q('INSERT INTO beds(room_id,label) VALUES(?,?)', [$roomId, 'Bed ' . ($s + $i)]); }

if (is_post()) {
    $act = post('action');
    try {
        if ($act === 'save_house') {
            $f = ['name' => post('name'), 'address' => post('address'), 'lat' => post('latitude'), 'lng' => post('longitude'), 'cat' => post('category'),
                  'desc' => post('description'), 'rules' => post('rules'), 'contract' => post('contract'), 'phone' => post('contact_phone'), 'email' => post('contact_email')];
            if (mb_strlen($f['name']) < 2) $err[] = 'Enter the boarding house name.';
            if ($f['address'] === '') $err[] = 'Enter the address.';
            if (!is_numeric($f['lat']) || !is_numeric($f['lng'])) $err[] = 'Set the location: search for the address or click the map to drop the pin.';
            if (!in_array($f['cat'], HOUSE_CATEGORIES)) $err[] = 'Choose a category.';
            if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'The contact email is not valid.';
            $photo = save_upload('photo');
            if (!$err) {
                if ($h) {
                    q('UPDATE boarding_houses SET name=?,address=?,latitude=?,longitude=?,category=?,description=?,rules=?,contract=?,contact_phone=?,contact_email=?,photo=COALESCE(?,photo),is_active=? WHERE id=?',
                      [$f['name'], $f['address'], $f['lat'], $f['lng'], $f['cat'], $f['desc'], $f['rules'], $f['contract'], $f['phone'], $f['email'], $photo, isset($_POST['is_active']) ? 1 : 0, $hid]);
                    flash('success', 'Boarding house updated.'); redirect('owner/house_edit.php?id=' . $hid);
                }
                q('INSERT INTO boarding_houses(owner_id,name,address,latitude,longitude,category,description,rules,contract,contact_phone,contact_email,photo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                  [$u['id'], $f['name'], $f['address'], $f['lat'], $f['lng'], $f['cat'], $f['desc'], $f['rules'], $f['contract'], $f['phone'], $f['email'], $photo]);
                flash('success', 'Boarding house created. Now add its rooms and beds.'); redirect('owner/house_edit.php?id=' . db()->lastInsertId());
            }
        }
        if ($h && $act === 'save_room') {
            $rid = (int)post('room_id'); $name = post('name'); $price = (float)post('price'); $days = (int)post('stay_days'); $n = (int)post('beds');
            if ($name === '' || $price <= 0 || $days < 1 || $n < 1 || $n > 30) { flash('error', 'Give the room a name, a price above zero, the days covered by that price, and 1 to 30 beds.'); redirect('owner/house_edit.php?id=' . $hid . '#rooms'); }
            if ($rid) {
                $room = one('SELECT * FROM rooms WHERE id=? AND house_id=?', [$rid, $hid]);
                if ($room) {
                    $cur = (int)val('SELECT COUNT(*) FROM beds WHERE room_id=?', [$rid]);
                    if ($n < $cur) {
                        $drop = $cur - $n;
                        $free = all("SELECT id FROM beds WHERE room_id=? AND status='available' ORDER BY id DESC LIMIT " . $drop, [$rid]);
                        if (count($free) < $drop) { flash('error', 'You cannot remove beds that are reserved or occupied. End or cancel those stays first.'); redirect('owner/house_edit.php?id=' . $hid . '#rooms'); }
                        foreach ($free as $b) q('DELETE FROM beds WHERE id=?', [$b['id']]);
                    } elseif ($n > $cur) add_beds($rid, $n - $cur);
                    q('UPDATE rooms SET name=?,price=?,stay_days=?,description=? WHERE id=?', [$name, $price, $days, post('description'), $rid]);
                    flash('success', 'Room updated.');
                }
            } else {
                q('INSERT INTO rooms(house_id,name,price,stay_days,description) VALUES(?,?,?,?,?)', [$hid, $name, $price, $days, post('description')]);
                add_beds((int)db()->lastInsertId(), $n);
                flash('success', 'Room added with ' . $n . ' bed' . ($n == 1 ? '' : 's') . '.');
            }
            redirect('owner/house_edit.php?id=' . $hid . '#rooms');
        }
        if ($h && $act === 'delete_room') {
            $rid = (int)post('room_id');
            $busy = val("SELECT COUNT(*) FROM reservations WHERE room_id=? AND status IN ('pending','accepted')", [$rid]);
            if ($busy) flash('error', 'This room still has active reservations or tenants.');
            else { q('DELETE FROM rooms WHERE id=? AND house_id=?', [$rid, $hid]); flash('success', 'Room deleted.'); }
            redirect('owner/house_edit.php?id=' . $hid . '#rooms');
        }
    } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); }
}

$v = fn($k, $d = '') => $_POST[$k] ?? ($h[$k] ?? $d);
$rooms = $h ? all('SELECT r.*, (SELECT COUNT(*) FROM beds WHERE room_id=r.id) beds, (SELECT COUNT(*) FROM beds WHERE room_id=r.id AND status="available") free FROM rooms r WHERE house_id=? ORDER BY id', [$hid]) : [];
page_start($h ? 'Edit ' . $h['name'] : 'New boarding house', 'owner', 'owner/houses.php');
?>
<div class="page-head"><div><a class="small" href="<?= url('owner/houses.php') ?>"><i class="bi bi-arrow-left"></i> My boarding houses</a><h1><?= $h ? e($h['name']) : 'Add a boarding house' ?></h1></div></div>
<?php foreach ($err as $m): ?><div class="alert alert-danger py-2"><?= e($m) ?></div><?php endforeach; ?>
<form class="bh-card mb-4" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="action" value="save_house">
  <h2>Boarding house details</h2>
  <div class="row g-3">
    <div class="col-md-7"><label class="form-label" for="n">Boarding house name</label><input class="form-control" id="n" name="name" required value="<?= e($v('name')) ?>"></div>
    <div class="col-md-5"><label class="form-label" for="c">Category</label><select class="form-select" id="c" name="category" required><?php foreach (HOUSE_CATEGORIES as $c): ?><option <?= $v('category') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label" for="a">Address</label><input class="form-control" id="a" name="address" required value="<?= e($v('address')) ?>"></div>
    <div class="col-12">
      <label class="form-label" for="mapq">Location on the map</label>
      <div class="input-group mb-2"><input class="form-control" id="mapq" placeholder="Search the address or a landmark"><button class="btn btn-soft" type="button" id="mapgo"><i class="bi bi-search"></i> Find</button><button class="btn btn-soft" type="button" id="maploc"><i class="bi bi-crosshair"></i> Use my location</button></div>
      <div id="pickMap" class="map-box tall"></div>
      <input type="hidden" name="latitude" id="lat" value="<?= e($v('latitude')) ?>"><input type="hidden" name="longitude" id="lng" value="<?= e($v('longitude')) ?>">
      <div class="form-text">Click the map or drag the pin to the exact spot. Tenants see this location.</div>
    </div>
    <div class="col-12"><label class="form-label" for="d">Description</label><textarea class="form-control" id="d" name="description" rows="3" placeholder="Nearby schools, transport, what is included (water, Wi-Fi)…"><?= e($v('description')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label" for="r">House rules</label><textarea class="form-control" id="r" name="rules" rows="6" placeholder="Curfew, visitors, quiet hours, cleaning schedule…"><?= e($v('rules')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label" for="ct">Contract terms</label><textarea class="form-control" id="ct" name="contract" rows="6" placeholder="Deposit, notice before leaving, penalties, payment terms…"><?= e($v('contract')) ?></textarea><div class="form-text">Rules and contract are attached to every receipt.</div></div>
    <div class="col-md-4"><label class="form-label" for="cp">Contact number</label><input class="form-control" id="cp" name="contact_phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" data-mobile placeholder="09XXXXXXXXX" title="11 digits, starting with 09" value="<?= e($v('contact_phone')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="ce">Contact email</label><input class="form-control" id="ce" type="email" name="contact_email" value="<?= e($v('contact_email')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="ph">Photo</label><input class="form-control" id="ph" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><div class="form-text">Optional. The BoardingHub logo is used if empty.</div></div>
    <?php if ($h): ?><div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="act" name="is_active" <?= $h['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Listed for tenants to find</label></div></div><?php endif; ?>
  </div>
  <button class="btn btn-primary mt-3" data-confirm="<?= $h ? 'Save changes to this boarding house?' : 'Create this boarding house listing?' ?>" data-confirm-btn="Save"><?= $h ? 'Save changes' : 'Create boarding house' ?></button>
</form>

<?php if ($h): ?>
<div id="rooms" class="bh-card">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="m-0">Rooms and beds</h2><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#roomModal" data-room='{}'><i class="bi bi-plus-lg"></i> Add room</button></div>
  <?php if (!$rooms): ?><div class="empty"><i class="bi bi-door-closed"></i>No rooms yet. Add a room with its price and number of beds.</div><?php endif; ?>
  <div class="table-responsive"><table class="table align-middle"><?php if ($rooms): ?><thead><tr><th>Room</th><th>Price per bed</th><th>Covers</th><th>Beds</th><th></th></tr></thead><?php endif; ?><tbody>
  <?php foreach ($rooms as $r): ?>
    <tr><td><b><?= e($r['name']) ?></b><div class="small text-muted"><?= e($r['description']) ?></div></td><td><?= money($r['price']) ?></td><td><?= (int)$r['stay_days'] ?> days</td><td><?= (int)$r['free'] ?> free of <?= (int)$r['beds'] ?></td>
      <td class="text-end text-nowrap"><button class="btn btn-soft btn-sm" data-bs-toggle="modal" data-bs-target="#roomModal" data-room="<?= e(json_encode($r)) ?>">Edit</button>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete_room"><input type="hidden" name="room_id" value="<?= $r['id'] ?>">
          <button class="btn btn-outline-danger btn-sm" data-confirm="Delete <?= e($r['name']) ?> and all its beds? This cannot be undone." data-confirm-btn="Delete room" data-confirm-tone="danger">Delete</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>
<div class="modal fade" data-bs-focus="false" id="roomModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="save_room"><input type="hidden" name="room_id" id="rm_id" value="0">
  <div class="modal-header"><h2 class="modal-title h5" id="rm_title">Add room</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-12"><label class="form-label" for="rm_name">Room name</label><input class="form-control" id="rm_name" name="name" required placeholder="Room 1, Girls dorm…"></div>
    <div class="col-md-4"><label class="form-label" for="rm_price">Price per bed (₱)</label><input class="form-control" id="rm_price" type="number" step="0.01" min="1" name="price" required></div>
    <div class="col-md-4"><label class="form-label" for="rm_days">Days covered</label><input class="form-control" id="rm_days" type="number" min="1" name="stay_days" value="30" required></div>
    <div class="col-md-4"><label class="form-label" for="rm_beds">Number of beds</label><input class="form-control" id="rm_beds" type="number" min="1" max="30" name="beds" value="1" required></div>
    <div class="col-12"><label class="form-label" for="rm_desc">Description</label><input class="form-control" id="rm_desc" name="description" placeholder="Aircon, own bathroom, bunk beds…"></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-confirm="Save this room?" data-confirm-btn="Save room">Save room</button></div>
</form></div></div>
<?php endif;
page_end('<script>
BH.pickMap("pickMap","lat","lng","mapq","mapgo","maploc");
const rmModal=document.getElementById("roomModal");
if(rmModal)rmModal.addEventListener("show.bs.modal",e=>{const r=JSON.parse(e.relatedTarget.dataset.room||"{}");
 document.getElementById("rm_title").textContent=r.id?"Edit room":"Add room";
 rm_id.value=r.id||0;rm_name.value=r.name||"";rm_price.value=r.price||"";rm_days.value=r.stay_days||30;rm_beds.value=r.beds||1;rm_desc.value=r.description||"";});
</script>'); ?>
