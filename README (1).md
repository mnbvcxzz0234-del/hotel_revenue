# Hotel Revenue Manager

ระบบบริหารรายได้โรงแรม (Revenue Management) แบบเว็บ ใช้ดูอัตราการเข้าพัก จัดการการจอง แนะนำราคาห้องอัตโนมัติ (Dynamic Pricing) และให้ Gemini AI สรุปกลยุทธ์ราคา 14 วันข้างหน้า

- URL ที่ deploy: https://hotel-revenue.onrender.com
- สถานะ: MVP ใช้งานได้ (หน้า login, ตารางราคาแนะนำ ทำงานแล้ว)

---

## 1. Requirements (ความต้องการของระบบ)

### Functional (ทำอะไรได้)
| # | ความต้องการ | ไฟล์ที่เกี่ยวข้อง |
|---|---|---|
| 1 | ล็อกอิน / ล็อกเอาต์ ด้วย session | `login.php`, `logout.php`, `config.php` (`need_login()`) |
| 2 | จัดการการจอง (วันเช็คอิน/เอาต์ จำนวนห้อง ราคาต่อคืน สถานะ) | `bookings.php` |
| 3 | คำนวณสถิติรายวัน: ห้องที่ขายได้ รายได้ ความจุ แยกตามประเภทห้อง | `config.php` (`stats()`) |
| 4 | แนะนำราคาห้องล่วงหน้า 14 วัน พร้อมเหตุผล | `config.php` (`suggest()`), `ai.php` |
| 5 | ปุ่ม "ใช้ราคาที่แนะนำทั้งหมด" บันทึกราคาลงตาราง `rates` | `ai.php` |
| 6 | ปุ่ม "ให้ Gemini สรุปกลยุทธ์" สรุปเป็นภาษาไทยไม่เกิน 8 บรรทัด | `ai.php`, `gemini.php` |
| 7 | หน้า Dashboard, Insights, Settings | `index.php`, `insights.php`, `settings.php` |
| 8 | ตั้งค่าฐานข้อมูลเริ่มต้น | `setup.php`, `schema_pg.sql` |

### Non-functional
- ป้องกัน CSRF ทุกฟอร์ม (`csrf()`, `csrf_ok()`)
- ป้องกัน XSS ด้วย `h()` (htmlspecialchars)
- ใช้ prepared statements ผ่าน PDO
- ไม่เก็บรหัสผ่านหรือ API key ใน repo ใช้ Environment Variables
- เชื่อมฐานข้อมูลด้วย SSL (`sslmode=require`)
- แคชผล Gemini 30 นาทีใน session เพื่อประหยัดโควตา

---

## 2. MVP Scope

**อยู่ใน MVP**
- Login, จัดการการจอง, ดูสถิติรายวัน
- แนะนำราคาตามกฎ (อัตราเข้าพัก, วันหยุด, ใกล้วันเข้าพัก, ราคาคู่แข่ง)
- สรุปกลยุทธ์ด้วย Gemini
- Deploy ขึ้น Cloud

**ยังไม่อยู่ใน MVP (แนวทางต่อยอด)**
- ดึงราคาคู่แข่งอัตโนมัติ (ตอนนี้กรอกเองในตาราง `competitors`)
- เชื่อม Channel Manager / OTA
- พยากรณ์ความต้องการด้วยข้อมูลย้อนหลัง
- หลายผู้ใช้ / หลายสิทธิ์ (role)

---

## 3. Tech Stack และภาษาที่ใช้

| ส่วน | เทคโนโลยี |
|---|---|
| Backend | PHP (เขียนแบบ procedural + PDO) |
| Database | PostgreSQL (Supabase) |
| Frontend | HTML, Tailwind CSS (class utility), JavaScript (fetch) |
| AI | Google Gemini API (`generateContent`) |
| Web server | Apache + PHP ใน Docker container |
| Hosting | Render (Web Service แบบ Docker) |
| Dev local | XAMPP (โฟลเดอร์ `htdocs`) + VS Code |
| ภาษาในระบบ | อินเทอร์เฟซและข้อความเป็นภาษาไทย |

