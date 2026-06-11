<?php
// FILE: public_html/forgot-password.php
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
require_once PUBLIC_PATH . '/includes/mailer.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, name, status FROM users WHERE email=? AND portal='client' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always show success to prevent email enumeration
            if ($user && $user['status'] === 'active') {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                $db->prepare("UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?")
                   ->execute([$token, $expires, $user['id']]);

                $reset_url = SITE_URL . '/reset-password.php?token=' . $token . '&email=' . urlencode($email);
                mailPasswordReset($email, $user['name'], $reset_url);
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password — Dausto</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700;900&family=Playfair+Display:wght@900&display=swap" rel="stylesheet">
<style>
body{font-family:'DM Sans',sans-serif;background:linear-gradient(160deg,#F0F4FF,#EEF0FB,#F8FAFC);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:20px;border:1px solid #E8ECF2;box-shadow:0 20px 60px rgba(46,61,154,0.10);width:100%;max-width:420px;overflow:hidden}
.card-header{background:linear-gradient(135deg,#0F172A,#1A2540);padding:24px 32px;text-align:center}
.logo{font-family:'Playfair Display',serif;font-size:20px;font-weight:900;color:#fff}
.card-body{padding:32px}
h1{font-size:22px;font-weight:900;color:#0F172A;margin-bottom:6px}
.sub{font-size:13.5px;color:#64748B;margin-bottom:24px;line-height:1.6}
.form-label{display:block;font-size:11px;font-weight:700;color:#374151;letter-spacing:.06em;margin-bottom:5px;text-transform:uppercase}
.form-input{width:100%;border:1.5px solid #E2E8F0;border-radius:9px;padding:11px 14px;font-size:13.5px;color:#0F172A;font-family:'DM Sans',sans-serif;outline:none;background:#FAFBFC;transition:border-color .15s,box-shadow .15s}
.form-input:focus{border-color:#2E3D9A;box-shadow:0 0 0 3px rgba(46,61,154,0.12);background:#fff}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#2E3D9A,#1E2D7A);color:#fff;font-size:15px;font-weight:700;font-family:'DM Sans',sans-serif;border:none;border-radius:10px;cursor:pointer;margin-top:16px}
.alert-error{background:#FEF2F2;border:1px solid #FECACA;border-left:3px solid #DC2626;color:#7F1D1D;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert-success{background:#F0FDF4;border:1px solid #BBF7D0;border-left:3px solid #16A34A;color:#14532D;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;line-height:1.6}
.back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#64748B;text-decoration:none}
.back a{color:#2E3D9A;font-weight:700;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="logo">DAUSTO</div>
    <div style="font-size:10px;color:rgba(255,255,255,0.3);font-weight:700;letter-spacing:.12em;margin-top:3px">CORPORATE GIFTS</div>
  </div>
  <div class="card-body">
    <h1>🔐 Forgot Password?</h1>
    <p class="sub">Enter your registered email and we'll send you a link to reset your password.</p>

```
<?php if ($msg === 'success'): ?>
<div class="alert-success">
  ✅ <strong>Check your inbox!</strong><br>
  If this email is registered, you'll receive a password reset link shortly. The link is valid for 1 hour.
</div>
<p class="back"><a href="<?=$site_url?>/login.php">← Back to Login</a></p>

<?php else: ?>

<?php if ($error): ?>
<div class="alert-error">❌ <?=$error?></div>
<?php endif ?>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
  <div>
    <label class="form-label">Email Address *</label>
    <input type="email" name="email" class="form-input" required
           placeholder="your@company.com"
           value="<?=htmlspecialchars($_POST['email']??'')?>">
  </div>
  <button type="submit" class="btn">Send Reset Link →</button>
</form>
<p class="back"><a href="<?=$site_url?>/login.php">← Back to Login</a></p>

<?php endif ?>
```

  </div>
</div>
</body>
</html>
