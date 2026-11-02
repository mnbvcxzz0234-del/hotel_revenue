-- บัญชีลูกค้า: รันที่ Supabase > SQL Editor หนึ่งครั้ง (รันซ้ำได้)
-- ต้องรัน migration_bookings.sql และ migration_guest.sql มาก่อนแล้ว

CREATE TABLE IF NOT EXISTS customers(
  id SERIAL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  consent_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  failed_count INT NOT NULL DEFAULT 0,
  locked_until TIMESTAMP,
  active BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE UNIQUE INDEX IF NOT EXISTS customers_email ON customers(lower(email));

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS customer_id INT REFERENCES customers(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS bookings_customer ON bookings(customer_id);
