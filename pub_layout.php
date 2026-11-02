<?php
// โครงหน้าร่วมสำหรับหน้าลูกค้า (ธีมฟ้าอ่อน มุมมน อ่านง่าย)
function pub_head($title, $hotel) {
    return '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' · ' . h($hotel) . '</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">'
        . '<script src="https://cdn.tailwindcss.com"></script>'
        . '<style>body{font-family:"Prompt",system-ui,sans-serif;background:linear-gradient(#fff,#f4f9fd 40%,#f4f9fd);color:#1e293b}'
        . 'input,select,textarea{font-family:inherit}.card{background:#fff;border:1px solid #e3eef7;border-radius:1.25rem;box-shadow:0 1px 3px rgba(74,159,216,.08)}'
        . '.btn{background:#4a9fd8;color:#fff;border-radius:.75rem;font-weight:600}.btn:hover{background:#3b8cc4}</style></head>';
}
function pub_nav($me, $hotel, $next = '') {
    $o  = '<header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-sky-100"><div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-6">';
    $o .= '<a href="book.php" class="text-2xl font-bold tracking-tight">' . h($hotel) . ' <span class="text-[#4a9fd8]">●</span></a>';
    $o .= '<span class="hidden lg:block text-sm text-slate-500 border-l pl-4">จองตรง ราคาดี ยืนยันโดยโรงแรม</span>';
    $o .= '<nav class="hidden md:flex items-center gap-6 font-medium ml-4"><a href="book.php" class="text-[#4a9fd8]">หน้าแรก</a><a href="book.php#rooms" class="hover:text-[#4a9fd8]">ห้องพัก</a><a href="book.php#track" class="hover:text-[#4a9fd8]">ตรวจสอบการจอง</a></nav>';
    $o .= '<div class="ml-auto flex items-center gap-3 text-sm">';
    if ($me) {
        $o .= '<a href="account.php" class="flex items-center gap-2 border border-sky-100 rounded-xl px-3 py-1.5 font-medium"><span class="w-7 h-7 rounded-lg bg-sky-100 text-[#4a9fd8] grid place-items-center">' . h(mb_substr($me['name'], 0, 1)) . '</span>' . h($me['name']) . '</a>'
            . '<form method="post" action="customer_logout.php" class="inline"><input type="hidden" name="t" value="' . csrf() . '"><button class="text-slate-500 hover:underline">ออกจากระบบ</button></form>';
    } else {
        $o .= '<a href="customer_login.php' . ($next ? '?next=' . h($next) : '') . '" class="font-medium px-3 py-2">เข้าสู่ระบบ</a><a href="customer_register.php" class="btn px-4 py-2">สมัครสมาชิก</a>';
    }
    return $o . '</div></div></header>';
}
function pub_footer($hotel, $tel = '') {
    return '<footer class="mt-12 border-t border-sky-100 bg-white"><div class="max-w-6xl mx-auto px-4 py-10 grid gap-8 md:grid-cols-3 text-sm">'
        . '<div><div class="text-xl font-bold">' . h($hotel) . ' <span class="text-[#4a9fd8]">●</span></div><p class="text-slate-500 mt-2">จองห้องพักออนไลน์กับโรงแรมโดยตรง ไม่ผ่านตัวกลาง</p></div>'
        . '<div><div class="font-semibold mb-2">บริการของเรา</div><ul class="space-y-1 text-slate-500"><li>ยืนยันการจองโดยเจ้าหน้าที่โรงแรม</li><li>ติดตามสถานะด้วยรหัสการจอง</li><li>ไม่มีการเรียกเก็บเงินตอนส่งคำขอ</li></ul></div>'
        . '<div><div class="font-semibold mb-2">ติดต่อโรงแรม</div><div class="text-slate-500">' . ($tel ? 'โทร ' . h($tel) : 'กรุณาติดต่อที่เคาน์เตอร์โรงแรม') . '</div></div>'
        . '</div><div class="border-t border-sky-50 py-4 text-center text-xs text-slate-400">© ' . date('Y') . ' ' . h($hotel) . '</div></footer>';
}
function pub_top($title) {
    $me = cust(db()); $hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา';
    echo pub_head($title, $hotel) . '<body class="min-h-screen">' . pub_nav($me, $hotel);
    echo '<main class="max-w-2xl mx-auto p-4 space-y-4 mt-6">';
}
function pub_bottom() { $hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา'; echo '</main>' . pub_footer($hotel, getenv('HOTEL_PHONE') ?: '') . '</body></html>'; }
