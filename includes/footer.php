<?php
// includes/footer.php

// (ตรวจสอบค่า $current_page ถ้าไม่มี ให้เป็น 'index')
$current_page = $current_page ?? 'index'; 
$user_role = $_SESSION['role'] ?? 'employee'; // (ดึง Role ปัจจุบัน)
?>

</main> 
<nav class="footer-nav">
    
    <a href="admin/index.php" class="<?php echo ($current_page == 'index') ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i>
        ภาพรวม
    </a>
    
    <a href="admin/return_dashboard.php" class="<?php echo ($current_page == 'return') ? 'active' : ''; ?>">
        <i class="fas fa-undo-alt"></i>
        คืนอุปกรณ์
    </a>
    
    <?php // (เมนูสำหรับ Admin และ Editor) ?>
    <?php if (in_array($user_role, ['admin', 'editor'])): ?>
    <a href="admin/manage_equipment.php" class="<?php echo ($current_page == 'manage_equip') ? 'active' : ''; ?>">
        <i class="fas fa-tools"></i>
        จัดการอุปกรณ์
    </a>
    
    <a href="admin/manage_fines.php" class="<?php echo ($current_page == 'manage_fines') ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        จัดการค่าปรับ
    </a>
    <?php endif; // (จบ Admin/Editor) ?>


    <?php 
    // (เมนูที่เหลือ จะแสดงเฉพาะ Admin เท่านั้น)
    if ($user_role == 'admin'): 
    ?>
    
    <a href="admin/manage_students.php" class="<?php echo ($current_page == 'manage_user') ? 'active' : ''; ?>">
        <i class="fas fa-users-cog"></i>
        จัดการผู้ใช้
    </a>
    
    <a href="admin/report_borrowed.php" class="<?php echo ($current_page == 'report') ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        รายงาน
    </a>
    
    <a href="admin/admin_log.php" class="<?php echo ($current_page == 'admin_log') ? 'active' : ''; ?>">
        <i class="fas fa-history"></i>
        Log Admin
    </a>

    <?php endif; // (จบการเช็ค Admin) ?>
</nav>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/admin_app.js?v=<?php echo time(); ?>"></script>

<script>
    // --- ⏳ ตั้งค่า Auto Logout (JavaScript) ---
    // ตั้งเวลาให้ตรงหรือน้อยกว่า PHP นิดหน่อย (หน่วยเป็น Milliseconds)
    // 30 นาที = 30 * 60 * 1000 = 1,800,000 ms
    const INACTIVITY_LIMIT = 1800000; 
    let inactivityTimer;

    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        // เริ่มนับถอยหลังใหม่
        inactivityTimer = setTimeout(doLogout, INACTIVITY_LIMIT);
    }

    function doLogout() {
        // แจ้งเตือนก่อนดีดออก (Optional) หรือดีดเลยก็ได้
        Swal.fire({
            title: 'หมดเวลาการใช้งาน',
            text: 'คุณไม่ได้ทำรายการเป็นเวลานาน ระบบจะออกจากระบบอัตโนมัติ',
            icon: 'warning',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false
        }).then(() => {
            // สั่ง Redirect ไปไฟล์ Logout
            // (ตรวจสอบ Path ให้ถูกว่าไฟล์ logout.php อยู่ไหน)
            window.location.href = '../logout.php?reason=timeout'; 
        });
    }

    // ดักจับเหตุการณ์การเคลื่อนไหวของผู้ใช้ เพื่อ Reset เวลา
    window.onload = resetInactivityTimer;
    document.onmousemove = resetInactivityTimer;
    document.onkeypress = resetInactivityTimer;
    document.ontouchstart = resetInactivityTimer; // สำหรับมือถือ
    document.onclick = resetInactivityTimer;
    document.onscroll = resetInactivityTimer;
</script>
</body>
</html>