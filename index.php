<?php include 'header.php'; $S=stats(date('Y-m-d'),30);
$occ=array_sum(array_column($S,'occ'));$rev=array_sum(array_column($S,'rev'));$cap=(reset($S)['cap'])*30;
$adr=$occ?$rev/$occ:0;$revpar=$cap?$rev/$cap:0;$pct=$cap?$occ/$cap*100:0;
$k=[['อัตราเข้าพัก (30 วัน)',number_format($pct,1).'%'],['ADR','฿'.number_format($adr)],['RevPAR','฿'.number_format($revpar)],['รายได้ที่จองแล้ว','฿'.number_format($rev)]]; ?>
<h1 class="text-3xl font-bold mb-1">ภาพรวม <span style="color:var(--p)">30 วันข้างหน้า</span></h1>
<p class="mb-5" style="color:var(--mute)">ตัวเลขสำคัญของโรงแรมจากการจองที่ยืนยันแล้ว</p>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"><?php foreach($k as [$n,$v]):?><div class="card p-5"><div class="text-sm" style="color:var(--mute)"><?=h($n)?></div><div class="text-3xl font-bold mt-1" style="color:var(--p)"><?=h($v)?></div></div><?php endforeach;?></div>
<div class="card p-5"><canvas id="c" height="110"></canvas></div>
<div class="mt-4 flex gap-2 flex-wrap"><a href="ai.php" class="chip on">✨ ดูราคาที่ AI แนะนำ</a><a href="insights.php" class="chip">📈 พยากรณ์ & สัญญาณ</a><a href="bookings.php" class="chip">การจองรอยืนยัน</a></div>
<script>const L=<?=json_encode(array_map(fn($d)=>substr($d,5),array_keys($S)))?>,O=<?=json_encode(array_values(array_map(fn($s)=>$s['cap']?round($s['occ']/$s['cap']*100,1):0,$S)))?>,R=<?=json_encode(array_values(array_column($S,'rev')))?>;
new Chart(c,{data:{labels:L,datasets:[{type:'bar',label:'รายได้ (฿)',data:R,backgroundColor:'#cfe6f6',borderRadius:8,yAxisID:'y'},{type:'line',label:'Occupancy %',data:O,borderColor:'#4a9fd8',backgroundColor:'#4a9fd8',tension:.35,yAxisID:'y1'}]},options:{font:{family:'Prompt'},scales:{y:{position:'left',grid:{color:'#eef4f9'}},y1:{position:'right',min:0,max:100,grid:{drawOnChartArea:false}}}}});</script>
<?php include 'footer.php'; ?>
