<?php
// FILE: User Registraion.php
// Simple user registration script that creates a user and sends a welcome email

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

// helper: send an HTML email
function sendHtmlEmail($toEmail, $toName, $subject, $htmlBody, $fromName = 'Team Dausto', $fromEmail = 'corporate@dausto.com'){
    $boundary = md5(time());
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    // use mail() for simple setups; swap to your mailer library if available
    return mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')){
        $error = 'Invalid form submission.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6){
            $error = 'Please provide a valid name, email and a password (min 6 characters).';
        } else {
            $db = getDB();
            // Check for existing user
            $stmt = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()){
                $error = 'An account with that email already exists.';
            } else {
                $pwHash = password_hash($password, PASSWORD_DEFAULT);
                $now = date('Y-m-d H:i:s');
                $status = 'active';
                $insert = $db->prepare('INSERT INTO users (name,email,password_hash,status,created_at) VALUES (?,?,?,?,?)');
                $ok = $insert->execute([$name,$email,$pwHash,$status,$now]);
                if ($ok){
                    // load the HTML template and replace placeholders
                    $tplPath = __DIR__ . '/User Registraion.html';
                    $html = '';
                    if (file_exists($tplPath)){
                        $html = file_get_contents($tplPath);
                        $html = str_replace('{{Name}}', htmlspecialchars($name, ENT_QUOTES|ENT_HTML5), $html);
                        $html = str_replace('{{Registered Email}}', htmlspecialchars($email, ENT_QUOTES|ENT_HTML5), $html);
                    } else {
                        $html = "<p>Hi " . htmlspecialchars($name) . ",<br>Your account has been created. Visit <a href='" . (defined('SITE_URL')?SITE_URL:'#') . "'>Dausto</a> to sign in.</p>";
                    }

                    // subject
                    $subject = 'Welcome to Dausto – Registration Successful';

                    // send welcome email (use your preferred mailer if available)
                    $sent = sendHtmlEmail($email, $name, $subject, $html);

                    $success = true;
                } else {
                    $error = 'Failed to create account. Please try again later.';
                }
            }
        }
    }
}

// ensure csrf token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$site_url = defined('SITE_URL') ? SITE_URL : '';
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:520px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} input{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#2E3D9A;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Create your account</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="ok">Your account was created. A welcome email was sent to <?=htmlspecialchars($email)?>.</div>
    <p><a href="<?=$site_url?>/login.php">Go to login</a></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="name">Full name</label>
    <input id="name" name="name" required>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required>
    <label for="password">Password (min 6 chars)</label>
    <input id="password" name="password" type="password" required>
    <button type="submit">Create account</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
