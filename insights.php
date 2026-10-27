<?php require_once 'config.php'; need_login(); $db=db();
/* ---------- 1) Demand forecast (pickup model, ไม่ใช้ LLM) ---------- */
$cap=(int)$db->query('SELECT COALESCE(SUM(total_rooms),0) FROM room_types')->fetchColumn();
$bk=$db->query("SELECT checkin,checkout,rooms,rate_per_night,created_at FROM bookings WHERE status<>'cancelled' AND checkout>=CURRENT_DATE - INTERVAL '70 days'")->fetchAll();
// ห้องที่ถูกจองสำหรับคืน $d โดยนับเฉพาะการจองที่สร้างก่อนหรือเท่ากับ $asof
function occ_asof($bk,$d,$asof){$o=0;foreach($bk as $b)if($b['checkin']<=$d&&$b['checkout']>$d&&substr($b['created_at'],0,10)<=$asof)$o+=$b['rooms'];return $o;}
$today=date('Y-m-d');$F=[];
for($i=0;$i<30;$i++){
 $d=date('Y-m-d',strtotime("$today +$i day"));$otb=occ_asof($bk,$d,$today);$pk=[];
 for($k=1;$k<=8;$k++){$hd=date('Y-m-d',strtotime("$d -".(7*$k).' day'));if($hd>=$today)continue;
  $fin=occ_asof($bk,$hd,$hd);$then=occ_asof($bk,$hd,date('Y-m-d',strtotime("$hd -$i day")));$pk[]=max(0,$fin-$then);}
 $n=count($pk);$avg=$n?array_sum($pk)/$n:0;
 $F[$d]=['otb'=>$otb,'fc'=>min($cap,$otb+$avg),'lo'=>$n?min($cap,$otb+min($pk)):$otb,'hi'=>$n?min($cap,$otb+max($pk)):$otb,'n'=>$n,'dow'=>date('D',strtotime($d))];
}
/* ---------- 2) Anomaly detection (กฎ + สถิติ) ---------- */
$rt=$db->query('SELECT AVG(base_rate) FROM room_types')->fetchColumn();
$cq=$db->prepare('SELECT AVG(rate) FROM competitors WHERE rate_date=?');
$rq=$db->prepare('SELECT AVG(rate) FROM rates WHERE rate_date=?');
$flags=[];
foreach($F as $d=>$f){$pct=$cap?$f['fc']/$cap*100:0;$cq->execute([$d]);$comp=(float)$cq->fetchColumn();$rq->execute([$d]);$ours=(float)$rq->fetchColumn()?:(float)$rt;
 $lead=(strtotime($d)-strtotime($today))/86400;
 if($pct>=85&&$comp&&$ours<$comp*0.95)$flags[]=['date'=>$d,'type'=>'underpriced','detail'=>sprintf('คาดเต็ม %d%% แต่ราคาเรา %d ต่ำกว่าคู่แข่ง %d',$pct,$ours,$comp)];
 if($pct<40&&$lead<=10)$flags[]=['date'=>$d,'type'=>'low_demand','detail'=>sprintf('คาดเข้าพักเพียง %d%% ในอีก %d วัน',$pct,$lead)];
 if($comp&&$ours>$comp*1.25&&$pct<60)$flags[]=['date'=>$d,'type'=>'overpriced','detail'=>sprintf('ราคาเรา %d สูงกว่าคู่แข่ง %d เกิน 25%% และคาดเข้าพัก %d%%',$ours,$comp,$pct)];}
/* ---------- 3) Gemini แบบ structured JSON (ไม่ใช่แชท) ---------- */
if($_SERVER['REQUEST_METHOD']=='POST'&&csrf_ok()&&isset($_POST['review'])){header('Content-Type: application/json');
 if(!trim(GEMINI_KEY)){echo json_encode(['error'=>'ยังไม่ได้ตั้งค่า GEMINI_KEY']);exit;}
 $schema=['type'=>'ARRAY','items'=>['type'=>'OBJECT','properties'=>[
  'date'=>['type'=>'STRING'],'severity'=>['type'=>'STRING','enum'=>['high','medium','low']],
  'action'=>['type'=>'STRING','enum'=>['raise_price','lower_price','keep','promo','restrict_min_stay']],
  'price_change_pct'=>['type'=>'NUMBER'],'reason'=>['type'=>'STRING']],
  'required'=>['date','severity','action','price_change_pct','reason']]];
 $body=json_encode(['contents'=>[['parts'=>[['text'=>"คุณคือระบบวิเคราะห์รายได้โรงแรม ประเมินแต่ละรายการด้านล่างแล้วตอบเป็น JSON ตาม schema เท่านั้น (reason เป็นภาษาไทยไม่เกิน 100 ตัวอักษร, price_change_pct ระหว่าง -25 ถึง 25):\n".json_encode($flags,JSON_UNESCAPED_UNICODE)]]]],
  'generationConfig'=>['responseMimeType'=>'application/json','responseSchema'=>$schema,'temperature'=>0.2,'thinkingConfig'=>['thinkingLevel'=>'low']]]);
 require_once 'gemini.php';$r=gemini_post($body);$j=json_decode($r['candidates'][0]['content']['parts'][0]['text']??'null',true);
 echo json_encode(is_array($j)?['items'=>$j]:['error'=>$r['_err']??'Gemini ตอบกลับไม่สำเร็จ']);exit;}
