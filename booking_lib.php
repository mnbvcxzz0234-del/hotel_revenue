<?php
// ใช้ร่วมกันระหว่างหน้าผู้ดูแล (bookings.php) และหน้าลูกค้า (book.php)
const CHANNELS = ['direct'=>'จองตรง (โทร/เว็บ)','web'=>'เว็บไซต์ (ลูกค้าจองเอง)','walkin'=>'Walk-in','booking'=>'Booking.com','agoda'=>'Agoda','expedia'=>'Expedia','other'=>'อื่น ๆ'];
const STATUS   = ['pending'=>'รอยืนยัน','confirmed'=>'ยืนยันแล้ว','checked_in'=>'เข้าพักอยู่','completed'=>'เช็กเอาต์แล้ว','no_show'=>'ไม่มา','cancelled'=>'ยกเลิก'];
const ACTIVE   = ['confirmed','checked_in','completed'];   // สถานะที่นับว่าใช้ห้องและมีรายได้ (pending ยังไม่จองห้อง)
const NEXT     = ['pending'=>['confirmed','cancelled'],'confirmed'=>['checked_in','no_show','cancelled'],'checked_in'=>['completed','confirmed'],
                  'completed'=>['checked_in'],'no_show'=>['confirmed'],'cancelled'=>['confirmed']];

function nights($ci, $co) { return (int)round((strtotime($co) - strtotime($ci)) / 86400); }
function valid_date($d) { $x = DateTime::createFromFormat('Y-m-d', (string)$d); return $x && $x->format('Y-m-d') === $d; }
function dth($d) { return date('d/m/Y', strtotime($d)); }

function log_b($db, $id, $act, $detail = '', $by = null) {
    $db->prepare('INSERT INTO booking_log(booking_id,action,by_user,detail) VALUES(?,?,?,?)')
       ->execute([$id, $act, $by ?? ($_SESSION['user'] ?? ''), $detail]);
}

// ห้องว่างน้อยที่สุดในช่วงคืนที่เลือก [จำนวนห้องว่าง, วันที่ที่ว่างน้อยสุด] (ไม่นับรายการ $excludeId)
function rooms_left($db, $tid, $total, $ci, $co, $excludeId = 0) {
    $st = $db->prepare("SELECT checkin,checkout,rooms FROM bookings WHERE room_type_id=? AND status NOT IN ('cancelled','no_show','pending') AND checkin<? AND checkout>? AND id<>?");
    $st->execute([$tid, $co, $ci, $excludeId]); $bk = $st->fetchAll();
    $min = PHP_INT_MAX; $at = $ci;
    for ($i = 0, $N = nights($ci, $co); $i < $N; $i++) {
        $d = date('Y-m-d', strtotime("$ci +$i day")); $used = 0;
        foreach ($bk as $b) if ($b['checkin'] <= $d && $b['checkout'] > $d) $used += $b['rooms'];
        if ($total - $used < $min) { $min = $total - $used; $at = $d; }
    }
    return [$min === PHP_INT_MAX ? $total : $min, $at];
}

// คืนข้อความ error ถ้าห้องไม่พอในคืนใดคืนหนึ่ง
function rooms_error($db, $tid, $total, $ci, $co, $n, $excludeId = 0) {
    [$left, $d] = rooms_left($db, $tid, $total, $ci, $co, $excludeId);
    return $left < $n ? 'ห้องไม่เพียงพอวันที่ ' . dth($d) . ' (เหลือ ' . max(0, $left) . ' ห้อง)' : null;
}

// ราคารวมทั้งการเข้าพัก (ทุกคืน × จำนวนห้อง) ใช้ราคาที่อนุมัติใน rates ถ้ามี ไม่งั้นใช้ราคาฐาน
function quote($db, $t, $ci, $co, $n) {
    $st = $db->prepare('SELECT rate_date,rate FROM rates WHERE room_type_id=? AND rate_date>=? AND rate_date<?');
    $st->execute([$t['id'], $ci, $co]); $m = [];
    foreach ($st as $r) $m[$r['rate_date']] = (float)$r['rate'];
    $tot = 0;
    for ($i = 0, $N = nights($ci, $co); $i < $N; $i++) { $d = date('Y-m-d', strtotime("$ci +$i day")); $tot += $m[$d] ?? (float)$t['base_rate']; }
    return $tot * $n;
}
