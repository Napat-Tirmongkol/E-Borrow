<?php
// includes/header.php (ฉบับสมบูรณ์ที่แก้ไขแล้ว)
@session_start(); 
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <base href="/e_Borrow_test/">

    <title><?php echo isset($page_title) ? $page_title : 'ระบบยืมคืนอุปกรณ์'; ?></title>
    
    <script>
        (function() {
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    // (เราจะเพิ่มคลาสให้ <html> ซึ่งปลอดภัยและทำงานได้ทันที)
                    document.documentElement.classList.add('dark-mode');
                }
            } catch (e) { 
                console.error('Theme init error:', e); 
            }
        })();
    </script>
    
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=2.2">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body> <header class="header"> 
        <h1>MedLoan - (Admin)</h1>
        
        <div class="user-info"> 
            
            <div class="user-greeting">
                สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                (<?php 
                    if ($_SESSION['role'] == 'admin') {
                        echo '<span style="color: var(--color-warning); font-weight: bold;">Admin <i class="fa-solid fa-crown"></i></span>';
                    } elseif ($_SESSION['role'] == 'employee') {
                        echo '<span style="color: #B7E5CD;">Employee</span>';
                    } else {
                        echo htmlspecialchars($_SESSION['role']);
                    }
                ?>)
            </div>

            <button type="button" class="theme-toggle-btn" id="theme-toggle-btn" title="สลับโหมด">
                <i class="fas fa-moon"></i>
                <i class="fas fa-sun"></i>
            </button>
            
            <a href="admin/logout.php" class="btn btn-logout" title="ออกจากระบบ">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="logout-text">ออกจากระบบ</span>
            </a>
        </div>
    </header>

    <main class="content" style="margin-top: 80px;">