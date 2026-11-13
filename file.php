<?php
// Serves private ID / ownership images. Only admins and the owner who uploaded them can open them.
require __DIR__ . '/includes/init.php';
$u = user(); if (!$u) { http_response_code(403); exit; }
$p = one('SELECT * FROM owner_profiles WHERE id=?', [(int)($_GET['p'] ?? 0)]);
if (!$p || ($u['role'] !== 'admin' && (int)$p['user_id'] !== (int)$u['id'])) { http_response_code(403); exit('Not allowed'); }
$path = ($_GET['f'] ?? '') === 'proof' ? $p['ownership_proof_image'] : $p['national_id_image'];
$full = realpath(UPLOAD_DIR . $path);
if (!$full || strpos($full, realpath(UPLOAD_DIR . 'private')) !== 0) { http_response_code(404); exit; }
$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext]);
header('Cache-Control: private, no-store');
readfile($full);
