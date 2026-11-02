<?php
require_once 'config.php'; need_login(); $db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)($_POST['id'] ?? 0); $a = $_POST['a'] ?? '';
    if ($a === 'reset') {
        $pw = bin2hex(random_bytes(5));
        $u = $db->prepare('UPDATE customers SET password_hash=?, failed_count=0, locked_until=NULL WHERE id=?'); $u->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
        $_SESSION['aflash'] = $u->rowCount() ? "ตั้งรหัสผ่านชั่วคราวให้ลูกค้า #$id แล้ว: $pw (แสดงครั้งเดียว แจ้งลูกค้าและแนะนำให้เปลี่ยนรหัส)" : 'ไม่พบลูกค้า';
    } elseif ($a === 'toggle') {
        $db->prepare('UPDATE customers SET active = NOT active WHERE id=?')->execute([$id]); $_SESSION['aflash'] = 'อัปเดตสถานะบัญชีแล้ว';
    } elseif ($a === 'unlock') {
        $db->prepare('UPDATE customers SET failed_count=0, locked_until=NULL WHERE id=?')->execute([$id]); $_SESSION['aflash'] = 'ปลดล็อกแล้ว';
    }
    header('Location: customers.php'); exit;
}
$fl = $_SESSION['aflash'] ?? null; unset($_SESSION['aflash']);
$rows = $db->query('SELECT c.*, (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=c.id) bc FROM customers c ORDER BY c.id DESC LIMIT 200')->fetchAll();
$now = date('Y-m-d H:i:s');
function abtn($id, $a, $label, $cls = 'text-indigo-700', $confirm = '') {
    return '<form method="post" class="inline" ' . ($confirm ? 'onsubmit="return confirm(\'' . h($confirm) . '\')"' : '') . '><input type="hidden" name="t" value="' . csrf() . '"><input type="hidden" name="a" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '"><button class="' . $cls . ' hover:underline">' . $label . '</button></form>';
}
include 'header.php'; ?>
<?php if ($fl): ?><div class="bg-green-100 p-3 rounded mb-3 text-sm"><?= h($fl) ?></div><?php endif; ?>
<h2 class="font-bold mb-2">บัญชีลูกค้า (<?= count($rows) ?>)</h2>
<div class="overflow-x-auto"><table class="w-full min-w-[800px] bg-white rounded-xl shadow text-sm">
<tr class="text-left bg-slate-50"><th class="p-2">#</th><th>ชื่อ</th><th>อีเมล</th><th>เบอร์โทร</th><th>สมัครเมื่อ</th><th>จอง</th><th>สถานะ</th><th></th></tr>
<?php foreach ($rows as $c): $locked = $c['locked_until'] && $c['locked_until'] > $now; ?>
<tr class="border-t <?= $c['active'] ? '' : 'opacity-50' ?>"><td class="p-2"><?= $c['id'] ?></td><td><?= h($c['name']) ?></td><td><?= h($c['email']) ?></td><td><?= h($c['phone']) ?></td>
<td><?= h(substr($c['created_at'], 0, 10)) ?></td><td><?= $c['bc'] ?></td>
<td><?= !$c['active'] ? 'ปิดใช้งาน' : ($locked ? '<span class="text-amber-700">ถูกล็อก</span>' : 'ปกติ') ?></td>
<td class="whitespace-nowrap space-x-2 pr-2"><?= abtn($c['id'], 'reset', 'รีเซ็ตรหัสผ่าน', 'text-indigo-700', 'ตั้งรหัสผ่านชั่วคราวใหม่ให้ ' . $c['name'] . ' ใช่หรือไม่?') ?>
<?= $locked ? abtn($c['id'], 'unlock', 'ปลดล็อก') : '' ?>
<?= abtn($c['id'], 'toggle', $c['active'] ? 'ปิดบัญชี' : 'เปิดบัญชี', 'text-red-600', $c['active'] ? 'ปิดบัญชี ' . $c['name'] . ' ใช่หรือไม่?' : '') ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="p-6 text-center text-slate-400">ยังไม่มีลูกค้าสมัคร</td></tr><?php endif; ?></table></div>
<?php include 'footer.php'; ?>
