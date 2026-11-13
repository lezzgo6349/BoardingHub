<?php
require __DIR__ . '/../includes/init.php';
$u = require_role('admin');
if (is_post()) {
    $op = one("SELECT op.*, u.email, u.name FROM owner_profiles op JOIN users u ON u.id=op.user_id WHERE op.id=? AND op.status='pending'", [(int)post('id')]);
    if ($op) {
        if (post('action') === 'approve') {
            q("UPDATE owner_profiles SET status='approved', reviewed_at=NOW(), reviewed_by=?, admin_note=NULL WHERE id=?", [$u['id'], $op['id']]);
            notify($op['user_id'], 'Your owner profile was approved. Open the owner dashboard to add your boarding house.', 'owner/dashboard.php');
            send_mail($op['email'], 'Your BoardingHub owner profile is approved', '<p>Hi ' . e($op['name']) . ', your owner profile was approved. Sign in and open the owner dashboard to list your boarding house.</p>');
            flash('success', $op['name'] . ' is now an owner.');
        } else {
            $note = post('note') ?: 'The documents could not be verified.';
            q("UPDATE owner_profiles SET status='declined', reviewed_at=NOW(), reviewed_by=?, admin_note=? WHERE id=?", [$u['id'], $note, $op['id']]);
            notify($op['user_id'], 'Your owner application was declined: ' . $note, 'tenant/become_owner.php');
            send_mail($op['email'], 'Your BoardingHub owner application', '<p>Hi ' . e($op['name']) . ', your owner application was declined.</p><p>Reason: ' . e($note) . '</p><p>You can fix this and apply again from your dashboard.</p>');
            flash('success', 'Application declined.');
        }
    }
    redirect('admin/owners.php');
}
$rows = all("SELECT op.*, u.name, u.email, u.phone uphone FROM owner_profiles op JOIN users u ON u.id=op.user_id ORDER BY op.status='pending' DESC, op.created_at DESC");
page_start('Owner requests', 'admin', 'admin/owners.php');
?>
<div class="page-head"><h1>Owner requests</h1><input class="form-control" style="width:280px" type="search" placeholder="Search by name, email or status" data-filter-items="#ownerList" aria-label="Search owner requests"></div>
<?php if (!$rows): ?><div class="bh-card empty"><i class="bi bi-patch-check"></i>No owner applications yet.</div><?php endif; ?>
<div class="vstack gap-3" id="ownerList">
<?php foreach ($rows as $o): ?>
  <div class="bh-card filter-item">
    <div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="mb-0"><?= e($o['display_name']) ?></h2><div class="small text-muted"><?= e($o['name']) ?> · <?= e($o['email']) ?><?= ($o['phone'] ?: $o['uphone']) ? ' · ' . e($o['phone'] ?: $o['uphone']) : '' ?> · applied <?= fdate($o['created_at']) ?></div></div><div><?= badge($o['status']) ?></div></div>
    <div class="row g-3 mt-1">
      <?php foreach (['id' => 'National ID', 'proof' => 'Proof of ownership'] as $k => $label): ?>
        <div class="col-md-6"><div class="small fw-semibold mb-1"><?= $label ?></div>
          <a href="<?= url('file.php?p=' . $o['id'] . '&f=' . $k) ?>" target="_blank" rel="noopener"><img src="<?= url('file.php?p=' . $o['id'] . '&f=' . $k) ?>" alt="<?= $label ?>" class="img-fluid rounded border" style="max-height:260px;width:100%;object-fit:contain;background:#fff"></a></div>
      <?php endforeach; ?>
    </div>
    <?php if ($o['status'] === 'pending'): ?>
      <form method="post" class="row g-2 mt-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $o['id'] ?>">
        <div class="col-md-6"><label class="form-label" for="n<?= $o['id'] ?>">Reason (shown to the applicant if you decline)</label><input class="form-control" id="n<?= $o['id'] ?>" name="note" maxlength="480" placeholder="e.g. The ID photo is blurry"></div>
        <div class="col-md-6 d-flex gap-2"><button name="action" value="approve" class="btn btn-primary" data-confirm="Approve <?= e($o['name']) ?> as an owner? They will get access to the owner dashboard." data-confirm-btn="Approve owner">Approve</button>
          <button name="action" value="decline" class="btn btn-outline-danger" data-confirm="Decline this application?" data-confirm-btn="Decline" data-confirm-tone="danger">Decline</button></div>
      </form>
    <?php elseif ($o['admin_note']): ?><div class="small text-muted mt-2">Note: <?= e($o['admin_note']) ?></div><?php endif; ?>
  </div>
<?php endforeach; ?>
  <div class="bh-card empty" id="ownerListEmpty" hidden><i class="bi bi-search"></i>No owner request matches your search.</div>
</div>
<?php page_end(); ?>
