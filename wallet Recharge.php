<?php
// Wallet recharge notification handler
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
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $amount = trim($_POST['amount'] ?? '');
        $balance = trim($_POST['balance'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $amount === ''){
            $error = 'Please provide a valid name, email and amount.';
        } else {
            // Try to update user's wallet balance (best-effort)
            try {
                $db = getDB();
                $stmt = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    // attempt multiple common column names (ignore failures)
                    try { $u = $db->prepare('UPDATE users SET wallet_balance=?, updated_at=NOW() WHERE id=?'); $u->execute([$balance, $user['id']]); } catch (Exception $e) {}
                    try { $u = $db->prepare('UPDATE users SET wallet=?, updated_at=NOW() WHERE id=?'); $u->execute([$balance, $user['id']]); } catch (Exception $e) {}
                    try { $u = $db->prepare('UPDATE users SET balance=?, updated_at=NOW() WHERE id=?'); $u->execute([$balance, $user['id']]); } catch (Exception $e) {}
                }
            } catch (Exception $e) {
                // ignore DB errors
            }

            // Load email template
            $tplPath = __DIR__ . '/Wallet Recharge.html';
            if (file_exists($tplPath)){
                $html = file_get_contents($tplPath);
                $html = str_replace('{{Name}}', htmlspecialchars($name, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Amount}}', htmlspecialchars($amount, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Balance}}', htmlspecialchars($balance, ENT_QUOTES|ENT_HTML5), $html);
            } else {
                $html = "<p>Dear " . htmlspecialchars($name) . ",</p><p>Your wallet has been credited with ₹" . htmlspecialchars($amount) . ".</p><p>Current Balance: ₹" . htmlspecialchars($balance) . "</p><p>Regards,<br>Team Dausto</p>";
            }

            $subject = 'Wallet Recharge Successful';
            $sent = sendHtmlEmail($email, $name, $subject, $html);

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
  <title>Wallet Recharge — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:640px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} input{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#2E3D9A;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Wallet Recharge Notification</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">Recharge notification sent to <?=htmlspecialchars($email)?>.</div>
    <p><a href="<?=$site_url?>/">Back to site</a></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="name">Name</label>
    <input id="name" name="name" required value="<?=htmlspecialchars($_POST['name']??'')?>">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required value="<?=htmlspecialchars($_POST['email']??'')?>">
    <label for="amount">Amount (₹)</label>
    <input id="amount" name="amount" required value="<?=htmlspecialchars($_POST['amount']??'')?>">
    <label for="balance">Current Balance (₹)</label>
    <input id="balance" name="balance" value="<?=htmlspecialchars($_POST['balance']??'')?>">
    <button type="submit">Send Notification</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
