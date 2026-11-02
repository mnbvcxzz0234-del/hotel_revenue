-- รูปห้อง (ไม่บังคับ): รันที่ Supabase SQL Editor แล้วใส่ลิงก์รูปในคอลัมน์ image_url
ALTER TABLE room_types ADD COLUMN IF NOT EXISTS image_url TEXT;
-- ตัวอย่าง: UPDATE room_types SET image_url='https://...' WHERE name='Standard';
