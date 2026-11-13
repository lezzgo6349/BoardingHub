<?php
require __DIR__ . '/../includes/init.php';
$u = require_role('tenant');
$s = trim($_GET['s'] ?? ''); $cat = $_GET['cat'] ?? ''; $max = (float)($_GET['max'] ?? 0); $vac = !empty($_GET['vac']);
$sql = "SELECT h.*, ou.name owner_name,
  (SELECT MIN(price) FROM rooms WHERE house_id=h.id) min_price,
  (SELECT COUNT(*) FROM beds b JOIN rooms r ON r.id=b.room_id WHERE r.house_id=h.id) beds_total,
  (SELECT COUNT(*) FROM beds b JOIN rooms r ON r.id=b.room_id WHERE r.house_id=h.id AND b.status='available') beds_free
  FROM boarding_houses h JOIN users ou ON ou.id=h.owner_id AND ou.deleted_at IS NULL
  JOIN owner_profiles op ON op.user_id=h.owner_id AND op.status='approved'
  WHERE h.is_active=1"; $p = [];
if ($s !== '') { $sql .= ' AND (h.name LIKE ? OR h.address LIKE ? OR h.description LIKE ?)'; $p = array_merge($p, ["%$s%", "%$s%", "%$s%"]); }
if ($cat !== '' && in_array($cat, HOUSE_CATEGORIES)) { $sql .= ' AND h.category=?'; $p[] = $cat; }
$sql .= ' HAVING min_price IS NOT NULL';
if ($max > 0) { $sql .= ' AND min_price<=?'; $p[] = $max; }
if ($vac) $sql .= ' AND beds_free>0';
$sql .= ' ORDER BY beds_free>0 DESC, h.created_at DESC';
$houses = all($sql, $p);
page_start('Browse boarding houses', 'tenant', 'tenant/browse.php');
?>
<div class="page-head"><div><h1>Browse boarding houses</h1><div class="text-muted"><?= count($houses) ?> result<?= count($houses) === 1 ? '' : 's' ?></div></div>
  <button class="btn btn-soft" type="button" data-bs-toggle="collapse" data-bs-target="#mapWrap"><i class="bi bi-geo-alt"></i> Show on map</button></div>
<form class="bh-card mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label" for="s">Search</label><input class="form-control" id="s" name="s" value="<?= e($s) ?>" placeholder="Name, street or area"></div>
    <div class="col-md-3"><label class="form-label" for="cat">Category</label><select class="form-select" id="cat" name="cat"><option value="">All categories</option><?php foreach (HOUSE_CATEGORIES as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label" for="max">Max price</label><input class="form-control" id="max" type="number" min="0" name="max" value="<?= $max ?: '' ?>" placeholder="₱"></div>
    <div class="col-md-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="vac" id="vac" value="1" <?= $vac ? 'checked' : '' ?>><label class="form-check-label" for="vac">Has vacancy</label></div></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Go</button></div>
  </div>
</form>
<div class="collapse mb-3" id="mapWrap"><div id="bigMap" class="map-box tall"></div></div>
<?php if (!$houses): ?><div class="bh-card empty"><i class="bi bi-house-door"></i>No boarding houses match your search. Try removing a filter.</div><?php endif; ?>
<div class="row g-3">
<?php foreach ($houses as $h): ?>
  <div class="col-md-6 col-xl-4">
    <a class="house-card" href="<?= url('tenant/house.php?id=' . $h['id']) ?>">
      <div class="ph"><img src="<?= img($h['photo']) ?>" class="<?= $h['photo'] ? '' : 'is-logo' ?>" alt="" loading="lazy"></div>
      <div class="body">
        <div class="d-flex justify-content-between gap-2"><h3><?= e($h['name']) ?></h3><?= badge($h['beds_free'] > 0 ? 'available' : 'occupied') ?></div>
        <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($h['address']) ?></div>
        <div class="small"><?= e($h['category']) ?> · Owner: <?= e($h['owner_name']) ?></div>
        <div class="mt-auto pt-2 d-flex justify-content-between align-items-center">
          <span class="price">from <?= money($h['min_price']) ?></span>
          <span class="small text-muted"><?= bed_icon($h['beds_free'] > 0 ? 'available' : 'occupied') ?> <?= (int)$h['beds_free'] ?> of <?= (int)$h['beds_total'] ?> beds free</span>
        </div>
      </div>
    </a>
  </div>
<?php endforeach; ?>
</div>
<?php
$pins = array_map(fn($h) => ['id' => $h['id'], 'name' => $h['name'], 'lat' => (float)$h['latitude'], 'lng' => (float)$h['longitude'], 'price' => money($h['min_price'])], $houses);
page_end('<script>
const pins=' . json_encode($pins) . ';let built=false;
document.getElementById("mapWrap").addEventListener("shown.bs.collapse",()=>{if(built)return;built=true;
 const map=BH.baseMap("bigMap",pins[0]?[pins[0].lat,pins[0].lng]:null,pins[0]?12:5);const b=[];
 pins.forEach(p=>{b.push([p.lat,p.lng]);L.marker([p.lat,p.lng]).addTo(map).bindPopup("<b>"+p.name.replace(/</g,"&lt;")+"</b><br>from "+p.price+"<br><a href=\"house.php?id="+p.id+"\">View rooms</a>")});
 if(b.length>1)map.fitBounds(b,{padding:[30,30]});});
</script>'); ?>
