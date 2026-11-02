<?php
require_once 'config.php'; require_once 'booking_lib.php'; require_once 'pub_layout.php';
$db = db(); need_cust($db); $me = cust($db);
function cflash($m, $ok = true) { $_SESSION['cflash'] = [$m, $ok]; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { cflash('หน้านี้หมดอายุ กรุณาลองใหม่', false); header('Location: account.php'); exit; }
    $a = $_POST['a'] ?? '';
    try {
        if ($a === 'cancel') {                                      // ลูกค้ายกเลิกได้เฉพาะคำขอที่ยังรอยืนยัน
            $id = (int)($_POST['id'] ?? 0); $db->beginTransaction();
            $s = $db->prepare('SELECT * FROM bookings WHERE id=? AND customer_id=? FOR UPDATE'); $s->execute([$id, $me['id']]); $b = $s->fetch();
            if (!$b || $b['status'] !== 'pending') { $db->rollBack(); cflash('ยกเลิกไม่ได้ (ยืนยันแล้วหรือไม่พบรายการ) กรุณาติดต่อโรงแรม', false); }
            else { $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$id]); log_b($db, $id, 'customer_cancel', '', 'customer:' . $me['id']); $db->commit(); cflash('ยกเลิกคำขอจองแล้ว'); }
        } elseif ($a === 'password') {
            $cur = (string)($_POST['cur'] ?? ''); $new = (string)($_POST['new'] ?? ''); $new2 = (string)($_POST['new2'] ?? '');
            if (!password_verify($cur, $me['password_hash'])) cflash('รหัสผ่านเดิมไม่ถูกต้อง', false);
            elseif (strlen($new) < 8 || strlen($new) > 72) cflash('รหัสผ่านใหม่ต้องยาว 8–72 ตัวอักษร', false);
            elseif ($new !== $new2) cflash('รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน', false);
            else { $db->prepare('UPDATE customers SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]); cflash('เปลี่ยนรหัสผ่านแล้ว'); }
        }
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); error_log('account: ' . $e->getMessage()); cflash('ระบบขัดข้อง กรุณาลองใหม่', false); }
    header('Location: account.php'); exit;
}
$fl = $_SESSION['cflash'] ?? null; unset($_SESSION['cflash']);
$q = $db->prepare('SELECT b.*, r.name tn FROM bookings b JOIN room_types r ON r.id=b.room_type_id WHERE b.customer_id=? ORDER BY b.checkin DESC, b.id DESC LIMIT 50');
$q->execute([$me['id']]); $rows = $q->fetchAll();
$stTxt = ['pending'=>'รอโรงแรมยืนยัน','confirmed'=>'ยืนยันแล้ว','checked_in'=>'เข้าพักแล้ว','completed'=>'เสร็จสิ้น','no_show'=>'ไม่มาเข้าพัก','cancelled'=>'ยกเลิก'];
$bd = ['pending'=>'bg-purple-100 text-purple-700','confirmed'=>'bg-blue-100 text-blue-700','checked_in'=>'bg-green-100 text-green-700','completed'=>'bg-slate-200 text-slate-700','no_show'=>'bg-amber-100 text-amber-700','cancelled'=>'bg-red-100 text-red-700'];
$in = 'border border-sky-100 bg-sky-50/50 p-2.5 rounded-xl w-full'; $lb = 'block text-xs text-slate-500 mb-1';
pub_top('บัญชีของฉัน'); ?>
<?php if ($fl): ?><div class="<?= $fl[1] ? 'bg-green-100' : 'bg-red-100' ?> p-3 rounded text-sm"><?= h($fl[0]) ?></div><?php endif; ?>
<div class="card p-5 flex items-center gap-3">
  <div class="flex-1"><div class="font-bold"><?= h($me['name']) ?></div><div class="text-sm text-slate-500"><?= h($me['email']) ?> · <?= h($me['phone']) ?></div></div>
  <a href="book.php" class="btn px-4 py-2 rounded text-sm">จองห้องพัก</a>
</div>
<div class="card p-5 space-y-3">
  <h2 class="font-semibold">การจองของฉัน</h2>
  <?php if (!$rows): ?><p class="text-sm text-slate-500">ยังไม่มีรายการจอง</p><?php endif; ?>
  <?php foreach ($rows as $r): $nn = nights($r['checkin'], $r['checkout']); ?>
  <div class="border rounded-lg p-3 text-sm flex items-start gap-3">
    <div class="flex-1"><div class="font-semibold"><?= h($r['tn']) ?> · <?= $r['rooms'] ?> ห้อง</div>
      <div class="text-slate-500"><?= dth($r['checkin']) ?> – <?= dth($r['checkout']) ?> (<?= $nn ?> คืน) · ประมาณ ฿<?= number_format($nn * $r['rooms'] * $r['rate_per_night']) ?></div>
      <?php if ($r['ref']): ?><div class="text-xs text-slate-400">รหัส <?= h($r['ref']) ?></div><?php endif; ?></div>
    <div class="text-right space-y-1"><span class="px-2 py-0.5 rounded text-xs <?= $bd[$r['status']] ?? '' ?>"><?= h($stTxt[$r['status']] ?? $r['status']) ?></span>
      <?php if ($r['status'] === 'pending'): ?><form method="post" onsubmit="return confirm('ยกเลิกคำขอจองนี้ใช่หรือไม่?')"><input type="hidden" name="t" value="<?= csrf() ?>"><input type="hidden" name="a" value="cancel"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="text-red-600 text-xs hover:underline">ยกเลิกคำขอ</button></form>
      <?php elseif ($r['status'] === 'confirmed'): ?><div class="text-xs text-slate-400">ขอแก้ไข/ยกเลิก<br>กรุณาติดต่อโรงแรม</div><?php endif; ?></div>
  </div>
  <?php endforeach; ?>
</div>
<details class="card p-5"><summary class="cursor-pointer font-semibold">เปลี่ยนรหัสผ่าน</summary>
  <form method="post" class="grid gap-3 mt-3"><input type="hidden" name="t" value="<?= csrf() ?>"><input type="hidden" name="a" value="password">
    <div><label class="<?= $lb ?>">รหัสผ่านเดิม</label><input type="password" name="cur" class="<?= $in ?>" required autocomplete="current-password"></div>
    <div class="grid grid-cols-2 gap-3"><div><label class="<?= $lb ?>">รหัสผ่านใหม่ (8 ตัวขึ้นไป)</label><input type="password" name="new" class="<?= $in ?>" required autocomplete="new-password"></div>
    <div><label class="<?= $lb ?>">ยืนยันรหัสผ่านใหม่</label><input type="password" name="new2" class="<?= $in ?>" required autocomplete="new-password"></div></div>
    <button class="border border-sky-100 bg-sky-50/50 p-2.5 rounded-xl">บันทึกรหัสผ่านใหม่</button></form></details>
<?php pub_bottom();
