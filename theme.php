<?php
// ธีมกลางสไตล์ Lobbii: ฟ้าอ่อน มุมมน ฟอนต์ Prompt — ใช้ทั้งหน้าลูกค้าและหน้าผู้ดูแล
// ทับคลาส Tailwind เดิม (indigo/slate/rounded-xl) จึงทำให้ทุกหน้าที่มีอยู่เปลี่ยนโทนตามอัตโนมัติ
function theme_head($title) {
    return '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title>'
    . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">'
    . '<script src="https://cdn.tailwindcss.com"></script><style>' . theme_css() . '</style>';
}
function theme_css() { return <<<'CSS'
:root{--p:#4a9fd8;--pd:#3b8cc4;--tint:#eaf4fb;--ink:#1e2a3a;--mute:#64748b;--line:#e3eef7}
body{font-family:"Prompt",system-ui,sans-serif;background:linear-gradient(#fff,#f4f9fd 40%,#f4f9fd) fixed;color:var(--ink)}
input,select,textarea,button{font-family:inherit}
body .bg-slate-100{background:transparent}
body .bg-indigo-600,body .bg-indigo-700,body .bg-emerald-600,.btn{background:var(--p)!important;color:#fff!important;border-radius:.85rem;font-weight:600;transition:.15s}
body .bg-indigo-600:hover,body .bg-emerald-600:hover,.btn:hover{background:var(--pd)!important}
body .text-indigo-600,body .text-indigo-700{color:var(--p)}
body .rounded,body .rounded-xl,body .rounded-lg{border-radius:1rem}
body .bg-white.shadow,body .bg-white.rounded-xl,.card{background:#fff;border:1px solid var(--line);border-radius:1.25rem;box-shadow:0 1px 3px rgba(74,159,216,.08)}
body table{border-collapse:separate;border-spacing:0;overflow:hidden}
body .bg-slate-50{background:#f4f9fd}
body .border-t{border-color:var(--line)}
body input:not([type=checkbox]):not([type=radio]),body select,body textarea{border:1px solid #d5e6f3;border-radius:.85rem;background:#fff}
body input:focus,body select:focus,body textarea:focus{outline:2px solid #bfe0f5;border-color:var(--p)}
.chip{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;border:1px solid var(--line);border-radius:.9rem;background:#fff;font-weight:500;font-size:.9rem}
.chip.on{background:var(--p);color:#fff;border-color:var(--p)}
.tint{background:var(--tint);color:var(--pd);border-radius:999px;padding:.2rem .7rem;font-size:.8rem;font-weight:500}
.toggle{display:inline-flex;gap:.25rem;padding:.25rem;border:1px solid var(--line);background:var(--tint);border-radius:1rem}
.toggle a,.toggle span{padding:.45rem 1rem;border-radius:.8rem;font-weight:500;font-size:.9rem}
.toggle .on{background:var(--p);color:#fff}
.ph{background:linear-gradient(135deg,#eaf4fb,#d6ebf8);display:grid;place-items:center;font-size:2.5rem}
CSS; }
// แถบเมนูแบบ Lobbii (logo + tagline + ลิงก์ + ชิปผู้ใช้)
function theme_nav($brand, $tag, $links, $cur, $right, $badge = []) {
    $o = '<header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b" style="border-color:var(--line)"><div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-6">'
       . '<a href="' . h($links ? array_key_first($links) : '#') . '" class="text-2xl font-bold tracking-tight whitespace-nowrap">' . h($brand) . ' <span style="color:var(--p)">●</span></a>'
       . '<span class="hidden lg:block text-sm border-l pl-4" style="color:var(--mute)">' . h($tag) . '</span>'
       . '<nav class="flex items-center gap-5 font-medium ml-2 overflow-x-auto">';
    foreach ($links as $u => $n) {
        $on = basename($u) === $cur;
        $o .= '<a href="' . h($u) . '" class="whitespace-nowrap ' . ($on ? '' : 'hover:opacity-70') . '" style="' . ($on ? 'color:var(--p)' : '') . '">' . h($n)
            . (!empty($badge[$u]) ? ' <span class="text-xs px-1.5 rounded-full bg-amber-300 text-slate-900">' . (int)$badge[$u] . '</span>' : '') . '</a>';
    }
    return $o . '</nav><div class="ml-auto flex items-center gap-3 text-sm">' . $right . '</div></div></header>';
}
function theme_footer($brand, $tel = '', $lead = 'จองห้องพักออนไลน์กับโรงแรมโดยตรง ไม่ผ่านตัวกลาง') {
    return '<footer class="mt-14 bg-white border-t" style="border-color:var(--line)"><div class="max-w-6xl mx-auto px-4 py-10 grid gap-8 md:grid-cols-3 text-sm">'
     . '<div><div class="text-xl font-bold">' . h($brand) . ' <span style="color:var(--p)">●</span></div><p class="mt-2" style="color:var(--mute)">' . h($lead) . '</p></div>'
     . '<div><div class="font-semibold mb-2">บริการของเรา</div><ul class="space-y-1" style="color:var(--mute)"><li>ยืนยันการจองโดยเจ้าหน้าที่โรงแรม</li><li>ติดตามสถานะด้วยรหัสการจอง</li><li>ไม่เรียกเก็บเงินตอนส่งคำขอ</li></ul></div>'
     . '<div><div class="font-semibold mb-2">ติดต่อโรงแรม</div><div style="color:var(--mute)">' . ($tel ? 'โทร ' . h($tel) : 'กรุณาติดต่อที่เคาน์เตอร์โรงแรม') . '</div></div>'
     . '</div><div class="border-t py-4 text-center text-xs" style="border-color:var(--line);color:#94a3b8">© ' . date('Y') . ' ' . h($brand) . '</div></footer>';
}
