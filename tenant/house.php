<?php
require __DIR__ . '/../includes/init.php';
$u = require_role('tenant');
$hid = (int)($_GET['id'] ?? 0);
$h = one("SELECT h.*, ou.name owner_name, op.photo owner_photo, op.phone owner_phone FROM boarding_houses h JOIN users ou ON ou.id=h.owner_id AND ou.deleted_at IS NULL
          JOIN owner_profiles op ON op.user_id=h.owner_id AND op.status='approved' WHERE h.id=? AND h.is_active=1", [$hid]);
if (!$h) { flash('warning', 'That boarding house is not available.'); redirect('tenant/browse.php'); }

if (is_post() && post('action') === 'reserve') {
    $bedId = (int)post('bed_id'); $date = post('move_in');
    $max = date('Y-m-d', strtotime('+' . MAX_ADVANCE_DAYS . ' days'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < today() || $date > $max) {
        flash('error', 'Pick a move-in date between today and ' . fdate($max) . '. Online reservations are limited to ' . MAX_ADVANCE_DAYS . ' days ahead.');
        redirect('tenant/house.php?id=' . $hid);
    }
    $pdo = db(); $pdo->beginTransaction();
    $bed = one('SELECT b.*, r.house_id, r.name room_name FROM beds b JOIN rooms r ON r.id=b.room_id WHERE b.id=? FOR UPDATE', [$bedId]);
    if (!$bed || (int)$bed['house_id'] !== $hid || $bed['status'] !== 'available') {
        $pdo->rollBack(); flash('error', 'Sorry, that bed was just taken. Please pick another one.'); redirect('tenant/house.php?id=' . $hid);
    }
    q("INSERT INTO reservations(source,tenant_id,guest_name,guest_email,guest_phone,house_id,room_id,bed_id,move_in_date,status) VALUES('online',?,?,?,?,?,?,?,?, 'pending')",
      [$u['id'], $u['name'], $u['email'], $u['phone'], $hid, $bed['room_id'], $bedId, $date]);
    q("UPDATE beds SET status='reserved' WHERE id=?", [$bedId]);
    $pdo->commit();
    notify($h['owner_id'], $u['name'] . ' reserved ' . $bed['room_name'] . ' · ' . $bed['label'] . ' at ' . $h['name'] . ' for ' . fdate($date) . '.', 'owner/reservations.php');
    flash('success', 'Bed reserved. The owner will accept or decline your reservation. You will be notified.');
    redirect('tenant/reservations.php');
}

$rooms = all('SELECT * FROM rooms WHERE house_id=? ORDER BY price', [$hid]);
$beds = [];
foreach (all('SELECT b.* FROM beds b JOIN rooms r ON r.id=b.room_id WHERE r.house_id=? ORDER BY b.id', [$hid]) as $b) $beds[$b['room_id']][] = $b;
$maxDate = date('Y-m-d', strtotime('+' . MAX_ADVANCE_DAYS . ' days'));
page_start($h['name'], 'tenant', 'tenant/browse.php');
?>
<a class="small d-inline-block mb-2" href="<?= url('tenant/browse.php') ?>"><i class="bi bi-arrow-left"></i> All boarding houses</a>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="house-hero mb-3"><img src="<?= img($h['photo']) ?>" class="<?= $h['photo'] ? '' : 'is-logo' ?>" alt="<?= e($h['name']) ?>"></div>
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
      <div><h1 class="h2 mb-1"><?= e($h['name']) ?></h1><div class="text-muted"><i class="bi bi-geo-alt"></i> <?= e($h['address']) ?></div></div>
      <span class="bh-badge bh-info"><?= e($h['category']) ?></span>
    </div>
    <?php if ($h['description']): ?><p class="mt-3 pre"><?= e($h['description']) ?></p><?php endif; ?>

    <h2 class="h4 mt-4">Rooms and beds</h2>
    <div class="bed-legend mb-3"><span><?= bed_icon('available') ?> Available</span><span><?= bed_icon('reserved') ?> Reserved</span><span><?= bed_icon('occupied') ?> Occupied</span></div>
    <?php if (!$rooms): ?><div class="bh-card empty">The owner has not added rooms yet.</div><?php endif; ?>
    <div class="vstack gap-3">
    <?php foreach ($rooms as $rm): $rb = $beds[$rm['id']] ?? []; $free = array_filter($rb, fn($b) => $b['status'] === 'available'); ?>
      <div class="room-card">
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <div><h3 class="h5 mb-0"><?= e($rm['name']) ?></h3><div class="small text-muted"><?= e($rm['description']) ?></div></div>
          <div class="text-end"><div class="price"><?= money($rm['price']) ?></div><div class="small text-muted">per bed · <?= (int)$rm['stay_days'] ?> days</div></div>
        </div>
        <div class="d-flex flex-wrap gap-3 my-3"><?php foreach ($rb as $b): ?><span class="small text-center"><?= bed_icon($b['status'], $b['label'] . ': ' . $b['status']) ?><br><?= e($b['label']) ?></span><?php endforeach; ?></div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="small text-muted"><?= count($rb) ?> bed<?= count($rb) === 1 ? '' : 's' ?> in this room · <?= count($free) ?> free</span>
          <?php if ($free): ?><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reserve<?= $rm['id'] ?>">Reserve a bed</button><?php else: ?><button class="btn btn-light btn-sm" disabled>Full</button><?php endif; ?>
        </div>
      </div>
      <?php if ($free): ?>
      <div class="modal fade" data-bs-focus="false" id="reserve<?= $rm['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="reserve">
        <div class="modal-header"><h2 class="modal-title h5">Reserve in <?= e($rm['name']) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <label class="form-label">Choose a bed</label>
          <div class="bed-pick mb-3"><?php foreach ($free as $i => $b): ?><span><input type="radio" name="bed_id" id="b<?= $b['id'] ?>" value="<?= $b['id'] ?>" required <?= $i === array_key_first($free) ? 'checked' : '' ?>><label for="b<?= $b['id'] ?>"><?= bed_icon('available') ?> <?= e($b['label']) ?></label></span><?php endforeach; ?></div>
          <label class="form-label" for="d<?= $rm['id'] ?>">Move-in date</label>
          <input class="form-control" id="d<?= $rm['id'] ?>" type="date" name="move_in" min="<?= today() ?>" max="<?= $maxDate ?>" value="<?= today() ?>" required>
          <div class="form-text">Online reservations are limited to <?= MAX_ADVANCE_DAYS ?> days ahead (until <?= fdate($maxDate) ?>).</div>
          <div class="alert alert-info mt-3 mb-0 py-2 small">You pay <b><?= money($rm['price']) ?></b> for <?= (int)$rm['stay_days'] ?> days after the owner accepts your reservation.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" data-confirm="Reserve this bed at <?= e($h['name']) ?>? The owner will be asked to accept it." data-confirm-btn="Reserve bed">Reserve bed</button></div>
      </form></div></div>
      <?php endif; ?>
    <?php endforeach; ?>
    </div>

    <h2 class="h4 mt-4">House rules</h2>
    <div class="bh-card"><p class="mb-0 pre"><?= e($h['rules'] ?: 'The owner has not listed house rules.') ?></p></div>
    <h2 class="h4 mt-4">Contract</h2>
    <div class="bh-card"><p class="mb-0 pre"><?= e($h['contract'] ?: 'No separate contract. The house rules apply.') ?></p></div>
  </div>
  <div class="col-lg-4">
    <div class="bh-card mb-3">
      <div class="d-flex align-items-center gap-3 mb-3"><img class="avatar" src="<?= img($h['owner_photo']) ?>" alt=""><div><div class="small text-muted">Owner</div><b><?= e($h['owner_name']) ?></b></div></div>
      <?php if ($h['contact_phone'] || $h['owner_phone']): ?><div class="mb-1"><i class="bi bi-telephone text-primary"></i> <?= e($h['contact_phone'] ?: $h['owner_phone']) ?></div><?php endif; ?>
      <?php if ($h['contact_email']): ?><div class="mb-3"><i class="bi bi-envelope text-primary"></i> <?= e($h['contact_email']) ?></div><?php endif; ?>
      <a class="btn btn-outline-primary w-100" href="<?= url('tenant/messages.php?new=owner&house=' . $h['id']) ?>"><i class="bi bi-chat-dots"></i> Message the owner</a>
    </div>
    <div class="bh-card"><h2>Location</h2><div id="houseMap" class="map-box"></div>
      <a class="small d-inline-block mt-2" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= $h['latitude'] ?>&mlon=<?= $h['longitude'] ?>#map=17/<?= $h['latitude'] ?>/<?= $h['longitude'] ?>">Open larger map</a></div>
  </div>
</div>
<?php page_end('<script>BH.viewMap("houseMap",' . (float)$h['latitude'] . ',' . (float)$h['longitude'] . ',' . json_encode($h['name']) . ');</script>'); ?>
