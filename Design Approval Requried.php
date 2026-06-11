<?php
// Notify client that a design has been uploaded and requires approval
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
        $client_email = strtolower(trim($_POST['client_email'] ?? ''));
        $client_name = trim($_POST['client_name'] ?? '');
        $design_link = trim($_POST['design_link'] ?? '');

        if ($order_id === '' || $client_name === '' || !filter_var($client_email, FILTER_VALIDATE_EMAIL)){
            $error = 'Please provide order id, client name and a valid client email.';
        } else {
            // Best-effort: mark order design status in DB
            try {
                $db = getDB();
                $stmt = $db->prepare('SELECT id FROM orders WHERE order_id=? LIMIT 1');
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                if ($order) {
                    $u = $db->prepare("UPDATE orders SET design_status='pending_approval', design_link=?, design_uploaded_at=NOW(), updated_at=NOW() WHERE id=?");
                    $u->execute([$design_link, $order['id']]);
                } else {
                    $i = $db->prepare('INSERT INTO orders (order_id, customer_name, customer_email, design_status, design_link, created_at, design_uploaded_at) VALUES (?,?,?,?,?,?,NOW())');
                    $i->execute([$order_id, $client_name, $client_email, 'pending_approval', $design_link, date('Y-m-d H:i:s')]);
                }
            } catch (Exception $e) {
                // ignore DB errors
            }

            // Load template
            $tplPath = __DIR__ . '/Design Approval Required.html';
            if (file_exists($tplPath)){
                $html = file_get_contents($tplPath);
                $html = str_replace('{{Client Name}}', htmlspecialchars($client_name, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Order ID}}', htmlspecialchars($order_id, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Design Link}}', htmlspecialchars($design_link, ENT_QUOTES|ENT_HTML5), $html);
            } else {
                $html = "<p>Dear " . htmlspecialchars($client_name) . ",</p><p>A design has been uploaded for your review and approval.</p><p>Order ID: " . htmlspecialchars($order_id) . "</p>";
                if ($design_link) $html .= "<p>Design: <a href='" . htmlspecialchars($design_link) . "'>View design</a></p>";
                $html .= "<p>Regards,<br>Team Dausto</p>";
            }

            $subject = 'Design Approval Required';
            $sent = sendHtmlEmail($client_email, $client_name, $subject, $html);

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
  <title>Design Approval Required — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:640px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} input{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#2E3D9A;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Notify Client: Design Approval Required</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">Notification sent to <?=htmlspecialchars($client_email)?>.</div>
    <p><a href="<?=$site_url?>/">Back to site</a></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="order_id">Order ID</label>
    <input id="order_id" name="order_id" required value="<?=htmlspecialchars($_POST['order_id']??'')?>">
    <label for="client_name">Client name</label>
    <input id="client_name" name="client_name" required value="<?=htmlspecialchars($_POST['client_name']??'')?>">
    <label for="client_email">Client email</label>
    <input id="client_email" name="client_email" type="email" required value="<?=htmlspecialchars($_POST['client_email']??'')?>">
    <label for="design_link">Design link (optional)</label>
    <input id="design_link" name="design_link" value="<?=htmlspecialchars($_POST['design_link']??'')?>">
    <button type="submit">Send Notification</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
