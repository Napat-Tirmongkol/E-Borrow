<?php
/*
 * db_connect.php
 * ไฟล์สำหรับเชื่อมต่อฐานข้อมูล
 */

// 1. ตั้งค่าตัวแปรเชื่อมต่อ (สำหรับ Localhost)
$db_host = "localhost";       // <-- (แก้ไข) เปลี่ยนจาก IP มาเป็น 'localhost' หรือ '127.0.0.1'
$db_user = "root";            // <-- (แก้ไข) ปกติ XAMPP/MAMP จะใช้ "root"
$db_pass = "";                // <-- (แก้ไข) ปกติ XAMPP/MAMP จะไม่มีรหัสผ่าน (เว้นว่างไว้)
$db_name = "medical_equipment_db";    // <-- (คงเดิม) *ต้องแน่ใจว่าคุณสร้าง DB ชื่อนี้ใน localhost แล้ว*
$db_port = 3306;              // <-- (คงเดิม)

// 2. พยายามเชื่อมต่อ
try {
    // สร้างการเชื่อมต่อแบบ PDO
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // สร้างตัวแปร $pdo เพื่อเก็บการเชื่อมต่อ
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

} catch (PDOException $e) {
    // หากเชื่อมต่อล้มเหลว ให้หยุดทำงานและแสดงข้อผิดพลาด
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล (Localhost): " . $e->getMessage()); // ◀️ (แก้ไข)
}
// กำหนดอัตราค่าปรับ (บาท ต่อ วัน)
define('FINE_RATE_PER_DAY', 10.00);
?>