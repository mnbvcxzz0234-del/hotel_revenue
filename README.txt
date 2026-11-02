วิธีติดตั้ง (Render + Supabase PostgreSQL)

ติดตั้งใหม่
1. Supabase > SQL Editor: รันไฟล์ schema_pg.sql
2. Supabase > Connect: คัดลอก Session pooler connection string
3. Render > New Web Service (Runtime: Docker) ชี้ไปที่ repo นี้
4. ตั้ง Environment: DATABASE_URL (หรือ DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS),
   GEMINI_API_KEY (ไม่บังคับ), SETUP_PASS,
   HOTEL_NAME และ HOTEL_PHONE (ไม่บังคับ แสดงในหน้าลูกค้า)
5. เปิด /setup.php หนึ่งครั้งเพื่อสร้าง admin แล้วลบไฟล์ setup.php และตัวแปร SETUP_PASS
6. เข้าสู่ระบบที่ /login.php ด้วยผู้ใช้ admin

อัปเดตจากเวอร์ชันเดิม (ฐานข้อมูลมีอยู่แล้ว) รันตามลำดับ
1. Supabase > SQL Editor: migration_bookings.sql แล้วตามด้วย migration_guest.sql (รันซ้ำได้)
2. อัปโหลดไฟล์ทั้งหมดทับ แล้ว Deploy
3. ตั้ง GEMINI_API_KEY ที่ Render (ไม่ฝัง key ในโค้ดแล้ว)
   ถ้าเคยฝัง key ไว้ในโค้ดหรือ repo ให้ยกเลิก key เดิมแล้วสร้างใหม่

หน้าลูกค้า
- ลูกค้าเปิด /book.php (ไม่ต้องล็อกอิน) ตรวจห้องว่าง ส่งคำขอจอง และติดตามสถานะด้วยรหัสการจอง + เบอร์โทร
- คำขอเป็นสถานะ "รอยืนยัน" ยังไม่ล็อกห้องและไม่นับในแดชบอร์ด/AI
- เจ้าหน้าที่กด "ยืนยัน" หรือ "ปฏิเสธ" ในหน้า การจอง (มีป้ายเตือนจำนวนคำขอที่เมนู)
- ข้อความยินยอมในหน้าลูกค้าเป็นข้อความตัวอย่าง ควรให้ผู้เชี่ยวชาญตรวจ และมีนโยบายความเป็นส่วนตัวจริงของโรงแรม
