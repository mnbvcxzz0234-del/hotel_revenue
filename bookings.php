<?php include 'header.php'; $db=db(); $msg='';
if($_SERVER['REQUEST_METHOD']=='POST'&&csrf_ok()){
 if(isset($_POST['cancel'])){$db->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([(int)$_POST['cancel']]);}
 else{ $ci=$_POST['checkin'];$co=$_POST['checkout'];$tid=(int)$_POST['type'];$n=max(1,(int)$_POST['rooms']);
  if($co<=$ci)$msg='วันออกต้องหลังวันเข้า';
  else{$t=$db->prepare('SELECT * FROM room_types WHERE id=?');$t->execute([$tid]);$t=$t->fetch();
   $S=stats($ci,(int)((strtotime($co)-strtotime($ci))/86400));$ok=true;
   foreach($S as $s)if(($s['bt'][$tid]??0)+$n>$t['total_rooms'])$ok=false;
   if(!$ok)$msg='ห้องประเภทนี้ไม่เพียงพอในช่วงวันที่เลือก';
   else{$db->prepare('INSERT INTO bookings(guest,room_type_id,checkin,checkout,rooms,rate_per_night) VALUES(?,?,?,?,?,?)')->execute([$_POST['guest'],$tid,$ci,$co,$n,(float)$_POST['rate']]);$msg='บันทึกแล้ว';}}}}
$types=$db->query('SELECT * FROM room_types')->fetchAll();
$rows=$db->query("SELECT b.*,r.name tn FROM bookings b JOIN room_types r ON r.id=b.room_type_id WHERE checkout>=CURRENT_DATE ORDER BY checkin LIMIT 100")->fetchAll(); ?>
<?php if($msg):?><p class="bg-amber-100 p-2 rounded mb-3"><?=h($msg)?></p><?php endif;?>
<form method="post" class="bg-white p-4 rounded-xl shadow grid md:grid-cols-7 gap-2 mb-4"><input type="hidden" name="t" value="<?=csrf()?>">
<input name="guest" placeholder="ชื่อแขก" class="border p-2 rounded md:col-span-2" required>
<select name="type" class="border p-2 rounded"><?php foreach($types as $t):?><option value="<?=$t['id']?>"><?=h($t['name'])?></option><?php endforeach;?></select>
<input type="date" name="checkin" class="border p-2 rounded" required><input type="date" name="checkout" class="border p-2 rounded" required>
<input type="number" name="rooms" value="1" min="1" class="border p-2 rounded" title="จำนวนห้อง"><input type="number" name="rate" placeholder="ราคา/คืน" class="border p-2 rounded" required>
<button class="bg-indigo-600 text-white p-2 rounded md:col-span-7">เพิ่มการจอง</button></form>
<table class="w-full bg-white rounded-xl shadow text-sm"><tr class="text-left bg-slate-50"><th class="p-2">แขก</th><th>ห้อง</th><th>เข้า</th><th>ออก</th><th>จำนวน</th><th>ราคา/คืน</th><th>สถานะ</th><th></th></tr>
<?php foreach($rows as $r):?><tr class="border-t <?=$r['status']=='cancelled'?'opacity-40':''?>"><td class="p-2"><?=h($r['guest'])?></td><td><?=h($r['tn'])?></td><td><?=$r['checkin']?></td><td><?=$r['checkout']?></td><td><?=$r['rooms']?></td><td>฿<?=number_format($r['rate_per_night'])?></td><td><?=$r['status']?></td>
<td><?php if($r['status']=='confirmed'):?><form method="post"><input type="hidden" name="t" value="<?=csrf()?>"><button name="cancel" value="<?=$r['id']?>" class="text-red-600">ยกเลิก</button></form><?php endif;?></td></tr><?php endforeach;?></table>
<?php include 'footer.php'; ?>
