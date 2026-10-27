<?php require_once 'config.php'; need_login(); $cur=basename($_SERVER['PHP_SELF']); ?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AI Hotel Revenue Manager</title><script src="https://cdn.tailwindcss.com"></script><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head>
<body class="bg-slate-100 text-slate-800"><nav class="bg-indigo-700 text-white px-4 py-3 flex gap-4 items-center flex-wrap">
<b>🏨 AI Hotel Revenue Manager</b>
<?php foreach(['index.php'=>'แดชบอร์ด','bookings.php'=>'การจอง','ai.php'=>'AI แนะนำราคา','settings.php'=>'ตั้งค่า'] as $u=>$n): ?>
<a href="<?=$u?>" class="<?=$cur==$u?'underline font-semibold':'opacity-80'?>"><?=$n?></a><?php endforeach; ?>
<a href="logout.php" class="ml-auto text-sm opacity-80">ออกจากระบบ (<?=h($_SESSION['user'])?>)</a></nav><main class="p-4 max-w-6xl mx-auto">
