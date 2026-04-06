<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo htmlspecialchars(asset_url('assets/css/style.css')); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">

    <?php
    $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $reqUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $isPatientSection = (strpos($scriptFile, '/patient/') !== false)
        || (strpos($reqUri, '/patient/') !== false);
    ?>
</head>
<body class="<?php echo $isPatientSection ? 'section-patient' : ''; ?>">
    
    <?php if (Auth::isLoggedIn()): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
        <button type="button" class="sidebar-mobile-toggle d-md-none" id="sidebarMobileOpen" aria-controls="app-sidebar" aria-expanded="false" aria-label="<?php echo htmlspecialchars(__('open_menu', 'Open menu')); ?>">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <div class="sidebar-backdrop d-md-none" id="sidebarBackdrop" hidden></div>
        <script>
        (function () {
            var sb = document.getElementById('app-sidebar');
            var bd = document.getElementById('sidebarBackdrop');
            var bt = document.getElementById('sidebarMobileOpen');
            if (!sb || !bd || !bt) return;
            function closeSidebar() {
                sb.classList.remove('open');
                bd.hidden = true;
                bt.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('sidebar-drawer-open');
            }
            function openSidebar() {
                sb.classList.add('open');
                bd.hidden = false;
                bt.setAttribute('aria-expanded', 'true');
                document.body.classList.add('sidebar-drawer-open');
            }
            bt.addEventListener('click', function () {
                if (sb.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
            bd.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeSidebar();
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 768) closeSidebar();
            });
        })();
        </script>
        <div class="main-content">
    <?php endif; ?>