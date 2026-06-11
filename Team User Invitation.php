<?php
// Team user invitation handler
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
        $emp_name = trim($_POST['employee_name'] ?? '');
        $emp_email = strtolower(trim($_POST['employee_email'] ?? ''));
        $role = trim($_POST['role'] ?? '');

        if ($emp_name === '' || !filter_var($emp_email, FILTER_VALIDATE_EMAIL)){
            $error = 'Please provide a valid name and email.';
        } else {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 60*60*24); // 24 hours
            $activation_link = (defined('SITE_URL')?SITE_URL:'') . '/activate.php?token=' . $token . '&email=' . urlencode($emp_email);

            // Attempt to persist token on users table (best-effort)
            try {
                $db = getDB();
                $stmt = $db->prepare('SELECT id,status FROM users WHERE email=? LIMIT 1');
                $stmt->execute([$emp_email]);
                $user = $stmt->fetch();
                if ($user) {
                    // update existing user with invite token and status 'invited'
                    $update = $db->prepare('UPDATE users SET invite_token=?, invite_token_expires=?, status=? WHERE id=?');
                    $update->execute([$token, $expires, 'invited', $user['id']]);
                } else {
                    // insert a placeholder user row (no password) with status 'invited'
                    $insert = $db->prepare('INSERT INTO users (name,email,status,created_at,invite_token,invite_token_expires) VALUES (?,?,?,?,?,?)');
                    $insert->execute([$emp_name, $emp_email, 'invited', date('Y-m-d H:i:s'), $token, $expires]);
                }
            } catch (Exception $e) {
                // If DB schema doesn't have invite fields or other error, proceed anyway
            }

            // Load invitation template
            $tplPath = __DIR__ . '/Team User Invitation.html';
            if (file_exists($tplPath)){
                $html = file_get_contents($tplPath);
                $html = str_replace('{{Employee Name}}', htmlspecialchars($emp_name, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Activation Link}}', htmlspecialchars($activation_link, ENT_QUOTES|ENT_HTML5), $html);
            } else {
                $html = "<p>Dear " . htmlspecialchars($emp_name) . ",</p><p>You have been added as a team member. Click the link below to activate your account.</p><p><a href='" . htmlspecialchars($activation_link) . "'>Activate account</a></p><p>Regards,<br>Team Dausto</p>";
            }

            $subject = 'You Have Been Invited to Dausto';
            $sent = sendHtmlEmail($emp_email, $emp_name, $subject, $html);

            // Optional admin notification
            if (defined('ADMIN_EMAIL')){
                $adminHtml = "<p>Team invitation sent to " . htmlspecialchars($emp_name) . " (" . htmlspecialchars($emp_email) . ")</p>";
                sendHtmlEmail(ADMIN_EMAIL, 'Admin', 'Team Invitation Sent', $adminHtml);
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
  <title>Invite Team Member — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:640px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} input,select{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#2E3D9A;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Invite Team Member</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">Invitation sent to <?=htmlspecialchars($emp_email)?>.</div>
    <p><a href="<?=$site_url?>/">Back to site</a></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="employee_name">Full name</label>
    <input id="employee_name" name="employee_name" required value="<?=htmlspecialchars($_POST['employee_name']??'')?>">
    <label for="employee_email">Email address</label>
    <input id="employee_email" name="employee_email" type="email" required value="<?=htmlspecialchars($_POST['employee_email']??'')?>">
    <label for="role">Role (optional)</label>
    <input id="role" name="role" value="<?=htmlspecialchars($_POST['role']??'')?>">
    <button type="submit">Send Invitation</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
