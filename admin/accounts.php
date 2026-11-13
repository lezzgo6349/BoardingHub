<?php
require __DIR__ . '/../includes/init.php';
$u = require_role('admin');

if (is_post()) {
    $t = one('SELECT * FROM users WHERE id=?', [(int)post('id')]);
    if ($t && post('action') === 'delete') {
        if ((int)$t['id'] === (int)$u['id']) flash('warning', 'You cannot delete your own account.');
        elseif (!$t['deleted_at']) {
            q('UPDATE users SET deleted_at=NOW() WHERE id=?', [$t['id']]);
            flash('success', $t['name'] . ' was deleted. You can restore the account from the Deleted tab.');
        }
    } elseif ($t && post('action') === 'restore' && $t['deleted_at']) {
        q('UPDATE users SET deleted_at=NULL WHERE id=?', [$t['id']]);
        flash('success', $t['name'] . ' was restored.');
    }
    redirect('admin/accounts.php' . (post('view') === 'deleted' ? '?view=deleted' : ''));
}

$view = ($_GET['view'] ?? '') === 'deleted' ? 'deleted' : 'active';
$nDeleted = (int)val('SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL');
$rows = all('SELECT u.*, op.status owner_status, (SELECT COUNT(*) FROM boarding_houses WHERE owner_id=u.id) houses FROM users u LEFT JOIN owner_profiles op ON op.user_id=u.id WHERE u.deleted_at IS ' . ($view === 'deleted' ? 'NOT NULL' : 'NULL') . ' ORDER BY u.created_at DESC');
page_start('Accounts', 'admin', 'admin/accounts.php');
?>
<div class="page-head"><div><h1>Accounts</h1><div class="text-muted"><?= count($rows) ?> <?= $view === 'deleted' ? 'deleted' : 'active' ?> account<?= count($rows) == 1 ? '' : 's' ?></div></div><input class="form-control" style="width:260px" placeholder="Search accounts" data-filter="#acc" aria-label="Search accounts"></div>
<ul class="nav nav-pills mb-3 gap-1">
  <li class="nav-item"><a class="nav-link <?= $view === 'active' ? 'active' : '' ?>" href="<?= url('admin/accounts.php') ?>">Active</a></li>
  <li class="nav-item"><a class="nav-link <?= $view === 'deleted' ? 'active' : '' ?>" href="<?= url('admin/accounts.php?view=deleted') ?>">Deleted<?= $nDeleted ? ' (' . $nDeleted . ')' : '' ?></a></li>
</ul>
<div class="bh-card flush"><div class="table-responsive"><table class="table align-middle mb-0" id="acc"><thead><tr><th class="ps-3">Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Role</th><th><?= $view === 'deleted' ? 'Deleted' : 'Joined' ?></th><th class="text-end pe-3">Action</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= $view === 'deleted' ? 'No deleted accounts.' : 'No accounts yet.' ?></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td class="ps-3"><b><?= e($r['name']) ?></b></td><td><?= e($r['email']) ?></td><td><?= e($r['phone'] ?: '—') ?></td><td><?= e($r['address'] ?: '—') ?></td>
  <td><?php if ($r['role'] === 'admin') echo '<span class="bh-badge bh-info">Admin</span>'; else { echo '<span class="bh-badge bh-mute">Tenant</span>'; if ($r['owner_status']) echo ' ' . ($r['owner_status'] === 'approved' ? '<span class="bh-badge bh-ok">Owner · ' . (int)$r['houses'] . ' house' . ($r['houses'] == 1 ? '' : 's') . '</span>' : badge($r['owner_status']) . ' <span class="small text-muted">owner</span>'); } ?></td>
  <td><?= fdate($view === 'deleted' ? $r['deleted_at'] : $r['created_at']) ?></td>
  <td class="text-end pe-3">
    <?php if ($view === 'deleted'): ?>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="view" value="deleted">
        <button name="action" value="restore" class="btn btn-soft btn-sm" data-confirm="Restore <?= e($r['name']) ?>? They will be able to sign in again." data-confirm-btn="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button></form>
    <?php elseif ((int)$r['id'] !== (int)$u['id']): ?>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button name="action" value="delete" class="btn btn-outline-danger btn-sm" data-confirm="Delete <?= e($r['name']) ?>? They will not be able to sign in and their boarding houses are hidden. You can restore the account later." data-confirm-btn="Delete account" data-confirm-tone="danger"><i class="bi bi-trash"></i> Delete</button></form>
    <?php else: ?><span class="small text-muted">You</span><?php endif; ?>
  </td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php page_end(); ?>
