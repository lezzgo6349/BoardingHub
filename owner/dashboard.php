<?php
require __DIR__ . '/../includes/init.php';
$u = require_owner();
$id = $u['id'];
$beds = one("SELECT COUNT(*) total, COALESCE(SUM(b.status='occupied'),0) occ, COALESCE(SUM(b.status='reserved'),0) res, COALESCE(SUM(b.status='available'),0) free
             FROM beds b JOIN rooms r ON r.id=b.room_id JOIN boarding_houses h ON h.id=r.house_id WHERE h.owner_id=?", [$id]);
$pending = (int)val("SELECT COUNT(*) FROM reservations r JOIN boarding_houses h ON h.id=r.house_id WHERE h.owner_id=? AND r.status='pending'", [$id]);
$refunds = (int)val("SELECT COUNT(*) FROM payments WHERE owner_id=? AND status='refund_requested'", [$id]);
$month = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE owner_id=? AND status IN ('paid','refund_requested') AND DATE_FORMAT(paid_at,'%Y-%m')=?", [$id, date('Y-m')]);
$overdue = all("SELECT r.guest_name, r.id, rm.name room_name, b.label, h.name house_name,
   (SELECT MAX(period_end) FROM payments p WHERE p.reservation_id=r.id AND p.status IN ('paid','refund_requested')) paid_until, r.move_in_date
   FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id
   WHERE h.owner_id=? AND r.status='accepted' HAVING (paid_until IS NOT NULL AND paid_until<=?) ORDER BY paid_until LIMIT 6", [$id, date('Y-m-d', strtotime('+3 days'))]);
$houses = (int)val('SELECT COUNT(*) FROM boarding_houses WHERE owner_id=?', [$id]);
$unreadMsgs = (int)val("SELECT COUNT(*) FROM messages m JOIN threads t ON t.id=m.thread_id WHERE t.owner_id=? AND t.target='owner' AND m.sender_role='tenant' AND m.is_read=0", [$id]);
page_start('Owner overview', 'owner', 'owner/dashboard.php');
?>
<div class="page-head"><div><h1>Owner overview</h1><div class="text-muted">Beds, requests and money at a glance.</div></div>
  <div class="d-flex gap-2"><a class="btn btn-soft" href="<?= url('owner/rent.php') ?>"><i class="bi bi-person-plus"></i> Rent a tenant</a><a class="btn btn-primary" href="<?= url('owner/house_edit.php') ?>"><i class="bi bi-plus-lg"></i> Add boarding house</a></div></div>
<?php if (!$houses): ?><div class="alert alert-info">You have no boarding house yet. <a href="<?= url('owner/house_edit.php') ?>">Create your first listing</a> so tenants can find you.</div><?php endif; ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat accent"><div class="l">Collected this month</div><div class="n"><?= money($month) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="n"><?= (int)$beds['occ'] ?><span class="fs-6 text-muted"> / <?= (int)$beds['total'] ?></span></div><div class="l">Beds occupied</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="n"><?= $pending ?></div><div class="l">Reservations to answer</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="n"><?= $unreadMsgs ?></div><div class="l">Unread messages</div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="bh-card h-100"><h2>Bed status</h2>
    <div class="d-flex gap-4 flex-wrap my-3">
      <div><?= bed_icon('available') ?> <b><?= (int)$beds['free'] ?></b> available</div>
      <div><?= bed_icon('reserved') ?> <b><?= (int)$beds['res'] ?></b> reserved</div>
      <div><?= bed_icon('occupied') ?> <b><?= (int)$beds['occ'] ?></b> occupied</div></div>
    <?php if ($pending || $refunds): ?><a class="btn btn-primary" href="<?= url('owner/reservations.php') ?>">Review <?= $pending ?> reservation<?= $pending == 1 ? '' : 's' ?><?= $refunds ? ' and ' . $refunds . ' refund request' . ($refunds == 1 ? '' : 's') : '' ?></a>
    <?php else: ?><span class="text-muted">No requests are waiting.</span><?php endif; ?></div></div>
  <div class="col-lg-6"><div class="bh-card h-100"><h2>Payments due soon or overdue</h2>
    <?php if (!$overdue): ?><div class="text-muted mt-2">Everyone is paid up for the next 3 days.</div><?php endif; ?>
    <?php foreach ($overdue as $o): ?><div class="d-flex justify-content-between border-top py-2"><div><b><?= e($o['guest_name']) ?></b><div class="small text-muted"><?= e($o['house_name'] . ' · ' . $o['room_name'] . ' · ' . $o['label']) ?></div></div>
      <div class="text-end"><?= $o['paid_until'] < today() ? '<span class="bh-badge bh-bad">Overdue</span>' : '<span class="bh-badge bh-warn">Due soon</span>' ?><div class="small"><?= fdate($o['paid_until']) ?></div></div></div><?php endforeach; ?>
    <?php if ($overdue): ?><a class="d-inline-block mt-2" href="<?= url('owner/tenants.php') ?>">Manage tenants</a><?php endif; ?></div></div>
</div>
<?php page_end(); ?>
