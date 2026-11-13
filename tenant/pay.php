<?php
// Starts an online payment for an accepted reservation.
require __DIR__ . '/../includes/init.php';
$u = require_role('tenant');
if (!is_post()) redirect('tenant/reservations.php');
$r = one("SELECT r.*, h.name house_name, rm.price, rm.stay_days, rm.name room_name FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id
          WHERE r.id=? AND r.tenant_id=? AND r.status='accepted'", [(int)post('rid'), $u['id']]);
if (!$r) { flash('error', 'That reservation cannot be paid right now.'); redirect('tenant/reservations.php'); }

$method = post('method');
if (!in_array($method, ['gcash', 'paymaya', 'card'], true)) { flash('error', 'Choose GCash, PayMaya or card to pay.'); redirect('tenant/checkout.php?rid=' . (int)$r['id']); }

q("UPDATE payments SET status='failed' WHERE reservation_id=? AND status='pending'", [$r['id']]);
$pid = create_payment($r['id'], $r['price'], $r['stay_days'], $method, 'pending');

if (!paymongo_enabled()) {   // demo mode: no gateway, so the checkout screen is the confirmation
    mark_paid($pid);
    flash('success', 'Payment received through ' . method_label($method) . '. Your receipt was emailed to ' . $u['email'] . '.');
    redirect('tenant/reservations.php');
}

$resp = paymongo('POST', '/checkout_sessions', ['data' => ['attributes' => [
    'line_items' => [['currency' => 'PHP', 'amount' => (int)round($r['price'] * 100), 'name' => $r['house_name'] . ' · ' . $r['room_name'], 'description' => $r['stay_days'] . ' days of stay', 'quantity' => 1]],
    'payment_method_types' => [$method],
    'description' => 'BoardingHub payment ' . $pid,
    'reference_number' => 'BH-' . $pid,
    'success_url' => APP_URL . '/pay_return.php?pid=' . $pid,
    'cancel_url' => APP_URL . '/pay_return.php?pid=' . $pid . '&cancel=1',
    'send_email_receipt' => false, 'show_description' => true, 'show_line_items' => true,
]]]);
$checkout = $resp['data']['attributes']['checkout_url'] ?? null;
if (!$checkout) { q("UPDATE payments SET status='failed' WHERE id=?", [$pid]); flash('error', 'The payment page could not be opened. Try again, or check the payment key in config.php.'); redirect('tenant/reservations.php'); }
q('UPDATE payments SET provider_ref=? WHERE id=?', [$resp['data']['id'], $pid]);
redirect($checkout);
