<?php
require __DIR__ . '/includes/init.php';
$u = user(); if (!$u || $u['role'] === 'admin') redirect('index.php');
$rows = all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 60', [$u['id']]);
q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]);
$area = (isset($_GET['from']) && $_GET['from'] === 'owner' && is_approved_owner($u['id'])) ? 'owner' : 'tenant';
page_start('Notifications', $area);
?>
<div class="page-head"><h1>Notifications</h1></div>
<div class="bh-card flush">
<?php if (!$rows): ?><div class="empty"><i class="bi bi-bell"></i>Nothing new yet. Updates about your reservations and payments show up here.</div><?php endif; ?>
<?php foreach ($rows as $n): ?>
  <div class="d-flex justify-content-between gap-3 p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-white' ?>" style="<?= $n['is_read'] ? '' : 'border-left:4px solid var(--bh-lamp)' ?>">
    <div><?= e($n['text']) ?><?php if ($n['link']): ?> <a href="<?= url($n['link']) ?>">Open</a><?php endif; ?></div>
    <small class="text-muted text-nowrap"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></small>
  </div>
<?php endforeach; ?>
</div>
<?php page_end(); ?>
