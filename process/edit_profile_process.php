<?php
session_start();
require_once('../includes/db_connect.php');

if (empty($_SESSION['student_id']) || $_SERVER["REQUEST_METHOD"] != "POST") { header("Location: ../login.php"); exit; }

$student_id = $_SESSION['student_id'];

$prefix = trim($_POST['prefix']);
if ($prefix == 'other') { $prefix = trim($_POST['prefix_other']); }
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);

$full_name = trim($prefix . ' ' . $first_name . ' ' . $last_name);

$department = trim($_POST['department']);
$student_personnel_id = trim($_POST['student_personnel_id']);
$phone_number = trim($_POST['phone_number']);

if (empty($first_name) || empty($last_name)) { header("Location: ../profile.php?status=error&message=" . urlencode("กรุณากรอกชื่อและนามสกุลให้ครบถ้วน")); exit; }

try {
    $sql = "UPDATE med_students SET full_name = ?, department = ?, student_personnel_id = ?, phone_number = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$full_name, $department, $student_personnel_id, $phone_number, $student_id]);
    $_SESSION['student_full_name'] = $full_name;
    header("Location: ../profile.php?status=success");
    exit;
} catch (PDOException $e) {
    header("Location: ../profile.php?status=error&message=" . urlencode($e->getMessage()));
    exit;
}
?>