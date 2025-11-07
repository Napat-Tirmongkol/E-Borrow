<?php
// login.php (เดิมคือ line_login.php)
// หน้าสำหรับเลือกประเภทการ Login

// ◀️ (แก้ไข) แก้ไข Path นี้ครับ
require_once('includes/line_config.php');
// ◀️ (ลบ) ลบบรรทัด 'require_once' ที่ค้างอยู่ออกครับ

session_start();
if (empty($_SESSION['line_login_state'])) {
    $_SESSION['line_login_state'] = bin2hex(random_bytes(16));
}
$state = $_SESSION['line_login_state'];

// (สร้าง URL สำหรับ LINE Login)
$line_login_url = "https://access.line.me/oauth2/v2.1/authorize?" . http_build_query([
    'response_type' => 'code',
    'client_id' => LINE_LOGIN_CHANNEL_ID,
    'redirect_uri' => LINE_LOGIN_CALLBACK_URL, // (ค่านี้มาจาก line_config.php)
    'state' => $state,
    'scope' => 'profile openid'
]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบยืมคืนอุปกรณ์</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    
     <style>
        :root { /* (ใส่ตัวแปรสีสำรองไว้ เผื่อ style.css โหลดไม่ทัน) */
            --color-primary: #0B6623;
            --color-page-bg: #B7E5CD;
            --color-content-bg: #FFFFFF;
            --border-radius-main: 12px;
            --box-shadow-main: 0 4px 12px rgba(0,0,0,0.08);
        }
        body {
            background-color: var(--color-page-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-choice-container {
            background: var(--color-content-bg);
            padding: 40px;
            border-radius: var(--border-radius-main);
            box-shadow: var(--box-shadow-main);
            width: 400px;
            text-align: center;
        }
        .btn-line-login {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #00B900; /* (สีเขียว LINE คงเดิม) */
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
        .btn-line-login:hover {
            background-color: #009A00;
        }
        .staff-login-link {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background-color: var(--color-primary); /* (ใช้สีเขียวเข้ม) */
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            box-sizing: border-box;
        }
        .staff-login-link:hover {
            background-color: var(--color-primary-dark);
        }
    </style>
</head>
<body>

    <div class="login-choice-container">
        <h2>ระบบยืมคืนอุปกรณ์การแพทย์</h2>
        <p style="margin-bottom: 30px;">กรุณาเลือกวิธีการเข้าสู่ระบบ</p>

        <a href="<?php echo htmlspecialchars($line_login_url); ?>" class="btn-line-login">
            <i class="fab fa-line"></i> Log in with LINE
        </a>

        <a href="<?php echo STAFF_LOGIN_URL; ?>" class="staff-login-link">
            สำหรับพนักงาน / Admin
        </a>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
</body>
</html>