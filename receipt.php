<?php
// Receipt + rules/contract. Opened from the email link (secret token) or from the dashboards.
require __DIR__ . '/includes/init.php';
$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
$pid = (int)val('SELECT id FROM payments WHERE receipt_token=?', [$t]);
$p = $pid ? payment_full($pid) : null;
if (!$p || !in_array($p['status'], ['paid', 'refund_requested', 'refunded'])) { http_response_code(404); page_start('Receipt not found'); echo '<div class="container py-5"><div class="empty"><i class="bi bi-receipt"></i>This receipt link is not valid.</div></div>'; page_end(); exit; }
$u = user();
$area = $u ? ($u['role'] === 'admin' ? 'admin' : ((int)$u['id'] === (int)$p['owner_id'] ? 'owner' : 'tenant')) : 'public';
page_start('Receipt ' . $p['receipt_code'], $area === 'public' ? 'receipt' : $area);
?>
<div class="container py-4">
  <div class="no-print d-flex flex-wrap gap-2 justify-content-between mb-3" style="max-width:800px;margin:0 auto">
    <?php if ($u): ?><a class="btn btn-light" href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Back</a><?php else: ?><span></span><?php endif; ?>
    <div class="d-flex gap-2">
      <button class="btn btn-soft" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <button class="btn btn-primary" id="downloadPdf" data-name="<?= e($p['receipt_code']) ?>.pdf"><i class="bi bi-download"></i> Download PDF</button>
    </div>
  </div>
  <article class="sheet" id="receiptSheet">
    <div class="brandline"><img src="<?= url('assets/img/logo.png') ?>" width="46" height="46" alt=""><div><h1 class="m-0">BoardingHub</h1><div class="text-muted small">Official payment receipt</div></div><div class="ms-auto text-end"><div class="fw-semibold"><?= e($p['receipt_code']) ?></div><div class="small text-muted"><?= fdate($p['paid_at']) ?></div></div></div>
    <?php if ($p['status'] === 'refunded'): ?><p><?= badge('refunded') ?> This payment was refunded on <?= fdate($p['refunded_at']) ?>.</p><?php endif; ?>
    <table class="w-100">
      <tr><td class="text-muted" style="width:38%">Received from</td><td><b><?= e($p['guest_name']) ?></b><br><span class="small text-muted"><?= e($p['guest_email']) ?></span></td></tr>
      <tr><td class="text-muted">Boarding house</td><td><b><?= e($p['house_name']) ?></b><br><span class="small text-muted"><?= e($p['address']) ?></span></td></tr>
      <tr><td class="text-muted">Owner</td><td><?= e($p['owner_name']) ?><?= $p['contact_phone'] ? ' · ' . e($p['contact_phone']) : '' ?></td></tr>
      <tr><td class="text-muted">Room and bed</td><td><?= e($p['room_name'] . ' · ' . $p['bed_label']) ?></td></tr>
      <tr><td class="text-muted">Move-in date</td><td><?= fdate($p['move_in_date']) ?></td></tr>
      <tr><td class="text-muted">Days of stay paid</td><td><?= (int)$p['days'] ?> days (<?= fdate($p['period_start']) ?> to <?= fdate($p['period_end']) ?>)</td></tr>
      <tr><td class="text-muted">Payment method</td><td><?= e(method_label($p['method'])) ?></td></tr>
      <tr><td class="text-muted">Amount paid</td><td class="fs-4 fw-semibold"><?= money($p['amount']) ?></td></tr>
      <tr><td class="text-muted">Next payment date</td><td><b><?= fdate($p['period_end']) ?></b></td></tr>
    </table>
    <hr class="my-4">
    <h2 class="h5">House rules</h2>
    <p class="pre"><?= e($p['rules'] ?: 'The owner has not listed house rules.') ?></p>
    <h2 class="h5 mt-4">Contract</h2>
    <p class="pre"><?= e($p['contract'] ?: 'The owner has not added a contract. The house rules above apply.') ?></p>
    <div class="d-flex flex-wrap gap-5">
      <div class="sign">Tenant: <?= e($p['guest_name']) ?></div>
      <div class="sign">Owner: <?= e($p['owner_name']) ?></div>
    </div>
  </article>
</div>
<?php page_end('<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>'); ?>
