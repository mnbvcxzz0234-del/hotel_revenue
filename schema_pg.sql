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
CREATE TABLE IF NOT EXISTS bookings(
  id SERIAL PRIMARY KEY,
  guest VARCHAR(120),
  room_type_id INT REFERENCES room_types(id),
  checkin DATE,
  checkout DATE,
  rooms INT DEFAULT 1,
  rate_per_night NUMERIC(10,2),
  status VARCHAR(20) DEFAULT 'confirmed' CHECK (status IN ('confirmed','cancelled')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS bookings_dates ON bookings(checkin, checkout);
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
