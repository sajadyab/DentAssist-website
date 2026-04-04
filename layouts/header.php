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
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">

    <?php
    $reqUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $isPatientSection = (strpos($reqUri, '/patient/') !== false);
    ?>
    <?php if ($isPatientSection): ?>
        <link href="<?php echo SITE_URL; ?>/assets/css/patient.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="<?php echo $isPatientSection ? 'section-patient' : ''; ?>">
    
    <?php if (Auth::isLoggedIn()): ?>
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
    <?php endif; ?>