<?php include 'header.php'; $S=stats(date('Y-m-d'),30);
$occ=array_sum(array_column($S,'occ'));$rev=array_sum(array_column($S,'rev'));$cap=(reset($S)['cap'])*30;
$adr=$occ?$rev/$occ:0;$revpar=$cap?$rev/$cap:0;$pct=$cap?$occ/$cap*100:0;
$k=['อัตราเข้าพัก (30 วัน)'=>number_format($pct,1).'%','ADR'=>'฿'.number_format($adr),'RevPAR'=>'฿'.number_format($revpar),'รายได้ที่จองแล้ว'=>'฿'.number_format($rev)]; ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"><?php foreach($k as $n=>$v):?><div class="bg-white p-4 rounded-xl shadow"><div class="text-sm text-slate-500"><?=$n?></div><div class="text-2xl font-bold"><?=$v?></div></div><?php endforeach;?></div>
<div class="bg-white p-4 rounded-xl shadow"><canvas id="c" height="110"></canvas></div>
<p class="mt-3 text-sm text-slate-500">ดูราคาที่ AI แนะนำได้ที่เมนู “AI แนะนำราคา”</p>
<script>const L=<?=json_encode(array_map(fn($d)=>substr($d,5),array_keys($S)))?>,O=<?=json_encode(array_values(array_map(fn($s)=>$s['cap']?round($s['occ']/$s['cap']*100,1):0,$S)))?>,R=<?=json_encode(array_values(array_column($S,'rev')))?>;
new Chart(c,{data:{labels:L,datasets:[{type:'bar',label:'รายได้ (฿)',data:R,backgroundColor:'#c7d2fe',yAxisID:'y'},{type:'line',label:'Occupancy %',data:O,borderColor:'#4f46e5',yAxisID:'y1'}]},options:{scales:{y:{position:'left'},y1:{position:'right',min:0,max:100,grid:{drawOnChartArea:false}}}}});</script>
<?php include 'footer.php'; ?>
