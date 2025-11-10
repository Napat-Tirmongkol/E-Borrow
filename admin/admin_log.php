<?php
include('../includes/check_session.php'); 
require_once('../includes/db_connect.php');

// 2. ตรวจสอบสิทธิ์ Admin 
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// ◀️ (ใหม่) 3.1: ตรวจสอบว่าเป็น AJAX Request หรือไม่
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// 3.2: ตั้งค่า Pagination
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;


// ◀️ (แก้ไข) 4. Query 1 (Sign-in Log) - จะทำเฉพาะเมื่อ "ไม่ใช่" AJAX
if (!$is_ajax) {
    $signin_logs = [];
    try {
        $sql_signin = "SELECT l.*, u.full_name as admin_name 
                       FROM med_logs l
                       LEFT JOIN med_users u ON l.user_id = u.id
                       WHERE l.action IN ('login_password', 'login_line')
                       ORDER BY l.timestamp DESC";
                     
        $stmt_signin = $pdo->prepare($sql_signin);
        $stmt_signin->execute();
        $signin_logs = $stmt_signin->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // ◀️ (แก้ไข)
        $log_error = "เกิดข้อผิดพลาดในการดึงข้อมูล Log (Sign-in): " . $e->getMessage();
    }
}

// 5. (Query ที่ 2) ดึงข้อมูล Admin Log (ส่วนนี้ทำงานทุกครั้ง)
$admin_logs = [];
$total_admin_logs = 0;
$total_admin_pages = 0;

try {
    // 5.1: นับจำนวน Log ทั้งหมด
    $sql_logs_count = "SELECT COUNT(*) 
                       FROM med_logs l
                       WHERE l.action NOT IN ('login_password', 'login_line')";
    
    $stmt_logs_count = $pdo->prepare($sql_logs_count);
    $stmt_logs_count->execute();
    $total_admin_logs = $stmt_logs_count->fetchColumn();
    $total_admin_pages = ceil($total_admin_logs / $limit);

    // 5.2: ดึงข้อมูล Log แบบแบ่งหน้า
    $sql_logs = "SELECT l.*, u.full_name as admin_name 
                 FROM med_logs l
                 LEFT JOIN med_users u ON l.user_id = u.id
                 WHERE l.action NOT IN ('login_password', 'login_line')
                 ORDER BY l.timestamp DESC
                 LIMIT :limit OFFSET :offset"; 
                 
    $stmt_logs = $pdo->prepare($sql_logs);
    $stmt_logs->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_logs->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_logs->execute();
    $admin_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // ◀️ (แก้ไข)
    $log_error = (isset($log_error) ? $log_error . "<br>" : "") . "เกิดข้อผิดพลาดในการดึงข้อมูล Log (Admin): " . $e->getMessage();
}


// 6. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "บันทึก Log (Admin)";
$current_page = "admin_log"; 

// ◀️ (ใหม่) 7. ถ้า "ไม่ใช่" AJAX ให้แสดง Header
if (!$is_ajax) {
    include('../includes/header.php');
?>

<div class="header-row">
    <h2><i class="fas fa-history"></i> 📜 บันทึก Log (Admin)</h2>
</div>

<?php if (isset($log_error)) echo "<p style='color: red;'>$log_error</p>"; ?>

<div class="header-row" style="margin-top: 2rem;">
    <h2><i class="fas fa-sign-in-alt"></i> ประวัติการเข้าสู่ระบบ (Sign-in Log)</h2>
</div>
<div class="table-container" style="margin-bottom: 2rem;">
    <table>
        <thead>
            <tr>
                <th style="width: 160px;">เวลา</th>
                <th style="width: 150px;">ผู้ดำเนินการ</th>
                <th style="width: 150px;">การกระทำ (Action)</th>
                <th>รายละเอียด</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($signin_logs)): ?>
                <tr>
                    <td colspan="4" style="text-align: center;">ยังไม่มีข้อมูลการเข้าสู่ระบบ</td>
                </tr>
            <?php else: ?>
                <?php foreach ($signin_logs as $log): ?>
                    <tr>
                        <td><?php echo date('d/m/Y h:i:s A', strtotime($log['timestamp'])); ?></td>
                        <td><?php echo htmlspecialchars($log['admin_name'] ?? '[N/A]'); ?></td>
                        <td>
                            <?php if ($log['action'] == 'login_line'): ?>
                                <span class="status-badge" style="background-color: #00B900; color: white;">
                                    <i class="fab fa-line"></i> <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge grey">
                                    <i class="fas fa-key"></i> <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: pre-wrap;"><?php echo htmlspecialchars($log['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="header-row">
    <h2><i class="fas fa-user-shield"></i> บันทึกการดำเนินการอื่นๆ (Admin Actions)</h2>
</div>

<?php 
} // ◀️ (ใหม่) จบเงื่อนไข if (!$is_ajax)
?>


<div id="admin-log-content"> 
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 160px;">เวลา</th>
                    <th style="width: 150px;">ผู้ดำเนินการ (Admin)</th>
                    <th style="width: 150px;">การกระทำ (Action)</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admin_logs)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">ไม่พบข้อมูลการดำเนินการใน Log</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($admin_logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y h:i:s A', strtotime($log['timestamp'])); ?></td>
                            <td><?php echo htmlspecialchars($log['admin_name'] ?? '[N/A]'); ?></td>
                            <td>
                                <span class="status-badge grey"><?php echo htmlspecialchars($log['action']); ?></span>
                            </td>
                            <td style="white-space: pre-wrap;"><?php echo htmlspecialchars($log['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_admin_pages > 1): ?>
        <div class="pagination-container">
            <span class="pagination-info">
                หน้า <?php echo $page; ?> จาก <?php echo $total_admin_pages; ?> (ทั้งหมด <?php echo $total_admin_logs; ?> รายการ)
            </span>
            
            <div>
                <a href="?page=<?php echo $page - 1; ?>" 
                   class="btn btn-secondary <?php if ($page <= 1) echo 'disabled'; ?>">
                    <i class="fas fa-chevron-left"></i> ก่อนหน้า
                </a>
                
                <a href="?page=<?php echo $page + 1; ?>" 
                   class="btn btn-secondary <?php if ($page >= $total_admin_pages) echo 'disabled'; ?>">
                    ถัดไป <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
// ◀️ (ใหม่) 9. ถ้า "ไม่ใช่" AJAX ให้แสดง Footer
if (!$is_ajax) {
    include('../includes/footer.php');
}
// ถ้าเป็น AJAX request สคริปต์จะจบการทำงานตรงนี้
// โดยส่งเฉพาะ HTML ที่อยู่ใน #admin-log-content กลับไป
?>