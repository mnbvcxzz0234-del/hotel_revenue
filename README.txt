วิธีติดตั้ง (Render + Supabase PostgreSQL)
1. Supabase > SQL Editor: รันไฟล์ schema_pg.sql
2. Supabase > Connect: คัดลอก Session pooler connection string
3. Render > New Web Service (Runtime: Docker) ชี้ไปที่ repo นี้
4. ตั้ง Environment: DATABASE_URL (หรือ DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS), GEMINI_KEY (ไม่บังคับ), SETUP_PASS
5. เปิด /setup.php หนึ่งครั้งเพื่อสร้าง admin แล้วลบไฟล์ setup.php และตัวแปร SETUP_PASS
6. เข้าสู่ระบบที่ /login.php ด้วยผู้ใช้ admin
