<?php
// admin/admin_log.php
include('../includes/check_session.php'); 
require_once('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$log_type = $_GET['log_type'] ?? 'signin';
$limit = 10; 
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

function renderTableHeaders($type) {
    if ($type == 'signin') {
        return '<tr><th style="width: 180px;">เวลา</th><th style="width: 200px;">ผู้เข้าใช้งาน</th><th style="width: 150px;">ประเภทการเข้าสู่ระบบ</th><th>รายละเอียด IP / สถานะ</th></tr>';
    } else {
        return '<tr><th style="width: 180px;">เวลา</th><th style="width: 200px;">ผู้ดำเนินการ (Admin)</th><th style="width: 150px;">การกระทำ (Action)</th><th>รายละเอียดการเปลี่ยนแปลง</th></tr>';
    }
}

function renderTableRows($data, $type) {
    if (empty($data)) return '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #999;">ไม่พบข้อมูลในช่วงเวลานี้</td></tr>';
    $html = '';
    foreach ($data as $log) {
        $badge = ($type == 'signin') 
            ? (($log['action'] == 'login_line') ? '<span class="status-badge" style="background-color: #06c755; color: white;"><i class="fab fa-line"></i> LINE Login</span>' : '<span class="status-badge" style="background-color: #6c757d; color: white;"><i class="fas fa-key"></i> Password</span>')
            : '<span class="status-badge grey">'.htmlspecialchars($log['action']).'</span>';
        $html .= '<tr><td>'.date('d/m/Y H:i:s', strtotime($log['timestamp'])).'</td><td>'.htmlspecialchars($log['admin_name'] ?? 'Unknown').'</td><td>'.$badge.'</td><td style="white-space: pre-wrap; text-align: left;">'.htmlspecialchars($log['description']).'</td></tr>';
    }
    return $html;
}

function renderPagination($current_page, $total_pages) {
    if ($total_pages <= 1) return '';
    $prev_disabled = ($current_page <= 1) ? 'disabled' : '';
    $next_disabled = ($current_page >= $total_pages) ? 'disabled' : '';
    return '<span class="pagination-info">หน้า '.$current_page.' จาก '.$total_pages.'</span><div><button type="button" class="btn btn-secondary '.$prev_disabled.'" onclick="changePage('.($current_page - 1).')"><i class="fas fa-chevron-left"></i> ก่อนหน้า</button><button type="button" class="btn btn-secondary '.$next_disabled.'" onclick="changePage('.($current_page + 1).')">ถัดไป <i class="fas fa-chevron-right"></i></button></div>';
}

$date_condition = ""; $date_params = [];
if (!empty($start_date)) { $date_condition .= " AND DATE(l.timestamp) >= ?"; $date_params[] = $start_date; }
if (!empty($end_date)) { $date_condition .= " AND DATE(l.timestamp) <= ?"; $date_params[] = $end_date; }

$base_where = ($log_type == 'signin') ? "WHERE l.action IN ('login_password', 'login_line')" : "WHERE l.action NOT IN ('login_password', 'login_line')";
$sql_count = "SELECT COUNT(*) FROM med_logs l $base_where $date_condition";
$sql_data = "SELECT l.*, u.full_name as admin_name FROM med_logs l LEFT JOIN med_users u ON l.user_id = u.id $base_where $date_condition ORDER BY l.timestamp DESC LIMIT $limit OFFSET $offset";

if (isset($_GET['ajax_update']) && $_GET['ajax_update'] == '1') {
    try {
        $stmt_count = $pdo->prepare($sql_count); $stmt_count->execute($date_params); $total_logs = $stmt_count->fetchColumn(); $total_pages = ceil($total_logs / $limit);
        $stmt_data = $pdo->prepare($sql_data); $stmt_data->execute($date_params); $logs_data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'headers_html' => renderTableHeaders($log_type), 'body_html' => renderTableRows($logs_data, $log_type), 'pagination_html' => renderPagination($page, $total_pages)]);
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit;
}

try {
    $stmt_count = $pdo->prepare($sql_count); $stmt_count->execute($date_params); $total_logs = $stmt_count->fetchColumn(); $total_pages = ceil($total_logs / $limit);
    $stmt_data = $pdo->prepare($sql_data); $stmt_data->execute($date_params); $initial_logs = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $log_error = "Database Error: " . $e->getMessage(); }

