<?php
// หน้าลูกค้า (ไม่ต้องล็อกอิน): ตรวจห้องว่าง ส่งคำขอจอง และติดตามสถานะ
// คำขอจะเป็นสถานะ "รอยืนยัน" ยังไม่ล็อกห้อง เจ้าหน้าที่ยืนยันในหน้า bookings.php
require_once 'config.php'; require_once 'booking_lib.php'; require_once 'pub_layout.php';
class PubErr extends Exception {}
$db = db(); $today = date('Y-m-d'); $me = cust($db);
$hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา'; $hotelTel = getenv('HOTEL_PHONE') ?: '';
const MAX_ROOMS = 5; const MAX_NIGHTS = 30; const HOURLY_LIMIT = 5; const PENDING_CAP = 200;

function pub_dates_error($ci, $co, $n, $today) {
    if (!valid_date($ci) || !valid_date($co)) return 'กรุณาเลือกวันเข้าพักและวันออก';
    if ($ci < $today) return 'วันเข้าพักต้องไม่ใช่วันที่ผ่านมาแล้ว';
    if ($co <= $ci) return 'วันออกต้องหลังวันเข้าพัก';
    if (nights($ci, $co) > MAX_NIGHTS) return 'จองออนไลน์ได้ไม่เกิน ' . MAX_NIGHTS . ' คืน กรุณาติดต่อโรงแรม';
    if ($co > date('Y-m-d', strtotime('+365 day'))) return 'จองล่วงหน้าได้ไม่เกิน 1 ปี';
    if ($n < 1 || $n > MAX_ROOMS) return 'จองออนไลน์ได้ครั้งละ 1–' . MAX_ROOMS . ' ห้อง';
    return null;
}
function client_ip() {
    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))[0]);
    return filter_var($ip, FILTER_VALIDATE_IP) ? substr($ip, 0, 45) : '';
}

