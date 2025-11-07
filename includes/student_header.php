<?php
// includes/student_header.php
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
                    document.documentElement.classList.add('dark-mode');
                    // (ลบบรรทัด document.body.classList.add('dark-mode'); ออกจากตรงนี้)
                }
            } catch (e) { 
                console.error('Theme init error:', e); 
            }
        })();
    </script>
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
</head>
<body>