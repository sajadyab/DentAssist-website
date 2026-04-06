<?php
/**
 * Simple HTML email helper (uses PHP mail()).
 * Configure sendmail / SMTP in php.ini or your host panel for delivery.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * @return array{ok:bool,error:?string}
 */
function dental_send_password_reset_email(string $to, string $recipientName, string $username, string $newPasswordPlain): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email address.'];
    }

    $loginUrl = SITE_URL . '/login.php';
    $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safePass = htmlspecialchars($newPasswordPlain, ENT_QUOTES, 'UTF-8');

    $subject = 'Your new password — ' . MAIL_FROM_NAME;

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Segoe UI,Arial,sans-serif;line-height:1.5;color:#333;">
  <p>Hello {$safeName},</p>
  <p>Your password for <strong>DentAssist</strong> has been reset.</p>
  <p>
    <strong>Username:</strong> {$safeUser}<br>
    <strong>New password:</strong> {$safePass}
  </p>
  <p>Please sign in and change your password from your profile when you can.</p>
  <p><a href="{$loginUrl}">Sign in</a></p>
  <p style="font-size:12px;color:#666;">If you did not request this, contact the clinic immediately.</p>
</body>
</html>
HTML;

    $from = MAIL_FROM_ADDRESS;
    $fromName = MAIL_FROM_NAME;
    $encodedName = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n")
        : $fromName;

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $encodedName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $params = '';
    if (stripos(PHP_OS, 'WIN') === false && $from !== '') {
        $params = '-f' . $from;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = mail($to, $encodedSubject, $html, implode("\r\n", $headers), $params);

    if ($ok) {
        return ['ok' => true, 'error' => null];
    }

    $err = error_get_last();
    $reason = is_array($err) ? ($err['message'] ?? null) : null;
    $sendmailPath = (string) ini_get('sendmail_path');

    $msg = 'mail() failed.';
    if ($reason) {
        $msg .= ' Last error: ' . $reason;
    } else {
        // When sendmail.exe is misconfigured, PHP often fails without a useful error_get_last().
        $msg .= ' No extra error details.';
    }
    if ($sendmailPath !== '') {
        $msg .= ' sendmail_path=' . $sendmailPath;
    }
    $msg .= ' If you are using XAMPP sendmail, check C:\\xampp\\sendmail\\sendmail.ini (smtp_server / smtp_port / auth_*)';

    return ['ok' => false, 'error' => $msg];
}
