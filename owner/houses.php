<?php
require __DIR__ . '/../includes/init.php'; require __DIR__ . '/../includes/queries.php';
$u = require_owner();
$houses = all("SELECT h.*, (SELECT COUNT(*) FROM rooms WHERE house_id=h.id) rooms FROM boarding_houses h WHERE h.owner_id=? ORDER BY h.created_at DESC", [$u['id']]);
page_start('My boarding houses', 'owner', 'owner/houses.php');
?>
<div class="page-head"><h1>My boarding houses</h1><a class="btn btn-primary" href="<?= url('owner/house_edit.php') ?>"><i class="bi bi-plus-lg"></i> Add boarding house</a></div>
<?php if (!$houses): ?><div class="bh-card empty"><i class="bi bi-house-add"></i>No boarding houses yet. Add one with its location, rooms and prices.</div><?php endif; ?>
<div class="row g-3">
<?php foreach ($houses as $h): $s = house_beds_summary($h['id']); ?>
  <div class="col-md-6 col-xl-4"><div class="house-card">
    <div class="ph"><img src="<?= img($h['photo']) ?>" class="<?= $h['photo'] ? '' : 'is-logo' ?>" alt=""></div>
    <div class="body">
      <div class="d-flex justify-content-between gap-2"><h3><?= e($h['name']) ?></h3><?= $h['is_active'] ? badge('approved') : '<span class="bh-badge bh-mute">Hidden</span>' ?></div>
      <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($h['address']) ?></div>
      <div class="small"><?= e($h['category']) ?> · <?= (int)$h['rooms'] ?> room<?= $h['rooms'] == 1 ? '' : 's' ?> · <?= (int)$s['free'] ?> of <?= (int)$s['total'] ?> beds free</div>
      <div class="mt-auto pt-2 d-flex gap-2"><a class="btn btn-primary btn-sm" href="<?= url('owner/house_edit.php?id=' . $h['id']) ?>">Edit house and rooms</a>
        <a class="btn btn-light btn-sm" href="<?= url('tenant/house.php?id=' . $h['id']) ?>">Preview</a></div>
    </div></div></div>
<?php endforeach; ?>
</div>
<?php page_end(); ?>
