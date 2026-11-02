<?php
require_once 'config.php'; require_once 'booking_lib.php'; require_once 'pub_layout.php';
$db = db(); if (cust($db)) { header('Location: account.php'); exit; }
$errs = []; $v = ['name'=>'','email'=>'','phone'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $errs[] = 'หน้านี้หมดอายุ กรุณาลองใหม่อีกครั้ง';
    elseif (!empty($_POST['website'])) { header('Location: customer_register.php'); exit; }          // honeypot
    else {
        $_SESSION['reg'] = ($_SESSION['reg'] ?? 0) + 1;
        $v = ['name'=>trim((string)($_POST['name'] ?? '')),'email'=>mb_strtolower(trim((string)($_POST['email'] ?? ''))),'phone'=>trim((string)($_POST['phone'] ?? ''))];
        $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
        if ($_SESSION['reg'] > 10) $errs[] = 'ลองหลายครั้งเกินไป กรุณาลองใหม่ภายหลัง';
        if ($v['name'] === '' || mb_strlen($v['name']) > 120) $errs[] = 'กรุณากรอกชื่อ-นามสกุล';
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 120) $errs[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        $dg = preg_replace('/\D/', '', $v['phone']);
        if (strlen($dg) < 8 || strlen($dg) > 15 || !preg_match('/^[0-9+\-\s()]+$/', $v['phone'])) $errs[] = 'กรุณากรอกเบอร์โทรให้ถูกต้อง';
        if (strlen($pw) < 8) $errs[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
        elseif (strlen($pw) > 72) $errs[] = 'รหัสผ่านยาวเกินไป (ไม่เกิน 72 ตัวอักษร)';
        elseif ($pw !== $pw2) $errs[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
        if (empty($_POST['consent'])) $errs[] = 'กรุณายอมรับการเก็บและใช้ข้อมูลส่วนบุคคล';
        if (!$errs) {
            try {
                $now = date('Y-m-d H:i:s');
                $s = $db->prepare('INSERT INTO customers(name,email,phone,password_hash,consent_at,created_at) VALUES(?,?,?,?,?,?) RETURNING id');
                $s->execute([$v['name'], $v['email'], $v['phone'], password_hash($pw, PASSWORD_DEFAULT), $now, $now]);
                session_regenerate_id(true); $_SESSION['cid'] = (int)$s->fetchColumn(); $_SESSION['reg'] = 0;
                header('Location: account.php'); exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23505') $errs[] = 'ไม่สามารถสมัครด้วยอีเมลนี้ได้ หากเคยสมัครแล้วให้เข้าสู่ระบบ';
                else { error_log('register: ' . $e->getMessage()); $errs[] = 'ระบบขัดข้อง กรุณาลองใหม่ภายหลัง'; }
            }
        }
    }
}
$in = 'border p-2 rounded w-full'; $lb = 'block text-xs text-slate-500 mb-1';
pub_top('สมัครสมาชิก'); ?>
<form method="post" class="bg-white p-5 rounded-xl shadow space-y-3">
  <h1 class="text-lg font-bold">สมัครสมาชิก</h1>
  <?php if ($errs): ?><div class="bg-red-100 p-3 rounded text-sm"><?php foreach ($errs as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>
  <input type="hidden" name="t" value="<?= csrf() ?>">
  <div><label class="<?= $lb ?>">ชื่อ-นามสกุล *</label><input name="name" value="<?= h($v['name']) ?>" maxlength="120" class="<?= $in ?>" required autofocus></div>
  <div><label class="<?= $lb ?>">อีเมล * (ใช้เป็นชื่อผู้ใช้)</label><input type="email" name="email" value="<?= h($v['email']) ?>" maxlength="120" class="<?= $in ?>" required autocomplete="email"></div>
  <div><label class="<?= $lb ?>">เบอร์โทร *</label><input type="tel" name="phone" value="<?= h($v['phone']) ?>" maxlength="30" class="<?= $in ?>" required autocomplete="tel"></div>
  <div class="grid grid-cols-2 gap-3">
    <div><label class="<?= $lb ?>">รหัสผ่าน * (อย่างน้อย 8 ตัว)</label><input type="password" name="password" class="<?= $in ?>" required autocomplete="new-password"></div>
    <div><label class="<?= $lb ?>">ยืนยันรหัสผ่าน *</label><input type="password" name="password2" class="<?= $in ?>" required autocomplete="new-password"></div>
  </div>
  <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
  <label class="flex gap-2 text-xs text-slate-600"><input type="checkbox" name="consent" value="1" required class="mt-0.5"><span>ข้าพเจ้ายินยอมให้เก็บและใช้ชื่อ อีเมล และเบอร์โทร เพื่อสร้างบัญชี ติดต่อ และจัดการการจองของข้าพเจ้า</span></label>
  <button class="bg-indigo-600 text-white px-4 py-3 rounded w-full font-semibold">สมัครสมาชิก</button>
  <p class="text-sm text-center text-slate-500">มีบัญชีอยู่แล้ว? <a href="customer_login.php" class="text-indigo-700 underline">เข้าสู่ระบบ</a></p>
</form>
<?php pub_bottom();
