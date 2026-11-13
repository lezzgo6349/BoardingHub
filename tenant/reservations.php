<?php
require __DIR__ . '/../includes/init.php'; require __DIR__ . '/../includes/queries.php';
$u = require_role('tenant');

if (is_post()) {
    $rid = (int)post('rid');
    $r = one('SELECT r.*, h.owner_id, h.name house_name FROM reservations r JOIN boarding_houses h ON h.id=r.house_id WHERE r.id=? AND r.tenant_id=?', [$rid, $u['id']]);
    if ($r && post('action') === 'cancel' && in_array($r['status'], ['pending', 'accepted'])) {
        if (paid_until($rid)) { flash('warning', 'This reservation is already paid. Request a refund instead of cancelling.'); }
        else {
            q("UPDATE reservations SET status='cancelled', ended_at=NOW() WHERE id=?", [$rid]); release_bed($r['bed_id']);
            q("UPDATE payments SET status='failed' WHERE reservation_id=? AND status='pending'", [$rid]);
            notify($r['owner_id'], $u['name'] . ' cancelled the reservation at ' . $r['house_name'] . '.', 'owner/reservations.php');
            flash('success', 'Reservation cancelled. The bed is available again.');
        }
    }
    if ($r && post('action') === 'refund') {
        $p = one("SELECT * FROM payments WHERE id=? AND reservation_id=? AND status='paid'", [(int)post('pid'), $rid]);
        if ($p && today() < $p['period_start']) {
            q("UPDATE payments SET status='refund_requested' WHERE id=?", [$p['id']]);
            notify($r['owner_id'], $u['name'] . ' requested a refund of ' . money($p['amount']) . ' (' . $p['receipt_code'] . ').', 'owner/reservations.php');
            flash('success', 'Refund requested. The owner will review it.');
        } else flash('warning', 'Refunds can only be requested before your move-in date.');
    }
    redirect('tenant/reservations.php');
}
$res = tenant_reservations($u['id']);
$pay = [];
foreach (all("SELECT p.* FROM payments p WHERE p.tenant_id=? AND p.status<>'pending' AND p.status<>'failed' ORDER BY p.id DESC", [$u['id']]) as $p) $pay[$p['reservation_id']][] = $p;
page_start('My reservations', 'tenant', 'tenant/reservations.php');
?>
<div class="page-head"><h1>My reservations</h1><a class="btn btn-primary" href="<?= url('tenant/browse.php') ?>">Reserve another bed</a></div>
<?php if (!$res): ?><div class="bh-card empty"><i class="bi bi-calendar2-week"></i>You have not reserved a bed yet.</div><?php endif; ?>
<div class="vstack gap-3">
<?php foreach ($res as $r): $ps = $pay[$r['id']] ?? []; $paidP = array_values(array_filter($ps, fn($p) => $p['status'] === 'paid')); ?>
  <div class="bh-card">
    <div class="d-flex flex-wrap justify-content-between gap-2">
      <div><h2 class="mb-0"><a class="text-reset text-decoration-none" href="<?= url('tenant/house.php?id=' . $r['house_id']) ?>"><?= e($r['house_name']) ?></a></h2>
        <div class="text-muted small"><?= e($r['address']) ?></div></div>
      <div><?= badge($r['status']) ?></div>
    </div>
    <div class="row g-3 mt-1">
      <div class="col-sm-4"><div class="small text-muted">Room and bed</div><b><?= e($r['room_name'] . ' · ' . $r['bed_label']) ?></b></div>
      <div class="col-sm-4"><div class="small text-muted">Move-in date</div><b><?= fdate($r['move_in_date']) ?></b></div>
      <div class="col-sm-4"><div class="small text-muted"><?= $r['paid_until'] ? 'Next payment date' : 'Price' ?></div><b><?= $r['paid_until'] ? fdate($r['paid_until']) : money($r['price']) . ' / ' . (int)$r['stay_days'] . ' days' ?></b></div>
    </div>
    <?php if ($r['status'] === 'pending'): ?><div class="alert alert-warning py-2 mt-3 mb-0">Waiting for the owner to accept or decline. The bed is held for you.</div><?php endif; ?>
    <?php if ($r['status'] === 'declined'): ?><div class="alert alert-light border py-2 mt-3 mb-0">The owner declined this reservation. The bed is available again.</div><?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
      <?php if ($r['status'] === 'accepted'): ?>
        <a class="btn btn-primary" href="<?= url('tenant/checkout.php?rid=' . $r['id']) ?>"><i class="bi bi-credit-card"></i> <?= $r['paid_until'] ? 'Pay next ' . (int)$r['stay_days'] . ' days' : 'Pay ' . money($r['price']) . ' now' ?></a>
      <?php endif; ?>
      <?php if (in_array($r['status'], ['pending', 'accepted']) && !$r['paid_until']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="rid" value="<?= $r['id'] ?>">
          <button class="btn btn-outline-danger" data-confirm="Cancel this reservation? The bed will be released for other tenants." data-confirm-btn="Cancel reservation" data-confirm-tone="danger">Cancel reservation</button></form>
      <?php endif; ?>
      <a class="btn btn-light" href="<?= url('tenant/messages.php?new=owner&house=' . $r['house_id']) ?>"><i class="bi bi-chat-dots"></i> Message owner</a>
    </div>
    <?php if ($ps): ?>
      <div class="mt-3 pt-3 border-top"><div class="fw-semibold mb-2">Payments</div>
      <?php foreach ($ps as $p): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-1">
          <div><?= money($p['amount']) ?> · <?= (int)$p['days'] ?> days <span class="text-muted small">(<?= fdate($p['period_start']) ?> to <?= fdate($p['period_end']) ?>)</span> <?= badge($p['status']) ?></div>
          <div class="d-flex gap-2">
            <a class="btn btn-soft btn-sm" href="<?= url('receipt.php?t=' . $p['receipt_token']) ?>"><i class="bi bi-receipt"></i> Receipt & contract</a>
            <?php if ($p['status'] === 'paid' && today() < $p['period_start']): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="refund"><input type="hidden" name="rid" value="<?= $r['id'] ?>"><input type="hidden" name="pid" value="<?= $p['id'] ?>">
                <button class="btn btn-outline-danger btn-sm" data-confirm="Request a refund of <?= money($p['amount']) ?>? If the owner approves, your reservation is cancelled." data-confirm-btn="Request refund" data-confirm-tone="danger">Request refund</button></form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php page_end(); ?>
