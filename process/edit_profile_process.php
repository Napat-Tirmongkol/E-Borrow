<?php
// edit_profile_process.php
// รับข้อมูลจากฟอร์ม "แก้ไขโปรไฟล์"

session_start();
require_once('../includes/db_connect.php');

// 1. "ยามเฝ้าประตู"
if (empty($_SESSION['student_id']) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../login.php");
    exit;
}

// 2. ดึง ID นักศึกษาจาก Session
$student_id = $_SESSION['student_id'];

// 3. รับข้อมูลจากฟอร์ม
$full_name = trim($_POST['full_name']);
$department = trim($_POST['department']);
$student_personnel_id = trim($_POST['student_personnel_id']);
$phone_number = trim($_POST['phone_number']);

// 4. ตรวจสอบข้อมูลบังคับ
if (empty($full_name)) {
    header("Location: ../profile.php?status=error&message=" . urlencode("กรุณากรอกชื่อ-สกุล"));
    exit;
}

// 5. บันทึกลงฐานข้อมูล (med_students)
try {
    $sql = "UPDATE med_students 
            SET full_name = ?, 
                department = ?, 
                student_personnel_id = ?, 
                phone_number = ?
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $full_name,
        $department,
        $student_personnel_id,
        $phone_number,
        $student_id
    ]);

    // 6. (สำคัญ) อัปเดต Session 'student_full_name' ด้วย
    $_SESSION['student_full_name'] = $full_name;

    // 7. บันทึกสำเร็จ! ส่งกลับไปหน้าเดิมพร้อมข้อความ
    header("Location: ../profile.php?status=success");
    exit;

} catch (PDOException $e) {
    header("Location: ../profile.php?status=error&message=" . urlencode($e->getMessage())); // ◀️ (แก้ไข)
    exit;
}
?>