---

## 4. โครงสร้างโปรเจกต์

```
hotel_revenue/
├── ai.php          # หน้าแนะนำราคา + endpoint เรียก Gemini
├── gemini.php      # ฟังก์ชัน gemini_post(): แคช, retry, สลับโมเดลสำรอง
├── bookings.php    # จัดการการจอง
├── config.php      # session, ค่า GEMINI_*, db(), stats(), suggest(), csrf, h()
├── header.php      # ส่วนหัวของหน้า
├── footer.php      # ส่วนท้ายของหน้า
├── index.php       # หน้าหลัก / dashboard
├── insights.php    # หน้าวิเคราะห์
├── login.php       # เข้าสู่ระบบ
├── logout.php      # ออกจากระบบ
├── settings.php    # ตั้งค่า
├── setup.php       # ตั้งค่าเริ่มต้น
├── schema_pg.sql   # โครงสร้างตาราง PostgreSQL
├── Dockerfile      # สำหรับ build บน Render
└── README.md
```

### ตารางฐานข้อมูล (จากโค้ดที่ใช้)
- `room_types` : name, total_rooms, base_rate, min_rate, max_rate
- `bookings` : room_type_id, checkin, checkout, rooms, rate_per_night, status
- `rates` : room_type_id, rate_date, rate (unique ต่อ room_type_id + rate_date)
- `competitors` : rate_date, rate
- ตารางผู้ใช้ (อ้างอิงด้วย `$_SESSION['uid']`) ดูชื่อจริงใน `schema_pg.sql`

---

## 5. ตรรกะแนะนำราคา (`suggest()`)

1. คำนวณอัตราเข้าพัก % ของประเภทห้องในวันนั้น
2. ปรับราคาฐานตามอัตราเข้าพัก

| อัตราเข้าพัก | ปรับราคา |
|---|---|
| ≥ 90% | +25% |
| 75 – 89% | +15% |
| 60 – 74% | +7% |
| 40 – 59% | 0% |
| 20 – 39% | -7% |
| < 20% | -15% |

3. คืนวันศุกร์/เสาร์ : +8%
4. ใกล้วันเข้าพัก (ไม่เกิน 3 วัน) และเข้าพักต่ำกว่า 50% : -5%
5. ถ้ามีราคาคู่แข่ง : ผสม 70% ราคาที่คำนวณ + 30% ราคาคู่แข่งเฉลี่ย
6. จำกัดให้อยู่ระหว่าง `min_rate` ถึง `max_rate` และปัดเป็นหลัก 10

---

## 6. Environment Variables (ตั้งใน Render → Environment)

| ชื่อ | ความหมาย | จำเป็น |
|---|---|---|
| `DB_HOST` | host ของ PostgreSQL | ใช่ (หรือใช้ `DATABASE_URL`) |
| `DB_PORT` | port (ค่าเริ่มต้น 5432) | ไม่ |
| `DB_NAME` | ชื่อฐานข้อมูล (ค่าเริ่มต้น postgres) | ไม่ |
| `DB_USER` | ผู้ใช้ (ค่าเริ่มต้น postgres) | ไม่ |
| `DB_PASS` | รหัสผ่านฐานข้อมูล | ใช่ |
| `DATABASE_URL` | connection string สำรอง ใช้เมื่อไม่ได้ตั้ง `DB_HOST` | ไม่ |
| `GEMINI_API_KEY` | API key จาก Google AI Studio | ใช่ (ถ้าจะใช้ปุ่ม Gemini) |
| `GEMINI_MODEL` | รหัสโมเดล (ค่าเริ่มต้น `gemini-3.8-flash`) | ไม่ |

> อย่า commit key หรือรหัสผ่านลง Git

---

## 7. คำสั่งที่ใช้ในโปรเจกต์

> คำสั่งชุด git / docker ด้านล่างเป็นรูปแบบมาตรฐาน ปรับชื่อ repo และ branch ให้ตรงกับของคุณ

