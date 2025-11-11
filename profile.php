<?php
// edit_profile.php (หน้าตั้งค่า/แก้ไขโปรไฟล์)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
@session_start(); 
include('includes/check_student_session.php'); // (◀️ เปิดยาม)
require_once('includes/db_connect.php'); //

// 2. ดึง ID นักศึกษาจาก Session
$student_id = $_SESSION['student_id']; 

// 3. (Query ข้อมูลผู้ใช้ปัจจุบัน)
try {
    $stmt = $pdo->prepare("SELECT * FROM med_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) {
        // (ถ้าหา ID ไม่เจอ ให้เด้งออก)
        header("Location: logout.php");
        exit;
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . $e->getMessage());
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ตั้งค่าโปรไฟล์";
$active_page = 'settings'; // (บอก Footer ว่าเมนูไหน Active)
include('includes/student_header.php');
?>

<main class="main-container">

    <?php 
    // (ส่วนสำหรับแสดงข้อความ Success หรือ Error หลังจากกดบันทึก)
    if (isset($_GET['status'])): 
    ?>
        <div class="section-card" style="padding: 1.25rem; margin-bottom: 1rem; border: 2px solid <?php echo ($_GET['status'] == 'success') ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
            <?php if ($_GET['status'] == 'success'): ?>
                <p style="color: var(--color-success); font-weight: bold; margin: 0;">
                    <i class="fas fa-check-circle"></i> บันทึกข้อมูลสำเร็จ!
                </p>
            <?php else: ?>
                <p style="color: var(--color-danger); font-weight: bold; margin: 0;">
                    <i class="fas fa-times-circle"></i> เกิดข้อผิดพลาด: <?php echo htmlspecialchars($_GET['message'] ?? 'ไม่ทราบสาเหตุ'); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; 
    // (จบส่วนแสดงข้อความ)
    ?>

    <div class="section-card">
        <h2 class="section-title">ตั้งค่าโปรไฟล์</h2>
        <p class="text-muted">คุณสามารถแก้ไขข้อมูลส่วนตัวของคุณได้ที่นี่ (ข้อมูลสถานภาพจะถูกใช้โดย Admin เท่านั้น)</p>
        
        <form action="process/edit_profile_process.php" method="POST" id="profileForm">
            
            <div class="form-group">
                <label for="full_name">ชื่อ-นามสกุล <span style="color:red;">*</span></label>
                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="department">คณะ/หน่วยงาน/สถาบัน</label>
                <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="status">สถานภาพ (ดูได้อย่างเดียว)</label>
                <input type="text" id="status" value="<?php 
                    echo htmlspecialchars($user_data['status']); 
                    if ($user_data['status'] == 'other') {
                        echo ' (' . htmlspecialchars($user_data['status_other']) . ')';
                    }
                ?>" disabled style="background-color: #f4f4f4;">
            </div>

            <div class="form-group">
                <label for="student_personnel_id">รหัสผู้ใช้งาน/บุคลากร</label>
                <input type="text" name="student_personnel_id" id="student_personnel_id" value="<?php echo htmlspecialchars($user_data['student_personnel_id'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="phone_number">เบอร์โทรศัพท์</label>
                <input type="text" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn-loan" style="width: 100%; font-size: 1rem; padding: 12px;">
                <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
            </button>
        </form>
    </div>
</div> 

<?php
// 5. เรียกใช้ Footer
include('includes/student_footer.php'); 
?>