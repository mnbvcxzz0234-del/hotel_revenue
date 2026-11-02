<?php require_once 'config.php'; $err='';
if($_SERVER['REQUEST_METHOD']=='POST'&&csrf_ok()){
 $s=db()->prepare('SELECT * FROM users WHERE username=?');$s->execute([$_POST['u']??'']);$u=$s->fetch();
 if($u&&password_verify($_POST['p']??'',$u['password_hash'])){session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['user']=$u['username'];header('Location: index.php');exit;}
 $err='ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><title>เข้าสู่ระบบ</title></head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center"><form method="post" class="bg-white p-8 rounded-xl shadow w-80 sm:w-96 space-y-3">
<h1 class="text-xl font-bold text-indigo-700">🏨 Revenue Manager</h1><p class="text-xs text-slate-500 -mt-2">สำหรับเจ้าหน้าที่โรงแรม</p><?php if($err):?><p class="text-red-600 text-sm"><?=h($err)?></p><?php endif;?>
<input type="hidden" name="t" value="<?=csrf()?>"><input name="u" placeholder="ชื่อผู้ใช้" class="border w-full p-2 rounded" required>
<input name="p" type="password" placeholder="รหัสผ่าน" class="border w-full p-2 rounded" required><button class="bg-indigo-600 text-white w-full p-2 rounded">เข้าสู่ระบบ</button>
<div class="text-sm text-center text-slate-500 pt-3 border-t">เป็นลูกค้า? <a href="customer_register.php" class="text-indigo-700 underline font-semibold">Register / สมัครสมาชิก</a><br><a href="customer_login.php" class="text-indigo-700 underline">เข้าสู่ระบบลูกค้า</a></div></form></body></html>
