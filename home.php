<?php
// หน้าแรกลูกค้า (สไตล์ Lobbii): hero + ค้นหาวันเข้าพัก + การ์ดห้องพร้อมราคาวันนี้
require_once 'config.php'; require_once 'booking_lib.php'; require_once 'pub_layout.php';
$db = db(); $me = cust($db); $today = date('Y-m-d'); $tom = date('Y-m-d', strtotime('+1 day'));
$hotel = getenv('HOTEL_NAME') ?: 'โรงแรมของเรา'; $tel = getenv('HOTEL_PHONE') ?: '';
$st = $db->prepare('SELECT rt.*, (SELECT rate FROM rates r WHERE r.room_type_id=rt.id AND r.rate_date=?) AS today_rate FROM room_types rt ORDER BY rt.base_rate');
$st->execute([$today]); $types = $st->fetchAll();
foreach ($types as &$t) { $t['price'] = (float)($t['today_rate'] ?? $t['base_rate']); [$t['left']] = rooms_left($db, $t['id'], $t['total_rooms'], $today, $tom); } unset($t);
$from = $types ? min(array_column($types, 'price')) : 0; $hero = null; foreach ($types as $t) if (!empty($t['image_url'])) { $hero = $t; break; }
echo pub_head('หน้าแรก', $hotel) . '<body class="min-h-screen">' . pub_nav($me, $hotel); ?>
<main class="max-w-6xl mx-auto px-4">
<section class="grid lg:grid-cols-2 gap-10 items-center py-12">
 <div>
  <span class="tint inline-block mb-5">✨ จองตรงกับโรงแรม ราคาดีที่สุด</span>
  <h1 class="text-5xl md:text-6xl font-bold leading-tight">พักสบาย<br><span style="color:var(--p)">ราคาดี จองตรง</span></h1>
  <p class="mt-6 text-lg" style="color:var(--mute)">เลือกวันเข้าพักแล้วดูห้องว่างได้ทันที ราคาอัปเดตตามความต้องการจริง ไม่มีค่าธรรมเนียมแอบแฝง ยืนยันโดยเจ้าหน้าที่โรงแรม</p>
  <form action="book.php" class="card mt-8 p-3 flex flex-wrap gap-2 items-center">
   <label class="flex-1 min-w-[9rem] px-2 text-xs" style="color:var(--mute)">เช็กอิน<input type="date" name="ci" value="<?=$today?>" min="<?=$today?>" class="block w-full text-base text-slate-800 border-0 p-1"></label>
   <label class="flex-1 min-w-[9rem] px-2 text-xs" style="color:var(--mute)">เช็กเอาต์<input type="date" name="co" value="<?=$tom?>" min="<?=$tom?>" class="block w-full text-base text-slate-800 border-0 p-1"></label>
   <label class="w-24 px-2 text-xs" style="color:var(--mute)">ห้อง<input type="number" name="n" value="1" min="1" max="5" class="block w-full text-base text-slate-800 border-0 p-1"></label>
   <button class="btn px-6 py-3">ค้นหาห้องว่าง</button>
  </form>
  <div class="mt-6 pt-5 border-t grid sm:grid-cols-3 gap-3 text-sm" style="border-color:var(--line);color:var(--mute)"><span>🛎 ยืนยันโดยโรงแรมโดยตรง</span><span>🔒 ไม่เก็บเงินตอนส่งคำขอ</span><span>🔎 ติดตามสถานะด้วยรหัสจอง</span></div>
 </div>
 <div class="card overflow-hidden" style="border-radius:1.75rem">
  <?php if($hero):?><img src="<?=h($hero['image_url'])?>" alt="<?=h($hero['name'])?>" class="w-full h-96 object-cover"><?php else:?><div class="ph h-96">🛏️</div><?php endif;?>
  <div class="p-5 flex items-end justify-between"><div><div class="text-xs font-semibold" style="color:var(--p)">ROOM TYPES</div><div class="font-semibold"><?=count($types)?> ประเภทห้องให้เลือก</div></div>
  <div class="text-right"><div class="text-xs" style="color:var(--mute)">เริ่มต้นเพียง</div><div class="text-xl font-bold" style="color:var(--p)">฿<?=number_format($from)?> <span class="text-sm font-normal text-slate-500">/คืน</span></div></div></div>
 </div>
</section>
<section id="rooms" class="pb-6">
 <div class="flex items-center justify-between mb-4"><div><h2 class="text-2xl font-bold">ห้องพักของเรา</h2><p class="text-sm" style="color:var(--mute)">ราคาและห้องว่างสำหรับคืนนี้</p></div><span class="chip on">ทั้งหมด <?=count($types)?> ประเภท</span></div>
 <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
 <?php foreach($types as $t): $full=$t['left']<=0; ?>
  <div class="card overflow-hidden flex flex-col">
   <div class="relative"><?php if(!empty($t['image_url'])):?><img src="<?=h($t['image_url'])?>" alt="" class="w-full h-44 object-cover"><?php else:?><div class="ph h-44">🛏️</div><?php endif;?>
    <span class="absolute top-3 left-3 text-xs bg-white/90 rounded-lg px-2 py-1"><?=$full?'เต็มคืนนี้':($t['left']<=3?'เหลือ '.(int)$t['left'].' ห้อง':'ว่างอยู่')?></span></div>
   <div class="p-4 flex flex-col flex-1"><div class="font-semibold text-lg"><?=h($t['name'])?></div>
    <div class="mt-auto pt-4 border-t mt-4" style="border-color:var(--line)"><span class="text-sm" style="color:var(--mute)">ราคา</span> <span class="text-2xl font-bold" style="color:var(--p)">฿<?=number_format($t['price'])?></span> <span class="text-sm" style="color:var(--mute)">/คืน</span></div>
    <a href="book.php?ci=<?=$today?>&co=<?=$tom?>&n=1" class="mt-3 text-center py-2.5 rounded-xl font-semibold <?=$full?'opacity-50 pointer-events-none':''?>" style="background:var(--tint);color:var(--pd)">＋ ดูห้องว่าง & จอง</a></div>
  </div>
 <?php endforeach; if(!$types):?><p style="color:var(--mute)">ยังไม่มีข้อมูลห้องพัก</p><?php endif;?>
 </div>
</section></main>
<?=pub_footer($hotel,$tel)?></body></html>
