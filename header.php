<?php require_once 'config.php'; require_once 'theme.php'; need_login(); $cur=basename($_SERVER['PHP_SELF']);
$pend=(int)db()->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(); ?>
<!doctype html><html lang="th"><head><?=theme_head('AI Hotel Revenue Manager')?><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head>
<body class="min-h-screen"><?=theme_nav('Revenue Manager','ระบบบริหารราคาและรายได้โรงแรม',
 ['index.php'=>'แดชบอร์ด','bookings.php'=>'การจอง','ai.php'=>'AI แนะนำราคา','insights.php'=>'พยากรณ์','customers.php'=>'ลูกค้า','settings.php'=>'ตั้งค่า'],$cur,
 '<a href="home.php" class="hidden md:block" style="color:var(--mute)">ดูหน้าลูกค้า ↗</a><span class="flex items-center gap-2 border rounded-xl px-3 py-1.5 font-medium" style="border-color:var(--line)"><span class="w-7 h-7 rounded-lg grid place-items-center" style="background:var(--tint);color:var(--p)">'.h(mb_substr($_SESSION['user'],0,1)).'</span>'.h($_SESSION['user']).'</span><a href="logout.php" style="color:var(--mute)">ออกจากระบบ</a>',
 ['bookings.php'=>$pend])?><main class="p-4 max-w-6xl mx-auto mt-4">
