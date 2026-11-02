<?php
require_once 'config.php'; require_once 'booking_lib.php'; require_once 'pub_layout.php';
$db = db(); $next = in_array($_GET['next'] ?? $_POST['next'] ?? '', ['account.php', 'book.php'], true) ? ($_GET['next'] ?? $_POST['next']) : 'account.php';
if (cust($db)) { header("Location: $next"); exit; }
$err = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $err = 'หน้านี้หมดอายุ กรุณาลองใหม่อีกครั้ง';
    else {
        $email = mb_strtolower(trim((string)($_POST['email'] ?? ''))); $pw = (string)($_POST['password'] ?? ''); $now = date('Y-m-d H:i:s');
        $s = $db->prepare('SELECT * FROM customers WHERE lower(email)=?'); $s->execute([$email]); $c = $s->fetch();
        if ($c && $c['locked_until'] && $c['locked_until'] > $now) {
            $err = 'ลองผิดหลายครั้ง บัญชีถูกล็อกชั่วคราว กรุณารอ 15 นาทีแล้วลองใหม่';
        } elseif ($c && $c['active'] && password_verify($pw, $c['password_hash'])) {
            $db->prepare('UPDATE customers SET failed_count=0, locked_until=NULL WHERE id=?')->execute([$c['id']]);
            session_regenerate_id(true); $_SESSION['cid'] = (int)$c['id']; header("Location: $next"); exit;
        } else {
            if (!$c) password_hash($pw, PASSWORD_DEFAULT);   // ใช้เวลาใกล้เคียงกรณีไม่พบอีเมล
            if ($c) {
                $f = (int)$c['failed_count'] + 1;
                if ($f >= 5) $db->prepare('UPDATE customers SET failed_count=0, locked_until=? WHERE id=?')->execute([date('Y-m-d H:i:s', strtotime('+15 minutes')), $c['id']]);
                else $db->prepare('UPDATE customers SET failed_count=? WHERE id=?')->execute([$f, $c['id']]);
            }
            $err = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
$in = 'border p-2 rounded w-full'; $lb = 'block text-xs text-slate-500 mb-1';
pub_top('เข้าสู่ระบบ'); ?>
<form method="post" class="bg-white p-5 rounded-xl shadow space-y-3">
  <h1 class="text-lg font-bold">เข้าสู่ระบบ</h1>
  <?php if ($err): ?><div class="bg-red-100 p-3 rounded text-sm"><?= h($err) ?></div><?php endif; ?>
  <input type="hidden" name="t" value="<?= csrf() ?>"><input type="hidden" name="next" value="<?= h($next) ?>">
  <div><label class="<?= $lb ?>">อีเมล</label><input type="email" name="email" value="<?= h($email) ?>" class="<?= $in ?>" required autofocus autocomplete="email"></div>
  <div><label class="<?= $lb ?>">รหัสผ่าน</label><input type="password" name="password" class="<?= $in ?>" required autocomplete="current-password"></div>
  <button class="bg-indigo-600 text-white px-4 py-3 rounded w-full font-semibold">เข้าสู่ระบบ</button>
  <p class="text-sm text-center text-slate-500">ยังไม่มีบัญชี? <a href="customer_register.php" class="text-indigo-700 underline">สมัครสมาชิก</a><br>ลืมรหัสผ่าน? กรุณาติดต่อโรงแรมเพื่อขอรหัสผ่านใหม่</p>
</form>
<?php pub_bottom();
