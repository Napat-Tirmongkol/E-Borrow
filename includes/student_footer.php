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

<script src="assets/js/theme.js"></script>

</body>
</html>