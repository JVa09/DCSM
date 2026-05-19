<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['reset_otp'])) {
    header("Location: forgot_password.php");
    exit();
}

$otpError = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $enteredOtp = trim($_POST['otp']);
    if ($enteredOtp == $_SESSION['reset_otp'] && time() < $_SESSION['otp_expiry']) {
        $_SESSION['otp_verified'] = true;
        header("Location: reset_password.php");
        exit();
    } else {
        $otpError = true;
    }
}

$remaining = max(0, $_SESSION['otp_expiry'] - time());
$maskedEmail = '';
if (!empty($_SESSION['reset_email'])) {
    $parts = explode('@', $_SESSION['reset_email']);
    $name  = $parts[0];
    $domain = $parts[1] ?? '';
    $masked = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
    $maskedEmail = $masked . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --navy:#002147; --blue:#0038A8; --gold:#D4AF37; --border:#e2e8f0; --muted:#64748b; --success:#10b981; --danger:#ef4444; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:"Segoe UI",Arial,sans-serif; }

        body {
            background: linear-gradient(135deg, #001233 0%, var(--navy) 45%, #003580 75%, var(--blue) 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px; overflow: hidden; position: relative;
        }

        .bubbles { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
        .bubble  { position: absolute; border-radius: 50%; background: rgba(255,255,255,.06); bottom: -140px; animation: rise 18s linear infinite; }
        .bubble:nth-child(1){width:55px;height:55px;left:8%;  animation-delay:0s; animation-duration:14s}
        .bubble:nth-child(2){width:30px;height:30px;left:30%; animation-delay:3s; animation-duration:19s}
        .bubble:nth-child(3){width:80px;height:80px;left:62%; animation-delay:6s; animation-duration:23s}
        .bubble:nth-child(4){width:40px;height:40px;left:85%; animation-delay:1s; animation-duration:16s}
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
        .card-top::after { content:''; position:absolute;bottom:0;left:0;right:0;height:3px; background:linear-gradient(90deg,var(--gold),rgba(255,255,255,.4),var(--gold)); }
        .card-shine { position:absolute;top:-50%;left:-50%;width:200%;height:200%; background:radial-gradient(circle,rgba(255,255,255,.05) 0%,transparent 60%);animation:spin 20s linear infinite; }
        @keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
        .card-icon { width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,.12);border:2px solid rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:var(--gold);position:relative;z-index:1; }
        .card-top h2 { color:#fff;font-size:20px;font-weight:800;margin-bottom:6px;position:relative;z-index:1; }
        .card-top p  { color:rgba(255,255,255,.72);font-size:13px;line-height:1.6;position:relative;z-index:1; }
        .card-top .email-chip {
            display:inline-block;background:rgba(212,175,55,.2);border:1px solid rgba(212,175,55,.35);
            color:var(--gold);padding:4px 14px;border-radius:50px;font-size:12px;font-weight:700;
            margin-top:8px;position:relative;z-index:1;
        }

        .card-body { padding: 32px; }

        .step-label { font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.6px;text-align:center;margin-bottom:6px; }
        .step-indicator { display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:26px; }
        .step-dot { width:8px;height:8px;border-radius:50%;background:var(--border); }
        .step-dot.done   { background:var(--success); }
        .step-dot.active { background:var(--blue);width:22px;border-radius:4px; }

        /* ── 6-BOX OTP ── */
        .otp-boxes { display:flex;gap:10px;justify-content:center;margin-bottom:22px; }
        .otp-box {
            width:50px;height:58px;border-radius:12px;
            border:2px solid var(--border);background:#f8fafc;
            font-size:26px;font-weight:800;text-align:center;color:var(--navy);
            outline:none;transition:all .22s ease;caret-color:transparent;
        }
        .otp-box:focus { border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(0,56,168,.12);transform:translateY(-2px); }
        .otp-box.filled { border-color:var(--blue);background:#eff6ff; }
        .otp-box.error  { border-color:var(--danger);background:#fff5f5;animation:shake .4s ease; }
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}

        /* ── COUNTDOWN ── */
        .countdown-wrap { text-align:center;margin-bottom:22px; }
        .countdown-ring { display:inline-flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid var(--border);border-radius:50px;padding:7px 18px;font-size:13px;color:var(--muted);font-weight:600; }
        .countdown-ring .timer { color:var(--navy);font-size:15px;font-weight:800;font-variant-numeric:tabular-nums; }
        .countdown-ring.urgent .timer { color:var(--danger); }
        .countdown-ring.expired .timer { color:var(--danger); }

        .btn-verify {
            width:100%;padding:13px;border:none;border-radius:12px;
            background:linear-gradient(135deg,var(--blue),var(--navy));
            color:#fff;font-weight:700;font-size:15px;cursor:pointer;
            box-shadow:0 5px 20px rgba(0,56,168,.38);
            transition:all .3s ease;display:flex;align-items:center;justify-content:center;gap:9px;
        }
        .btn-verify:hover { transform:translateY(-2px);box-shadow:0 8px 28px rgba(0,56,168,.48); }
        .btn-verify:disabled { opacity:.6;cursor:not-allowed;transform:none; }

        .resend-row { text-align:center;margin-top:18px;font-size:13px;color:var(--muted); }
        .resend-row a { color:var(--blue);font-weight:700;text-decoration:none; }
        .resend-row a:hover { text-decoration:underline; }
    </style>
</head>
<body>

<div class="bubbles">
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
</div>

<div class="auth-card">
    <div class="card-top">
        <div class="card-shine"></div>
        <div class="card-icon"><i class="fas fa-shield-check"></i></div>
        <h2>Verify Your Identity</h2>
        <p>We sent a 6-digit code to your email</p>
        <span class="email-chip"><?= htmlspecialchars($maskedEmail) ?></span>
    </div>

    <div class="card-body">
        <div class="step-label">Step 2 of 3 — Enter OTP</div>
        <div class="step-indicator">
            <div class="step-dot done"></div>
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
        </div>

        <div class="countdown-wrap">
            <div class="countdown-ring" id="countdownRing">
                <i class="fas fa-clock"></i>
                Code expires in <span class="timer" id="timerDisplay">5:00</span>
            </div>
        </div>

        <form method="POST" id="otpForm">
            <input type="hidden" name="otp" id="otpHidden">
            <div class="otp-boxes" id="otpBoxes">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
            </div>
            <button type="submit" class="btn-verify" id="verifyBtn" disabled>
                <i class="fas fa-check-circle"></i> Verify Code
            </button>
        </form>

        <div class="resend-row">
            Didn't receive it? <a href="forgot_password.php">Resend OTP</a>
        </div>
    </div>
</div>

<script>
/* ── OTP BOX LOGIC ── */
const boxes     = document.querySelectorAll('.otp-box');
const hidden    = document.getElementById('otpHidden');
const verifyBtn = document.getElementById('verifyBtn');
const form      = document.getElementById('otpForm');

boxes.forEach((box, i) => {
    box.addEventListener('input', () => {
        box.value = box.value.replace(/\D/g, '').slice(-1);
        box.classList.toggle('filled', box.value !== '');
        if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
        syncHidden();
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) { boxes[i - 1].focus(); boxes[i - 1].value = ''; boxes[i-1].classList.remove('filled'); syncHidden(); }
        if (e.key === 'ArrowLeft'  && i > 0) boxes[i - 1].focus();
        if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        pasted.split('').forEach((ch, j) => { if (boxes[j]) { boxes[j].value = ch; boxes[j].classList.add('filled'); } });
        boxes[Math.min(pasted.length, boxes.length - 1)].focus();
        syncHidden();
    });
});

function syncHidden() {
    const val = [...boxes].map(b => b.value).join('');
    hidden.value = val;
    verifyBtn.disabled = val.length < 6;
}

// Auto-submit when all filled
form.addEventListener('submit', () => {
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';
});

boxes[0].focus();

/* ── COUNTDOWN TIMER ── */
let secs = <?= $remaining ?>;
const ring    = document.getElementById('countdownRing');
const display = document.getElementById('timerDisplay');

function updateTimer() {
    if (secs <= 0) {
        display.textContent = 'Expired';
        ring.classList.add('expired');
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-clock"></i> Code Expired';
        return;
    }
    const m = Math.floor(secs / 60), s = secs % 60;
    display.textContent = `${m}:${String(s).padStart(2,'0')}`;
    ring.classList.toggle('urgent', secs <= 60);
    secs--;
    setTimeout(updateTimer, 1000);
}
updateTimer();

<?php if ($otpError): ?>
boxes.forEach(b => b.classList.add('error'));
setTimeout(() => boxes.forEach(b => { b.classList.remove('error'); b.value=''; b.classList.remove('filled'); }), 800);
boxes[0].focus();
hidden.value = ''; verifyBtn.disabled = true;

Swal.fire({
    icon: 'error', title: 'Incorrect Code',
    text: 'The code you entered is wrong or has expired. Please try again.',
    confirmButtonColor: '#002147',
    background: '#fff', backdrop: 'rgba(0,33,71,.7)'
});
<?php endif; ?>
</script>
</body>
</html>
