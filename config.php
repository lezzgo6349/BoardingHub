<?php
// ---------- BoardingHub settings ----------
// Folder name inside xampp/htdocs (no trailing slash). Use '' if the site is at the web root.
define('BASE_URL', '/boardinghub');

// Database (XAMPP defaults)
define('DB_HOST', 'localhost');
define('DB_NAME', 'boardinghub');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'BoardingHub');
define('APP_TZ', 'Asia/Manila');

// The first admin can register freely. Every later admin must enter this code. CHANGE IT.
define('ADMIN_INVITE_CODE', 'change-this-code');

// Online reservations can only be made for a move-in date within this many days.
define('MAX_ADVANCE_DAYS', 14);

// PayMongo (https://dashboard.paymongo.com/developers). Leave empty to use the built-in demo checkout.
define('PAYMONGO_SECRET_KEY', '');   // e.g. sk_test_xxxxxxxx

// Shown on the checkout QR panel for GCash / PayMaya. CHANGE these to your real merchant details.
define('MERCHANT_NAME', 'BoardingHub');
define('MERCHANT_GCASH', '0927 123 4567');
define('MERCHANT_PAYMAYA', '0927 123 4567');

// Email. Fill these in to really send emails (Gmail example: smtp.gmail.com, port 587,
// your Gmail address as SMTP_USER and MAIL_FROM, and a Google "App Password" as SMTP_PASS).
// Left empty, emails are only saved in the Email log (admin menu) so you can still test.
define('MAIL_FROM', 'no-reply@boardinghub.local');
define('SMTP_HOST', '');             // e.g. smtp.gmail.com
define('SMTP_PORT', 587);            // 587 (STARTTLS) or 465 (SSL)
define('SMTP_USER', '');
define('SMTP_PASS', '');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('APP_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL);
