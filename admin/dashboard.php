<?php
require __DIR__ . '/../includes/init.php';
$u = require_role('admin');
$c = fn($sql) => (int)val($sql);
$stats = [
  ['Tenant accounts', $c("SELECT COUNT(*) FROM users WHERE role='tenant'")],
  ['Approved owners', $c("SELECT COUNT(*) FROM owner_profiles WHERE status='approved'")],
  ['Boarding houses', $c('SELECT COUNT(*) FROM boarding_houses')],
  ['Open reports and inquiries', $c("SELECT COUNT(DISTINCT t.id) FROM threads t JOIN messages m ON m.thread_id=t.id WHERE t.target='admin' AND m.sender_role='tenant' AND m.is_read=0")],
];
$pending = $c("SELECT COUNT(*) FROM owner_profiles WHERE status='pending'");
$latest = all("SELECT op.*, u.name, u.email FROM owner_profiles op JOIN users u ON u.id=op.user_id WHERE op.status='pending' ORDER BY op.created_at LIMIT 5");
page_start('Admin overview', 'admin', 'admin/dashboard.php');
?>
<div class="page-head"><div><h1>Admin overview</h1><div class="text-muted">Signed in as <?= e($u['name']) ?></div></div></div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat accent"><div class="l">Owner requests waiting</div><div class="n"><?= $pending ?></div></div></div>
  <?php foreach ($stats as [$l, $n]): ?><div class="col-6 col-lg-3"><div class="stat"><div class="n"><?= $n ?></div><div class="l"><?= e($l) ?></div></div></div><?php endforeach; ?>
</div>
<div class="bh-card"><div class="d-flex justify-content-between"><h2>Owner applications to review</h2><a href="<?= url('admin/owners.php') ?>">Open all</a></div>
<?php if (!$latest): ?><div class="empty"><i class="bi bi-patch-check"></i>No applications are waiting.</div><?php endif; ?>
<?php foreach ($latest as $o): ?><div class="d-flex justify-content-between border-top py-2"><div><b><?= e($o['display_name']) ?></b><div class="small text-muted"><?= e($o['email']) ?></div></div><span class="text-muted small align-self-center"><?= fdate($o['created_at']) ?></span></div><?php endforeach; ?></div>
<?php page_end(); ?>
