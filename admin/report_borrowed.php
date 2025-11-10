<?php
include('../includes/check_session.php'); 
require_once('../includes/db_connect.php');

// 2. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// (ฟังก์ชันสำหรับ Export Excel)
// ◀️ (แก้ไข) เปลี่ยน $filename เริ่มต้น ◀️
function exportToExcel($data, $filename = "รายงานการยืมคืน") {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '_' . date('YmdHi') . '.xls"');
    header('Cache-Control: max-age=0');

    $output = fopen("php://output", "w");
    
    // (ใส่ BOM สำหรับ UTF-8 เพื่อให้อ่านภาษาไทยใน Excel ได้)
    fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

    // หัวตาราง
    fputcsv($output, [
        'ID', 'ประเภท', 'ชื่อ/รุ่น', 'ซีเรียล', 
        'ผู้ยืม', 'รหัสนักศึกษา/บุคลากร', 'เบอร์โทร', 'สถานภาพ', 
        'วันที่ยืม', 'กำหนดคืน', 'วันที่คืนจริง', 
        'สถานะ', 'ค่าปรับ (บาท)'
    ], "\t"); // (ใช้ Tab เป็นตัวคั่น)

    // ข้อมูล
    foreach ($data as $row) {
        // (เตรียมข้อมูล)
        $status_text = $row['status'];
        if ($status_text == 'borrowed') $status_text = 'ยืมอยู่';
        if ($status_text == 'returned') $status_text = 'คืนแล้ว';
        
        $student_status = $row['student_status'];
        if ($student_status == 'other') {
            $student_status = 'อื่นๆ (' . $row['student_status_other'] . ')';
        }

        fputcsv($output, [
            $row['transaction_id'],
            $row['type_name'],
            $row['item_name'],
            $row['serial_number'] ?? '-',
            $row['student_name'],
            $row['student_personnel_id'] ?? '-',
            $row['student_phone'] ?? '-',
            $student_status,
            $row['borrow_date'],
            $row['due_date'],
            $row['return_date'] ?? 'ยังไม่คืน',
            $status_text,
            $row['fine_amount'] ?? '0.00'
        ], "\t");
    }

    fclose($output);
    exit;
}

// 3. (Query หลัก) ดึงข้อมูลการยืมทั้งหมด
try {
    // (กำหนดค่าเริ่มต้นสำหรับ Filter)
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $status_filter = $_GET['status'] ?? '';

    $sql = "SELECT 
                t.id as transaction_id, t.borrow_date, t.due_date, t.return_date, t.status,
                et.name as type_name,
                ei.name as item_name, ei.serial_number, ei.image_url,
                s.full_name as student_name, s.student_personnel_id, s.phone_number as student_phone,
                s.status as student_status, s.status_other,
                f.amount as fine_amount
            FROM med_transactions t
            LEFT JOIN med_equipment_types et ON t.type_id = et.id
            LEFT JOIN med_equipment_items ei ON t.item_id = ei.id
            LEFT JOIN med_students s ON t.borrower_student_id = s.id
            LEFT JOIN med_fines f ON t.id = f.transaction_id
            WHERE t.approval_status = 'approved' "; // (ดึงเฉพาะที่อนุมัติแล้ว)

    $params = [];

    // (เพิ่มเงื่อนไข Filter)
    if (!empty($start_date)) {
        $sql .= " AND DATE(t.borrow_date) >= ?";
        $params[] = $start_date;
    }
    if (!empty($end_date)) {
        $sql .= " AND DATE(t.borrow_date) <= ?";
        $params[] = $end_date;
    }
    if (!empty($status_filter)) {
        $sql .= " AND t.status = ?";
        $params[] = $status_filter;
    }

    $sql .= " ORDER BY t.borrow_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // (ตรวจสอบ ถ้ามีการร้องขอ Export)
    if (isset($_GET['export']) && $_GET['export'] == 'excel') {
        exportToExcel($transactions, "รายงานการยืมคืน"); // ◀️ (แก้ไข) เปลี่ยนชื่อไฟล์ตอนเรียกใช้ด้วย ◀️
    }

} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    $transactions = [];
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "รายงานการยืม-คืน";
$current_page = "report";
// ◀️ (แก้ไข) เพิ่ม ../ ◀️
include('../includes/header.php');
?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="header-row">
    <h2><i class="fas fa-chart-line"></i> รายงานการยืม-คืนทั้งหมด</h2>
    <div>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export to Excel
        </a>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> พิมพ์รายงาน
        </a>
    </div>
</div>

<div class="filter-row section-card" style="margin-bottom: 1.5rem;">
    <form action="admin/report_borrowed.php" method="GET" style="display: contents; gap: 1rem;">
        <label for="start_date">ตั้งแต่วันที่:</label>
        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">

        <label for="end_date">ถึงวันที่:</label>
        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">

        <label for="status">สถานะ:</label>
        <select name="status" id="status">
            <option value="">-- ทั้งหมด --</option>
            <option value="borrowed" <?php echo ($status_filter == 'borrowed') ? 'selected' : ''; ?>>ยืมอยู่ (Borrowed)</option>
            <option value="returned" <?php echo ($status_filter == 'returned') ? 'selected' : ''; ?>>คืนแล้ว (Returned)</option>
        </select>

        <button type="submit" class="btn btn-return"><i class="fas fa-filter"></i> กรอง</button>
        <a href="admin/report_borrowed.php" class="btn btn-secondary"><i class="fas fa-times"></i> ล้างค่า</a>
    </form>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>อุปกรณ์</th>
                <th>ผู้ยืม</th>
                <th>วันที่ยืม</th>
                <th>กำหนดคืน</th>
                <th>วันที่คืนจริง</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">ไม่พบข้อมูลการทำรายการ</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $item): ?>
                    <tr>
                        <td>
                            <div class="item-cell">
                                <?php 
                                $image_path = $item['image_url'] ?? null;
                                if ($image_path): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                         alt="รูป" 
                                         class="item-thumbnail"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                    <div class="equipment-card-image-placeholder item-thumbnail" style="display: none;"><i class="fas fa-image"></i></div>
                                <?php else: ?>
                                    <div class="equipment-card-image-placeholder item-thumbnail">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-details">
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <small>(<?php echo htmlspecialchars($item['type_name']); ?>)</small>
                                    <small>S/N: <?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['student_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($item['student_personnel_id'] ?? 'N/A'); ?></small>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($item['borrow_date'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($item['due_date'])); ?></td>
                        <td>
                            <?php if ($item['return_date']): ?>
                                <?php echo date('d/m/Y', strtotime($item['return_date'])); ?>
                            <?php else: ?>
                                <span class="text-muted">ยังไม่คืน</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item['status'] == 'returned'): ?>
                                <span class="status-badge returned">
                                    <i class="fas fa-check-circle"></i> คืนแล้ว
                                </span>
                            <?php else: ?>
                                <span class="status-badge borrowed">
                                    <i class="fas fa-hourglass-half"></i> ยืมอยู่
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// 5. เรียกใช้ Footer
// ◀️ (แก้ไข) เพิ่ม ../ ◀️
include('../includes/footer.php');
?>