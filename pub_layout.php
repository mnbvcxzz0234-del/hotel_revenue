<?php
// โครงหน้าร่วมสำหรับหน้าลูกค้า
function pub_top($title) {
    $me = cust(db()); $hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา';
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' · ' . h($hotel) . '</title><script src="https://cdn.tailwindcss.com"></script></head>';
    echo '<body class="bg-slate-100 text-slate-800 min-h-screen"><header class="bg-indigo-700 text-white px-4 py-3"><div class="max-w-2xl mx-auto flex items-center gap-3 flex-wrap">';
    echo '<a href="book.php" class="text-lg font-bold">🏨 ' . h($hotel) . '</a><div class="ml-auto flex items-center gap-3 text-sm">';
    if ($me) {
        echo '<a href="account.php" class="underline">บัญชีของฉัน</a><form method="post" action="customer_logout.php" class="inline"><input type="hidden" name="t" value="' . csrf() . '"><button class="opacity-80 hover:underline">ออกจากระบบ</button></form>';
    } else {
        echo '<a href="customer_login.php" class="underline">เข้าสู่ระบบ</a><a href="customer_register.php" class="bg-white text-indigo-700 px-3 py-1 rounded">สมัครสมาชิก</a>';
    }
    echo '</div></div></header><main class="max-w-2xl mx-auto p-4 space-y-4">';
}
function pub_bottom() { echo '</main></body></html>'; }
