<?php
/**
 * Public auth layout — head + top bar (login, register, forgot password, reset).
 * Set before include:
 *   $pageTitle (optional), $authBodyClass (e.g. auth-shell--login), $authNavActive (login|register|'').
 *   $authIncludeIntlTel (bool), $authHeadExtra (raw HTML for <head>).
 */
if (!defined('SITE_NAME') || !defined('SITE_URL')) {
    throw new RuntimeException('auth_header.php requires config (SITE_NAME, SITE_URL).');
}
$authBodyClass = $authBodyClass ?? 'auth-shell--login';
$authNavActive = $authNavActive ?? '';
$authBrandPlain = trim(preg_replace('/\s+/', ' ', strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', SITE_NAME))));
$base = rtrim(SITE_URL, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars((string) $pageTitle) . ' - ' : ''; ?><?php echo htmlspecialchars($authBrandPlain); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo htmlspecialchars(asset_url('assets/css/style.css')); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
    <?php if (!empty($authIncludeIntlTel)): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">
    <?php endif; ?>
    <?php if (!empty($authHeadExtra)) {
        echo $authHeadExtra;
    } ?>
</head>
<body class="auth-layout <?php echo htmlspecialchars($authBodyClass); ?>">
<header class="auth-site-header">
  
</header>
<main class="auth-main" role="main">
