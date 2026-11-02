<?php
// โครงหน้าร่วมสำหรับหน้าลูกค้า (สไตล์ Lobbii: ฟ้าอ่อน มุมมน) — ใช้ theme.php
require_once __DIR__ . '/theme.php';
function pub_head($title, $hotel) { return '<!doctype html><html lang="th"><head>' . theme_head($title . ' · ' . $hotel) . '</head>'; }
function pub_nav($me, $hotel, $next = '') {
    if ($me) $r = '<a href="account.php" class="flex items-center gap-2 border rounded-xl px-3 py-1.5 font-medium" style="border-color:var(--line)"><span class="w-7 h-7 rounded-lg grid place-items-center" style="background:var(--tint);color:var(--p)">' . h(mb_substr($me['name'], 0, 1)) . '</span>' . h($me['name']) . '</a>'
        . '<form method="post" action="customer_logout.php" class="inline"><input type="hidden" name="t" value="' . csrf() . '"><button style="color:var(--mute)">ออกจากระบบ</button></form>';
    else $r = '<a href="customer_login.php' . ($next ? '?next=' . h($next) : '') . '" class="font-medium px-3 py-2">เข้าสู่ระบบ</a><a href="customer_register.php" class="btn px-4 py-2">สมัครสมาชิก</a>';
    return theme_nav($hotel, 'จองตรง ราคาดี ยืนยันโดยโรงแรม',
        ['home.php' => 'หน้าแรก', 'home.php#rooms' => 'ห้องพัก', 'book.php' => 'จองห้อง', 'account.php' => 'การจองของฉัน'], basename($_SERVER['PHP_SELF']), $r);
}
function pub_footer($hotel, $tel = '') { return theme_footer($hotel, $tel); }
function pub_top($title) {
    $me = cust(db()); $hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา';
    echo pub_head($title, $hotel) . '<body class="min-h-screen">' . pub_nav($me, $hotel);
    echo '<main class="max-w-3xl mx-auto p-4 space-y-4 mt-6">';
}
function pub_bottom() { $hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา'; echo '</main>' . pub_footer($hotel, getenv('HOTEL_PHONE') ?: '') . '</body></html>'; }
