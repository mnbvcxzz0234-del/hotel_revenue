-- รันที่ Supabase > SQL Editor หนึ่งครั้ง (รันซ้ำได้ ไม่เสียหาย) ก่อนอัปโหลดโค้ดใหม่

-- 1) ช่องทางจอง
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS channel VARCHAR(30) NOT NULL DEFAULT 'direct';

-- 2) สถานะเพิ่มเติม
DO $$
DECLARE c text;
BEGIN
  FOR c IN SELECT conname FROM pg_constraint
           WHERE conrelid = 'bookings'::regclass AND contype = 'c'
             AND pg_get_constraintdef(oid) LIKE '%status%'
  LOOP
    EXECUTE format('ALTER TABLE bookings DROP CONSTRAINT %I', c);
  END LOOP;
END $$;
ALTER TABLE bookings ADD CONSTRAINT bookings_status_check
  CHECK (status IN ('confirmed','checked_in','completed','no_show','cancelled'));

-- 3) กันวันออกไม่หลังวันเข้า (NOT VALID = ไม่ตรวจแถวเก่า ตรวจเฉพาะแถวใหม่)
ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_dates_ok;
ALTER TABLE bookings ADD CONSTRAINT bookings_dates_ok CHECK (checkout > checkin) NOT VALID;

-- 4) ประวัติการแก้ไข (audit log)
CREATE TABLE IF NOT EXISTS booking_log(
  id SERIAL PRIMARY KEY,
  booking_id INT,
  action VARCHAR(30),
  by_user VARCHAR(50),
  detail TEXT,
  at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS booking_log_b ON booking_log(booking_id);

-- (ไม่บังคับ) ลบข้อมูลทดสอบ: ตรวจดูก่อนด้วย SELECT แล้วค่อยลบ
-- SELECT id, guest, checkin, checkout, rate_per_night, status FROM bookings WHERE guest = 'thjerht';
-- DELETE FROM bookings WHERE guest = 'thjerht';
