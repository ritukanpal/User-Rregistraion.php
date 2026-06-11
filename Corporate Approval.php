<?php
// FILE: Corporate Approval.php
// Admin helper to approve a corporate account and send approval email using Corporate Approval.html

$config_locations = [
    '/home/u624259711/domains/orchid-alligator-169347.hostingersite.com/public_html/config.php',
    dirname(__FILE__) . '/config.php',
    dirname(__DIR__) . '/config.php',
];
foreach ($config_locations as $cfg) {
    if (file_exists($cfg)) { require_once $cfg; break; }
}
if (!defined('PUBLIC_PATH')) die('config.php not found');
if (session_status()===PHP_SESSION_NONE) {
    session_start(['cookie_httponly'=>true,'cookie_secure'=>isset($_SERVER['HTTPS']),'cookie_samesite'=>'Lax','use_strict_mode'=>true]);
}

require_once PUBLIC_PATH . '/includes/db.php';
@include_once PUBLIC_PATH . '/includes/mailer.php';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$error = '';
$success = false;

function sendHtmlEmail($toEmail, $toName, $subject, $htmlBody, $plainText = null){
    // Prefer project mailer hooks when available
    if (function_exists('send_mail')) return send_mail($toEmail, $subject, $htmlBody, ['html'=>true]);
    if (function_exists('mailHtml')) return mailHtml($toEmail, $subject, $htmlBody);

    // Fallback: send multipart/alternative with plain-text and HTML parts
    // Use provided plain-text when available (ensures exact messaging), otherwise generate from HTML
    $plain = $plainText ?? html_entity_decode(strip_tags(preg_replace('#<br */?>#i', "\n", $htmlBody)), ENT_QUOTES|ENT_HTML5);
    $boundary = '=_'.bin2hex(random_bytes(8));

    $headers = [];
    $headers[] = 'From: Team Dausto <corporate@dausto.com>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = "--".$boundary."\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $plain . "\r\n\r\n";

    $body .= "--".$boundary."\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";

    $body .= "--".$boundary."--\r\n";

    return mail($toEmail, $subject, $body, implode("\r\n", $headers));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')){
        $error = 'Invalid form submission.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = 'Please enter a valid email.';
        } else {
            $db = getDB();
            // try corporates table first
            $client = null;
            try {
                $stmt = $db->prepare('SELECT id,name,email FROM corporates WHERE email=? LIMIT 1');
                $stmt->execute([$email]);
                $client = $stmt->fetch();
            } catch (Exception $e) { /* ignore */ }

            if (!$client){
                // fallback to users table with portal=client
                $stmt = $db->prepare('SELECT id,name,email FROM users WHERE email=? AND portal="client" LIMIT 1');
                $stmt->execute([$email]);
                $client = $stmt->fetch();
            }

            if (!$client){
                $error = 'Corporate account not found.';
            } else {
                // update status to active
                try {
                    if (isset($client['id'])){
                        $res = $db->query("SHOW TABLES LIKE 'corporates'");
                        if ($res && $res->rowCount()>0){
                            $db->prepare('UPDATE corporates SET status=?, approved_at=? WHERE id=?')
                               ->execute(['active', date('Y-m-d H:i:s'), $client['id']]);
                        } else {
                            $db->prepare('UPDATE users SET status=?, updated_at=? WHERE id=?')
                               ->execute(['active', date('Y-m-d H:i:s'), $client['id']]);
                        }
                    }
                } catch (Exception $e){
                    $error = 'Failed to update account status: ' . $e->getMessage();
                }

                // send approval email
                $tpl = __DIR__ . '/Corporate Approval.html';
                if (file_exists($tpl)){
                    $html = file_get_contents($tpl);
                    $html = str_replace('{{Client Name}}', htmlspecialchars($client['name'] ?? '', ENT_QUOTES|ENT_HTML5), $html);
                    $login = defined('SITE_URL') ? SITE_URL . '/login.php' : '#';
                    $html = str_replace('{{Login Link}}', $login, $html);
                } else {
                    $html = "<p>Dear " . htmlspecialchars($client['name'] ?? '') . ",<br>Your corporate account has been approved. Visit " . (defined('SITE_URL')?SITE_URL:'#') . " to sign in.</p>";
                }

                $subject = 'Corporate Account Approved';
                $plain = "Dear " . ($client['name'] ?? '') . ",\n\nYour corporate account has been successfully approved and activated.\n\nRegards,\nTeam Dausto";
                $sent = sendHtmlEmail($client['email'], $client['name'] ?? '', $subject, $html, $plain);
                if ($sent) $success = true; else $error = $error ?: 'Failed to send approval email.';
            }
        }
    }
}

$site_url = defined('SITE_URL') ? SITE_URL : '';
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Approve Corporate Account — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:560px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} input{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#16a34a;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Approve Corporate Account</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="ok">Corporate account approved and notification sent to <?=htmlspecialchars($email)?></div>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="email">Corporate email to approve</label>
    <input id="email" name="email" type="email" required value="<?=htmlspecialchars($_POST['email']??'')?>">
    <button type="submit">Approve Account</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
