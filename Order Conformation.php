<?php
// Order confirmation handler
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
        $order_id = trim($_POST['order_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($order_id === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = 'Please provide order id, name and a valid email.';
        } else {
            // Best-effort: mark order as placed in DB
            try {
                $db = getDB();
                $stmt = $db->prepare('SELECT id FROM orders WHERE order_id=? LIMIT 1');
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                if ($order) {
                    $u = $db->prepare("UPDATE orders SET status='placed', updated_at=NOW() WHERE id=?");
                    $u->execute([$order['id']]);
                } else {
                    // insert minimal order record
                    $i = $db->prepare('INSERT INTO orders (order_id,customer_name,customer_email,status,created_at) VALUES (?,?,?,?,?)');
                    $i->execute([$order_id,$name,$email,'placed',date('Y-m-d H:i:s')]);
                }
            } catch (Exception $e) {
                // ignore DB errors
            }

            // Load template
            $tplPath = __DIR__ . '/Order Conformation.html';
            if (file_exists($tplPath)){
                $html = file_get_contents($tplPath);
                $html = str_replace('{{Order ID}}', htmlspecialchars($order_id, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Name}}', htmlspecialchars($name, ENT_QUOTES|ENT_HTML5), $html);
            } else {
                $html = "<p>Dear " . htmlspecialchars($name) . ",</p><p>Your order has been successfully placed and is being processed.</p><p>Order ID: " . htmlspecialchars($order_id) . "</p><p>Regards,<br>Team Dausto</p>";
            }

            $subject = 'Order Confirmation - ' . $order_id;
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
  <title>Order Confirmation — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:640px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} input{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#2E3D9A;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Send Order Confirmation</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">Order confirmation sent to <?=htmlspecialchars($email)?>.</div>
    <p><a href="<?=$site_url?>/">Back to site</a></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="order_id">Order ID</label>
    <input id="order_id" name="order_id" required value="<?=htmlspecialchars($_POST['order_id']??'')?>">
    <label for="name">Customer name</label>
    <input id="name" name="name" required value="<?=htmlspecialchars($_POST['name']??'')?>">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required value="<?=htmlspecialchars($_POST['email']??'')?>">
    <button type="submit">Send Confirmation</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
