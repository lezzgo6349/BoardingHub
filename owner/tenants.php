<?php
require __DIR__ . '/../includes/init.php'; require __DIR__ . '/../includes/queries.php';
$u = require_owner();
if (is_post()) {
    $r = one("SELECT r.*, h.name house_name, rm.price, rm.stay_days FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id WHERE r.id=? AND h.owner_id=? AND r.status='accepted'", [(int)post('rid'), $u['id']]);
    if ($r) {
        $act = post('action');
        if ($act === 'pay') {
            $amount = (float)post('amount'); $days = (int)post('days');
            if ($amount > 0 && $days > 0) { create_payment($r['id'], $amount, $days, 'cash', 'paid'); flash('success', 'Payment recorded. Receipt sent to ' . $r['guest_email'] . '.'); }
            else flash('error', 'Enter the amount and days of stay.');
        } elseif ($act === 'end') {
            q("UPDATE reservations SET status='ended', ended_at=NOW() WHERE id=?", [$r['id']]); release_bed($r['bed_id']);
            if ($r['tenant_id']) notify($r['tenant_id'], 'Your stay at ' . $r['house_name'] . ' has ended.', 'tenant/reservations.php');
            flash('success', 'Stay ended. The bed is available again.');
        } elseif ($act === 'resend') {
            $pid = val("SELECT id FROM payments WHERE reservation_id=? AND status='paid' ORDER BY id DESC LIMIT 1", [$r['id']]);
            if ($pid) { send_receipt_email($pid); flash('success', 'Receipt sent again to ' . $r['guest_email'] . '.'); } else flash('warning', 'No paid receipt to send yet.');
        }
    }
    redirect('owner/tenants.php');
}
$rows = all("SELECT r.*, h.name house_name, rm.name room_name, rm.price, rm.stay_days, b.label bed_label,
   (SELECT MAX(period_end) FROM payments p WHERE p.reservation_id=r.id AND p.status IN ('paid','refund_requested')) paid_until,
   (SELECT receipt_token FROM payments p WHERE p.reservation_id=r.id AND p.status='paid' ORDER BY p.id DESC LIMIT 1) token
   FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id
   WHERE h.owner_id=? AND r.status='accepted' ORDER BY h.name, rm.name, b.id", [$u['id']]);
page_start('Tenants', 'owner', 'owner/tenants.php');
?>
<div class="page-head"><div><h1>Tenants</h1><div class="text-muted"><?= count($rows) ?> current tenant<?= count($rows) == 1 ? '' : 's' ?></div></div>
  <div class="d-flex gap-2"><input class="form-control" style="width:220px" placeholder="Search tenants" data-filter="#tt" aria-label="Search tenants"><a class="btn btn-primary text-nowrap" href="<?= url('owner/rent.php') ?>"><i class="bi bi-person-plus"></i> Rent a tenant</a></div></div>
<div class="bh-card flush"><div class="table-responsive"><table class="table align-middle mb-0" id="tt">
<?php if ($rows): ?><thead><tr><th class="ps-3">Tenant</th><th>Room</th><th>Moved in</th><th>Paid until</th><th></th></tr></thead><?php endif; ?><tbody>
<?php foreach ($rows as $r): $due = $r['paid_until'] ? $r['paid_until'] : null; $state = !$due ? 'unpaid' : ($due < today() ? 'overdue' : ($due <= date('Y-m-d', strtotime('+3 days')) ? 'soon' : 'ok')); ?>
  <tr><td class="ps-3"><b><?= e($r['guest_name']) ?></b><div class="small text-muted"><?= e($r['guest_email']) ?><?= $r['guest_phone'] ? ' · ' . e($r['guest_phone']) : '' ?></div></td>
    <td><?= e($r['house_name']) ?><div class="small text-muted"><?= e($r['room_name'] . ' · ' . $r['bed_label']) ?></div></td>
    <td><?= fdate($r['move_in_date']) ?><div class="small text-muted"><?= $r['source'] === 'owner' ? 'Added by you' : 'Online' ?></div></td>
    <td><?= $due ? fdate($due) : '—' ?> <?= ['unpaid' => '<span class="bh-badge bh-warn">Not paid yet</span>', 'overdue' => '<span class="bh-badge bh-bad">Overdue</span>', 'soon' => '<span class="bh-badge bh-warn">Due soon</span>', 'ok' => ''][$state] ?></td>
    <td class="text-end text-nowrap pe-3">
      <button class="btn btn-soft btn-sm" data-bs-toggle="modal" data-bs-target="#payModal" data-rid="<?= $r['id'] ?>" data-name="<?= e($r['guest_name']) ?>" data-price="<?= $r['price'] ?>" data-days="<?= $r['stay_days'] ?>">Record payment</button>
      <div class="dropdown d-inline"><button class="btn btn-light btn-sm" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php if ($r['token']): ?><li><a class="dropdown-item" href="<?= url('receipt.php?t=' . $r['token']) ?>">View latest receipt</a></li><?php endif; ?>
          <li><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="rid" value="<?= $r['id'] ?>"><button class="dropdown-item" data-confirm="Email the latest receipt to <?= e($r['guest_email']) ?> again?" data-confirm-btn="Send">Email receipt again</button></form></li>
          <li><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="end"><input type="hidden" name="rid" value="<?= $r['id'] ?>"><button class="dropdown-item text-danger" data-confirm="End <?= e($r['guest_name']) ?>'s stay? The bed becomes available." data-confirm-btn="End stay" data-confirm-tone="danger">End stay</button></form></li>
        </ul></div></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td class="empty"><i class="bi bi-people"></i>No tenants yet. Accepted reservations and tenants you rent to appear here.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="modal fade" data-bs-focus="false" id="payModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="rid" id="pm_rid">
  <div class="modal-header"><h2 class="modal-title h5" id="pm_title">Record payment</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body row g-3"><div class="col-6"><label class="form-label" for="pm_amount">Amount paid (₱)</label><input class="form-control" id="pm_amount" type="number" step="0.01" min="1" name="amount" required></div>
    <div class="col-6"><label class="form-label" for="pm_days">Days of stay</label><input class="form-control" id="pm_days" type="number" min="1" name="days" required></div>
    <div class="col-12 small text-muted">The new period starts when the last one ends. A receipt with the next payment date is emailed to the tenant.</div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-confirm="Record this payment and email the receipt?" data-confirm-btn="Record payment">Record payment</button></div>
</form></div></div>
<?php page_end('<script>document.getElementById("payModal").addEventListener("show.bs.modal",e=>{const d=e.relatedTarget.dataset;pm_rid.value=d.rid;pm_title.textContent="Payment from "+d.name;pm_amount.value=d.price;pm_days.value=d.days;});</script>'); ?>
