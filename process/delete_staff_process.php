<?php
// delete_staff_process.php

include('includes/check_session_ajax.php');
require_once('db_connect.php');
require_once('includes/log_function.php');

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid request'];

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

$user_id_to_delete = isset($_POST['user_id_to_delete']) ? (int)$_POST['user_id_to_delete'] : 0;

if ($user_id_to_delete == 0) {
    $response['message'] = 'Invalid User ID';
    echo json_encode($response);
    exit;
}

// Prevent admin from deleting themselves
if ($user_id_to_delete == $_SESSION['user_id']) {
    $response['message'] = 'ไม่สามารถลบบัญชีของตัวเองได้';
    echo json_encode($response);
    exit;
}

try {
    // Check if the user has any logs associated with them
    $sql_check = "SELECT COUNT(*) FROM med_logs WHERE user_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$user_id_to_delete]);
    $log_count = $stmt_check->fetchColumn();

    if ($log_count > 0) {
        $response['message'] = 'ไม่สามารถลบบัญชีพนักงานได้ เนื่องจากมีประวัติการดำเนินการค้างอยู่';
        echo json_encode($response);
        exit;
    }

    // Get user info for logging before deletion
    $stmt_get = $pdo->prepare("SELECT username, full_name FROM med_users WHERE id = ?");
    $stmt_get->execute([$user_id_to_delete]);
    $user_info = $stmt_get->fetch(PDO::FETCH_ASSOC);
    $user_name_for_log = $user_info ? $user_info['full_name'] : "ID: {$user_id_to_delete}";

    // Delete the user
    $sql_delete = "DELETE FROM med_users WHERE id = ?";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([$user_id_to_delete]);

    if ($stmt_delete->rowCount() > 0) {
        $admin_user_id = $_SESSION['user_id'] ?? null;
        $admin_user_name = $_SESSION['full_name'] ?? 'System';
        $log_desc = "Admin '{$admin_user_name}' (ID: {$admin_user_id}) ได้ลบบัญชีพนักงาน: '{$user_name_for_log}' (UID: {$user_id_to_delete})";
        log_action($pdo, $admin_user_id, 'delete_staff', $log_desc);

        $response = ['status' => 'success', 'message' => 'ลบบัญชีพนักงานสำเร็จ'];
    } else {
        $response['message'] = 'ไม่พบบัญชีพนักงานหรือไม่สามารถลบได้';
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>