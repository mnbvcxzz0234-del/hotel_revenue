-- PostgreSQL / Supabase: run in Supabase > SQL Editor
CREATE TABLE IF NOT EXISTS users(
  id SERIAL PRIMARY KEY,
  username VARCHAR(50) UNIQUE,
  password_hash VARCHAR(255)
);
CREATE TABLE IF NOT EXISTS room_types(
  id SERIAL PRIMARY KEY,
  name VARCHAR(100),
  total_rooms INT,
  base_rate NUMERIC(10,2),
  min_rate NUMERIC(10,2),
  max_rate NUMERIC(10,2)
);
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
CREATE TABLE IF NOT EXISTS bookings(
  id SERIAL PRIMARY KEY,
  guest VARCHAR(120),
  room_type_id INT REFERENCES room_types(id),
  checkin DATE,
  checkout DATE,
  rooms INT DEFAULT 1,
  rate_per_night NUMERIC(10,2),
  status VARCHAR(20) DEFAULT 'confirmed' CHECK (status IN ('pending','confirmed','checked_in','completed','no_show','cancelled')),
  channel VARCHAR(30) NOT NULL DEFAULT 'direct',
  phone VARCHAR(30),
  email VARCHAR(120),
  note TEXT,
  consent_at TIMESTAMP,
  ref VARCHAR(12),
  ip VARCHAR(45),
  customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT bookings_dates_ok CHECK (checkout > checkin)
);
CREATE INDEX IF NOT EXISTS bookings_dates ON bookings(checkin, checkout);
CREATE UNIQUE INDEX IF NOT EXISTS bookings_ref ON bookings(ref) WHERE ref IS NOT NULL;
CREATE INDEX IF NOT EXISTS bookings_ip_created ON bookings(ip, created_at);
CREATE INDEX IF NOT EXISTS bookings_customer ON bookings(customer_id);
CREATE TABLE IF NOT EXISTS booking_log(
  id SERIAL PRIMARY KEY,
  booking_id INT,
  action VARCHAR(30),
  by_user VARCHAR(50),
  detail TEXT,
  at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS booking_log_b ON booking_log(booking_id);
CREATE TABLE IF NOT EXISTS rates(
  room_type_id INT,
  rate_date DATE,
  rate NUMERIC(10,2),
  PRIMARY KEY(room_type_id, rate_date)
);
CREATE TABLE IF NOT EXISTS competitors(
  id SERIAL PRIMARY KEY,
  rate_date DATE,
  hotel VARCHAR(100),
  rate NUMERIC(10,2)
);
CREATE INDEX IF NOT EXISTS competitors_date ON competitors(rate_date);

INSERT INTO room_types(name,total_rooms,base_rate,min_rate,max_rate)
SELECT 'Standard',20,1500,1000,3000 WHERE NOT EXISTS(SELECT 1 FROM room_types);
INSERT INTO room_types(name,total_rooms,base_rate,min_rate,max_rate)
SELECT 'Deluxe',10,2200,1500,4500 WHERE (SELECT COUNT(*) FROM room_types)=1;
