<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set(APP_TZ);

/* ---------- Database ---------- */
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('Cannot connect to the database. Start MySQL in XAMPP and import boardinghub.sql, then check config.php.');
        }
    }
    return $pdo;
}
function q($sql, $p = []) { $s = db()->prepare($sql); $s->execute($p); return $s; }
function one($sql, $p = []) { return q($sql, $p)->fetch() ?: null; }
function all($sql, $p = []) { return q($sql, $p)->fetchAll(); }
function val($sql, $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

/* ---------- Small helpers ---------- */
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url($p = '') { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect($p) { header('Location: ' . (preg_match('~^https?://~', $p) ? $p : url($p))); exit; }
function is_post() { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function flash($type, $msg) { $_SESSION['flash'][] = [$type, $msg]; }
function take_flash() { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function money($n) { return '₱' . number_format((float)$n, 2); }
function today() { return date('Y-m-d'); }
function fdate($d) { return $d ? date('M j, Y', strtotime($d)) : '—'; }
function img($path) { return $path ? url('uploads/' . $path) : url('assets/img/logo.png'); }
/* Email typed without a domain gets @gmail.com added (juan -> juan@gmail.com). */
function norm_email($v) { $v = strtolower(trim((string)$v)); return ($v !== '' && strpos($v, '@') === false) ? $v . '@gmail.com' : $v; }
function digits($v) { return preg_replace('/\D/', '', (string)$v); }
function method_label($m) { return ['gcash' => 'GCash', 'paymaya' => 'PayMaya', 'card' => 'Card', 'demo' => 'Demo payment'][$m] ?? 'Online payment'; }
function post($k, $d = '') { return trim((string)($_POST[$k] ?? $d)); }
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
if (is_post() && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(419);
    exit('Your session expired. Go back, refresh the page and try again.');
}

const HOUSE_CATEGORIES = ['Co-ed', 'Female only', 'Male only', 'Students', 'Working professionals', 'Family'];

function badge($status) {
    $map = ['pending' => 'warn', 'accepted' => 'ok', 'approved' => 'ok', 'paid' => 'ok', 'declined' => 'bad', 'cancelled' => 'mute',
            'ended' => 'mute', 'refunded' => 'info', 'refund_requested' => 'warn', 'failed' => 'bad', 'available' => 'ok',
            'reserved' => 'warn', 'occupied' => 'info'];
    $label = ucfirst(str_replace('_', ' ', $status));
    return '<span class="bh-badge bh-' . ($map[$status] ?? 'mute') . '">' . e($label) . '</span>';
}
function bed_icon($state = 'available', $title = '') {
    return '<svg class="bed bed-' . e($state) . '" viewBox="0 0 48 32" width="34" height="23" role="img" aria-label="' . e($title ?: $state) . '"><title>' . e($title ?: ucfirst($state)) . '</title>'
        . '<rect x="2" y="3" width="3.5" height="26" rx="1"/><rect x="2" y="20" width="44" height="3.5"/><rect x="42.5" y="20" width="3.5" height="9"/>'
        . '<circle cx="14" cy="14" r="4.2"/><path d="M20 10.5h18a6 6 0 0 1 6 6V20H20z"/></svg>';
}

/* ---------- Auth ---------- */
function user() {
    static $u = false;
    if ($u === false) {
        $u = !empty($_SESSION['uid']) ? one('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]) : null;
        if ($u && !empty($u['deleted_at'])) $u = null;
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}
function login_user($id) { session_regenerate_id(true); $_SESSION['uid'] = $id; }
function home_for($u) { return $u['role'] === 'admin' ? 'admin/dashboard.php' : 'tenant/dashboard.php'; }
function require_role($role) {
    $u = user();
    if (!$u) redirect($role === 'admin' ? 'admin/login.php' : 'index.php');
    if ($u['role'] !== $role) redirect(home_for($u));
    return $u;
}
function owner_profile($uid) { return one('SELECT * FROM owner_profiles WHERE user_id=?', [$uid]); }
function require_owner() {
    $u = require_role('tenant');
    $p = owner_profile($u['id']);
    if (!$p || $p['status'] !== 'approved') {
        flash('warning', 'Apply as an owner first. An admin has to approve your profile.');
        redirect('tenant/become_owner.php');
    }
    return $u;
}
function is_approved_owner($uid) { return (bool)val("SELECT 1 FROM owner_profiles WHERE user_id=? AND status='approved'", [$uid]); }

/* Validate a registration form. Returns [errors, clean]. */
function reg_validate($needAddress = false) {
    $err = [];
    $c = ['name' => post('name'), 'email' => norm_email(post('email')), 'phone' => digits(post('phone')), 'address' => post('address')];
    $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password2'] ?? '';
    if (mb_strlen($c['name']) < 2) $err[] = 'Enter your full name.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    elseif ($ex = one('SELECT id, deleted_at FROM users WHERE email=?', [$c['email']])) $err[] = $ex['deleted_at'] ? 'That email belongs to a deleted account. Ask an admin to restore it.' : 'That email already has an account. Sign in instead.';
    if ($c['phone'] !== '' && !preg_match('/^09\d{9}$/', $c['phone'])) $err[] = 'Enter an 11-digit mobile number that starts with 09 (09XXXXXXXXX).';
    if ($needAddress && mb_strlen($c['address']) < 5) $err[] = 'Enter your address.';
    if (strlen($pw) < 8) $err[] = 'Use a password with at least 8 characters.';
    if ($pw !== $pw2) $err[] = 'The two passwords do not match.';
    $c['hash'] = password_hash($pw, PASSWORD_DEFAULT);
    return [$err, $c];
}

/* ---------- Uploads (JPG/PNG/WEBP up to 4 MB) ---------- */
function save_upload($field, $private = false) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 4 * 1024 * 1024) throw new RuntimeException('The image could not be uploaded. Use a file under 4 MB.');
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
    $sub = $private ? 'private/' : 'public/';
    if (!is_dir(UPLOAD_DIR . $sub)) mkdir(UPLOAD_DIR . $sub, 0755, true);
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $sub . $name)) throw new RuntimeException('Could not save the uploaded image.');
    return $sub . $name;
}

/* ---------- Notifications & email ---------- */
function notify($uid, $text, $link = null) { q('INSERT INTO notifications(user_id,text,link) VALUES(?,?,?)', [$uid, $text, $link]); }
function smtp_send($to, $subject, $html) {
    $fp = @stream_socket_client((SMTP_PORT == 465 ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15);
    if (!$fp) throw new RuntimeException('SMTP connect failed: ' . $errstr);
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function ($c, $expect) use ($fp, $read) {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $r = $read();
        if (strpos($r, (string)$expect) !== 0) throw new RuntimeException('SMTP error: ' . trim($r));
    };
    $cmd(null, 220); $cmd('EHLO localhost', 250);
    if (SMTP_PORT != 465) {
        $cmd('STARTTLS', 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS failed');
        $cmd('EHLO localhost', 250);
    }
    if (SMTP_USER !== '') { $cmd('AUTH LOGIN', 334); $cmd(base64_encode(SMTP_USER), 334); $cmd(base64_encode(SMTP_PASS), 235); }
    $cmd('MAIL FROM:<' . MAIL_FROM . '>', 250); $cmd('RCPT TO:<' . $to . '>', 250); $cmd('DATA', 354);
    $msg = 'From: ' . APP_NAME . ' <' . MAIL_FROM . ">\r\nTo: <" . $to . ">\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nDate: " . date('r')
         . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html));
    fwrite($fp, $msg . "\r\n.\r\n"); $cmd(null, 250);
    @fwrite($fp, "QUIT\r\n"); fclose($fp);
}
/* Sends through SMTP when configured. Every email is also saved in email_log. */
function send_mail($to, $subject, $html) {
    $status = 'logged';
    if (SMTP_HOST !== '') {
        try { smtp_send($to, $subject, $html); $status = 'sent'; } catch (Throwable $ex) { $status = 'failed'; }
    }
    q('INSERT INTO email_log(to_email,subject,body,status) VALUES(?,?,?,?)', [$to, $subject, $html, $status]);
    return $status;
}

/* ---------- Billing ---------- */
function paid_until($rid) {
    return val("SELECT MAX(period_end) FROM payments WHERE reservation_id=? AND status IN ('paid','refund_requested')", [$rid]);
}
function create_payment($rid, $amount, $days, $method, $status = 'pending', $ref = null) {
    $r = one('SELECT r.*, h.owner_id FROM reservations r JOIN boarding_houses h ON h.id=r.house_id WHERE r.id=?', [$rid]);
    $start = paid_until($rid) ?: $r['move_in_date'];
    $end = date('Y-m-d', strtotime($start . ' +' . (int)$days . ' days'));
    q('INSERT INTO payments(reservation_id,tenant_id,house_id,owner_id,amount,days,method,provider_ref,status,period_start,period_end,receipt_token,paid_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$rid, $r['tenant_id'], $r['house_id'], $r['owner_id'], $amount, (int)$days, $method, $ref, $status, $start, $end, bin2hex(random_bytes(16)), $status === 'paid' ? date('Y-m-d H:i:s') : null]);
    $pid = (int)db()->lastInsertId();
    q('UPDATE payments SET receipt_code=? WHERE id=?', ['BH-' . date('Ymd') . '-' . str_pad($pid, 5, '0', STR_PAD_LEFT), $pid]);
    if ($status === 'paid') after_paid($pid);
    return $pid;
}
function mark_paid($pid, $providerPaymentId = null) {
    q("UPDATE payments SET status='paid', paid_at=NOW(), provider_payment_id=COALESCE(?,provider_payment_id) WHERE id=? AND status='pending'", [$providerPaymentId, $pid]);
    after_paid($pid);
}
function after_paid($pid) {
    $p = payment_full($pid);
    if (!$p) return;
    if ($p['tenant_id'] && $p['source'] === 'online') {
        notify($p['owner_id'], $p['guest_name'] . ' paid ' . money($p['amount']) . ' for ' . $p['room_name'] . ' (' . $p['bed_label'] . ').', 'owner/finance.php');
    }
    send_receipt_email($pid);
}
function payment_full($pid) {
    return one('SELECT p.*, r.guest_name, r.guest_email, r.source, r.move_in_date, h.name house_name, h.address, h.rules, h.contract, h.contact_phone, h.contact_email,
                       rm.name room_name, b.label bed_label, ou.name owner_name
                FROM payments p JOIN reservations r ON r.id=p.reservation_id JOIN boarding_houses h ON h.id=p.house_id
                JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id JOIN users ou ON ou.id=p.owner_id WHERE p.id=?', [$pid]);
}
function receipt_url($token) { return APP_URL . '/receipt.php?t=' . $token; }
function send_receipt_email($pid) {
    $p = payment_full($pid);
    if (!$p || !$p['guest_email']) return;
    $link = receipt_url($p['receipt_token']);
    $body = '<div style="font-family:Arial,sans-serif;max-width:560px;color:#1D2B47">'
        . '<h2 style="color:#3C62A8">Payment receipt · ' . e($p['receipt_code']) . '</h2>'
        . '<p>Hi ' . e($p['guest_name']) . ', thank you for your payment to <b>' . e($p['house_name']) . '</b>.</p>'
        . '<table cellpadding="6" style="border-collapse:collapse;width:100%;font-size:14px">'
        . '<tr><td>Room / bed</td><td><b>' . e($p['room_name'] . ' · ' . $p['bed_label']) . '</b></td></tr>'
        . '<tr><td>Amount paid</td><td><b>' . money($p['amount']) . '</b> for ' . (int)$p['days'] . ' days</td></tr>'
        . '<tr><td>Covers</td><td>' . fdate($p['period_start']) . ' to ' . fdate($p['period_end']) . '</td></tr>'
        . '<tr><td>Next payment date</td><td><b>' . fdate($p['period_end']) . '</b></td></tr></table>'
        . '<p><a href="' . e($link) . '" style="background:#3C62A8;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block">View receipt, rules and contract</a></p>'
        . '<p style="font-size:12px;color:#667">You can print the page or save it as a PDF from the link above.</p></div>';
    send_mail($p['guest_email'], 'Your BoardingHub receipt ' . $p['receipt_code'], $body);
}

/* ---------- Online checkout (GCash, PayMaya, card) ---------- */
function paymongo_enabled() { return PAYMONGO_SECRET_KEY !== ''; }
function paymongo($method, $path, $body = null) {
    $ch = curl_init('https://api.paymongo.com/v1' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')]]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch); curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

/* ---------- Layout ---------- */
function nav_links($area) {
    $L = [
        'tenant' => [['tenant/browse.php', 'Browse', 'bi-search'], ['tenant/reservations.php', 'My reservations', 'bi-calendar-check'], ['tenant/messages.php', 'Messages', 'bi-chat-dots']],
        'owner'  => [['owner/dashboard.php', 'Overview', 'bi-grid'], ['owner/houses.php', 'My boarding houses', 'bi-house-door'], ['owner/reservations.php', 'Reservations', 'bi-inbox'],
                     ['owner/tenants.php', 'Tenants', 'bi-people'], ['owner/finance.php', 'Finance', 'bi-wallet2'], ['owner/messages.php', 'Messages', 'bi-chat-dots']],
        'admin'  => [['admin/dashboard.php', 'Overview', 'bi-grid'], ['admin/owners.php', 'Owner requests', 'bi-patch-check'], ['admin/accounts.php', 'Accounts', 'bi-people'],
                     ['admin/messages.php', 'Reports & inquiries', 'bi-flag'], ['admin/emails.php', 'Email log', 'bi-envelope']],
    ];
    return $L[$area] ?? [];
}
function page_start($title, $area = 'public', $active = '') {
    $u = user();
    $unread = ($u && $u['role'] !== 'admin') ? (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$u['id']]) : 0;
    $pendingOwner = ($u && $u['role'] === 'tenant' && is_approved_owner($u['id']));
    $isPublic = in_array($area, ['public', 'admin-login', 'receipt']);
    $showAdminLink = in_array($area, ['public', 'receipt']);
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<link rel="icon" href="<?= url('assets/img/favicon.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head><body class="area-<?= e($area) ?>">
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<nav class="bh-nav navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="<?= url($u ? home_for($u) : 'index.php') ?>"><img src="<?= url('assets/img/logo.png') ?>" alt="" width="34" height="34"><span>BoardingHub</span><?php if ($area === 'owner'): ?><small>Owner</small><?php elseif ($area === 'admin'): ?><small>Admin</small><?php endif; ?></a>
    <?php if ($isPublic): ?>
      <?php if ($showAdminLink): ?><a class="bh-admin-link ms-auto" href="<?= url('admin/login.php') ?>" title="Admin sign in" aria-label="Admin sign in"><i class="bi bi-shield-lock"></i></a><?php endif; ?>
    <?php else: ?>
      <button class="navbar-toggler border-0" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu"><i class="bi bi-list fs-2"></i></button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
          <?php foreach (nav_links($area) as [$href, $label, $icon]): ?>
            <li class="nav-item"><a class="nav-link <?= $active === $href ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <ul class="navbar-nav align-items-lg-center gap-lg-2">
          <?php if ($area === 'owner'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= url('tenant/dashboard.php') ?>"><i class="bi bi-arrow-left-right"></i> Tenant view</a></li>
          <?php elseif ($area === 'tenant' && $pendingOwner): ?>
            <li class="nav-item"><a class="nav-link" href="<?= url('owner/dashboard.php') ?>"><i class="bi bi-key"></i> Owner dashboard</a></li>
          <?php endif; ?>
          <?php if ($u && $u['role'] !== 'admin'): ?>
            <li class="nav-item"><a class="nav-link position-relative" href="<?= url('notifications.php' . ($area === 'owner' ? '?from=owner' : '')) ?>" aria-label="Notifications"><i class="bi bi-bell"></i><?php if ($unread): ?><span class="bh-dot"><?= $unread ?></span><?php endif; ?></a></li>
          <?php endif; ?>
          <?php if ($u): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?= e(explode(' ', $u['name'])[0]) ?></a>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($area === 'owner'): ?><li><a class="dropdown-item" href="<?= url('owner/profile.php') ?>">Owner profile</a></li><?php endif; ?>
              <?php if ($area === 'tenant'): ?><li><a class="dropdown-item" href="<?= url('tenant/become_owner.php') ?>"><?= $pendingOwner ? 'Owner profile' : 'Become an owner' ?></a></li><?php endif; ?>
              <li><a class="dropdown-item" href="<?= url('logout.php') ?>" data-confirm="Sign out of BoardingHub?" data-confirm-btn="Sign out">Sign out</a></li>
            </ul>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</nav>
<main id="main" class="<?= $isPublic ? '' : 'container py-4' ?>">
<?php foreach (take_flash() as [$t, $m]): ?>
  <div class="<?= $isPublic ? 'container pt-3' : '' ?>"><div class="alert alert-<?= $t === 'error' ? 'danger' : e($t) ?> alert-dismissible fade show" role="alert"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>
<?php endforeach;
}
function page_end($extraJs = '') { ?>
</main>
<footer class="bh-footer"><div class="container d-flex flex-wrap justify-content-between gap-2"><span><img src="<?= url('assets/img/logo.png') ?>" alt="" width="20" height="20"> BoardingHub</span><span>Boarding house rent and reservation system</span></div></footer>
<dialog id="bhConfirm" class="bh-dialog">
  <form method="dialog">
    <h2 id="bhConfirmTitle">Please confirm</h2>
    <p id="bhConfirmMsg"></p>
    <div class="d-flex justify-content-end gap-2">
      <button value="cancel" class="btn btn-light">Go back</button>
      <button value="ok" id="bhConfirmOk" class="btn btn-primary">Confirm</button>
    </div>
  </form>
</dialog>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?= $extraJs ?>
</body></html>
<?php }
