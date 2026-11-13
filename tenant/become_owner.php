<?php
require __DIR__ . '/../includes/init.php';
$u = require_role('tenant');
$op = owner_profile($u['id']);
$err = [];
if (is_post() && (!$op || $op['status'] === 'declined')) {
    try {
        $name = post('display_name'); $phone = post('phone');
        if (mb_strlen($name) < 2) $err[] = 'Enter the name that tenants will see.';
        $id = save_upload('national_id', true); $proof = save_upload('proof', true); $photo = save_upload('photo');
        if (!$id) $err[] = 'Upload a clear photo of your National ID.';
        if (!$proof) $err[] = 'Upload proof of ownership (title, tax declaration, or lease with permission to rent out beds).';
        if (!$err) {
            if ($op) q('DELETE FROM owner_profiles WHERE id=?', [$op['id']]);
            q("INSERT INTO owner_profiles(user_id,display_name,phone,photo,national_id_image,ownership_proof_image,status) VALUES(?,?,?,?,?,?, 'pending')", [$u['id'], $name, $phone, $photo, $id, $proof]);
            foreach (all("SELECT id FROM users WHERE role='admin'") as $a) notify($a['id'], $u['name'] . ' applied as an owner.', 'admin/owners.php');
            flash('success', 'Application sent. An admin will review it and you will be notified.');
            redirect('tenant/become_owner.php');
        }
    } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); }
}
$op = owner_profile($u['id']);
page_start('Become an owner', 'tenant');
?>
<div class="mx-auto" style="max-width:720px">
<div class="page-head"><h1>Owner profile</h1></div>
<?php if ($op && $op['status'] === 'approved'): ?>
  <div class="bh-card text-center"><div class="empty"><i class="bi bi-patch-check"></i><b>Your owner profile is approved.</b><br>You can now add boarding houses and rent beds.</div><a class="btn btn-primary" href="<?= url('owner/dashboard.php') ?>">Open owner dashboard</a></div>
<?php elseif ($op && $op['status'] === 'pending'): ?>
  <div class="bh-card"><?= badge('pending') ?><h2 class="mt-2">Application under review</h2><p class="text-muted mb-0">Submitted <?= fdate($op['created_at']) ?>. An admin is checking your National ID and proof of ownership. You will get a notification and an email when it is decided. Until then, the owner dashboard stays locked.</p></div>
<?php else: ?>
  <?php if ($op && $op['status'] === 'declined'): ?><div class="alert alert-danger"><b>Your last application was declined.</b> <?= e($op['admin_note']) ?> You can fix this and apply again below.</div><?php endif; ?>
  <?php foreach ($err as $m): ?><div class="alert alert-danger py-2"><?= e($m) ?></div><?php endforeach; ?>
  <form class="bh-card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <p class="text-muted">Apply to rent out beds with your tenant account. Your documents are only visible to the admin who reviews them.</p>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="dn">Name shown to tenants</label><input class="form-control" id="dn" name="display_name" required value="<?= e($_POST['display_name'] ?? $u['name']) ?>"></div>
      <div class="col-md-6"><label class="form-label" for="ph">Contact number</label><input class="form-control" id="ph" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" data-mobile placeholder="09XXXXXXXXX" title="11 digits, starting with 09" value="<?= e($_POST['phone'] ?? $u['phone']) ?>"></div>
      <div class="col-md-6"><label class="form-label" for="nid">National ID (photo)</label><input class="form-control" id="nid" type="file" name="national_id" accept="image/jpeg,image/png,image/webp" required><div class="form-text">Must be clear and legible. JPG, PNG or WEBP under 4 MB.</div></div>
      <div class="col-md-6"><label class="form-label" for="pr">Proof of ownership (photo)</label><input class="form-control" id="pr" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required><div class="form-text">Land title, tax declaration, or lease that allows renting out beds.</div></div>
      <div class="col-12"><label class="form-label" for="ph2">Owner photo (optional)</label><input class="form-control" id="ph2" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><div class="form-text">If you skip it, the BoardingHub logo is used.</div></div>
    </div>
    <button class="btn btn-primary mt-3" data-confirm="Send your owner application? Make sure your ID and proof of ownership are readable." data-confirm-btn="Send application">Send application</button>
  </form>
<?php endif; ?>
</div>
<?php page_end(); ?>