$page_title = "บันทึก Log (Admin)";
$current_page = "admin_log"; 
include('../includes/header.php');
?>
<style>
    .time-filter-group { display: flex; align-items: center; background-color: #1e1e1e; padding: 6px 15px; border-radius: 8px; gap: 15px; color: #e0e0e0; font-size: 0.9rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); flex-wrap: wrap; }
    .time-filter-btn { background: none; border: none; color: #4dabf7; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 4px 8px; transition: all 0.2s; border-radius: 4px; font-family: 'RSU', sans-serif; font-weight: bold; font-size: 1rem; }
    .time-filter-btn:hover { background-color: rgba(255,255,255,0.1); color: #fff; }
    .time-filter-select { background-color: transparent !important; color: #fff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; border-radius: 4px; cursor: pointer; font-family: 'RSU', sans-serif; font-size: 0.95rem; outline: none; padding: 4px 8px; box-shadow: none !important; }
    .time-filter-select option { color: #333; background: #fff; }
    .toolbar-separator { width: 1px; height: 20px; background-color: #555; display: inline-block; }
    .data-section-wrapper { position: relative; min-height: 200px; }
    .loading-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7); z-index: 10; display: none; justify-content: center; align-items: flex-start; padding-top: 100px; border-radius: var(--border-radius-main); }
    body.dark-mode .loading-overlay { background: rgba(45, 55, 72, 0.8); }
    .spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<div class="header-row" style="flex-wrap: wrap; gap: 15px; align-items: center;">
    <h2><i class="fas fa-history"></i> 📜 บันทึก Log (Admin)</h2>
    <div class="time-filter-group">
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-filter" style="color: #4dabf7;"></i>
            <select class="time-filter-select" id="logTypeSelect" onchange="refreshLogData(1)" style="font-weight: bold; min-width: 180px;">
                <option value="signin">ประวัติการเข้าสู่ระบบ</option>
                <option value="actions">บันทึกการดำเนินการอื่นๆ</option>
            </select>
        </div>
        <span class="toolbar-separator"></span>
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="far fa-clock" style="color: #aaa;"></i>
            <select class="time-filter-select" id="timeRangeSelect" onchange="handleTimeRangeChange(this.value)">
                <option value="" disabled selected>เลือกช่วงเวลา</option>
                <option value="today">วันนี้ (Today)</option>
                <option value="48h">2 วันย้อนหลัง</option>
                <option value="7d">7 วันย้อนหลัง</option>
                <option value="30d">30 วันย้อนหลัง</option>
                <option value="custom">กำหนดเอง (Custom range)...</option>
            </select>
        </div>
        <span class="toolbar-separator"></span>
        <button type="button" class="time-filter-btn" onclick="refreshLogData(1)"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
</div>

<?php if (isset($log_error)) echo "<p style='color: red;'>$log_error</p>"; ?>

<input type="hidden" id="hidden_start_date" value="">
<input type="hidden" id="hidden_end_date" value="">
<input type="hidden" id="hidden_current_page" value="1">

<div class="data-section-wrapper">
    <div id="mainLoader" class="loading-overlay"><div class="spinner"></div></div>
    <div class="table-container">
        <table>
            <thead id="logTableHead"><?php echo renderTableHeaders($log_type); ?></thead>
            <tbody id="logTableBody"><?php echo renderTableRows($initial_logs, $log_type); ?></tbody>
        </table>
    </div>
    <div id="paginationContainer" class="pagination-container"><?php echo renderPagination($page, $total_pages); ?></div>
</div>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}
function refreshLogData(page = 1, customStart = null, customEnd = null) {
    let start = customStart || document.getElementById('hidden_start_date').value;
    let end = customEnd || document.getElementById('hidden_end_date').value;
    let logType = document.getElementById('logTypeSelect').value;
    const loader = document.getElementById('mainLoader');
    loader.style.display = 'flex';
    const params = new URLSearchParams({ ajax_update: '1', page: page, start_date: start, end_date: end, log_type: logType });
    fetch(`admin/admin_log.php?${params.toString()}`).then(r => r.json()).then(d => {
        if(d.status === 'success') {
            setTimeout(() => {
                document.getElementById('logTableHead').innerHTML = d.headers_html;
                document.getElementById('logTableBody').innerHTML = d.body_html;
                document.getElementById('paginationContainer').innerHTML = d.pagination_html;
                if(customStart) document.getElementById('hidden_start_date').value = customStart;
                if(customEnd) document.getElementById('hidden_end_date').value = customEnd;
                document.getElementById('hidden_current_page').value = page;
                loader.style.display = 'none';
            }, 300);
        } else { throw new Error(d.message); }
    }).catch(e => { console.error(e); loader.style.display = 'none'; Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลได้', 'error'); });
}
function changePage(newPage) { refreshLogData(newPage); }
function handleTimeRangeChange(value) {
    const today = new Date();
    let start = new Date();
    let end = new Date(); 
    if (value === 'custom') { openCustomRangePopup(); document.getElementById('timeRangeSelect').value = ""; return; }
    switch(value) {
        case 'today': break;
        case '48h': start.setDate(today.getDate() - 1); break;
        case '7d': start.setDate(today.getDate() - 7); break;
        case '30d': start.setDate(today.getDate() - 30); break;
        default: return;
    }
    refreshLogData(1, formatDate(start), formatDate(end));
}
function openCustomRangePopup() {
    Swal.fire({
        title: '<span style="font-size: 1.1rem;">Select a custom date range</span>',
        background: '#222', color: '#fff',
        html: `
            <div style="text-align: left; padding: 0 10px;">
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 5px; font-size: 0.9rem; color: #ccc;">From:</label>
                    <input type="date" id="swal-start-date" class="swal2-input" style="width: 100%; margin: 0; background: #333; color: #fff; border: 1px solid #555;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 5px; font-size: 0.9rem; color: #ccc;">To:</label>
                    <input type="date" id="swal-end-date" class="swal2-input" style="width: 100%; margin: 0; background: #333; color: #fff; border: 1px solid #555;">
                </div>
            </div>`,
        showCancelButton: true, confirmButtonText: 'OK', confirmButtonColor: '#0078d4', cancelButtonText: 'Cancel', cancelButtonColor: '#333',
        preConfirm: () => {
            const s = document.getElementById('swal-start-date').value;
            const e = document.getElementById('swal-end-date').value;
            if (!s || !e) { Swal.showValidationMessage('กรุณาเลือกวันที่ให้ครบถ้วน'); return false; }
            if (s > e) { Swal.showValidationMessage('วันที่เริ่มต้น ต้องไม่มากกว่าวันที่สิ้นสุด'); return false; }
            return { start: s, end: e };
        }
    }).then((result) => { if (result.isConfirmed) refreshLogData(1, result.value.start, result.value.end); });
}
</script>
<?php include('../includes/footer.php'); ?>