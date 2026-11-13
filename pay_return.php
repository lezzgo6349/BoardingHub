<?php
// PayMongo sends the tenant back here. We verify the checkout session with PayMongo before marking the payment as paid.
require __DIR__ . '/includes/init.php';
$u = require_role('tenant');
$p = one('SELECT * FROM payments WHERE id=? AND tenant_id=?', [(int)($_GET['pid'] ?? 0), $u['id']]);
if (!$p) redirect('tenant/reservations.php');
if ($p['status'] === 'pending' && paymongo_enabled() && $p['provider_ref'] && empty($_GET['cancel'])) {
    $s = paymongo('GET', '/checkout_sessions/' . urlencode($p['provider_ref']));
    $a = $s['data']['attributes'] ?? [];
    $pay = $a['payments'][0] ?? null;
    $ok = ($pay && ($pay['attributes']['status'] ?? '') === 'paid') || (($a['payment_intent']['attributes']['status'] ?? '') === 'succeeded');
    if ($ok) { mark_paid($p['id'], $pay['id'] ?? null); flash('success', 'Payment received. Your receipt was emailed to ' . $u['email'] . '.'); }
    else flash('warning', 'We could not confirm the payment yet. If you were charged, refresh this page in a minute or message the admin.');
} elseif ($p['status'] === 'pending') {
    q("UPDATE payments SET status='failed' WHERE id=?", [$p['id']]);
    flash('warning', 'Payment was cancelled. You were not charged.');
}
redirect('tenant/reservations.php');
