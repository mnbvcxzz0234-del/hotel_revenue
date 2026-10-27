function db(){
    static $p;
    if(!$p) {
        // ดึงค่า Environment Variables ที่เราตั้งไว้ใน Render มาใช้งานตรงๆ
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: 'postgres';
        $user = getenv('DB_USER') ?: 'postgres';
        $pass = getenv('DB_PASS');
        
        // ถ้าอยู่บน Render (มีค่า DB_HOST) จะต่อ PostgreSQL ของ Supabase
        if ($host) {
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            $p = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } else {
            // เผื่อเวลาคุณเอาไปรันเทสบนคอมตัวเอง (Local) ค่อยใช้ MySQL
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