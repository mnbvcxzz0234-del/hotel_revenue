<?php include 'header.php'; $db=db();
if($_SERVER['REQUEST_METHOD']=='POST'&&csrf_ok()){
 if(isset($_POST['rt'])){$db->prepare('INSERT INTO room_types(name,total_rooms,base_rate,min_rate,max_rate) VALUES(?,?,?,?,?)')->execute([$_POST['name'],(int)$_POST['n'],$_POST['base'],$_POST['min'],$_POST['max']]);}
 if(isset($_POST['cp'])){$db->prepare('INSERT INTO competitors(rate_date,hotel,rate) VALUES(?,?,?)')->execute([$_POST['date'],$_POST['hotel'],$_POST['rate']]);}}
$types=$db->query('SELECT * FROM room_types')->fetchAll();$cps=$db->query('SELECT * FROM competitors ORDER BY rate_date DESC LIMIT 30')->fetchAll(); $in='border p-2 rounded'; ?>
<h2 class="font-bold mb-2">ประเภทห้อง</h2>
<table class="w-full bg-white rounded-xl shadow text-sm mb-2"><tr class="text-left bg-slate-50"><th class="p-2">ชื่อ</th><th>จำนวนห้อง</th><th>ราคาฐาน</th><th>ต่ำสุด</th><th>สูงสุด</th></tr>
<?php foreach($types as $t):?><tr class="border-t"><td class="p-2"><?=h($t['name'])?></td><td><?=$t['total_rooms']?></td><td><?=number_format($t['base_rate'])?></td><td><?=number_format($t['min_rate'])?></td><td><?=number_format($t['max_rate'])?></td></tr><?php endforeach;?></table>
<form method="post" class="flex gap-2 flex-wrap mb-6"><input type="hidden" name="t" value="<?=csrf()?>"><input type="hidden" name="rt" value="1">
<input name="name" placeholder="ชื่อ" class="<?=$in?>" required><input name="n" type="number" placeholder="จำนวนห้อง" class="<?=$in?>" required><input name="base" type="number" placeholder="ราคาฐาน" class="<?=$in?>" required><input name="min" type="number" placeholder="ต่ำสุด" class="<?=$in?>" required><input name="max" type="number" placeholder="สูงสุด" class="<?=$in?>" required><button class="bg-indigo-600 text-white px-4 rounded">เพิ่ม</button></form>
<h2 class="font-bold mb-2">ราคาคู่แข่ง</h2>
<form method="post" class="flex gap-2 flex-wrap mb-3"><input type="hidden" name="t" value="<?=csrf()?>"><input type="hidden" name="cp" value="1">
<input type="date" name="date" class="<?=$in?>" required><input name="hotel" placeholder="โรงแรมคู่แข่ง" class="<?=$in?>" required><input name="rate" type="number" placeholder="ราคา/คืน" class="<?=$in?>" required><button class="bg-indigo-600 text-white px-4 rounded">บันทึก</button></form>
<table class="w-full bg-white rounded-xl shadow text-sm"><?php foreach($cps as $c):?><tr class="border-t"><td class="p-2"><?=$c['rate_date']?></td><td><?=h($c['hotel'])?></td><td>฿<?=number_format($c['rate'])?></td></tr><?php endforeach;?></table>
<?php include 'footer.php'; ?>
