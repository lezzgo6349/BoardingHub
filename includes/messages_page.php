<?php
// Shared inbox for tenant, owner and admin. The wrapper sets $ROLE ('tenant'|'owner'|'admin') and $ACTIVE.
$ME = $ROLE === 'admin' ? require_role('admin') : ($ROLE === 'owner' ? require_owner() : require_role('tenant'));
$base = $ROLE . '/messages.php';
$where = ['tenant' => 't.tenant_id=?', 'owner' => "t.target='owner' AND t.owner_id=?", 'admin' => "t.target='admin' AND ?>0"][$ROLE];
$param = $ROLE === 'admin' ? 1 : $ME['id'];

function thread_for($where, $param, $tid) { return one("SELECT t.*, tu.name tenant_name, ou.name owner_name, h.name house_name FROM threads t JOIN users tu ON tu.id=t.tenant_id
    LEFT JOIN users ou ON ou.id=t.owner_id LEFT JOIN boarding_houses h ON h.id=t.house_id WHERE t.id=? AND $where", [$tid, $param]); }

if (is_post()) {
    if (post('action') === 'reply' && ($t = thread_for($where, $param, (int)post('tid'))) && post('body') !== '') {
        q('INSERT INTO messages(thread_id,sender_id,sender_role,body) VALUES(?,?,?,?)', [$t['id'], $ME['id'], $ROLE, post('body')]);
        q('UPDATE threads SET updated_at=NOW() WHERE id=?', [$t['id']]);
        if ($ROLE !== 'tenant') notify($t['tenant_id'], 'New reply: ' . $t['subject'], 'tenant/messages.php?t=' . $t['id']);
        elseif ($t['target'] === 'owner') notify($t['owner_id'], $ME['name'] . ' replied: ' . $t['subject'], 'owner/messages.php?t=' . $t['id']);
        redirect($base . '?t=' . $t['id']);
    }
    if (post('action') === 'new' && $ROLE === 'tenant' && post('body') !== '' && post('subject') !== '') {
        $target = post('target') === 'admin' ? 'admin' : 'owner'; $hid = (int)post('house_id'); $ownerId = null;
        if ($target === 'owner') {
            $h = one("SELECT h.* FROM boarding_houses h JOIN owner_profiles op ON op.user_id=h.owner_id AND op.status='approved' WHERE h.id=?", [$hid]);
            if (!$h) { flash('error', 'That boarding house was not found.'); redirect($base); }
            $ownerId = $h['owner_id'];
        }
        $cat = in_array(post('category'), ['inquiry', 'complaint', 'report']) ? post('category') : 'inquiry';
        q('INSERT INTO threads(tenant_id,target,owner_id,house_id,category,subject) VALUES(?,?,?,?,?,?)', [$ME['id'], $target, $ownerId, $target === 'owner' ? $hid : null, $cat, mb_substr(post('subject'), 0, 160)]);
        $tid = (int)db()->lastInsertId();
        q("INSERT INTO messages(thread_id,sender_id,sender_role,body) VALUES(?,?, 'tenant', ?)", [$tid, $ME['id'], post('body')]);
        if ($ownerId) notify($ownerId, 'New message from ' . $ME['name'] . ': ' . post('subject'), 'owner/messages.php?t=' . $tid);
        flash('success', 'Message sent.');
        redirect($base . '?t=' . $tid);
    }
    redirect($base);
}

$threads = all("SELECT t.*, tu.name tenant_name, ou.name owner_name, h.name house_name,
    (SELECT COUNT(*) FROM messages m WHERE m.thread_id=t.id AND m.sender_role<>? AND m.is_read=0) unread
    FROM threads t JOIN users tu ON tu.id=t.tenant_id LEFT JOIN users ou ON ou.id=t.owner_id LEFT JOIN boarding_houses h ON h.id=t.house_id
    WHERE $where ORDER BY t.updated_at DESC", [$ROLE, $param]);
$open = isset($_GET['t']) ? thread_for($where, $param, (int)$_GET['t']) : null;
$msgs = [];
if ($open) {
    q('UPDATE messages SET is_read=1 WHERE thread_id=? AND sender_role<>?', [$open['id'], $ROLE]);
    $msgs = all('SELECT m.*, u.name FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.thread_id=? ORDER BY m.id', [$open['id']]);
}
$new = ($ROLE === 'tenant' && isset($_GET['new'])) ? $_GET['new'] : null;
$newHouse = ($new === 'owner') ? one("SELECT h.id, h.name FROM boarding_houses h WHERE h.id=?", [(int)($_GET['house'] ?? 0)]) : null;
function thread_title($t, $ROLE) {
    if ($ROLE === 'tenant') return $t['target'] === 'admin' ? 'BoardingHub admin' : ($t['house_name'] ?: 'Owner');
    return $t['tenant_name'];
}
page_start('Messages', $ROLE, $ACTIVE);
?>
<div class="page-head"><h1><?= $ROLE === 'admin' ? 'Reports and inquiries' : 'Messages' ?></h1>
<?php if ($ROLE === 'tenant'): ?><a class="btn btn-primary" href="<?= url($base . '?new=admin') ?>"><i class="bi bi-flag"></i> Contact admin</a><?php endif; ?></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="bh-card flush thread-list">
    <?php if (!$threads): ?><div class="empty"><i class="bi bi-chat-dots"></i>No conversations yet.<?= $ROLE === 'tenant' ? ' Open a boarding house and choose “Message the owner”, or contact the admin.' : '' ?></div><?php endif; ?>
    <?php foreach ($threads as $t): ?>
      <a href="<?= url($base . '?t=' . $t['id']) ?>" class="<?= $open && $open['id'] == $t['id'] ? 'active' : '' ?>">
        <div class="d-flex justify-content-between gap-2"><b><?= e(thread_title($t, $ROLE)) ?></b><?php if ($t['unread']): ?><span class="bh-badge bh-warn"><?= $t['unread'] ?> new</span><?php endif; ?></div>
        <div class="small"><?= e($t['subject']) ?></div>
        <div class="small text-muted"><?= ucfirst($t['category']) ?> · <?= date('M j, g:i A', strtotime($t['updated_at'])) ?></div>
      </a>
    <?php endforeach; ?>
  </div></div>
  <div class="col-lg-8">
  <?php if ($new === 'admin' || ($new === 'owner' && $newHouse)): ?>
    <form class="bh-card" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="new"><input type="hidden" name="target" value="<?= e($new) ?>"><input type="hidden" name="house_id" value="<?= $newHouse['id'] ?? 0 ?>">
      <h2><?= $new === 'admin' ? 'Contact the admin' : 'Message the owner of ' . e($newHouse['name']) ?></h2>
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="sj">Subject</label><input class="form-control" id="sj" name="subject" required maxlength="160"></div>
        <div class="col-md-4"><label class="form-label" for="ct">Type</label><select class="form-select" id="ct" name="category">
          <option value="inquiry">Inquiry</option><option value="complaint">Complaint</option><?php if ($new === 'admin'): ?><option value="report">Bug or technical issue</option><?php endif; ?></select></div>
        <div class="col-12"><label class="form-label" for="bd">Message</label><textarea class="form-control" id="bd" name="body" rows="5" required></textarea></div>
      </div>
      <button class="btn btn-primary mt-3" data-confirm="Send this message?" data-confirm-btn="Send">Send message</button>
    </form>
  <?php elseif ($open): ?>
    <div class="bh-card">
      <div class="d-flex flex-wrap justify-content-between gap-2 mb-3 pb-2 border-bottom"><div><h2 class="mb-0"><?= e($open['subject']) ?></h2>
        <div class="small text-muted"><?= $ROLE === 'tenant' ? 'With ' . e(thread_title($open, 'tenant')) : 'From ' . e($open['tenant_name']) . ($open['house_name'] ? ' · ' . e($open['house_name']) : '') ?></div></div><span class="bh-badge bh-info align-self-start"><?= ucfirst($open['category']) ?></span></div>
      <div class="mb-3">
        <?php foreach ($msgs as $m): ?><div class="msg <?= $m['sender_role'] === $ROLE ? 'me' : '' ?>"><small><?= e($m['name']) ?> · <?= date('M j, g:i A', strtotime($m['created_at'])) ?></small><?= e($m['body']) ?></div><?php endforeach; ?>
      </div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="tid" value="<?= $open['id'] ?>">
        <label class="form-label" for="rb">Reply</label><textarea class="form-control" id="rb" name="body" rows="3" required></textarea>
        <button class="btn btn-primary mt-2">Send reply</button></form>
    </div>
  <?php else: ?>
    <div class="bh-card empty"><i class="bi bi-chat-square-text"></i>Select a conversation to read it.</div>
  <?php endif; ?>
  </div>
</div>
<?php page_end(); ?>
