<?php
require __DIR__ . '/../includes/init.php';
$u = require_owner(); $id = $u['id'];
$live = "status IN ('paid','refund_requested')";
$total = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE owner_id=? AND $live", [$id]);
$month = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE owner_id=? AND $live AND DATE_FORMAT(paid_at,'%Y-%m')=?", [$id, date('Y-m')]);
$refunded = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE owner_id=? AND status='refunded'", [$id]);
$pendingRefund = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE owner_id=? AND status='refund_requested'", [$id]);
$online = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE owner_id=? AND $live AND method<>'cash'", [$id]);
$cash = $total - $online;
$bars = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("first day of -$i month"));
    $bars[] = ['label' => date('M', strtotime($m . '-01')), 'v' => (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE owner_id=? AND $live AND DATE_FORMAT(paid_at,'%Y-%m')=?", [$id, $m])];
}
$maxBar = max(1, max(array_column($bars, 'v')));
$byHouse = all("SELECT h.name, COALESCE(SUM(CASE WHEN p.status IN ('paid','refund_requested') THEN p.amount END),0) total, COUNT(p.id) n FROM boarding_houses h LEFT JOIN payments p ON p.house_id=h.id AND p.status<>'pending' AND p.status<>'failed' WHERE h.owner_id=? GROUP BY h.id ORDER BY total DESC", [$id]);
$fm = $_GET['month'] ?? ''; $where = ''; $par = [$id];
if (preg_match('/^\d{4}-\d{2}$/', $fm)) { $where = " AND DATE_FORMAT(p.paid_at,'%Y-%m')=?"; $par[] = $fm; }
$pays = all("SELECT p.*, r.guest_name, h.name house_name, rm.name room_name FROM payments p JOIN reservations r ON r.id=p.reservation_id JOIN boarding_houses h ON h.id=p.house_id JOIN rooms rm ON rm.id=r.room_id
             WHERE p.owner_id=? AND p.status IN ('paid','refund_requested','refunded') $where ORDER BY p.paid_at DESC LIMIT 100", $par);
page_start('Finance', 'owner', 'owner/finance.php');
?>
<div class="page-head"><div><h1>Finance</h1><div class="text-muted">Money collected from tenants.</div></div></div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat accent"><div class="l">This month</div><div class="n"><?= money($month) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="l">All time</div><div class="n"><?= money($total) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="l">Online / cash</div><div class="n fs-5"><?= money($online) ?> <span class="text-muted">/</span> <?= money($cash) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="l">Refunded<?= $pendingRefund ? ' (+' . money($pendingRefund) . ' requested)' : '' ?></div><div class="n"><?= money($refunded) ?></div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-7"><div class="bh-card h-100"><h2>Last 6 months</h2>
    <div class="bars mt-3" role="img" aria-label="Monthly collections"><?php foreach ($bars as $b): ?><div class="bar"><span><?= $b['v'] ? '₱' . number_format($b['v']) : '' ?></span><i style="height:<?= max(2, round($b['v'] / $maxBar * 100)) ?>%"></i><span><?= $b['label'] ?></span></div><?php endforeach; ?></div></div></div>
  <div class="col-lg-5"><div class="bh-card h-100"><h2>By boarding house</h2>
    <?php foreach ($byHouse as $h): ?><div class="d-flex justify-content-between border-top py-2"><span><?= e($h['name']) ?><span class="small text-muted"> · <?= (int)$h['n'] ?> payment<?= $h['n'] == 1 ? '' : 's' ?></span></span><b><?= money($h['total']) ?></b></div><?php endforeach; ?>
    <?php if (!$byHouse): ?><div class="text-muted mt-2">No boarding houses yet.</div><?php endif; ?></div></div>
</div>
<div class="bh-card flush"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 pb-0"><h2 class="m-0">Payments</h2>
  <form class="d-flex gap-2" method="get"><input class="form-control" type="month" name="month" value="<?= e($fm) ?>" aria-label="Month"><button class="btn btn-soft">Filter</button><?php if ($fm): ?><a class="btn btn-light" href="<?= url('owner/finance.php') ?>">Clear</a><?php endif; ?></form></div>
  <div class="table-responsive"><table class="table align-middle mb-0 mt-2"><?php if ($pays): ?><thead><tr><th class="ps-3">Paid</th><th>Tenant</th><th>Room</th><th>Covers</th><th>Method</th><th>Amount</th><th></th></tr></thead><?php endif; ?><tbody>
  <?php foreach ($pays as $p): ?><tr><td class="ps-3"><?= fdate($p['paid_at']) ?></td><td><?= e($p['guest_name']) ?></td><td><?= e($p['house_name'] . ' · ' . $p['room_name']) ?></td><td><?= (int)$p['days'] ?> days to <?= fdate($p['period_end']) ?></td><td><?= e(method_label($p['method'])) ?></td>
    <td><b><?= money($p['amount']) ?></b> <?= $p['status'] === 'paid' ? '' : badge($p['status']) ?></td><td class="pe-3"><a href="<?= url('receipt.php?t=' . $p['receipt_token']) ?>">Receipt</a></td></tr><?php endforeach; ?>
  <?php if (!$pays): ?><tr><td class="empty" colspan="7"><i class="bi bi-wallet2"></i>No payments for this period.</td></tr><?php endif; ?></tbody></table></div></div>
<?php page_end(); ?>
