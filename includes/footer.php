<?php
// [แก้ไขไฟล์: napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/includes/footer.php]
// includes/footer.php (ฉบับสมบูรณ์ V3.2 - เพิ่ม Workflow ค่าปรับ+คืน)

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

<script src="assets/js/theme.js"></script>
<script src="assets/js/admin_app.js"></script> </body>
</html>