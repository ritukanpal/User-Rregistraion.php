<?php
// Notify vendor that the client requested design revisions
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
        $vendor_email = strtolower(trim($_POST['vendor_email'] ?? ''));
        $vendor_name = trim($_POST['vendor_name'] ?? '');
        $notes = trim($_POST['revision_notes'] ?? '');

        if ($order_id === '' || $vendor_name === '' || !filter_var($vendor_email, FILTER_VALIDATE_EMAIL)){
            $error = 'Please provide order id, vendor name and a valid vendor email.';
        } else {
            // Best-effort: update orders record to mark revision requested
            try {
                $db = getDB();
                $stmt = $db->prepare('SELECT id FROM orders WHERE order_id=? LIMIT 1');
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                if ($order) {
                    // attempt to set design_status and notes
                    try {
                        $u = $db->prepare("UPDATE orders SET design_status='revision_required', revision_requested_at=NOW(), revision_notes=? , updated_at=NOW() WHERE id=?");
                        $u->execute([$notes, $order['id']]);
                    } catch (Exception $e) {
                        // fallback to simpler update without notes
                        try { $u = $db->prepare("UPDATE orders SET design_status='revision_required', revision_requested_at=NOW(), updated_at=NOW() WHERE id=?"); $u->execute([$order['id']]); } catch (Exception $e) {}
                    }
                } else {
                    // insert minimal record
                    try {
                        $i = $db->prepare('INSERT INTO orders (order_id, vendor_name, vendor_email, design_status, revision_notes, created_at, revision_requested_at) VALUES (?,?,?,?,?, ?, NOW())');
                        $i->execute([$order_id, $vendor_name, $vendor_email, 'revision_required', $notes, date('Y-m-d H:i:s')]);
                    } catch (Exception $e) {
                        // ignore
                    }
                }
            } catch (Exception $e) {
                // ignore DB errors
            }

            // Prepare email
            $tplPath = __DIR__ . '/Design Revision Required.html';
            if (file_exists($tplPath)){
                $html = file_get_contents($tplPath);
                $html = str_replace('{{Vendor Name}}', htmlspecialchars($vendor_name, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Order ID}}', htmlspecialchars($order_id, ENT_QUOTES|ENT_HTML5), $html);
                $html = str_replace('{{Revision Notes}}', nl2br(htmlspecialchars($notes, ENT_QUOTES|ENT_HTML5)), $html);
            } else {
                $html = "<p>Dear " . htmlspecialchars($vendor_name) . ",</p><p>The client has requested changes to the submitted design.</p><p>Order ID: " . htmlspecialchars($order_id) . "</p>";
                if ($notes) $html .= "<p><strong>Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>";
                $html .= "<p>Regards,<br>Team Dausto</p>";
            }

            $subject = 'Design Revision Required';
            $sent = sendHtmlEmail($vendor_email, $vendor_name, $subject, $html);

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
  <title>Notify Vendor: Design Revision Required — Dausto</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f6fb;padding:24px} .box{max-width:640px;margin:32px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)} label{display:block;margin-bottom:8px;font-weight:700} textarea,input{width:100%;padding:10px;margin-bottom:14px;border:1px solid #dbe6ff;border-radius:8px} button{background:#2E3D9A;color:#fff;padding:12px 18px;border-radius:9px;border:none;cursor:pointer} .err{color:#9b1c1c;background:#fff1f1;padding:10px;border-radius:8px;margin-bottom:12px} .ok{color:#0b6b32;background:#f0fdf4;padding:10px;border-radius:8px;margin-bottom:12px}</style>
</head>
<body>
<div class="box">
  <h2>Notify Vendor: Design Revision Required</h2>
  <?php if ($error): ?>
    <div class="err"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">Revision request sent to <?=htmlspecialchars($vendor_email)?>.</div>
    <p><a href="<?=$site_url?>/">Back to site</a></p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <label for="order_id">Order ID</label>
    <input id="order_id" name="order_id" required value="<?=htmlspecialchars($_POST['order_id']??'')?>">
    <label for="vendor_name">Vendor name</label>
    <input id="vendor_name" name="vendor_name" required value="<?=htmlspecialchars($_POST['vendor_name']??'')?>">
    <label for="vendor_email">Vendor email</label>
    <input id="vendor_email" name="vendor_email" type="email" required value="<?=htmlspecialchars($_POST['vendor_email']??'')?>">
    <label for="revision_notes">Revision notes (optional)</label>
    <textarea id="revision_notes" name="revision_notes" rows="6"><?=htmlspecialchars($_POST['revision_notes']??'')?></textarea>
    <button type="submit">Send Revision Request</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
