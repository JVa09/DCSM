<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../functions/db_connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../mail/Exception.php';
require '../mail/PHPMailer.php';
require '../mail/SMTP.php';

function sanitize($data) { return htmlspecialchars(trim($data)); }

$emailSent    = false;
$emailNotFound = false;
$error_msg    = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitize($_POST['email']);

    $stmt = $conn->prepare("SELECT id, username, full_name FROM ADMIN_USERS WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $otp  = rand(100000, 999999);

        $_SESSION['reset_otp']   = $otp;
        $_SESSION['reset_email'] = $email;
        $_SESSION['otp_expiry']  = time() + 300;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'r2dictdcsm@gmail.com';
            $mail->Password   = 'svmq prqf vojn yvbz';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('no-reply@dcsm-dict.gov.ph', 'DICT DCSM System');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'DCSM Password Reset OTP';
            $mail->Body    = "
            <div style='font-family:\"Segoe UI\",sans-serif;max-width:480px;margin:0 auto;padding:30px;border:1px solid #e2e8f0;border-radius:16px;background:#fff'>
                <div style='text-align:center;margin-bottom:24px'>
                    <div style='display:inline-block;background:linear-gradient(135deg,#002147,#0038A8);width:60px;height:60px;border-radius:50%;line-height:60px;font-size:26px'>🔐</div>
                </div>
                <h2 style='color:#002147;text-align:center;margin-bottom:8px'>Password Reset Request</h2>
                <p style='color:#64748b;text-align:center;margin-bottom:24px'>Hello <strong style='color:#002147'>{$user['full_name']}</strong>, your OTP is:</p>
                <div style='background:#f1f5f9;border-radius:12px;padding:22px;text-align:center;margin-bottom:24px'>
                    <span style='font-size:38px;font-weight:900;letter-spacing:10px;color:#0038A8'>{$otp}</span>
                </div>
                <p style='color:#64748b;font-size:13px;text-align:center'>Valid for <strong>5 minutes</strong>. If you did not request this, ignore this email.</p>
                <hr style='border:0;border-top:1px solid #e2e8f0;margin:20px 0'>
                <p style='font-size:11px;color:#94a3b8;text-align:center'>DICT Digital Client Satisfaction Measurement System</p>
            </div>";
            $mail->send();
            $emailSent = true;
            header("refresh:2;url=verify_otp.php");
        } catch (Exception $e) {
            $error_msg = "Could not send email. Please try again later.";
        }
    } else {
        $emailNotFound = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --navy:#002147; --blue:#0038A8; --gold:#D4AF37; --border:#e2e8f0; --muted:#64748b; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:"Segoe UI",Arial,sans-serif; }

        body {
            background: linear-gradient(135deg, #001233 0%, var(--navy) 45%, #003580 75%, var(--blue) 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px; overflow: hidden; position: relative;
        }

        .bubbles { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
        .bubble  { position: absolute; border-radius: 50%; background: rgba(255,255,255,.06); bottom: -140px; animation: rise 18s linear infinite; }
        .bubble:nth-child(1){width:50px;height:50px;left:10%;animation-delay:0s;animation-duration:15s}
        .bubble:nth-child(2){width:75px;height:75px;left:35%;animation-delay:4s;animation-duration:20s}
        .bubble:nth-child(3){width:35px;height:35px;left:60%;animation-delay:2s;animation-duration:13s}
        .bubble:nth-child(4){width:90px;height:90px;left:80%;animation-delay:6s;animation-duration:22s}
        @keyframes rise{0%{transform:translateY(0);opacity:0}10%{opacity:.8}90%{opacity:.5}100%{transform:translateY(-110vh);opacity:0}}

        .auth-card {
            background: #fff; width: 100%; max-width: 420px;
            border-radius: 24px; box-shadow: 0 30px 80px rgba(0,0,0,.4);
            overflow: hidden; position: relative; z-index: 10;
            animation: cardIn .55s cubic-bezier(.22,1,.36,1);
        }
        @keyframes cardIn{from{opacity:0;transform:translateY(36px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}

        .card-top {
            background: linear-gradient(160deg, #001233, var(--navy) 60%, #003580);
            padding: 36px 32px; text-align: center; position: relative; overflow: hidden;
        }
        .card-top::after { content:''; position:absolute; bottom:0;left:0;right:0;height:3px; background:linear-gradient(90deg,var(--gold),rgba(255,255,255,.4),var(--gold)); }
        .card-top-shine { position:absolute;top:-50%;left:-50%;width:200%;height:200%; background:radial-gradient(circle,rgba(255,255,255,.05) 0%,transparent 60%); animation:spin 20s linear infinite; }
        @keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
        .card-icon { width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,.12);border:2px solid rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:var(--gold);position:relative;z-index:1; }
        .card-top h2 { color:#fff;font-size:20px;font-weight:800;margin-bottom:6px;position:relative;z-index:1; }
        .card-top p  { color:rgba(255,255,255,.72);font-size:13px;line-height:1.6;position:relative;z-index:1; }

        .card-body { padding: 32px; }

        .field { margin-bottom: 20px; }
        .field label { display:block;font-size:11px;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-wrap .ico { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--blue);font-size:14px;pointer-events:none; }
        .input-wrap input { width:100%;padding:12px 14px 12px 40px;border-radius:10px;border:1.5px solid var(--border);background:#f8fafc;font-size:14px;outline:none;transition:all .25s; }
        .input-wrap input:focus { border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(0,56,168,.1); }

        .btn-send {
            width:100%;padding:13px;border:none;border-radius:12px;
            background:linear-gradient(135deg,var(--blue),var(--navy));
            color:#fff;font-weight:700;font-size:15px;cursor:pointer;
            box-shadow:0 5px 20px rgba(0,56,168,.38);
            transition:all .3s ease; display:flex;align-items:center;justify-content:center;gap:9px;
        }
        .btn-send:hover { transform:translateY(-2px);box-shadow:0 8px 28px rgba(0,56,168,.48); }
        .btn-send:disabled { opacity:.7;cursor:not-allowed;transform:none; }

        .back-link { display:block;text-align:center;margin-top:20px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;transition:color .2s; }
        .back-link:hover { color:var(--blue); }

        .step-indicator { display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:22px; }
        .step-dot { width:8px;height:8px;border-radius:50%;background:var(--border); }
        .step-dot.active { background:var(--blue);width:22px;border-radius:4px; }
        .step-label { font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.6px;text-align:center;margin-bottom:6px; }
    </style>
</head>
<body>

<div class="bubbles">
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
</div>

<div class="auth-card">
    <div class="card-top">
        <div class="card-top-shine"></div>
        <div class="card-icon"><i class="fas fa-envelope-open-text"></i></div>
        <h2>Forgot Password?</h2>
        <p>Enter your registered email address and we'll send you a one-time verification code.</p>
    </div>

    <div class="card-body">
        <div class="step-label">Step 1 of 3 — Enter Email</div>
        <div class="step-indicator">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <form method="POST" id="fpForm">
            <div class="field">
                <label>Email Address</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope ico"></i>
                    <input type="email" name="email" placeholder="e.g. admin@dict.gov.ph" required autocomplete="email">
                </div>
            </div>
            <button type="submit" class="btn-send" id="sendBtn">
                <i class="fas fa-paper-plane"></i> Send Verification Code
            </button>
        </form>

        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left" style="margin-right:5px"></i>Back to Login
        </a>
    </div>
</div>

<script>
document.getElementById('fpForm').addEventListener('submit', function() {
    const btn  = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
});

<?php if ($emailSent): ?>
Swal.fire({
    icon: 'success', title: 'OTP Sent!',
    text: 'A verification code has been sent to your email. Redirecting…',
    showConfirmButton: false, timer: 2000,
    background: '#fff', backdrop: 'rgba(0,33,71,.7)'
});
<?php endif; ?>

<?php if ($emailNotFound): ?>
Swal.fire({
    icon: 'error', title: 'Account Not Found',
    text: 'That email is not registered or is pending approval.',
    confirmButtonColor: '#002147',
    background: '#fff', backdrop: 'rgba(0,33,71,.7)'
});
<?php endif; ?>

<?php if ($error_msg): ?>
Swal.fire({
    icon: 'error', title: 'Mail Error',
    text: '<?= addslashes($error_msg) ?>',
    confirmButtonColor: '#002147'
});
<?php endif; ?>
</script>
</body>
</html>
