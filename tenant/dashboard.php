<?php
require __DIR__ . '/../includes/init.php'; require __DIR__ . '/../includes/queries.php';
$u = require_role('tenant');
$res = tenant_reservations($u['id']);
$active = array_values(array_filter($res, fn($r) => in_array($r['status'], ['pending', 'accepted'])));
$due = array_values(array_filter($active, fn($r) => $r['status'] === 'accepted'));
usort($due, fn($a, $b) => strcmp($a['paid_until'] ?: $a['move_in_date'], $b['paid_until'] ?: $b['move_in_date']));
$op = owner_profile($u['id']);
page_start('Dashboard', 'tenant', 'tenant/dashboard.php');
?>
<div class="page-head">
  <div><h1>Hi, <?= e(explode(' ', $u['name'])[0]) ?></h1><div class="text-muted">Here is where your reservations and payments stand.</div></div>
  <a class="btn btn-primary" href="<?= url('tenant/browse.php') ?>"><i class="bi bi-search"></i> Browse boarding houses</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3"><div class="stat"><div class="n"><?= count($active) ?></div><div class="l">Active reservations</div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="stat"><div class="n"><?= count(array_filter($res, fn($r) => $r['status'] === 'pending')) ?></div><div class="l">Waiting for owner reply</div></div></div>
  <div class="col-sm-6 col-lg-6"><div class="stat accent"><div class="l">Next payment</div>
    <?php if ($due): $d = $due[0]; ?><div class="n"><?= fdate($d['paid_until'] ?: $d['move_in_date']) ?></div><div class="l"><?= e($d['house_name']) ?> · <?= e($d['room_name']) ?><?= $d['paid_until'] ? '' : ' · first payment due' ?></div>
    <?php else: ?><div class="n">—</div><div class="l">Nothing due right now</div><?php endif; ?></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="bh-card">
      <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="m-0">Your reservations</h2><a href="<?= url('tenant/reservations.php') ?>">See all</a></div>
      <?php if (!$res): ?><div class="empty"><i class="bi bi-calendar2-week"></i>You have no reservations yet. Browse boarding houses and reserve a bed.</div><?php endif; ?>
      <?php foreach (array_slice($res, 0, 4) as $r): ?>
        <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-top">
          <div><b><?= e($r['house_name']) ?></b><div class="small text-muted"><?= e($r['room_name'] . ' · ' . $r['bed_label']) ?> · move in <?= fdate($r['move_in_date']) ?></div></div>
          <?= badge($r['status']) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="bh-card h-100">
      <h2>Own a boarding house?</h2>
      <?php if (!$op): ?>
        <p class="text-muted">Apply as an owner using this account. You will upload your National ID and proof of ownership for an admin to review.</p>
        <a class="btn btn-outline-primary" href="<?= url('tenant/become_owner.php') ?>">Apply as owner</a>
      <?php elseif ($op['status'] === 'pending'): ?>
        <p class="text-muted">Your owner application is with the admin. You will be notified once it is reviewed.</p><?= badge('pending') ?>
      <?php elseif ($op['status'] === 'approved'): ?>
        <p class="text-muted">Your owner profile is approved.</p><a class="btn btn-primary" href="<?= url('owner/dashboard.php') ?>">Open owner dashboard</a>
      <?php else: ?>
        <p class="text-muted">Your last application was declined<?= $op['admin_note'] ? ': ' . e($op['admin_note']) : '.' ?></p><a class="btn btn-outline-primary" href="<?= url('tenant/become_owner.php') ?>">Apply again</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_end(); ?>
