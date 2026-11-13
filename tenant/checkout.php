<?php
// Checkout screen: pick GCash, PayMaya or card, review the details, then pay.
require __DIR__ . '/../includes/init.php';
$u = require_role('tenant');
$r = one("SELECT r.*, h.name house_name, rm.price, rm.stay_days, rm.name room_name, b.label bed_label
          FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id
          WHERE r.id=? AND r.tenant_id=? AND r.status='accepted'", [(int)($_GET['rid'] ?? 0), $u['id']]);
if (!$r) { flash('error', 'That reservation cannot be paid right now.'); redirect('tenant/reservations.php'); }

$paidUntil = paid_until($r['id']);
$start = $paidUntil ?: $r['move_in_date'];
$end = date('Y-m-d', strtotime($start . ' +' . (int)$r['stay_days'] . ' days'));
$live = paymongo_enabled();   // live gateway: card details are typed on the gateway's own secure page
$merchant = defined('MERCHANT_NAME') ? MERCHANT_NAME : 'BoardingHub';
$acct = ['gcash' => defined('MERCHANT_GCASH') ? MERCHANT_GCASH : '0927 123 4567', 'paymaya' => defined('MERCHANT_PAYMAYA') ? MERCHANT_PAYMAYA : '0927 123 4567'];

page_start('Checkout', 'tenant', 'tenant/reservations.php');
?>
<div class="page-head"><div><h1>Checkout</h1><div class="text-muted">Choose how you want to pay for your bed.</div></div></div>
<form method="post" action="<?= url('tenant/pay.php') ?>" id="checkout" novalidate
      data-amount="<?= e(number_format((float)$r['price'], 2, '.', '')) ?>" data-ref="BH-R<?= (int)$r['id'] ?>" data-merchant="<?= e($merchant) ?>"
      data-gcash="<?= e($acct['gcash']) ?>" data-paymaya="<?= e($acct['paymaya']) ?>" data-live="<?= $live ? '1' : '0' ?>">
  <?= csrf_field() ?><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="bh-card h-100">
        <h2 class="h4 mb-1">Select Payment Method</h2>
        <p class="text-muted">Choose your preferred payment method to complete your reservation.</p>
        <div class="vstack gap-3">
          <label class="pm-opt" for="m-gcash"><input class="form-check-input" type="radio" name="method" id="m-gcash" value="gcash" checked>
            <span class="pm-logo"><span class="logo-gcash"><i>G</i>GCash</span></span>
            <span class="pm-text"><b>GCash</b><small>Pay using your GCash account.</small></span></label>
          <label class="pm-opt" for="m-paymaya"><input class="form-check-input" type="radio" name="method" id="m-paymaya" value="paymaya">
            <span class="pm-logo"><span class="logo-maya"><svg viewBox="0 0 40 30" width="38" height="29" aria-hidden="true"><path d="M2 14C2 6 10 1 20 2c-3 4-3 9 1 12-4 5-9 6-14 5-3-1-5-3-5-5z" fill="#1B6FE0"/><path d="M22 3c6-2 12 1 12 7 0 7-7 12-14 11 4-3 5-9 2-18z" fill="#7BC043"/></svg>PayMaya</span></span>
            <span class="pm-text"><b>PayMaya</b><small>Pay using your PayMaya account.</small></span></label>
          <label class="pm-opt" for="m-card"><input class="form-check-input" type="radio" name="method" id="m-card" value="card">
            <span class="pm-logo"><i class="bi bi-credit-card-2-front fs-1" style="color:var(--bh-blue-dark)"></i></span>
            <span class="pm-text"><b>Credit / Debit Card</b><small>Visa, Mastercard, JCB, and more.</small></span>
            <span class="pm-brands ms-auto"><span class="b-visa">VISA</span><span class="b-mc"><i></i><i></i></span><span class="b-jcb">JCB</span></span></label>
        </div>
        <div class="pm-perks mt-4">
          <div><i class="bi bi-shield-check"></i><span>Secure<br>transactions</span></div>
          <div><i class="bi bi-lightning-charge-fill"></i><span>Instant<br>confirmation</span></div>
          <div><i class="bi bi-phone"></i><span>Available on<br>mobile &amp; app</span></div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="bh-card">
        <h2 class="h4 mb-3">Payment Details</h2>
        <div class="pay-house"><i class="bi bi-house-door-fill"></i><div><b>Room Reservation</b><div class="small"><?= e($r['house_name']) ?></div></div></div>
        <dl class="pay-rows">
          <div><dt>Room</dt><dd><?= e($r['room_name'] . ' · ' . $r['bed_label']) ?></dd></div>
          <div><dt><?= $paidUntil ? 'Covers from' : 'Check-in Date' ?></dt><dd><?= fdate($start) ?></dd></div>
          <div><dt><?= $paidUntil ? 'Covers until' : 'Check-out Date' ?></dt><dd><?= fdate($end) ?></dd></div>
          <div><dt>Days of stay</dt><dd><?= (int)$r['stay_days'] ?> days</dd></div>
          <div class="fw-semibold"><dt>Total Amount</dt><dd><?= money($r['price']) ?></dd></div>
        </dl>
        <div class="pay-due"><span>Amount Due</span><b><?= money($r['price']) ?></b></div>

        <!-- GCash / PayMaya: QR + instructions -->
        <div id="panel-qr" class="mt-3">
          <div class="fw-semibold mb-2"><span class="js-app">GCash</span> Payment Instructions</div>
          <div class="pay-box">
            <div class="d-flex gap-3">
              <i class="bi bi-phone pay-phone"></i>
              <ol class="small mb-0 ps-3">
                <li>Open your <span class="js-app">GCash</span> app.</li><li>Tap Pay QR or Scan.</li>
                <li>Scan the QR code below or enter the account details.</li><li>Confirm the payment and wait for the confirmation.</li>
              </ol>
            </div>
            <div class="d-flex align-items-center gap-3 mt-3">
              <div id="qrBox" class="qr-box" aria-label="Payment QR code"></div>
              <div class="border-start ps-3 small">
                <div class="text-muted">Merchant Name</div><b><?= e($merchant) ?></b>
                <div class="text-muted mt-2">Account Number</div><b id="acctNo"><?= e($acct['gcash']) ?></b>
              </div>
            </div>
            <div id="mayaMobile" class="mt-3" hidden>
              <div class="or-line"><span>or</span></div>
              <label class="form-label small" for="mayaNo">PayMaya mobile number</label>
              <input class="form-control" id="mayaNo" type="tel" inputmode="numeric" maxlength="11" data-mobile placeholder="09XXXXXXXXX" autocomplete="tel">
              <div class="form-text">Optional. The mobile number linked to your PayMaya account.</div>
            </div>
            <div class="small text-muted mt-3"><i class="bi bi-info-circle-fill" style="color:var(--bh-sky)"></i> Payment will be verified within a few minutes.<?= $live ? '' : ' (Demo mode: this QR code is a sample and no real money moves.)' ?></div>
          </div>
        </div>

        <!-- Card -->
        <div id="panel-card" class="mt-3" hidden>
          <div class="fw-semibold mb-2">Credit / Debit Card</div>
          <?php if ($live): ?>
            <div class="pay-box small"><i class="bi bi-lock-fill"></i> You will enter your card details on the secure payment page after you press Pay Now. BoardingHub never sees or stores your card number.</div>
          <?php else: ?>
            <div class="pay-box">
              <label class="form-label small" for="ccn">Card Number</label>
              <input class="form-control mb-2" id="ccn" inputmode="numeric" maxlength="19" placeholder="1234 5678 9012 3456" autocomplete="cc-number">
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label small" for="cce">Expiry Date</label><input class="form-control" id="cce" inputmode="numeric" maxlength="7" placeholder="MM / YY" autocomplete="cc-exp"></div>
                <div class="col-5"><label class="form-label small" for="ccv">CVV</label><input class="form-control" id="ccv" inputmode="numeric" maxlength="4" placeholder="123" autocomplete="cc-csc"></div>
              </div>
              <label class="form-label small" for="ccm">Cardholder Name</label>
              <input class="form-control" id="ccm" placeholder="As shown on card" autocomplete="cc-name">
              <div class="form-text"><i class="bi bi-lock-fill"></i> Demo mode: card details are checked in your browser only and are never sent or saved.</div>
            </div>
          <?php endif; ?>
        </div>

        <div id="payErr" class="alert alert-danger py-2 mt-3 mb-0" role="alert" hidden></div>
        <button class="btn btn-primary w-100 mt-3 py-2" id="payNow" data-confirm="Pay <?= money($r['price']) ?> for <?= (int)$r['stay_days'] ?> days at <?= e($r['house_name']) ?>?" data-confirm-btn="Pay now">Pay Now <i class="bi bi-arrow-right"></i></button>
        <div class="text-center mt-3"><a href="<?= url('tenant/reservations.php') ?>">Cancel Payment</a></div>
      </div>
    </div>
  </div>
</form>
<?php page_end('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
  const f = document.getElementById("checkout"), $ = id => document.getElementById(id);
  const names = { gcash: "GCash", paymaya: "PayMaya", card: "Card" };
  const method = () => f.querySelector("input[name=method]:checked").value;
  function qr(m) {
    const box = $("qrBox"); box.innerHTML = "";
    if (typeof QRCode === "undefined") { box.textContent = "QR"; return; }
    new QRCode(box, { text: [f.dataset.merchant, names[m], f.dataset[m], f.dataset.amount, f.dataset.ref].join("|"), width: 104, height: 104 });
  }
  function show() {
    const m = method(), isCard = m === "card";
    $("panel-qr").hidden = isCard; $("panel-card").hidden = !isCard; $("mayaMobile").hidden = m !== "paymaya";
    document.querySelectorAll(".js-app").forEach(s => s.textContent = names[m]);
    if (!isCard) { $("acctNo").textContent = f.dataset[m]; qr(m); }
    $("payErr").hidden = true;
  }
  f.querySelectorAll("input[name=method]").forEach(r => r.addEventListener("change", show));
  show();

  // Card field formatting (demo mode only; these fields have no name so they are never submitted)
  const ccn = $("ccn"), cce = $("cce"), ccv = $("ccv");
  if (ccn) {
    ccn.addEventListener("input", () => { ccn.value = ccn.value.replace(/\D/g, "").slice(0, 16).replace(/(\d{4})(?=\d)/g, "$1 "); });
    cce.addEventListener("input", () => { let d = cce.value.replace(/\D/g, "").slice(0, 4); cce.value = d.length > 2 ? d.slice(0, 2) + " / " + d.slice(2) : d; });
    ccv.addEventListener("input", () => { ccv.value = ccv.value.replace(/\D/g, "").slice(0, 4); });
  }
  const luhn = n => { let s = 0, alt = false; for (let i = n.length - 1; i >= 0; i--) { let d = +n[i]; if (alt) { d *= 2; if (d > 9) d -= 9; } s += d; alt = !alt; } return s % 10 === 0; };
  function problems() {
    const m = method(), out = [];
    if (m === "paymaya" && $("mayaNo").value && !/^09\d{9}$/.test($("mayaNo").value)) out.push("Enter the PayMaya mobile number as 11 digits, like 09XXXXXXXXX, or leave it empty and scan the QR code.");
    if (m === "card" && f.dataset.live !== "1") {
      const n = ccn.value.replace(/\D/g, ""), e = cce.value.replace(/\D/g, ""), mm = +e.slice(0, 2), yy = 2000 + +e.slice(2, 4), now = new Date();
      if (n.length < 13 || !luhn(n)) out.push("The card number does not look right.");
      if (e.length !== 4 || mm < 1 || mm > 12 || new Date(yy, mm, 0, 23, 59) < now) out.push("The expiry date is invalid or the card has expired.");
      if (ccv.value.length < 3) out.push("Enter the 3 or 4 digit CVV.");
      if ($("ccm").value.trim().length < 2) out.push("Enter the cardholder name as shown on the card.");
    }
    return out;
  }
  // Runs before the confirmation pop-up so a bad entry never reaches it.
  $("payNow").addEventListener("click", e => {
    const p = problems();
    if (p.length) { e.preventDefault(); e.stopPropagation(); $("payErr").innerHTML = p.map(x => "<div>" + x + "</div>").join(""); $("payErr").hidden = false; }
    else $("payErr").hidden = true;
  });
})();
</script>'); ?>