include 'header.php'; ?>
<h2 class="font-bold mb-2">พยากรณ์อัตราเข้าพัก 30 วัน (Pickup Model)</h2>
<div class="bg-white p-4 rounded-xl shadow mb-4"><canvas id="fc" height="100"></canvas>
<p class="text-xs text-slate-500 mt-2">คาดการณ์ = ห้องที่จองแล้ว + ค่าเฉลี่ย pickup ของวันเดียวกันในสัปดาห์ย้อนหลัง 8 สัปดาห์ (ต้องมีข้อมูลการจองย้อนหลังอย่างน้อย 3 สัปดาห์จึงแม่นขึ้น)</p></div>
<div class="flex items-center gap-3 mb-2"><h2 class="font-bold">สัญญาณผิดปกติ (<?=count($flags)?>)</h2>
<button onclick="rv()" class="bg-emerald-600 text-white px-3 py-1 rounded text-sm" <?=$flags?'':'disabled'?>>✨ ให้ AI ประเมินและจัดลำดับ</button></div>
<div id="out" class="grid md:grid-cols-2 gap-3">
<?php foreach($flags as $x):?><div class="bg-white p-3 rounded-xl shadow text-sm"><b><?=h($x['date'])?></b> <span class="text-xs bg-slate-100 px-2 rounded"><?=h($x['type'])?></span><div class="text-slate-500"><?=h($x['detail'])?></div></div><?php endforeach;?>
<?php if(!$flags):?><p class="text-slate-500 text-sm">ไม่พบความผิดปกติ</p><?php endif;?></div>
<script>
const L=<?=json_encode(array_map(fn($d)=>substr($d,5),array_keys($F)))?>,C=<?=$cap?:1?>;
const P=k=><?=json_encode(array_values($F))?>.map(f=>Math.round(f[k]/C*1000)/10);
new Chart(fc,{type:'line',data:{labels:L,datasets:[
{label:'จองแล้ว %',data:P('otb'),borderColor:'#94a3b8',borderDash:[4,4]},
{label:'คาดการณ์ %',data:P('fc'),borderColor:'#4f46e5'},
{label:'ต่ำสุด',data:P('lo'),borderColor:'#c7d2fe',pointRadius:0},
{label:'สูงสุด',data:P('hi'),borderColor:'#c7d2fe',pointRadius:0,fill:'-1',backgroundColor:'#eef2ff'}]},options:{scales:{y:{min:0,max:100}}}});
async function rv(){const o=document.getElementById('out');o.textContent='กำลังวิเคราะห์...';
const f=new FormData();f.append('t','<?=csrf()?>');f.append('review','1');
let j;try{const x=await (await fetch('insights.php',{method:'POST',body:f})).text();try{j=JSON.parse(x)}catch(_){o.textContent='เซิร์ฟเวอร์ตอบกลับผิดรูปแบบ: '+x.replace(/<[^>]*>/g,' ').trim().slice(0,400);return}}catch(e){o.textContent='เกิดข้อผิดพลาด: '+e.message;return}
if(j.error){o.textContent=j.error;return}
const col={high:'border-red-500',medium:'border-amber-500',low:'border-slate-300'};
o.innerHTML='';j.items.sort((a,b)=>({high:0,medium:1,low:2}[a.severity]-{high:0,medium:1,low:2}[b.severity])).forEach(i=>{
const d=document.createElement('div');d.className='bg-white p-3 rounded-xl shadow text-sm border-l-4 '+(col[i.severity]||'');
d.innerHTML='<b></b> · <span></span><div class="text-slate-500 reason"></div>';
d.querySelector('b').textContent=i.date;d.querySelector('span').textContent=i.action+' ('+(i.price_change_pct>0?'+':'')+i.price_change_pct+'%)';d.querySelector('.reason').textContent=i.reason;o.appendChild(d);});}
</script>
<?php include 'footer.php'; ?>