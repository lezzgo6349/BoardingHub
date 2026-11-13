<?php
// Shared queries
function tenant_reservations($uid) {
    return all("SELECT r.*, h.name house_name, h.address, h.owner_id, rm.name room_name, rm.price, rm.stay_days, b.label bed_label,
        (SELECT MAX(period_end) FROM payments p WHERE p.reservation_id=r.id AND p.status IN ('paid','refund_requested')) paid_until
        FROM reservations r JOIN boarding_houses h ON h.id=r.house_id JOIN rooms rm ON rm.id=r.room_id JOIN beds b ON b.id=r.bed_id
        WHERE r.tenant_id=? ORDER BY r.created_at DESC", [$uid]);
}
function house_beds_summary($hid) {
    return one("SELECT COUNT(*) total, SUM(b.status='available') free FROM beds b JOIN rooms r ON r.id=b.room_id WHERE r.house_id=?", [$hid]);
}
function release_bed($bedId) { q("UPDATE beds SET status='available' WHERE id=?", [$bedId]); }
