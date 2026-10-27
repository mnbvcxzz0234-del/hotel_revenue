<?php
// เรียก Gemini: แคชผลลัพธ์ 30 นาที (ประหยัดโควตา), ลองใหม่เฉพาะกรณีโมเดลยุ่งชั่วคราว,
// ถ้าโควตาโมเดลหนึ่งหมดจะสลับไปโมเดลสำรองทันที (ไม่ลองซ้ำให้เปลืองโควตา)
// คืนค่า array ที่ decode แล้ว หรือ ['_err'=>'ข้อความ'] เมื่อล้มเหลว
function gemini_post($body){
 $ck='gm_'.md5($body);
 if(isset($_SESSION[$ck])&&time()-$_SESSION[$ck]['t']<1800)return $_SESSION[$ck]['r'];
 $models=[defined('GEMINI_MODEL')?GEMINI_MODEL:'gemini-3.8-flash','gemini-3.7-flash'];
 $err='ไม่ทราบสาเหตุ';$quota=false;@set_time_limit(70);$deadline=time()+45;
 foreach($models as $m){
  for($try=0;$try<2;$try++){
   if(time()>$deadline)return ['_err'=>$err.' (หมดเวลารอ ลองใหม่อีกครั้งภายหลัง)'];
   $ch=curl_init("https://generativelanguage.googleapis.com/v1beta/models/$m:generateContent");
   curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.trim(GEMINI_KEY)],CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>8]);
   $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
   if($raw===false){$err='เชื่อมต่อไม่ได้: '.curl_error($ch);sleep(1);continue;}
   $r=json_decode($raw,true);
   if($code==200&&isset($r['candidates'])){$_SESSION[$ck]=['t'=>time(),'r'=>$r];return $r;}
   $msg=$r['error']['message']??$raw;$err="[$m] ".$msg;
   if($code==429&&stripos($msg,'quota')!==false){$quota=true;break;}   // โควตาหมด: ไม่ลองซ้ำ ไปโมเดลถัดไป
   if(!in_array($code,[429,500,503,504]))break;                         // error อื่นที่ลองซ้ำไม่ช่วย
   sleep($try+1);
  }
 }
 if($quota)$err='โควตาฟรีของ Gemini หมดแล้ว (นับต่อโปรเจกต์ ไม่ใช่ต่อ key) รอรีเซ็ตรายวัน หรือเปิดใช้ billing — รายละเอียด: '.$err;
 return ['_err'=>$err];
}