<?php
/**
 * Closes auth layout: main, footer, scripts.
 * Optional: $authFooterExtra — raw HTML/JS before </body> (inline scripts).
 */
if (!defined('SITE_NAME') || !defined('SITE_URL')) {
    throw new RuntimeException('auth_footer.php requires config.');
}
if (!isset($authBrandPlain)) {
    $authBrandPlain = trim(preg_replace('/\s+/', ' ', strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', SITE_NAME))));
}
$base = rtrim(SITE_URL, '/');
?>
</main>
<footer class="auth-site-footer">
    <div class="container-fluid py-3 text-center small">
        <span class="auth-footer-copy">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($authBrandPlain); ?></span>
        <span class="auth-footer-sep mx-2" aria-hidden="true">·</span>
        <a href="<?php echo htmlspecialchars($base); ?>/login.php" class="auth-footer-link">Sign in</a>
        <span class="auth-footer-sep mx-2" aria-hidden="true">·</span>
        <a href="<?php echo htmlspecialchars($base); ?>/register.php" class="auth-footer-link">Register</a>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars(asset_url('assets/js/api-forms.js')); ?>"></script>
<?php if (!empty($authFooterExtra)) {
    echo $authFooterExtra;
} ?>
</body>
</html>
