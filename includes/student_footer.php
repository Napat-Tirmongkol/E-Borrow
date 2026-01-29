<?php
// [แก้ไขไฟล์: napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/includes/student_footer.php]
// includes/student_footer.php

$active_page = $active_page ?? ''; 
?>

</main> 
<nav class="footer-nav">
    <a href="index.php" class="<?php echo ($active_page == 'home') ? 'active' : ''; ?>">
        <i class="fas fa-hand-holding-medical"></i>
        ที่ยืมอยู่
    </a>
    <a href="borrow.php" class="<?php echo ($active_page == 'borrow') ? 'active' : ''; ?>">
        <i class="fas fa-boxes-stacked"></i>
        ยืมอุปกรณ์
    </a>
    <a href="history.php" class="<?php echo ($active_page == 'history') ? 'active' : ''; ?>">
        <i class="fas fa-history"></i>
        ประวัติ
    </a>
    <a href="profile.php" class="<?php echo ($active_page == 'settings') ? 'active' : ''; ?>">
        <i class="fas fa-user-cog"></i>
        ตั้งค่า
    </a>
</nav>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/student_app.js?v=<?php echo time(); ?>"></script>

<script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
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