$ci = (string)($_POST['ci'] ?? $_GET['ci'] ?? ''); $co = (string)($_POST['co'] ?? $_GET['co'] ?? ''); $n = (int)($_POST['n'] ?? $_GET['n'] ?? 1);
$errs = []; $trackRes = null; $trackErr = '';
$pv = ['guest'=>'','phone'=>'','email'=>'','note'=>'','type'=>0];
if ($me) $pv = ['guest'=>$me['name'],'phone'=>$me['phone'],'email'=>$me['email'],'note'=>'','type'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? '';
    if (!csrf_ok()) { $errs[] = 'หน้านี้หมดอายุ กรุณาลองใหม่อีกครั้ง'; }
    elseif ($a === 'request') {
        if (!empty($_POST['website'])) { header('Location: book.php?done=00000000'); exit; }       // honeypot กันบอท
        $pv = ['guest'=>trim((string)($_POST['guest'] ?? '')),'phone'=>trim((string)($_POST['phone'] ?? '')),'email'=>trim((string)($_POST['email'] ?? '')),'note'=>trim((string)($_POST['note'] ?? '')),'type'=>(int)($_POST['type'] ?? 0)];
        if ($e = pub_dates_error($ci, $co, $n, $today)) $errs[] = $e;
        if ($pv['guest'] === '' || mb_strlen($pv['guest']) > 120) $errs[] = 'กรุณากรอกชื่อ (ไม่เกิน 120 ตัวอักษร)';
        $digits = preg_replace('/\D/', '', $pv['phone']);
        if (strlen($digits) < 8 || strlen($digits) > 15 || !preg_match('/^[0-9+\-\s()]+$/', $pv['phone'])) $errs[] = 'กรุณากรอกเบอร์โทรให้ถูกต้อง';
        if ($pv['email'] !== '' && (strlen($pv['email']) > 120 || !filter_var($pv['email'], FILTER_VALIDATE_EMAIL))) $errs[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        if (mb_strlen($pv['note']) > 500) $errs[] = 'ข้อความถึงโรงแรมยาวเกิน 500 ตัวอักษร';
        if (empty($_POST['consent'])) $errs[] = 'กรุณายอมรับการเก็บและใช้ข้อมูลเพื่อการจอง';
        if (!$errs) {
            try {
                $db->beginTransaction(); $ip = client_ip();
                if ($ip !== '') { $c = $db->prepare('SELECT COUNT(*) FROM bookings WHERE ip=? AND created_at>?'); $c->execute([$ip, date('Y-m-d H:i:s', strtotime('-1 hour'))]);
                    if ($c->fetchColumn() >= HOURLY_LIMIT) throw new PubErr('ส่งคำขอบ่อยเกินไป กรุณาลองใหม่ภายหลัง' . ($hotelTel ? " หรือโทร $hotelTel" : '')); }
                if ((int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn() >= PENDING_CAP) throw new PubErr('ขณะนี้ไม่สามารถรับคำขอออนไลน์ได้' . ($hotelTel ? " กรุณาโทร $hotelTel" : ' กรุณาติดต่อโรงแรมโดยตรง'));
                $s = $db->prepare('SELECT * FROM room_types WHERE id=? FOR UPDATE'); $s->execute([$pv['type']]); $t = $s->fetch();
                if (!$t) throw new PubErr('กรุณาเลือกประเภทห้อง');
                if ($e = rooms_error($db, $t['id'], (int)$t['total_rooms'], $ci, $co, $n)) throw new PubErr($e . ' กรุณาเลือกวันหรือห้องอื่น');
                $rate = round(quote($db, $t, $ci, $co, $n) / (nights($ci, $co) * $n), 2);   // ราคาเฉลี่ย/ห้อง/คืน คำนวณฝั่งเซิร์ฟเวอร์เสมอ
                $ref = strtoupper(bin2hex(random_bytes(4))); $now = date('Y-m-d H:i:s');
                $ins = $db->prepare("INSERT INTO bookings(guest,room_type_id,checkin,checkout,rooms,rate_per_night,status,channel,created_at,phone,email,note,consent_at,ref,ip,customer_id) VALUES(?,?,?,?,?,?,'pending','web',?,?,?,?,?,?,?,?) RETURNING id");
                $ins->execute([$pv['guest'], $t['id'], $ci, $co, $n, $rate, $now, $pv['phone'], $pv['email'] ?: null, $pv['note'] ?: null, $now, $ref, $ip ?: null, $me['id'] ?? null]);
                log_b($db, (int)$ins->fetchColumn(), 'guest_request', "ref $ref", 'guest');
                $db->commit(); header('Location: book.php?done=' . $ref); exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                if ($e instanceof PubErr) $errs[] = $e->getMessage();
                else { error_log('book.php: ' . $e->getMessage()); $errs[] = 'ขออภัย ระบบขัดข้อง กรุณาลองใหม่' . ($hotelTel ? " หรือโทร $hotelTel" : ''); }
            }
        }
    } elseif ($a === 'track') {
        $_SESSION['trk'] = ($_SESSION['trk'] ?? 0) + 1;
        $ref = strtoupper(trim((string)($_POST['ref'] ?? ''))); $dg = preg_replace('/\D/', '', (string)($_POST['phone'] ?? ''));
        if ($_SESSION['trk'] > 10) $trackErr = 'ค้นหาบ่อยเกินไป กรุณาลองใหม่ภายหลัง';
        elseif (!preg_match('/^[A-F0-9]{8}$/', $ref) || $dg === '') $trackErr = 'กรุณากรอกรหัสการจอง (8 ตัว) และเบอร์โทรที่ใช้จอง';
        else {
            $q = $db->prepare('SELECT b.*, r.name tn FROM bookings b JOIN room_types r ON r.id=b.room_type_id WHERE b.ref=?'); $q->execute([$ref]); $b = $q->fetch();
            if ($b && $dg === preg_replace('/\D/', '', (string)$b['phone'])) { $trackRes = $b; $_SESSION['trk'] = 0; }
            else $trackErr = 'ไม่พบรายการ กรุณาตรวจรหัสและเบอร์โทร';
        }
    }
}

// ค้นหาห้องว่าง
$results = null; $searchErr = '';
$doneRef = preg_match('/^[A-F0-9]{8}$/', (string)($_GET['done'] ?? '')) ? $_GET['done'] : '';
if (!$doneRef && $ci !== '' && $co !== '') {
    if ($e = pub_dates_error($ci, $co, $n, $today)) $searchErr = $e;
    else {
        $results = [];
        foreach ($db->query('SELECT * FROM room_types ORDER BY id') as $t) {
            [$left] = rooms_left($db, $t['id'], (int)$t['total_rooms'], $ci, $co);
            if ($left >= $n) $results[] = ['t' => $t, 'left' => $left, 'total' => quote($db, $t, $ci, $co, $n)];
        }
    }
}
$nn = ($ci && $co && valid_date($ci) && valid_date($co) && $co > $ci) ? nights($ci, $co) : 0;
$stTxt = ['pending'=>'รอโรงแรมยืนยัน','confirmed'=>'ยืนยันการจองแล้ว','checked_in'=>'เข้าพักแล้ว','completed'=>'เสร็จสิ้น','no_show'=>'ไม่มาเข้าพัก','cancelled'=>'ยกเลิก / โรงแรมไม่สามารถรับจองได้'];
$in = 'border border-sky-100 bg-sky-50/50 p-2.5 rounded-xl w-full outline-none focus:border-[#4a9fd8]'; $lb = 'block text-xs text-slate-500 mb-1';
?>
<?php
$hero = getenv('HERO_IMAGE_URL') ?: '';
$minRate = (float)$db->query('SELECT MIN(base_rate) FROM room_types')->fetchColumn();
$lbl = 'block text-xs text-slate-500 mb-1'; $fld = 'w-full bg-sky-50/60 border border-sky-100 rounded-xl px-3 py-2.5 outline-none focus:border-[#4a9fd8] font-medium';
?>
<?= pub_head('จองห้องพัก', $hotel) ?>
<body class="min-h-screen">
<?= pub_nav($me, $hotel, 'book.php') ?>
<section class="max-w-6xl mx-auto px-4 pt-10 pb-6 grid lg:grid-cols-2 gap-10 items-center">
  <div>
    <span class="inline-flex items-center gap-2 bg-sky-100 text-[#3b8cc4] text-sm font-medium rounded-full px-4 py-1.5">✨ จองตรงกับโรงแรม ไม่มีค่าธรรมเนียมแอบแฝง</span>
    <h1 class="mt-5 text-4xl md:text-6xl font-bold leading-tight">พักสบายใจ<br><span class="text-[#4a9fd8]">จองง่ายในไม่กี่คลิก</span></h1>
    <p class="mt-4 text-lg text-slate-600">เลือกวันเข้าพัก ดูห้องว่างและราคาแบบเรียลไทม์ แล้วส่งคำขอจอง เจ้าหน้าที่โรงแรมจะติดต่อกลับเพื่อยืนยัน</p>
    <form method="get" action="book.php#rooms" class="card mt-7 p-4 grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
      <div class="col-span-1"><label class="<?= $lbl ?>">เช็กอิน</label><input type="date" name="ci" id="ci" value="<?= h($ci) ?>" min="<?= $today ?>" class="<?= $fld ?>" required></div>
      <div class="col-span-1"><label class="<?= $lbl ?>">เช็กเอาต์</label><input type="date" name="co" id="co" value="<?= h($co) ?>" min="<?= $today ?>" class="<?= $fld ?>" required></div>
      <div class="col-span-1"><label class="<?= $lbl ?>">จำนวนห้อง</label><select name="n" class="<?= $fld ?>"><?php for ($i = 1; $i <= MAX_ROOMS; $i++): ?><option value="<?= $i ?>" <?= max(1, $n) == $i ? 'selected' : '' ?>><?= $i ?> ห้อง</option><?php endfor; ?></select></div>
      <button class="btn col-span-1 py-3">ค้นหา</button>
    </form>
    <div class="mt-6 pt-5 border-t border-sky-100 grid sm:grid-cols-3 gap-3 text-sm text-slate-600">
      <div>🛡️ ยืนยันโดยโรงแรมโดยตรง</div><div>🔎 ติดตามสถานะด้วยรหัสจอง</div><div>💳 ไม่เก็บเงินตอนส่งคำขอ</div></div>
  </div>
  <div class="card overflow-hidden shadow-lg">
    <div class="h-72 md:h-96 grid place-items-center text-7xl" style="<?= $hero ? 'background:url(' . h($hero) . ') center/cover' : 'background:linear-gradient(135deg,#dbeefb,#9fd0ef)' ?>"><?= $hero ? '' : '🏨' ?></div>
    <div class="p-5 flex items-end justify-between"><div><div class="text-xs font-semibold tracking-wide text-[#4a9fd8]">ROOM &amp; STAY</div><div class="font-semibold"><?= h($hotel) ?></div></div>
      <?php if ($minRate): ?><div class="text-right"><div class="text-xs text-slate-500">เริ่มต้นเพียง</div><div class="text-2xl font-bold text-[#4a9fd8]">฿<?= number_format($minRate) ?> <span class="text-sm font-normal text-slate-500">/คืน</span></div></div><?php endif; ?></div>
  </div>
</section>
<main id="rooms" class="max-w-3xl mx-auto p-4 space-y-4">

<?php if ($doneRef): ?>
<div class="card p-6 text-center space-y-2">
  <div class="text-3xl">✅</div><h1 class="text-lg font-bold">ส่งคำขอจองเรียบร้อยแล้ว</h1>
  <p class="text-sm text-slate-600">รหัสการจองของคุณ</p><div class="text-3xl font-mono font-bold tracking-widest text-[#4a9fd8]"><?= h($doneRef) ?></div>
  <p class="text-sm text-slate-600">โปรดจดรหัสนี้ไว้ ทางโรงแรมจะติดต่อกลับทางเบอร์โทรที่ให้ไว้เพื่อยืนยันการจอง<br><b>คำขอนี้ยังไม่ใช่การยืนยัน</b>และยังไม่ได้ชำระเงินใด ๆ<?= $hotelTel ? '<br>สอบถามด่วน โทร ' . h($hotelTel) : '' ?></p>
  <a href="book.php" class="inline-block mt-2 text-[#4a9fd8] underline text-sm">กลับหน้าจอง</a>
</div>
<?php else: ?>

<?php if ($errs): ?><div class="bg-red-100 p-3 rounded text-sm"><?php foreach ($errs as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($searchErr): ?><div class="bg-amber-100 p-3 rounded text-sm"><?= h($searchErr) ?></div><?php endif; ?>

<?php if ($results !== null): ?>
  <?php if (!$results): ?><div class="bg-amber-100 p-3 rounded text-sm">ขออภัย ไม่มีห้องว่างตามที่เลือก ลองเปลี่ยนวันที่หรือจำนวนห้อง<?= $hotelTel ? ' หรือโทร ' . h($hotelTel) : '' ?></div>
  <?php else: ?>
  <form method="post" class="card p-5 space-y-3">
    <input type="hidden" name="t" value="<?= csrf() ?>"><input type="hidden" name="a" value="request">
    <input type="hidden" name="ci" value="<?= h($ci) ?>"><input type="hidden" name="co" value="<?= h($co) ?>"><input type="hidden" name="n" value="<?= $n ?>">
    <div class="font-semibold">2. เลือกห้อง <span class="text-sm font-normal text-slate-500">(<?= dth($ci) ?> – <?= dth($co) ?> · <?= $nn ?> คืน · <?= $n ?> ห้อง)</span></div>
    <?php foreach ($results as $i => $r): $img = $r['t']['image_url'] ?? ''; ?>
    <label class="flex flex-col sm:flex-row gap-4 border border-sky-100 rounded-2xl p-3 cursor-pointer bg-white has-[:checked]:border-[#4a9fd8] has-[:checked]:bg-sky-50 has-[:checked]:ring-2 has-[:checked]:ring-sky-200">
      <div class="sm:w-40 h-28 rounded-xl grid place-items-center text-4xl shrink-0" style="<?= $img ? 'background:url(' . h($img) . ') center/cover' : 'background:linear-gradient(135deg,#dbeefb,#b7dcf3)' ?>"><?= $img ? '' : '🛏️' ?></div>
      <div class="flex-1 flex flex-col justify-between"><div class="flex items-start gap-2"><input type="radio" name="type" value="<?= $r['t']['id'] ?>" <?= ($pv['type'] ? $pv['type'] == $r['t']['id'] : $i === 0) ? 'checked' : '' ?> required class="mt-1.5 accent-[#4a9fd8]">
        <div><div class="font-semibold text-lg"><?= h($r['t']['name']) ?></div>
        <div class="text-xs text-slate-500"><?= $r['left'] <= 3 ? 'เหลือ <b class="text-red-500">' . $r['left'] . '</b> ห้อง' : 'ห้องว่างเพียงพอ' ?></div></div></div>
        <div class="text-right mt-2"><span class="text-xs text-slate-500">รวม <?= $nn ?> คืน</span> <span class="text-2xl font-bold text-[#4a9fd8]">฿<?= number_format($r['total']) ?></span>
        <div class="text-xs text-slate-400">เฉลี่ย ฿<?= number_format($r['total'] / ($nn * $n)) ?>/ห้อง/คืน</div></div></div>
    </label>
    <?php endforeach; ?>
    <p class="text-xs text-slate-500">ราคาข้างต้นเป็นราคาโดยประมาณ โรงแรมจะยืนยันราคาสุดท้ายพร้อมการจอง</p>

    <div class="font-semibold pt-2">3. ข้อมูลผู้จอง</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div><label class="<?= $lb ?>">ชื่อ-นามสกุล *</label><input name="guest" value="<?= h($pv['guest']) ?>" maxlength="120" class="<?= $in ?>" required></div>
      <div><label class="<?= $lb ?>">เบอร์โทร *</label><input name="phone" type="tel" value="<?= h($pv['phone']) ?>" maxlength="30" class="<?= $in ?>" required></div>
      <div class="sm:col-span-2"><label class="<?= $lb ?>">อีเมล (ถ้ามี)</label><input name="email" type="email" value="<?= h($pv['email']) ?>" maxlength="120" class="<?= $in ?>"></div>
      <div class="sm:col-span-2"><label class="<?= $lb ?>">ข้อความถึงโรงแรม (ถ้ามี)</label><textarea name="note" rows="2" maxlength="500" class="<?= $in ?>"><?= h($pv['note']) ?></textarea></div>
    </div>
    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
    <label class="flex gap-2 text-xs text-slate-600"><input type="checkbox" name="consent" value="1" required class="mt-0.5">
      <span>ข้าพเจ้ายินยอมให้ <?= h($hotel) ?> เก็บและใช้ชื่อ เบอร์โทร และอีเมลของข้าพเจ้าเพื่อติดต่อและยืนยันการจองนี้เท่านั้น</span></label>
    <button class="btn px-4 py-3 w-full" >ส่งคำขอจอง</button>
    <p class="text-xs text-slate-500">การส่งคำขอยังไม่ใช่การยืนยันการจองและไม่มีการเรียกเก็บเงิน</p>
  </form>
  <?php endif; ?>
<?php endif; ?>

<details id="track" class="card p-4" <?= ($trackRes || $trackErr) ? 'open' : '' ?>>
  <summary class="cursor-pointer font-semibold">ตรวจสอบสถานะการจอง</summary>
  <form method="post" class="grid grid-cols-2 gap-3 mt-3">
    <input type="hidden" name="t" value="<?= csrf() ?>"><input type="hidden" name="a" value="track">
    <div><label class="<?= $lb ?>">รหัสการจอง</label><input name="ref" maxlength="8" class="<?= $in ?> uppercase font-mono" required></div>
    <div><label class="<?= $lb ?>">เบอร์โทรที่ใช้จอง</label><input name="phone" type="tel" class="<?= $in ?>" required></div>
    <button class="col-span-2 border p-2 rounded">ตรวจสอบ</button>
  </form>
  <?php if ($trackErr): ?><p class="text-sm text-red-600 mt-2"><?= h($trackErr) ?></p><?php endif; ?>
  <?php if ($trackRes): $b = $trackRes; ?>
  <div class="mt-3 text-sm space-y-1 border-t pt-3">
    <div>สถานะ: <b><?= h($stTxt[$b['status']] ?? $b['status']) ?></b></div>
    <div><?= h($b['tn']) ?> · <?= $b['rooms'] ?> ห้อง · <?= dth($b['checkin']) ?> – <?= dth($b['checkout']) ?> (<?= nights($b['checkin'], $b['checkout']) ?> คืน)</div>
    <div>ราคาโดยประมาณ ฿<?= number_format(nights($b['checkin'], $b['checkout']) * $b['rooms'] * $b['rate_per_night']) ?></div>
  </div>
  <?php endif; ?>
</details>
<?php endif; ?>

<?php if ($hotelTel): ?><p class="text-center text-sm text-slate-500">สอบถามเพิ่มเติม โทร <?= h($hotelTel) ?></p><?php endif; ?>
</main>
<?= pub_footer($hotel, $hotelTel) ?>
<script>
const ci=document.getElementById('ci'),co=document.getElementById('co');
if(ci&&co){const add=(d,k)=>{const x=new Date(d+'T00:00:00');x.setDate(x.getDate()+k);const p=n=>String(n).padStart(2,'0');return x.getFullYear()+'-'+p(x.getMonth()+1)+'-'+p(x.getDate())};
ci.addEventListener('change',()=>{if(!ci.value)return;co.min=add(ci.value,1);if(!co.value||co.value<=ci.value)co.value=add(ci.value,1);});
if(ci.value)co.min=add(ci.value,1);}
</script>
</body></html>
