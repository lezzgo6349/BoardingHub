<?php
// Owner rents a bed to a tenant directly (walk-in or someone who contacted the owner).
require __DIR__ . '/../includes/init.php';
$u = require_owner();
$rows = all("SELECT h.id hid, h.name hname, r.id rid, r.name rname, r.price, r.stay_days, b.id bid, b.label FROM boarding_houses h JOIN rooms r ON r.house_id=h.id JOIN beds b ON b.room_id=r.id
             WHERE h.owner_id=? AND b.status='available' ORDER BY h.name, r.name, b.id", [$u['id']]);
$err = [];
if (is_post()) {
    $bed = one("SELECT b.*, r.house_id, r.id room_id, h.name hname FROM beds b JOIN rooms r ON r.id=b.room_id JOIN boarding_houses h ON h.id=r.house_id WHERE b.id=? AND h.owner_id=? AND b.status='available'", [(int)post('bed_id'), $u['id']]);
    $name = post('guest_name'); $email = strtolower(post('guest_email')); $amount = (float)post('amount'); $days = (int)post('days'); $date = post('move_in');
    if (!$bed) $err[] = 'Choose an available bed.';
    if (mb_strlen($name) < 2) $err[] = 'Enter the tenant name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Enter the tenant email. The receipt and contract are sent there.';
    if ($amount <= 0) $err[] = 'Enter the amount paid.';
    if ($days < 1) $err[] = 'Enter the days of stay.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $err[] = 'Choose the move-in date.';
    if (!$err) {
        $tid = val("SELECT id FROM users WHERE email=? AND role='tenant'", [$email]);
        $pdo = db(); $pdo->beginTransaction();
        q("INSERT INTO reservations(source,tenant_id,guest_name,guest_email,guest_phone,house_id,room_id,bed_id,move_in_date,status,decided_at) VALUES('owner',?,?,?,?,?,?,?,?, 'accepted', NOW())",
          [$tid, $name, $email, post('guest_phone'), $bed['house_id'], $bed['room_id'], $bed['id'], $date]);
        $rid = (int)$pdo->lastInsertId();
        q("UPDATE beds SET status='occupied' WHERE id=?", [$bed['id']]);
        $pdo->commit();
        create_payment($rid, $amount, $days, 'cash', 'paid');   // saves the payment and emails the receipt + contract + next payment date
        if ($tid) notify($tid, $bed['hname'] . ' added you as a tenant. Your receipt is in your email.', 'tenant/reservations.php');
        flash('success', 'Tenant added. The receipt, rules and next payment date were emailed to ' . $email . '.');
        redirect('owner/tenants.php');
    }
}
page_start('Rent a tenant', 'owner', 'owner/tenants.php');
?>
<div class="page-head"><div><h1>Rent a tenant</h1><div class="text-muted">Record who is moving in and what they paid. The receipt, rules and next payment date are emailed automatically.</div></div></div>
<?php foreach ($err as $m): ?><div class="alert alert-danger py-2"><?= e($m) ?></div><?php endforeach; ?>
<?php if (!$rows): ?><div class="bh-card empty"><i class="bi bi-door-open"></i>No available beds. Add rooms to a boarding house first, or end a stay to free a bed.</div>
<?php else: ?>
<form class="bh-card" method="post" style="max-width:760px">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="hs">Boarding house</label><select class="form-select" id="hs"></select></div>
    <div class="col-md-4"><label class="form-label" for="rs">Room</label><select class="form-select" id="rs"></select></div>
    <div class="col-md-4"><label class="form-label" for="bs">Bed</label><select class="form-select" id="bs" name="bed_id" required></select></div>
    <div class="col-md-6"><label class="form-label" for="gn">Tenant name</label><input class="form-control" id="gn" name="guest_name" required value="<?= e($_POST['guest_name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="ge">Tenant email</label><input class="form-control" id="ge" type="email" name="guest_email" required value="<?= e($_POST['guest_email'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="gp">Phone (optional)</label><input class="form-control" id="gp" name="guest_phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" data-mobile placeholder="09XXXXXXXXX" title="11 digits, starting with 09" value="<?= e($_POST['guest_phone'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="mi">Move-in date</label><input class="form-control" id="mi" type="date" name="move_in" value="<?= e($_POST['move_in'] ?? today()) ?>" required></div>
    <div class="col-md-4"></div>
    <div class="col-md-4"><label class="form-label" for="am">Amount paid (₱)</label><input class="form-control" id="am" type="number" step="0.01" min="1" name="amount" required value="<?= e($_POST['amount'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="dy">Days of stay</label><input class="form-control" id="dy" type="number" min="1" name="days" required value="<?= e($_POST['days'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Next payment date</label><div class="form-control bg-light" id="np">—</div></div>
  </div>
  <button class="btn btn-primary mt-3" data-confirm="Rent this bed and email the receipt and contract to the tenant?" data-confirm-btn="Rent bed"><i class="bi bi-envelope-check"></i> Rent and send receipt</button>
</form>
<?php endif;
page_end('<script>
const rows=' . json_encode($rows) . ';
const hs=document.getElementById("hs"),rs=document.getElementById("rs"),bs=document.getElementById("bs"),am=document.getElementById("am"),dy=document.getElementById("dy"),mi=document.getElementById("mi"),np=document.getElementById("np");
if(hs){
const uniq=(a,k)=>[...new Map(a.map(x=>[x[k],x])).values()];
const opt=(v,t)=>`<option value="${v}">${String(t).replace(/</g,"&lt;")}</option>`;
function fillH(){hs.innerHTML=uniq(rows,"hid").map(r=>opt(r.hid,r.hname)).join("");fillR();}
function fillR(){rs.innerHTML=uniq(rows.filter(r=>r.hid==hs.value),"rid").map(r=>opt(r.rid,r.rname)).join("");fillB(true);}
function fillB(reset){bs.innerHTML=rows.filter(r=>r.rid==rs.value).map(r=>opt(r.bid,r.label)).join("");
 if(reset){const r=rows.find(x=>x.rid==rs.value);if(r){am.value=r.price;dy.value=r.stay_days;}}nextDate();}
function nextDate(){const d=new Date(mi.value);if(isNaN(d)||!dy.value){np.textContent="—";return;}d.setDate(d.getDate()+ +dy.value);np.textContent=d.toLocaleDateString(undefined,{year:"numeric",month:"short",day:"numeric"});}
hs.onchange=fillR;rs.onchange=()=>fillB(true);dy.oninput=nextDate;mi.oninput=nextDate;fillH();}
</script>'); ?>
