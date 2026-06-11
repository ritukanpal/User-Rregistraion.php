<?php
// FILE: Email verification.php
// Page to request/resend an email verification link.

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = '';
$error = '';

// helper: send HTML email (prefers project mailer functions when available)
function sendHtmlEmail($toEmail, $toName, $subject, $htmlBody, $fromName = 'Team Dausto', $fromEmail = 'corporate@dausto.com'){
    if (function_exists('mailVerification')) {
        return mailVerification($toEmail, $toName, $htmlBody);
    }
    if (function_exists('send_mail')) {
        return send_mail($toEmail, $subject, $htmlBody, ['from_name'=>$fromName,'from_email'=>$fromEmail,'html'=>true]);
    }
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    return mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, name, status FROM users WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always show success to avoid enumeration
            if ($user && $user['status'] !== 'active') {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours

                $db->prepare("UPDATE users SET email_verification_token=?, email_verification_expires=? WHERE id=?")
                   ->execute([$token, $expires, $user['id']]);

                $verification_url = SITE_URL . '/verify-email.php?token=' . $token . '&email=' . urlencode($email);

                // load template
                $tpl = __DIR__ . '/Email verification.html';
                $html = '';
                if (file_exists($tpl)){
                    $html = file_get_contents($tpl);
                    $html = str_replace('{{Name}}', htmlspecialchars($user['name'] ?? '', ENT_QUOTES|ENT_HTML5), $html);
                    $html = str_replace('{{Verification Link}}', $verification_url, $html);
                } else {
                    $html = "<p>Dear " . htmlspecialchars($user['name'] ?? '') . ",<br>Please verify your email by visiting: <a href='".$verification_url."'>Verify Email</a></p>";
                }

                $subject = 'Verify Your Email Address';
                sendHtmlEmail($email, $user['name'] ?? '', $subject, $html);
            }

            $msg = 'success';
        }
    }
}

$site_url = defined('SITE_URL') ? SITE_URL : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify Email — Dausto</title>
<style>body{font-family:Arial,Helvetica,sans-serif;background:linear-gradient(160deg,#F0F4FF,#EEF0FB,#F8FAFC);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px} .card{background:#fff;border-radius:20px;border:1px solid #E8ECF2;box-shadow:0 20px 60px rgba(46,61,154,0.10);width:100%;max-width:420px;overflow:hidden} .card-header{background:linear-gradient(135deg,#0F172A,#1A2540);padding:24px 32px;text-align:center} .logo{font-family:'Playfair Display',serif;font-size:20px;font-weight:900;color:#fff} .card-body{padding:32px} h1{font-size:22px;font-weight:900;color:#0F172A;margin-bottom:6px} .sub{font-size:13.5px;color:#64748B;margin-bottom:24px;line-height:1.6} .form-label{display:block;font-size:11px;font-weight:700;color:#374151;letter-spacing:.06em;margin-bottom:5px;text-transform:uppercase} .form-input{width:100%;border:1.5px solid #E2E8F0;border-radius:9px;padding:11px 14px;font-size:13.5px;color:#0F172A;background:#FAFBFC} .btn{width:100%;padding:13px;background:linear-gradient(135deg,#2E3D9A,#1E2D7A);color:#fff;font-size:15px;font-weight:700;border:none;border-radius:10px;cursor:pointer;margin-top:16px} .alert-error{background:#FEF2F2;border:1px solid #FECACA;border-left:3px solid #DC2626;color:#7F1D1D;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px} .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;border-left:3px solid #16A34A;color:#14532D;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;line-height:1.6} .back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#64748B;text-decoration:none} .back a{color:#2E3D9A;font-weight:700;text-decoration:none}</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="logo">DAUSTO</div>
    <div style="font-size:10px;color:rgba(255,255,255,0.3);font-weight:700;letter-spacing:.12em;margin-top:3px">CORPORATE GIFTS</div>
  </div>
  <div class="card-body">
    <h1>📧 Verify Your Email</h1>
    <p class="sub">Enter your email and we'll send a verification link to confirm your address.</p>

<?php if ($msg === 'success'): ?>
<div class="alert-success">✅ <strong>Check your inbox!</strong><br>If this email is registered, you'll receive a verification link shortly.</div>
<p class="back"><a href="<?=$site_url?>/login.php">← Back to Login</a></p>

<?php else: ?>

<?php if ($error): ?>
<div class="alert-error">❌ <?=$error?></div>
<?php endif ?>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
  <div>
    <label class="form-label">Email Address *</label>
    <input type="email" name="email" class="form-input" required placeholder="your@company.com" value="<?=htmlspecialchars($_POST['email']??'')?>">
  </div>
  <button type="submit" class="btn">Send Verification Link →</button>
</form>
<p class="back"><a href="<?=$site_url?>/login.php">← Back to Login</a></p>

<?php endif ?>

  </div>
</div>
</body>
</html>
