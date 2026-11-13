<?php
require __DIR__ . '/../includes/init.php';
$u = require_owner(); $op = owner_profile($u['id']);
$err = [];
if (is_post()) {
    try {
        $photo = save_upload('photo'); $name = post('display_name');
        if (mb_strlen($name) < 2) $err[] = 'Enter the name tenants will see.';
        if (!$err) { q('UPDATE owner_profiles SET display_name=?, phone=?, photo=COALESCE(?,photo) WHERE id=?', [$name, post('phone'), $photo, $op['id']]); flash('success', 'Owner profile saved.'); redirect('owner/profile.php'); }
    } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); }
}
page_start('Owner profile', 'owner');
?>
<div class="mx-auto" style="max-width:640px"><div class="page-head"><h1>Owner profile</h1></div>
<?php foreach ($err as $m): ?><div class="alert alert-danger py-2"><?= e($m) ?></div><?php endforeach; ?>
<form class="bh-card" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
  <div class="d-flex align-items-center gap-3 mb-3"><img class="avatar" style="width:84px;height:84px" src="<?= img($op['photo']) ?>" alt="Your photo"><div><?= badge($op['status']) ?><div class="small text-muted mt-1">Approved <?= fdate($op['reviewed_at']) ?></div></div></div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="dn">Name shown to tenants</label><input class="form-control" id="dn" name="display_name" required value="<?= e($op['display_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="ph">Contact number</label><input class="form-control" id="ph" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" data-mobile placeholder="09XXXXXXXXX" title="11 digits, starting with 09" value="<?= e($op['phone']) ?>"></div>
    <div class="col-12"><label class="form-label" for="pf">Photo</label><input class="form-control" id="pf" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><div class="form-text">The BoardingHub logo is used if you have no photo.</div></div>
  </div>
  <button class="btn btn-primary mt-3" data-confirm="Save your owner profile?" data-confirm-btn="Save">Save profile</button>
</form></div>
<?php page_end(); ?>
