<?php
// student_test_login.php
// หน้าสำหรับให้ Tester กรอกรหัสเพื่อเข้าสู่ระบบ (ฝั่ง User)
session_start();

// (ถ้าล็อกอินแล้ว ให้ไปหน้า dashboard เลย)
if (isset($_SESSION['student_id'])) {
    header("Location: student_dashboard.php");
    exit;
}

// (ดึง CSS มาใช้)
require_once('includes/student_header.php');
?>

<style>
    body {
        /* (ปรับ style ให้คล้ายหน้า create_profile) */
        background-color: var(--color-page-bg, #B7E5CD);
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }
    .login-container {
        background: var(--color-content-bg, #fff);
        padding: 30px;
        border-radius: var(--border-radius-main, 12px);
        box-shadow: var(--box-shadow-main, 0 4px 12px rgba(0,0,0,0.08));
        width: 350px;
        text-align: center;
    }
    .login-container h1 {
        color: var(--color-primary, #0B6623);
        margin-bottom: 20px;
    }
    .login-container input[type="password"] {
        width: 90%;
        padding: 12px;
        margin-bottom: 15px;
        border: 1px solid var(--border-color, #ddd);
        border-radius: 4px;
    }
    .login-container button {
        width: 100%;
        padding: 12px;
        background-color: var(--color-primary, #0B6623);
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
    }
    .error-message {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        display: <?php echo isset($_GET['error']) ? 'block' : 'none'; ?>;
    }
</style>

<div class="login-container">
    <h1>MedLoan (Test Mode)</h1>
    <p>สำหรับผู้ทดสอบ (ฝั่ง User)</p>

    <div class="error-message">
        รหัสทดสอบไม่ถูกต้อง!
    </div>

    <form action="student_test_login_process.php" method="POST">
        <div>
            <input type="password" name="test_code" placeholder="กรอกรหัสทดสอบ" required>
        </div>
        <button type="submit">เข้าสู่ระบบ</button>
    </form>
    
    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
    <a href="line_login.php">กลับไปหน้าล็อกอินปกติ (LINE)</a>
</div>

<?php 
// (เราไม่ต้องใช้ footer เพราะหน้านี้ไม่ต้องมี Footer Nav)
echo "</body></html>";
?>