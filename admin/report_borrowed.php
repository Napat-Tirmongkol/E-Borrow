<?php
// report_borrowed.php (เวอร์ชันลบ Log ออก)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session.php');
require_once('db_connect.php');

// 2. ตรวจสอบสิทธิ์ Admin 
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// 3. (PHP) รับค่าตัวกรอง (Filter)
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$filter_status     = isset($_GET['status']) ? $_GET['status'] : ''; 
$filter_overdue    = isset($_GET['overdue']) ? $_GET['overdue'] : ''; 

// 4. (PHP) สร้าง SQL Query แบบไดนามิก
$report_data = [];
$sql_conditions = []; 
$sql_params = [];     

$sql_base = "SELECT 
                t.borrow_date, 
                t.due_date,
                t.return_date,
                t.status as transaction_status,
                t.approval_status,
                et.name as equipment_name, 
                s.full_name as borrower_name,
                u_staff.full_name as staff_name
            FROM med_transactions t
            LEFT JOIN med_equipment_types et ON t.equipment_type_id = et.id
            LEFT JOIN med_students s ON t.borrower_student_id = s.id
            LEFT JOIN med_users u_staff ON t.lending_staff_id = u_staff.id
            ";

if (!empty($filter_start_date)) {
    $sql_conditions[] = "DATE(t.borrow_date) >= ?";
    $sql_params[] = $filter_start_date;
}
if (!empty($filter_end_date)) {
    $sql_conditions[] = "DATE(t.borrow_date) <= ?";
    $sql_params[] = $filter_end_date;
}
if (!empty($filter_status)) {
    if ($filter_status == 'borrowed') {
        $sql_conditions[] = "t.status = 'borrowed' AND t.approval_status IN ('approved', 'staff_added')";
    } elseif ($filter_status == 'returned') {
        $sql_conditions[] = "t.status = 'returned'";
    } elseif ($filter_status == 'pending') {
        $sql_conditions[] = "t.approval_status = 'pending'";
    } elseif ($filter_status == 'rejected') {
        $sql_conditions[] = "t.approval_status = 'rejected'";
    }
}
if (!empty($filter_overdue) && $filter_overdue == 'yes') {
    $sql_conditions[] = "t.status = 'borrowed' AND t.due_date < CURDATE()";
}

$sql_query = $sql_base; 
if (!empty($sql_conditions)) {
    $sql_query .= " WHERE " . implode(" AND ", $sql_conditions);
}
$sql_query .= " ORDER BY t.borrow_date DESC"; 

try {
    $stmt = $pdo->prepare($sql_query);
    $stmt->execute($sql_params); 
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $report_error = "เกิดข้อผิดพลาดในการดึงข้อมูลรายงาน: " . $e->getMessage();
}


// 5. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงาน (ประวัติการยืม-คืน)"; // ◀️ (แก้ไขชื่อเล็กน้อย)
$current_page = "report";

// 6. เรียกใช้ Header
include('includes/header.php');
?>

<div class="header-row">
    <h2><i class="fas fa-chart-line"></i> 📊 รายงานประวัติการยืมคืนทั้งหมด</h2>
</div>

<div class="filter-row">
    <form action="report_borrowed.php" method="GET" style="display: contents;">
        
        <label for="start_date">วันที่ยืม (เริ่มต้น):</label>
        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
        
        <label for="end_date">วันที่ยืม (สิ้นสุด):</label>
        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">

        <label for="status">สถานะ:</label>
        <select name="status" id="status">
            <option value="">-- ทั้งหมด --</option>
            <option value="borrowed" <?php if ($filter_status == 'borrowed') echo 'selected'; ?>>กำลังยืม (อนุมัติแล้ว)</option>
            <option value="returned" <?php if ($filter_status == 'returned') echo 'selected'; ?>>คืนแล้ว</option>
            <option value="pending" <?php if ($filter_status == 'pending') echo 'selected'; ?>>รอดำเนินการ</option>
            <option value="rejected" <?php if ($filter_status == 'rejected') echo 'selected'; ?>>ถูกปฏิเสธ</option>
        </select>

        <div style="display: flex; align-items: center; gap: 0.5rem; padding-left: 10px; border-left: 2px solid #ddd;">
            <input type="checkbox" name="overdue" id="overdue" value="yes" <?php if ($filter_overdue == 'yes') echo 'checked'; ?> style="width: 20px; height: 20px;">
            <label for="overdue" style="font-weight: bold; color: var(--color-danger); margin: 0;">เฉพาะที่เกินกำหนดคืน (ที่ยังไม่คืน)</label>
        </div>

        <button type="submit" class="btn btn-return"><i class="fas fa-filter"></i> กรองข้อมูล</button>
        <a href="report_borrowed.php" class="btn btn-secondary"><i class="fas fa-times"></i> ล้างค่า</a>
    </form>
</div>


<?php if (isset($report_error)) echo "<p style='color: red;'>$report_error</p>"; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>อุปกรณ์</th>
                <th>ผู้ยืม</th>
                <th>วันที่ยืม</th>
                <th>กำหนดคืน</th>
                <th>วันที่คืนจริง</th>
                <th>ผู้อนุมัติ (พนักงาน)</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($report_data)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">ไม่พบข้อมูลตามเงื่อนไขที่กำหนด</td>
                </tr>
            <?php else: ?>
                <?php foreach ($report_data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['equipment_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['borrower_name'] ?? '[N/A]'); ?></td>
                        <td>
                            <?php echo date('d/m/Y h:i A', strtotime($row['borrow_date'])); ?>
                        </td>
                        <td>
                            <?php echo date('d/m/Y', strtotime($row['due_date'])); ?>
                        </td>
                        <td>
                            <?php echo $row['return_date'] ? date('d/m/Y h:i A', strtotime($row['return_date'])) : '-'; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['staff_name'] ?? '[N/A]'); ?>
                        </td>
                        <td>
                            <?php 
                                if ($row['approval_status'] == 'pending') {
                                    echo '<span class="status-badge pending">รอดำเนินการ</span>';
                                } elseif ($row['approval_status'] == 'rejected') {
                                    echo '<span class="status-badge rejected">ถูกปฏิเสธ</span>';
                                } elseif ($row['transaction_status'] == 'returned') {
                                    echo '<span class="status-badge returned">คืนแล้ว</span>';
                                } elseif ($row['transaction_status'] == 'borrowed') {
                                    if (strtotime($row['due_date']) < time()) {
                                        echo '<span class="status-badge overdue">เกินกำหนดคืน</span>';
                                    } else {
                                        echo '<span class="status-badge borrowed-ok">กำลังยืม</span>';
                                    }
                                }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// 7. เรียกใช้ Footer
include('includes/footer.php');
?>