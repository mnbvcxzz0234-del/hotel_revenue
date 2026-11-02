<?php
// โครงหน้าร่วมสำหรับหน้าลูกค้า (สไตล์ Booking.com: น้ำเงินเข้ม + เหลือง)
function pub_head($title, $hotel) {
    return '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' · ' . h($hotel) . '</title><script src="https://cdn.tailwindcss.com"></script>'
        . '<style>body{font-family:"Noto Sans Thai",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif}</style></head>';
}
function pub_nav($me, $hotel, $next = '') {
    $o  = '<header style="background:#003b95" class="text-white"><div class="max-w-5xl mx-auto px-4 pt-4 pb-3 flex items-center gap-3 flex-wrap">';
    $o .= '<a href="book.php" class="text-2xl font-bold tracking-tight">🏨 ' . h($hotel) . '</a>';
    $o .= '<div class="ml-auto flex items-center gap-3 text-sm font-semibold"><span>THB</span><span title="ไทย">🇹🇭</span>';
    if ($me) {
        $o .= '<a href="account.php" class="underline">บัญชีของฉัน</a><form method="post" action="customer_logout.php" class="inline"><input type="hidden" name="t" value="' . csrf() . '"><button class="opacity-90 hover:underline">ออกจากระบบ</button></form>';
    } else {
        $o .= '<a href="customer_register.php" class="bg-white text-[#006ce4] border border-white px-3 py-1.5 rounded font-medium hover:bg-slate-100">สมัคร</a>'
            . '<a href="customer_login.php' . ($next ? '?next=' . h($next) : '') . '" class="bg-white text-[#006ce4] border border-white px-3 py-1.5 rounded font-medium hover:bg-slate-100">เข้าสู่ระบบ</a>';
    }
    $o .= '</div></div>';
    $o .= '<div class="max-w-5xl mx-auto px-4 pb-4"><a href="book.php" class="inline-flex items-center gap-2 border border-white bg-white/10 rounded-full px-4 py-2 text-sm font-semibold">🛏️ การเข้าพัก</a></div></header>';
    return $o;
}
function pub_top($title) {
    $me = cust(db()); $hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา';
    echo pub_head($title, $hotel) . '<body class="min-h-screen text-slate-800" style="background:#f2f2f2">' . pub_nav($me, $hotel);
    echo '<main class="max-w-2xl mx-auto p-4 space-y-4">';
}
function pub_bottom() { echo '</main></body></html>'; }
