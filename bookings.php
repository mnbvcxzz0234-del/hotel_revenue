<?php
require_once 'config.php'; need_login(); $db = db();

const CHANNELS = ['direct'=>'จองตรง (โทร/เว็บ)','walkin'=>'Walk-in','booking'=>'Booking.com','agoda'=>'Agoda','expedia'=>'Expedia','other'=>'อื่น ๆ'];
const STATUS   = ['confirmed'=>'ยืนยันแล้ว','checked_in'=>'เข้าพักอยู่','completed'=>'เช็กเอาต์แล้ว','no_show'=>'ไม่มา','cancelled'=>'ยกเลิก'];
const ACTIVE   = ['confirmed','checked_in','completed'];            // สถานะที่นับว่าใช้ห้องและมีรายได้
const NEXT     = ['confirmed'=>['checked_in','no_show','cancelled'],'checked_in'=>['completed','confirmed'],
                  'completed'=>['checked_in'],'no_show'=>['confirmed'],'cancelled'=>['confirmed']];
class UE extends Exception {}                                         // ข้อความที่แสดงให้ผู้ใช้เห็นได้

function flash($m, $ok = true, $lines = []) { $_SESSION['flash'] = ['m'=>$m,'ok'=>$ok,'lines'=>$lines]; }
function nights($ci, $co) { return (int)round((strtotime($co) - strtotime($ci)) / 86400); }
function valid_date($d) { $x = DateTime::createFromFormat('Y-m-d', (string)$d); return $x && $x->format('Y-m-d') === $d; }
function dth($d) { return date('d/m/Y', strtotime($d)); }
function qs($over = []) { $q = array_merge($_GET, $over); unset($q['edit']); return '?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null)); }

function log_b($db, $id, $act, $detail = '') {
    $db->prepare('INSERT INTO booking_log(booking_id,action,by_user,detail) VALUES(?,?,?,?)')
       ->execute([$id, $act, $_SESSION['user'] ?? '', $detail]);
}

// คืนข้อความ error ถ้าห้องไม่พอในคืนใดคืนหนึ่ง (ไม่นับรายการ $excludeId)
function rooms_error($db, $tid, $total, $ci, $co, $n, $excludeId = 0) {
    $st = $db->prepare("SELECT checkin,checkout,rooms FROM bookings WHERE room_type_id=? AND status NOT IN ('cancelled','no_show') AND checkin<? AND checkout>? AND id<>?");
    $st->execute([$tid, $co, $ci, $excludeId]); $bk = $st->fetchAll();
    for ($i = 0, $N = nights($ci, $co); $i < $N; $i++) {
        $d = date('Y-m-d', strtotime("$ci +$i day")); $used = 0;
        foreach ($bk as $b) if ($b['checkin'] <= $d && $b['checkout'] > $d) $used += $b['rooms'];
        if ($used + $n > $total) return 'ห้องไม่เพียงพอวันที่ ' . dth($d) . ' (เหลือ ' . max(0, $total - $used) . ' ห้อง)';
    }
    return null;
}

// ตรวจข้อมูลการจอง ต้องเรียกภายใน transaction (ล็อกแถวประเภทห้องกันจองซ้อนพร้อมกัน)
// คืน [errors[], warnings[assoc], cleaned[]]
function check_booking($db, $in, $excludeId = 0, $checkRooms = true) {
    $err = []; $warn = [];
    $g = trim($in['guest'] ?? ''); $tid = (int)($in['type'] ?? 0); $ci = $in['checkin'] ?? ''; $co = $in['checkout'] ?? '';
    $n = (int)($in['rooms'] ?? 0); $rate = $in['rate'] ?? ''; $ch = $in['channel'] ?? 'direct'; $bd = trim($in['booked_on'] ?? '');
    $s = $db->prepare('SELECT * FROM room_types WHERE id=? FOR UPDATE'); $s->execute([$tid]); $t = $s->fetch();

    if ($g === '') $err[] = 'กรุณากรอกชื่อแขก'; elseif (mb_strlen($g) > 120) $err[] = 'ชื่อแขกยาวเกิน 120 ตัวอักษร';
    if (!$t) $err[] = 'ไม่พบประเภทห้อง';
    $datesOk = false;
    if (!valid_date($ci) || !valid_date($co)) $err[] = 'รูปแบบวันที่ไม่ถูกต้อง';
    elseif ($co <= $ci) $err[] = 'วันออกต้องหลังวันเข้า';
    elseif (nights($ci, $co) > 366) $err[] = 'พักได้ไม่เกิน 366 คืนต่อรายการ';
    else $datesOk = true;
    if ($n < 1 || $n > 999) $err[] = 'จำนวนห้องต้องเป็นตัวเลข 1 ขึ้นไป';
    if (!is_numeric($rate) || (float)$rate <= 0) $err[] = 'ราคา/คืนต้องมากกว่า 0';
    if (!isset(CHANNELS[$ch])) $err[] = 'ช่องทางจองไม่ถูกต้อง';
    if ($bd === '') $bd = date('Y-m-d');
    elseif (!valid_date($bd)) $err[] = 'รูปแบบวันที่จองไม่ถูกต้อง';
    elseif ($bd > date('Y-m-d')) $err[] = 'วันที่จองต้องไม่เกินวันนี้';

    if (!$err) {
        if ($checkRooms && ($e = rooms_error($db, $tid, (int)$t['total_rooms'], $ci, $co, $n, $excludeId))) $err[] = $e;
        $r = (float)$rate;
        if ($r < (float)$t['min_rate'] || $r > (float)$t['max_rate'])
            $warn['price'] = 'ราคา ฿' . number_format($r) . ' อยู่นอกช่วงที่ตั้งไว้ของ ' . $t['name'] . ' (฿' . number_format($t['min_rate']) . ' – ฿' . number_format($t['max_rate']) . ')';
        $d = $db->prepare("SELECT 1 FROM bookings WHERE lower(guest)=lower(?) AND room_type_id=? AND checkin=? AND checkout=? AND status<>'cancelled' AND id<>?");
        $d->execute([$g, $tid, $ci, $co, $excludeId]);
        if ($d->fetchColumn()) $warn['dup'] = 'มีรายการของแขกชื่อนี้ ห้องและวันที่เดียวกันอยู่แล้ว อาจเป็นการบันทึกซ้ำ';
    }
    return [$err, $warn, ['guest'=>$g,'tid'=>$tid,'ci'=>$ci,'co'=>$co,'n'=>$n,'rate'=>(float)$rate,'ch'=>$ch,'bd'=>$bd]];
}

function created_at($bd) { return $bd === date('Y-m-d') ? date('Y-m-d H:i:s') : $bd . ' 12:00:00'; }

function insert_booking($db, $d, $act = 'create') {
    $s = $db->prepare('INSERT INTO bookings(guest,room_type_id,checkin,checkout,rooms,rate_per_night,channel,created_at) VALUES(?,?,?,?,?,?,?,?) RETURNING id');
    $s->execute([$d['guest'],$d['tid'],$d['ci'],$d['co'],$d['n'],$d['rate'],$d['ch'],created_at($d['bd'])]);
    $id = (int)$s->fetchColumn();
    log_b($db, $id, $act, "{$d['guest']} {$d['ci']}→{$d['co']} x{$d['n']} @{$d['rate']}");
    return $id;
}

/* ---------- ดาวน์โหลดไฟล์ตัวอย่าง CSV ---------- */
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="bookings_template.csv"');
    echo "\xEF\xBB\xBF", "guest,room_type,checkin,checkout,rooms,rate,channel,booked_on\n";
    echo 'สมชาย ใจดี,Standard,', date('Y-m-d', strtotime('+7 day')), ',', date('Y-m-d', strtotime('+9 day')), ',1,1500,agoda,', date('Y-m-d'), "\n";
    exit;
}

