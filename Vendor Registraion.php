<?php
// Vendor registraion handler
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

$error = '';
$success = false;

// Simple HTML mail helper (matches other files in the project)
function sendHtmlEmail($toEmail, $toName, $subject, $htmlBody, $fromName = 'Team Dausto', $fromEmail = 'corporate@dausto.com'){
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    return mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$site_url = defined('SITE_URL') ? SITE_URL : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')){
        $error = 'Invalid form submission.';
    } else {
        $vendor_name = trim($_POST['vendor_name'] ?? '');
        $vendor_email = strtolower(trim($_POST['vendor_email'] ?? ''));
        $company = trim($_POST['company'] ?? '');

        if ($vendor_name === '' || !filter_var($vendor_email, FILTER_VALIDATE_EMAIL)){
            $error = 'Please provide a valid name and email address.';
        } else {
            // Load HTML email template
            $tplPath = __DIR__ . '/Vendor Registraion.html';
            if (file_exists($tplPath)){
                $html = file_get_contents($tplPath);
                $html = str_replace('{{Vendor Name}}', htmlspecialchars($vendor_name, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Company}}', htmlspecialchars($company, ENT_QUOTES|ENT_HTML5), $html);
            } else {
                $html = "<p>Dear " . htmlspecialchars($vendor_name) . ",</p><p>Thank you for registering with Dausto. Your application has been received and is currently under review.</p><p>Regards,<br>Team Dausto</p>";
            }

            $subject = 'Vendor Registration Received';

            // Send confirmation to vendor
            $sent = sendHtmlEmail($vendor_email, $vendor_name, $subject, $html);

            // Optionally notify admin (best-effort)
            if (defined('ADMIN_EMAIL')){
                $adminHtml = "<p>New vendor registration:</p><ul><li>Name: " . htmlspecialchars($vendor_name) . "</li><li>Email: " . htmlspecialchars($vendor_email) . "</li><li>Company: " . htmlspecialchars($company) . "</li></ul>";
                sendHtmlEmail(ADMIN_EMAIL, 'Admin', 'New Vendor Registration', $adminHtml);
            }

            $success = true;
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vendor Registration — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:640px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} input,textarea{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#2E3D9A;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Vendor Registration</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">Thank you. A confirmation email has been sent to <?=htmlspecialchars($vendor_email)?>.</div>
    <p><a href="<?=$site_url?>/">Back to site</a></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="vendor_name">Full name</label>
    <input id="vendor_name" name="vendor_name" required value="<?=htmlspecialchars($_POST['vendor_name']??'')?>">
    <label for="vendor_email">Email address</label>
    <input id="vendor_email" name="vendor_email" type="email" required value="<?=htmlspecialchars($_POST['vendor_email']??'')?>">
    <label for="company">Company (optional)</label>
    <input id="company" name="company" value="<?=htmlspecialchars($_POST['company']??'')?>">
    <button type="submit">Register</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
