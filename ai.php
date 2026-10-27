<?php require_once 'config.php'; need_login(); $db=db(); $days=14; $S=stats(date('Y-m-d'),$days);
$types=$db->query('SELECT * FROM room_types')->fetchAll();
$cs=$db->prepare('SELECT AVG(rate) FROM competitors WHERE rate_date=?');
$cur=$db->prepare('SELECT rate FROM rates WHERE room_type_id=? AND rate_date=?');
$plan=[];foreach($S as $d=>$s){$cs->execute([$d]);$comp=(float)$cs->fetchColumn();
 foreach($types as $t){$cur->execute([$t['id'],$d]);$c=$cur->fetchColumn();$c=$c!==false?$c:$t['base_rate'];
  [$sg,$why]=suggest($t,$d,$s['bt'][$t['id']]??0,$comp);$plan[]=['d'=>$d,'t'=>$t,'cur'=>$c,'sg'=>$sg,'why'=>$why];}}
if($_SERVER['REQUEST_METHOD']=='POST'&&csrf_ok()){
 if(isset($_POST['apply'])){$q=$db->prepare('REPLACE INTO rates(room_type_id,rate_date,rate) VALUES(?,?,?)');foreach($plan as $p)$q->execute([$p['t']['id'],$p['d'],$p['sg']]);header('Location: ai.php');exit;}
 if(isset($_POST['narrate'])){header('Content-Type: application/json');
  if(!GEMINI_KEY){echo json_encode(['text'=>'ยังไม่ได้ตั้งค่า GEMINI_KEY ใน config.php']);exit;}
  $lines=array_map(fn($p)=>"{$p['d']} {$p['t']['name']}: ปัจจุบัน {$p['cur']} → แนะนำ {$p['sg']} ({$p['why']})",array_slice($plan,0,28));
  $body=json_encode(['contents'=>[['parts'=>[['text'=>"คุณคือ Revenue Manager โรงแรม สรุปภาพรวมและกลยุทธ์ราคา 14 วันข้างหน้าเป็นภาษาไทย กระชับ ไม่เกิน 8 บรรทัด จากข้อมูล:\n".implode("\n",$lines)]]]],'generationConfig'=>['thinkingConfig'=>['thinkingLevel'=>'low']]]);
  require_once 'gemini.php';$r=gemini_post($body);echo json_encode(['text'=>$r['candidates'][0]['content']['parts'][0]['text']??($r['_err']??'เรียก Gemini ไม่สำเร็จ')]);exit;}}
include 'header.php'; ?><div class="flex gap-2 mb-3"><form method="post"><input type="hidden" name="t" value="<?=csrf()?>"><button name="apply" value="1" class="bg-indigo-600 text-white px-4 py-2 rounded">ใช้ราคาที่แนะนำทั้งหมด</button></form>
<button onclick="nar()" class="bg-emerald-600 text-white px-4 py-2 rounded">✨ ให้ Gemini สรุปกลยุทธ์</button></div>
<div id="nar" class="bg-white p-4 rounded-xl shadow mb-3 whitespace-pre-line hidden"></div>
<table class="w-full bg-white rounded-xl shadow text-sm"><tr class="text-left bg-slate-50"><th class="p-2">วันที่</th><th>ห้อง</th><th>ราคาปัจจุบัน</th><th>แนะนำ</th><th>เหตุผล</th></tr>
<?php foreach($plan as $p):$up=$p['sg']<=>$p['cur'];?><tr class="border-t"><td class="p-2"><?=$p['d']?></td><td><?=h($p['t']['name'])?></td><td>฿<?=number_format($p['cur'])?></td>
<td class="font-semibold <?=$up>0?'text-green-600':($up<0?'text-red-600':'')?>">฿<?=number_format($p['sg'])?> <?=$up>0?'▲':($up<0?'▼':'')?></td><td class="text-slate-500"><?=h($p['why'])?></td></tr><?php endforeach;?></table>
<script>async function nar(){const b=document.getElementById('nar');b.classList.remove('hidden');b.textContent='กำลังวิเคราะห์...';
const f=new FormData();f.append('t','<?=csrf()?>');f.append('narrate','1');try{const r=await fetch('ai.php',{method:'POST',body:f});const x=await r.text();try{b.textContent=JSON.parse(x).text}catch(_){b.textContent='เซิร์ฟเวอร์ตอบกลับผิดรูปแบบ: '+x.replace(/<[^>]*>/g,' ').trim().slice(0,400)}}catch(e){b.textContent='เกิดข้อผิดพลาด: '+e.message;}}</script>
<?php include 'footer.php'; ?>