/* ---------- ประมวลผล POST (ทำก่อนส่ง HTML เพื่อ redirect ได้) ---------- */
$form = null; $formErr = []; $formWarn = []; $editId = (int)($_GET['edit'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash('เซสชันหมดอายุ กรุณาลองใหม่', false); header('Location: bookings.php'); exit; }
    $a = $_POST['a'] ?? '';
    try {
        if ($a === 'add' || $a === 'update') {
            $id = $a === 'update' ? (int)($_POST['id'] ?? 0) : 0; $old = null;
            $db->beginTransaction();
            if ($id) { $s = $db->prepare('SELECT * FROM bookings WHERE id=? FOR UPDATE'); $s->execute([$id]); $old = $s->fetch(); if (!$old) throw new UE('ไม่พบรายการจอง'); }
            [$err, $warn, $d] = check_booking($db, $_POST, $id, !$old || in_array($old['status'], ACTIVE));
            if ($err || ($warn && empty($_POST['force']))) {
                $db->rollBack(); $form = $_POST; $formErr = $err; $formWarn = $err ? [] : $warn; $editId = $id;
            } elseif ($old) {
                $set = 'guest=?,room_type_id=?,checkin=?,checkout=?,rooms=?,rate_per_night=?,channel=?'; $p = [$d['guest'],$d['tid'],$d['ci'],$d['co'],$d['n'],$d['rate'],$d['ch']];
                if (substr($old['created_at'], 0, 10) !== $d['bd']) { $set .= ',created_at=?'; $p[] = created_at($d['bd']); }
                $p[] = $id; $db->prepare("UPDATE bookings SET $set WHERE id=?")->execute($p);
                $chg = [];
                foreach (['guest'=>$d['guest'],'room_type_id'=>$d['tid'],'checkin'=>$d['ci'],'checkout'=>$d['co'],'rooms'=>$d['n'],'rate_per_night'=>$d['rate'],'channel'=>$d['ch']] as $k => $v)
                    if ((string)(is_numeric($old[$k]) ? (float)$old[$k] : $old[$k]) !== (string)(is_numeric($v) ? (float)$v : $v)) $chg[] = "$k: {$old[$k]}→$v";
                log_b($db, $id, 'update', implode('; ', $chg) ?: 'ไม่มีการเปลี่ยนแปลง');
                $db->commit(); flash('แก้ไขรายการแล้ว'); header('Location: bookings.php'); exit;
            } else {
                insert_booking($db, $d); $db->commit(); flash('บันทึกแล้ว'); header('Location: bookings.php'); exit;
            }
        } elseif ($a === 'status') {
            $id = (int)($_POST['id'] ?? 0); $to = $_POST['to'] ?? '';
            $db->beginTransaction();
            $s = $db->prepare('SELECT * FROM bookings WHERE id=? FOR UPDATE'); $s->execute([$id]); $old = $s->fetch();
            if (!$old) throw new UE('ไม่พบรายการจอง');
            if (!in_array($to, NEXT[$old['status']] ?? [], true)) throw new UE('เปลี่ยนสถานะนี้ไม่ได้');
            if (in_array($to, ACTIVE) && !in_array($old['status'], ACTIVE)) {   // กู้คืน: ต้องมีห้องว่าง
                $t = $db->prepare('SELECT * FROM room_types WHERE id=? FOR UPDATE'); $t->execute([$old['room_type_id']]); $t = $t->fetch();
                if ($e = rooms_error($db, $old['room_type_id'], (int)$t['total_rooms'], $old['checkin'], $old['checkout'], (int)$old['rooms'], $id)) throw new UE('กู้คืนไม่ได้: ' . $e);
            }
            $db->prepare('UPDATE bookings SET status=? WHERE id=?')->execute([$to, $id]);
            log_b($db, $id, 'status', $old['status'] . '→' . $to);
            $db->commit(); flash('เปลี่ยนสถานะเป็น "' . STATUS[$to] . '" แล้ว'); parse_str((string)($_POST['back'] ?? ''), $bk); unset($bk['edit']); header('Location: bookings.php' . ($bk ? '?' . http_build_query($bk) : '')); exit;
        } elseif ($a === 'import') {
            $f = $_FILES['csv'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new UE('กรุณาเลือกไฟล์ CSV');
            if ($f['size'] > 1048576) throw new UE('ไฟล์ใหญ่เกิน 1 MB');
            $h = fopen($f['tmp_name'], 'r'); $hdr = fgetcsv($h);
            if (!$hdr) throw new UE('ไฟล์ว่างเปล่า');
            $hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $hdr[0]);
            $cols = array_flip(array_map(fn($x) => mb_strtolower(trim($x)), $hdr));
            foreach (['guest','room_type','checkin','checkout','rate'] as $c) if (!isset($cols[$c])) throw new UE("ไฟล์ขาดคอลัมน์ $c (ดูไฟล์ตัวอย่าง)");
            $tmap = []; foreach ($db->query('SELECT id,name FROM room_types') as $r) $tmap[mb_strtolower(trim($r['name']))] = (int)$r['id'];
            $db->beginTransaction(); $ok = 0; $bad = []; $line = 1; $cnt = 0;
            while (($r = fgetcsv($h)) !== false) {
                $line++; if (count($r) === 1 && trim($r[0]) === '') continue;
                if (++$cnt > 500) { $bad[] = 'เกิน 500 แถว ตัดส่วนที่เหลือออก'; break; }
                $g = fn($k, $def = '') => (isset($cols[$k], $r[$cols[$k]]) && trim($r[$cols[$k]]) !== '') ? trim($r[$cols[$k]]) : $def;
                $tn = mb_strtolower($g('room_type'));
                if (!isset($tmap[$tn])) { $bad[] = "แถว $line: ไม่พบประเภทห้อง '" . $g('room_type') . "'"; continue; }
                [$err, $warn, $d] = check_booking($db, ['guest'=>$g('guest'),'type'=>$tmap[$tn],'checkin'=>$g('checkin'),'checkout'=>$g('checkout'),
                    'rooms'=>$g('rooms', '1'),'rate'=>$g('rate'),'channel'=>mb_strtolower($g('channel', 'direct')),'booked_on'=>$g('booked_on')], 0, true);
                if ($err) { $bad[] = "แถว $line: " . implode(', ', $err); continue; }
                if (isset($warn['dup'])) { $bad[] = "แถว $line: ข้าม (ซ้ำกับรายการเดิม)"; continue; }
                insert_booking($db, $d, 'import'); $ok++;
            }
            fclose($h); $db->commit();
            flash("นำเข้าสำเร็จ $ok รายการ" . ($bad ? ' · ข้าม ' . count($bad) . ' แถว' : ''), $ok > 0 || !$bad, array_slice($bad, 0, 15));
            header('Location: bookings.php'); exit;
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (!($e instanceof UE)) error_log('bookings.php: ' . $e->getMessage());
        flash($e instanceof UE ? $e->getMessage() : 'บันทึกไม่สำเร็จ (ตรวจว่ารัน migration_bookings.sql แล้ว)', false);
        header('Location: bookings.php'); exit;
    }
}

/* ---------- เตรียมข้อมูลแสดงผล ---------- */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$types = $db->query('SELECT * FROM room_types ORDER BY id')->fetchAll();
$today = date('Y-m-d');

$editRow = null;
if ($editId && !$form) {
    $s = $db->prepare('SELECT * FROM bookings WHERE id=?'); $s->execute([$editId]); $editRow = $s->fetch();
    if (!$editRow) { $editId = 0; }
}
if ($form) $v = ['guest'=>$form['guest'] ?? '','type'=>$form['type'] ?? '','checkin'=>$form['checkin'] ?? '','checkout'=>$form['checkout'] ?? '','rooms'=>$form['rooms'] ?? 1,'rate'=>$form['rate'] ?? '','channel'=>$form['channel'] ?? 'direct','booked_on'=>$form['booked_on'] ?? $today];
elseif ($editRow) $v = ['guest'=>$editRow['guest'],'type'=>$editRow['room_type_id'],'checkin'=>$editRow['checkin'],'checkout'=>$editRow['checkout'],'rooms'=>$editRow['rooms'],'rate'=>(float)$editRow['rate_per_night'],'channel'=>$editRow['channel'],'booked_on'=>substr($editRow['created_at'], 0, 10)];
else $v = ['guest'=>'','type'=>$types[0]['id'] ?? '','checkin'=>$today,'checkout'=>date('Y-m-d', strtotime('+1 day')),'rooms'=>1,'rate'=>'','channel'=>'direct','booked_on'=>$today];
$isEdit = (bool)($editRow || ($form && $editId));

// แผนที่ราคาเริ่มต้น: ราคาที่อนุมัติไว้ในหน้า AI (rates) แล้วค่อยใช้ราคาฐาน
$base = []; foreach ($types as $t) $base[$t['id']] = (float)$t['base_rate'];
$rmap = []; $rs = $db->prepare('SELECT room_type_id,rate_date,rate FROM rates WHERE rate_date>=? AND rate_date<?'); $rs->execute([$today, date('Y-m-d', strtotime('+120 day'))]);
foreach ($rs as $r) $rmap[$r['room_type_id']][$r['rate_date']] = (float)$r['rate'];

// ตัวกรองรายการ
$w = ['1=1']; $p = [];
$q = trim($_GET['q'] ?? ''); if ($q !== '') { $w[] = 'b.guest ILIKE ?'; $p[] = '%' . addcslashes($q, '%_\\') . '%'; }
$fs = $_GET['status'] ?? ''; if (isset(STATUS[$fs])) { $w[] = 'b.status=?'; $p[] = $fs; }
$ft = (int)($_GET['type'] ?? 0); if ($ft) { $w[] = 'b.room_type_id=?'; $p[] = $ft; }
$fc = $_GET['channel'] ?? ''; if (isset(CHANNELS[$fc])) { $w[] = 'b.channel=?'; $p[] = $fc; }
$from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
if (valid_date($from)) { $w[] = 'b.checkout>?'; $p[] = $from; }
if (valid_date($to)) { $w[] = 'b.checkin<=?'; $p[] = $to; }
if (empty($_GET['past']) && !valid_date($from)) { $w[] = 'b.checkout>=?'; $p[] = $today; }
$W = implode(' AND ', $w);
$orders = ['in_asc'=>'b.checkin ASC, b.id ASC','in_desc'=>'b.checkin DESC, b.id DESC','new'=>'b.id DESC'];
$sort = isset($orders[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'in_asc';

$act = "b.status NOT IN ('cancelled','no_show')";
$sm = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(b.rooms*(b.checkout-b.checkin)) FILTER (WHERE $act),0) rn, COALESCE(SUM(b.rooms*(b.checkout-b.checkin)*b.rate_per_night) FILTER (WHERE $act),0) rev FROM bookings b WHERE $W");
$sm->execute($p); $sum = $sm->fetch();
$per = 25; $pages = max(1, (int)ceil($sum['c'] / $per)); $page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$st = $db->prepare("SELECT b.*, r.name tn, (b.checkout-b.checkin) nn FROM bookings b JOIN room_types r ON r.id=b.room_type_id WHERE $W ORDER BY {$orders[$sort]} LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($p); $rows = $st->fetchAll();

$logs = [];
if ($isEdit && $editId) { $l = $db->prepare('SELECT * FROM booking_log WHERE booking_id=? ORDER BY id DESC LIMIT 10'); $l->execute([$editId]); $logs = $l->fetchAll(); }

$badge = ['confirmed'=>'bg-blue-100 text-blue-700','checked_in'=>'bg-green-100 text-green-700','completed'=>'bg-slate-200 text-slate-700','no_show'=>'bg-amber-100 text-amber-700','cancelled'=>'bg-red-100 text-red-700'];
function sbtn($id, $to, $label, $cls, $confirm = '') {
    return '<form method="post" class="inline" ' . ($confirm ? 'onsubmit="return confirm(\'' . h($confirm) . '\')"' : '') . '><input type="hidden" name="t" value="' . csrf() . '"><input type="hidden" name="a" value="status"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="to" value="' . $to . '"><input type="hidden" name="back" value="' . h(http_build_query($_GET)) . '"><button class="' . $cls . ' hover:underline">' . $label . '</button></form>';
}
$in = 'border p-2 rounded w-full'; $lb = 'block text-xs text-slate-500 mb-1';
include 'header.php'; ?>

<?php if ($flash): ?>
<div class="<?= $flash['ok'] ? 'bg-green-100' : 'bg-red-100' ?> p-3 rounded mb-3 text-sm"><?= h($flash['m']) ?>
<?php if ($flash['lines']): ?><ul class="list-disc ml-5 mt-1 text-xs"><?php foreach ($flash['lines'] as $l): ?><li><?= h($l) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
<?php endif; ?>
<?php if ($formErr): ?><div class="bg-red-100 p-3 rounded mb-3 text-sm"><?php foreach ($formErr as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($formWarn): ?><div class="bg-amber-100 p-3 rounded mb-3 text-sm"><b>โปรดตรวจสอบก่อนบันทึก</b><?php foreach ($formWarn as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
<div class="mt-2 text-xs">ถ้าถูกต้องแล้วให้กด "บันทึกต่อ" ด้านล่างฟอร์ม หรือแก้ข้อมูลแล้วกดบันทึกใหม่</div></div><?php endif; ?>

<form method="post" id="bf" class="bg-white p-4 rounded-xl shadow grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
<input type="hidden" name="t" value="<?= csrf() ?>"><input type="hidden" name="a" value="<?= $isEdit ? 'update' : 'add' ?>"><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
<div class="col-span-2 md:col-span-4 font-semibold"><?= $isEdit ? 'แก้ไขการจอง #' . $editId : 'เพิ่มการจอง' ?></div>
<div class="col-span-2"><label class="<?= $lb ?>">ชื่อแขก</label><input name="guest" id="f_guest" value="<?= h($v['guest']) ?>" class="<?= $in ?>" maxlength="120" required <?= $isEdit ? '' : 'autofocus' ?>></div>
<div><label class="<?= $lb ?>">ประเภทห้อง</label><select name="type" id="f_type" class="<?= $in ?>"><?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id'] == $v['type'] ? 'selected' : '' ?>><?= h($t['name']) ?></option><?php endforeach; ?></select></div>
<div><label class="<?= $lb ?>">ช่องทางจอง</label><select name="channel" id="f_ch" class="<?= $in ?>"><?php foreach (CHANNELS as $k => $n): ?><option value="<?= $k ?>" <?= $k == $v['channel'] ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></div>
<div><label class="<?= $lb ?>">วันเข้าพัก</label><input type="date" name="checkin" id="f_ci" value="<?= h($v['checkin']) ?>" class="<?= $in ?>" required></div>
<div><label class="<?= $lb ?>">วันออก</label><input type="date" name="checkout" id="f_co" value="<?= h($v['checkout']) ?>" class="<?= $in ?>" required></div>
<div><label class="<?= $lb ?>">จำนวนห้อง</label><input type="number" name="rooms" id="f_n" value="<?= h($v['rooms']) ?>" min="1" max="999" class="<?= $in ?>" required></div>
<div><label class="<?= $lb ?>">ราคา/คืน (฿)</label><input type="number" name="rate" id="f_rate" value="<?= h($v['rate']) ?>" min="1" step="any" class="<?= $in ?>" required></div>
<div><label class="<?= $lb ?>">วันที่จอง</label><input type="date" name="booked_on" id="f_bd" value="<?= h($v['booked_on']) ?>" max="<?= $today ?>" class="<?= $in ?>"></div>
<div class="flex items-end"><div id="calc" class="text-sm text-slate-600 pb-2"></div></div>
<div class="col-span-2 md:col-span-4 flex gap-2 flex-wrap">
<button class="bg-indigo-600 text-white px-4 py-2 rounded flex-1"><?= $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มการจอง' ?></button>
<?php if ($formWarn): ?><button name="force" value="1" class="bg-amber-500 text-white px-4 py-2 rounded">บันทึกต่อ (รับทราบคำเตือน)</button><?php endif; ?>
<?php if ($isEdit): ?><a href="bookings.php" class="px-4 py-2 rounded border">ยกเลิกการแก้ไข</a><?php endif; ?></div>
<?php if ($logs): ?><div class="col-span-2 md:col-span-4 text-xs text-slate-500 border-t pt-2"><b>ประวัติรายการนี้</b><?php foreach ($logs as $l): ?><div><?= h(substr($l['at'], 0, 16)) ?> · <?= h($l['by_user']) ?> · <?= h($l['action']) ?> <?= h($l['detail']) ?></div><?php endforeach; ?></div><?php endif; ?>
</form>

<form method="get" class="bg-white p-3 rounded-xl shadow grid grid-cols-2 md:grid-cols-6 gap-2 mb-3 text-sm">
<input name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อแขก" class="<?= $in ?> col-span-2">
<select name="status" class="<?= $in ?>"><option value="">ทุกสถานะ</option><?php foreach (STATUS as $k => $n): ?><option value="<?= $k ?>" <?= $k == $fs ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
<select name="type" class="<?= $in ?>"><option value="">ทุกห้อง</option><?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id'] == $ft ? 'selected' : '' ?>><?= h($t['name']) ?></option><?php endforeach; ?></select>
<select name="channel" class="<?= $in ?>"><option value="">ทุกช่องทาง</option><?php foreach (CHANNELS as $k => $n): ?><option value="<?= $k ?>" <?= $k == $fc ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
<select name="sort" class="<?= $in ?>"><option value="in_asc" <?= $sort == 'in_asc' ? 'selected' : '' ?>>วันเข้า: ใกล้ → ไกล</option><option value="in_desc" <?= $sort == 'in_desc' ? 'selected' : '' ?>>วันเข้า: ไกล → ใกล้</option><option value="new" <?= $sort == 'new' ? 'selected' : '' ?>>บันทึกล่าสุดก่อน</option></select>
<div><label class="<?= $lb ?>">พักตั้งแต่</label><input type="date" name="from" value="<?= h($from) ?>" class="<?= $in ?>"></div>
<div><label class="<?= $lb ?>">ถึงวันที่</label><input type="date" name="to" value="<?= h($to) ?>" class="<?= $in ?>"></div>
<label class="flex items-end gap-2 pb-2 col-span-2"><input type="checkbox" name="past" value="1" <?= !empty($_GET['past']) ? 'checked' : '' ?>> รวมรายการที่เช็กเอาต์ไปแล้ว</label>
<div class="col-span-2 flex items-end gap-2"><button class="bg-slate-700 text-white px-4 py-2 rounded">กรอง</button><a href="bookings.php" class="px-3 py-2 border rounded">ล้าง</a></div>
</form>

<div class="flex flex-wrap items-center gap-3 mb-2 text-sm">
<span><b><?= number_format($sum['c']) ?></b> รายการ · <b><?= number_format($sum['rn']) ?></b> ห้อง-คืน · รายได้รวม <b>฿<?= number_format($sum['rev']) ?></b> <span class="text-xs text-slate-400">(ไม่นับยกเลิก/ไม่มา)</span></span>
<form method="post" enctype="multipart/form-data" class="ml-auto flex items-center gap-2 text-xs"><input type="hidden" name="t" value="<?= csrf() ?>"><input type="hidden" name="a" value="import">
<a href="bookings.php?template=1" class="underline text-indigo-700">ไฟล์ตัวอย่าง CSV</a><input type="file" name="csv" accept=".csv,text/csv" class="max-w-[180px]" required><button class="border px-3 py-1 rounded">นำเข้า CSV</button></form></div>

<div class="overflow-x-auto"><table class="w-full min-w-[1000px] bg-white rounded-xl shadow text-sm">
<tr class="text-left bg-slate-50"><th class="p-2">แขก</th><th>ห้อง</th><th>เข้า</th><th>ออก</th><th>คืน</th><th>ห้อง</th><th>ราคา/คืน</th><th>รวม</th><th>ช่องทาง</th><th>จองเมื่อ</th><th>สถานะ</th><th></th></tr>
<?php foreach ($rows as $r): $dim = in_array($r['status'], ['cancelled', 'no_show']); $tot = $r['nn'] * $r['rooms'] * $r['rate_per_night']; ?>
<tr class="border-t <?= $dim ? 'opacity-50' : '' ?>"><td class="p-2"><?= h($r['guest']) ?></td><td><?= h($r['tn']) ?></td><td><?= dth($r['checkin']) ?></td><td><?= dth($r['checkout']) ?></td>
<td><?= $r['nn'] ?></td><td><?= $r['rooms'] ?></td><td>฿<?= number_format($r['rate_per_night']) ?></td><td>฿<?= number_format($tot) ?></td>
<td><?= h(CHANNELS[$r['channel']] ?? $r['channel']) ?></td><td><?= dth(substr($r['created_at'], 0, 10)) ?></td>
<td><span class="px-2 py-0.5 rounded text-xs <?= $badge[$r['status']] ?? '' ?>"><?= h(STATUS[$r['status']] ?? $r['status']) ?></span></td>
<td class="whitespace-nowrap space-x-2 pr-2">
<?php $s = $r['status'];
if ($s == 'confirmed') echo sbtn($r['id'], 'checked_in', 'เช็กอิน', 'text-green-700') . sbtn($r['id'], 'no_show', 'ไม่มา', 'text-amber-700', 'ทำเครื่องหมายว่าแขกไม่มา?') . sbtn($r['id'], 'cancelled', 'ยกเลิก', 'text-red-600', 'ยกเลิกการจองของ ' . $r['guest'] . ' ใช่หรือไม่?');
elseif ($s == 'checked_in') echo sbtn($r['id'], 'completed', 'เช็กเอาต์', 'text-slate-700');
elseif ($s == 'cancelled' || $s == 'no_show') echo sbtn($r['id'], 'confirmed', 'กู้คืน', 'text-blue-700'); ?>
<a href="?<?= h(http_build_query(array_merge($_GET, ['edit' => $r['id']]))) ?>#bf" class="text-indigo-700 hover:underline">แก้ไข</a>
<button type="button" class="text-slate-600 hover:underline" data-copy='<?= h(json_encode(['guest'=>$r['guest'],'type'=>$r['room_type_id'],'ci'=>$r['checkin'],'co'=>$r['checkout'],'n'=>$r['rooms'],'rate'=>(float)$r['rate_per_night'],'ch'=>$r['channel']])) ?>'>คัดลอก</button></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="12" class="p-6 text-center text-slate-400">ไม่พบรายการ</td></tr><?php endif; ?></table></div>

<?php if ($pages > 1): ?><div class="flex gap-2 justify-center mt-3 text-sm flex-wrap"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= h(qs(['page' => $i])) ?>" class="px-3 py-1 rounded <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-white border' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>

<script>
const BASE=<?= json_encode($base, JSON_FORCE_OBJECT) ?>, RMAP=<?= json_encode($rmap, JSON_FORCE_OBJECT) ?>, TODAY='<?= $today ?>', EDIT=<?= $isEdit ? 'true' : 'false' ?>;
const $=i=>document.getElementById(i), ci=$('f_ci'), co=$('f_co'), ty=$('f_type'), rt=$('f_rate'), nr=$('f_n');
let touched = rt.value !== '';
const addDay=(d,k)=>{const x=new Date(d+'T00:00:00');x.setDate(x.getDate()+k);const p=n=>String(n).padStart(2,'0');return x.getFullYear()+'-'+p(x.getMonth()+1)+'-'+p(x.getDate())};
function calc(){
  const a=ci.value,b=co.value,n=Math.max(1,+nr.value||1),r=+rt.value||0;
  if(a&&b&&b>a){const d=Math.round((new Date(b)-new Date(a))/864e5);
    $('calc').textContent=d+' คืน × '+n+' ห้อง'+(r?' = ฿'+(d*n*r).toLocaleString():'');}
  else $('calc').textContent=(a&&b)?'⚠ วันออกต้องหลังวันเข้า':'';
}
function suggestRate(){ if(touched&&rt.value!=='')return; const t=ty.value; const v=(RMAP[t]&&RMAP[t][ci.value])||BASE[t]; if(v) rt.value=v; calc(); }
ci.addEventListener('change',()=>{co.min=addDay(ci.value,1); if(!co.value||co.value<=ci.value)co.value=addDay(ci.value,1); suggestRate(); calc();});
co.addEventListener('change',calc); nr.addEventListener('input',calc);
rt.addEventListener('input',()=>{touched=true;calc();}); ty.addEventListener('change',()=>{touched=false;suggestRate();});
co.min=addDay(ci.value||TODAY,1); if(!EDIT)suggestRate(); calc();
document.querySelectorAll('[data-copy]').forEach(b=>b.addEventListener('click',()=>{
  const d=JSON.parse(b.dataset.copy); $('f_guest').value=d.guest; ty.value=d.type; ci.value=d.ci<TODAY?TODAY:d.ci;
  co.min=addDay(ci.value,1); co.value=addDay(ci.value,Math.max(1,Math.round((new Date(d.co)-new Date(d.ci))/864e5))); nr.value=d.n; rt.value=d.rate; touched=true; $('f_ch').value=d.ch; $('f_bd').value=TODAY;
  calc(); $('bf').scrollIntoView({behavior:'smooth'}); $('f_guest').focus(); $('f_guest').select();}));
</script>
<?php include 'footer.php'; ?>