### รันบนเครื่อง (XAMPP)
```
# วางโปรเจกต์ที่ C:\xampp\htdocs\hotel_revenue
# เปิด Apache ใน XAMPP Control Panel
# เปิดเบราว์เซอร์: http://localhost/hotel_revenue/login.php
```

### Git / Deploy
```bash
git add .
git commit -m "อธิบายสิ่งที่แก้"
git push origin main        # Render จะ deploy ใหม่อัตโนมัติ
```

### ทดสอบ Docker บนเครื่อง (ไม่บังคับ)
```bash
docker build -t hotel-revenue .
docker run -p 8080:80 --env-file .env hotel-revenue
```

### ตรวจรายชื่อโมเดล Gemini ที่ใช้ได้
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models?key=YOUR_KEY"
```

### ตรวจ log
- Render → service `hotel-revenue` → **Logs** ดูบรรทัด `php:error`

---

## 8. Deploy บน Render

1. สร้าง Web Service แบบ Docker เชื่อมกับ repo
2. ตรวจว่า `Dockerfile` ก๊อปไฟล์เข้า `/var/www/html`
3. ตั้ง Environment Variables ตามหัวข้อ 6
4. สร้างตารางด้วย `schema_pg.sql` (เช่นที่ Supabase SQL Editor)
5. กด Save, rebuild and deploy จนสถานะเป็น Live

หมายเหตุ Free tier ของ Render จะ spin down เมื่อไม่มีการใช้งาน การเปิดครั้งแรกอาจช้าประมาณ 50 วินาที

---

## 9. ปัญหาที่เจอระหว่างพัฒนาและวิธีแก้

| อาการ | สาเหตุ | วิธีแก้ |
|---|---|---|
| 404 `/setup.php` ใน log | ไฟล์ไม่ได้อยู่ใน container หรือเปิด URL ผิด | ตรวจ `COPY` ใน Dockerfile และว่าไฟล์ถูก commit |
| "เซิร์ฟเวอร์ตอบกลับผิดรูปแบบ" | มี output/warning ปนก่อน JSON | ลบโค้ดตัวอย่างออกจาก `config.php`, ห้ามมี `echo` ใน config |
| `headers already sent` | มี output ก่อน `header()` หรือมีช่องว่างก่อน `<?php` | `<?php` ต้องอยู่ตัวอักษรแรกของไฟล์ ไม่ใส่ `?>` ท้ายไฟล์ |
| "ยังไม่ได้ตั้งค่า GEMINI_KEY" | ไม่มีตัวแปร `GEMINI_API_KEY` ใน Render | เพิ่มใน Environment แล้ว redeploy |
| `API key not valid` | key ผิดหรือถูกปิด | สร้าง key ใหม่ วางโดยไม่มีช่องว่าง/เครื่องหมายคำพูด |
| `high demand` (503) | โมเดลของ Google แน่นชั่วคราว | รอแล้วลองใหม่ หรือเปลี่ยน `GEMINI_MODEL` |
| `You exceeded your current quota` | โควตาฟรีรายวันเต็ม (ราว 20 คำขอ/วัน/โมเดล นับต่อโปรเจกต์) | รอรีเซ็ต, สลับโมเดล หรือเปิด billing |

---

## 10. แนวทางพัฒนาต่อ

- แยก endpoint ของ Gemini ออกจากหน้า `ai.php` และตรวจสิทธิ์ล็อกอินก่อนเรียก
- เพิ่มหน้ากรอก/นำเข้าราคาคู่แข่ง
- เพิ่มรายงานย้อนหลัง (ADR, RevPAR, Occupancy)
- เพิ่มสิทธิ์ผู้ใช้หลายระดับ
- เพิ่ม unit test สำหรับ `suggest()` และ `stats()`

---

## 11. ความปลอดภัย (Checklist)

- [ ] ไม่มี API key / รหัสผ่านใน repo
- [ ] `.env` อยู่ใน `.gitignore`
- [ ] ลบหรือป้องกัน `setup.php` หลังติดตั้งเสร็จ
- [ ] เปลี่ยนรหัสผ่านผู้ดูแลเริ่มต้น
- [ ] ปิด `display_errors` บน production
