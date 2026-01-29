<?php
session_start();

// 1. ลบข้อมูลใน Session array
$_SESSION = array();

// 2. ลบ Session Cookie ใน Browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session บน Server
session_destroy();

// 4. ส่งกลับหน้า Login
header("Location: index.php");
exit;
?>