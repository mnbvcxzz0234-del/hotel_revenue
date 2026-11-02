-- หน้าลูกค้า (book.php): รันที่ Supabase > SQL Editor หนึ่งครั้ง (รันซ้ำได้)
-- ต้องรัน migration_bookings.sql มาก่อนแล้ว

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS phone VARCHAR(30);
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS email VARCHAR(120);
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS note TEXT;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS consent_at TIMESTAMP;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS ref VARCHAR(12);
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS ip VARCHAR(45);
CREATE UNIQUE INDEX IF NOT EXISTS bookings_ref ON bookings(ref) WHERE ref IS NOT NULL;
CREATE INDEX IF NOT EXISTS bookings_ip_created ON bookings(ip, created_at);

-- เพิ่มสถานะ pending (รอยืนยัน)
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
  CHECK (status IN ('pending','confirmed','checked_in','completed','no_show','cancelled'));
