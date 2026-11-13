<?php
require __DIR__ . '/../includes/init.php'; require __DIR__ . '/../includes/queries.php';
$u = require_owner();

if (is_post()) {
    $act = post('action');
    if (in_array($act, ['accept', 'decline'])) {
        $r = one("SELECT r.*, h.name house_name FROM reservations r JOIN boarding_houses h ON h.id=r.house_id WHERE r.id=? AND h.owner_id=? AND r.status='pending'", [(int)post('rid'), $u['id']]);
        if ($r) {
            if ($act === 'accept') {
                q("UPDATE reservations SET status='accepted', decided_at=NOW() WHERE id=?", [$r['id']]);
                q("UPDATE beds SET status='occupied' WHERE id=?", [$r['bed_id']]);
                if ($r['tenant_id']) notify($r['tenant_id'], 'Your reservation at ' . $r['house_name'] . ' was accepted. You can pay now.', 'tenant/reservations.php');
                send_mail($r['guest_email'], 'Your reservation was accepted', '<p>Hi ' . e($r['guest_name']) . ', <b>' . e($r['house_name']) . '</b> accepted your reservation for ' . fdate($r['move_in_date']) . '. Sign in to BoardingHub to pay and get your receipt.</p>');
                flash('success', 'Reservation accepted. The bed is now marked occupied.');
            } else {
                q("UPDATE reservations SET status='declined', decided_at=NOW() WHERE id=?", [$r['id']]);
                release_bed($r['bed_id']);
                if ($r['tenant_id']) notify($r['tenant_id'], 'Your reservation at ' . $r['house_name'] . ' was declined.', 'tenant/reservations.php');
                send_mail($r['guest_email'], 'Your reservation was declined', '<p>Hi ' . e($r['guest_name']) . ', unfortunately <b>' . e($r['house_name']) . '</b> declined your reservation. You can browse other boarding houses on BoardingHub.</p>');
                flash('success', 'Reservation declined. The bed is available again.');
            }
        }
    }
    if (in_array($act, ['refund_ok', 'refund_no'])) {
        $p = one("SELECT p.*, r.bed_id, r.id rid, r.tenant_id rt FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE p.id=? AND p.owner_id=? AND p.status='refund_requested'", [(int)post('pid'), $u['id']]);
        if ($p) {
            if ($act === 'refund_ok') {
                if (paymongo_enabled() && $p['provider_payment_id']) paymongo('POST', '/refunds', ['data' => ['attributes' => ['amount' => (int)round($p['amount'] * 100), 'payment_id' => $p['provider_payment_id'], 'reason' => 'requested_by_customer']]]);
                q("UPDATE payments SET status='refunded', refunded_at=NOW() WHERE id=?", [$p['id']]);
                q("UPDATE reservations SET status='cancelled', ended_at=NOW() WHERE id=?", [$p['rid']]);
                release_bed($p['bed_id']);
                if ($p['rt']) notify($p['rt'], 'Your refund of ' . money($p['amount']) . ' was approved.', 'tenant/reservations.php');
                flash('success', 'Refund approved. The reservation is cancelled and the bed is available again.' . (paymongo_enabled() && $p['provider_payment_id'] ? '' : ' Send the money back manually if it was paid in cash or demo mode.'));
            } else {
                q("UPDATE payments SET status='paid' WHERE id=?", [$p['id']]);
                if ($p['rt']) notify($p['rt'], 'Your refund request of ' . money($p['amount']) . ' was declined.', 'tenant/reservations.php');
                flash('success', 'Refund declined.');
            }
        }
    }
    redirect('owner/reservations.php');
}
$pending = all("SELECT r.*, h.name house_name, rm.name room_name, rm.price, b.label bed_label FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id
                WHERE h.owner_id=? AND r.status='pending' ORDER BY r.created_at", [$u['id']]);
$refunds = all("SELECT p.*, r.guest_name, h.name house_name, rm.name room_name, b.label bed_label FROM payments p JOIN reservations r ON r.id=p.reservation_id JOIN boarding_houses h ON h.id=p.house_id JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id
                WHERE p.owner_id=? AND p.status='refund_requested'", [$u['id']]);
$recent = all("SELECT r.*, h.name house_name, rm.name room_name, b.label bed_label FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id
               WHERE h.owner_id=? AND r.source='online' AND r.status<>'pending' ORDER BY r.created_at DESC LIMIT 15", [$u['id']]);
page_start('Reservations', 'owner', 'owner/reservations.php');
?>
<div class="page-head"><h1>Reservations</h1></div>
<h2 class="h5">Waiting for your answer</h2>
<div class="bh-card flush mb-4">
<?php if (!$pending): ?><div class="empty"><i class="bi bi-inbox"></i>No reservations are waiting.</div><?php endif; ?>
<?php foreach ($pending as $r): ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border-bottom">
    <div><b><?= e($r['guest_name']) ?></b> <span class="text-muted small"><?= e($r['guest_email']) ?><?= $r['guest_phone'] ? ' · ' . e($r['guest_phone']) : '' ?></span>
      <div class="small"><?= e($r['house_name'] . ' · ' . $r['room_name'] . ' · ' . $r['bed_label']) ?> · <?= money($r['price']) ?> · moves in <b><?= fdate($r['move_in_date']) ?></b></div></div>
    <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="rid" value="<?= $r['id'] ?>">
      <button name="action" value="accept" class="btn btn-primary btn-sm" data-confirm="Accept <?= e($r['guest_name']) ?>? The bed will be marked occupied and they can pay." data-confirm-btn="Accept">Accept</button>
      <button name="action" value="decline" class="btn btn-outline-danger btn-sm" data-confirm="Decline this reservation? The bed becomes available again." data-confirm-btn="Decline" data-confirm-tone="danger">Decline</button></form>
  </div>
<?php endforeach; ?></div>

<?php if ($refunds): ?>
<h2 class="h5">Refund requests</h2>
<div class="bh-card flush mb-4"><?php foreach ($refunds as $p): ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border-bottom">
    <div><b><?= e($p['guest_name']) ?></b> asked for <b><?= money($p['amount']) ?></b> back<div class="small text-muted"><?= e($p['house_name'] . ' · ' . $p['room_name'] . ' · ' . $p['bed_label']) ?> · receipt <?= e($p['receipt_code']) ?></div></div>
    <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= $p['id'] ?>">
      <button name="action" value="refund_ok" class="btn btn-primary btn-sm" data-confirm="Approve the refund of <?= money($p['amount']) ?>? The reservation will be cancelled." data-confirm-btn="Approve refund">Approve</button>
      <button name="action" value="refund_no" class="btn btn-outline-danger btn-sm" data-confirm="Decline this refund request?" data-confirm-btn="Decline" data-confirm-tone="danger">Decline</button></form>
  </div><?php endforeach; ?></div>
<?php endif; ?>

<h2 class="h5">Recent decisions</h2>
<div class="bh-card"><div class="table-responsive"><table class="table align-middle mb-0">
  <?php if ($recent): ?><thead><tr><th>Tenant</th><th>Room</th><th>Move-in</th><th>Status</th></tr></thead><?php endif; ?><tbody>
  <?php foreach ($recent as $r): ?><tr><td><?= e($r['guest_name']) ?></td><td><?= e($r['house_name'] . ' · ' . $r['room_name'] . ' · ' . $r['bed_label']) ?></td><td><?= fdate($r['move_in_date']) ?></td><td><?= badge($r['status']) ?></td></tr><?php endforeach; ?>
  <?php if (!$recent): ?><tr><td class="text-muted">Nothing yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php page_end(); ?>
