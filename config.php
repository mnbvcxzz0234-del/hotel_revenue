<?php

session_start();

// กำหนดค่า API Key
const GEMINI_KEY='AQ.Ab8RN6JKMCa-9wf5MvY0q-KS1VPwDqmR0mLcy5dUuH9SFC_rWQ';

function db(){
    static $p;
    if(!$p) {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: 'postgres';
        $user = getenv('DB_USER') ?: 'postgres';
        $pass = getenv('DB_PASS');
        
        if ($host) {
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            $p = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } else {
            $db_host = 'localhost';
            $db_name = 'hotel_revenue';
            $db_user = 'root';
            $db_pass = '';
            
            $p = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }
    }
    return $p;
}

function need_login(){
    if(empty($_SESSION['uid'])){
        header('Location: login.php');
        exit;
    }
}

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function csrf(){
    return $_SESSION['t'] ?? ($_SESSION['t'] = bin2hex(random_bytes(16)));
}

function csrf_ok(){
    return hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '');
}

// สถิติรายวัน: ห้องที่ขายได้, รายได้, แยกตามประเภทห้อง
function stats($from, $days){
    $to = date('Y-m-d', strtotime("$from +$days day"));
    $cap = (int)db()->query('SELECT COALESCE(SUM(total_rooms),0) FROM room_types')->fetchColumn();
    $st = db()->prepare("SELECT * FROM bookings WHERE status<>'cancelled' AND checkin<? AND checkout>?");
    $st->execute([$to, $from]);
    $bk = $st->fetchAll();
    $out = [];
    
    for($i = 0; $i < $days; $i++){
        $d = date('Y-m-d', strtotime("$from +$i day"));
        $o = 0; $r = 0; $bt = [];
        foreach($bk as $b){
            if($b['checkin'] <= $d && $b['checkout'] > $d){
                $o += $b['rooms'];
                $r += $b['rooms'] * $b['rate_per_night'];
                $bt[$b['room_type_id']] = ($bt[$b['room_type_id']] ?? 0) + $b['rooms'];
            }
        }
        $out[$d] = ['occ' => $o, 'rev' => $r, 'cap' => $cap, 'bt' => $bt];
    }
    return $out;
}

// เครื่องมือคำนวณราคาแนะนำ (Dynamic Pricing)
function suggest($t, $d, $sold, $comp){
    $p = $sold / max(1, $t['total_rooms']) * 100;
    $why = [];
    $f = $p >= 90 ? .25 : ($p >= 75 ? .15 : ($p >= 60 ? .07 : ($p >= 40 ? 0 : ($p >= 20 ? -.07 : -.15))));
    $why[] = 'เต็ม ' . round($p) . '%';
    
    if(in_array(date('N', strtotime($d)), [5, 6])){
        $f += .08;
        $why[] = 'คืนวันหยุด';
    }
    
    $lead = (strtotime($d) - strtotime(date('Y-m-d'))) / 86400;
    if($lead <= 3 && $p < 50){
        $f -= .05;
        $why[] = 'ใกล้วันเข้าพัก ห้องยังว่าง';
    }
    
    $x = $t['base_rate'] * (1 + $f);
    if($comp){
        $x = .7 * $x + .3 * $comp;
        $why[] = 'อิงคู่แข่งเฉลี่ย ' . number_format($comp);
    }
    
    $x = round(min($t['max_rate'], max($t['min_rate'], $x)) / 10) * 10;
    return [$x, implode(' · ', $why)];
}