<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../functions/db_connection.php";

if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$errorMessage  = "";
$showSweetAlert = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newPassword     = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $email           = $_SESSION['reset_email'];

    if (strlen($newPassword) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = "Passwords do not match. Please try again.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE ADMIN_USERS SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        if ($stmt->execute()) {
            $showSweetAlert = true;
            session_unset();
            session_destroy();
        } else {
            $errorMessage = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --navy:#002147; --blue:#0038A8; --gold:#D4AF37; --border:#e2e8f0; --muted:#64748b; --success:#10b981; --danger:#ef4444; --warn:#f59e0b; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:"Segoe UI",Arial,sans-serif; }

        body {
            background: linear-gradient(135deg, #001233 0%, var(--navy) 45%, #003580 75%, var(--blue) 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px; overflow: hidden; position: relative;
        }

        .bubbles { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
        .bubble  { position: absolute; border-radius: 50%; background: rgba(255,255,255,.06); bottom: -140px; animation: rise 18s linear infinite; }
        .bubble:nth-child(1){width:55px;height:55px;left:8%;  animation-delay:0s; animation-duration:14s}
        .bubble:nth-child(2){width:30px;height:30px;left:35%; animation-delay:3s; animation-duration:20s}
        .bubble:nth-child(3){width:85px;height:85px;left:65%; animation-delay:5s; animation-duration:23s}
        .bubble:nth-child(4){width:45px;height:45px;left:88%; animation-delay:1s; animation-duration:17s}
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

        .card-body { padding: 32px; }

        .step-label { font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.6px;text-align:center;margin-bottom:6px; }
        .step-indicator { display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:26px; }
        .step-dot { width:8px;height:8px;border-radius:50%;background:var(--border); }
        .step-dot.done   { background:var(--success); }
        .step-dot.active { background:var(--blue);width:22px;border-radius:4px; }

        /* ── ERROR ── */
        .alert-err { display:flex;align-items:center;gap:10px;background:#fef2f2;border:1px solid #fecaca;border-left:4px solid var(--danger);color:#b91c1c;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:20px; animation:shake .4s ease; }
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}

        /* ── FIELD ── */
        .field { margin-bottom: 18px; }
        .field label { display:block;font-size:11px;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-wrap .ico { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--blue);font-size:13px;pointer-events:none; }
        .input-wrap .tog { position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--muted);background:none;border:none;cursor:pointer;font-size:13px;transition:color .2s; }
        .input-wrap .tog:hover { color:var(--blue); }
        .input-wrap input { width:100%;padding:12px 40px 12px 40px;border-radius:10px;border:1.5px solid var(--border);background:#f8fafc;font-size:14px;outline:none;transition:all .25s; }
        .input-wrap input:focus { border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(0,56,168,.1); }

        /* ── STRENGTH METER ── */
        .strength-meter { margin-top: 8px; }
        .strength-bars { display:flex;gap:4px;margin-bottom:4px; }
        .sbar { flex:1;height:4px;border-radius:2px;background:var(--border);transition:background .3s; }
        .sbar.weak   { background:var(--danger); }
        .sbar.fair   { background:var(--warn); }
        .sbar.good   { background:#84cc16; }
        .sbar.strong { background:var(--success); }
        .strength-label { font-size:11px;font-weight:700;color:var(--muted);transition:color .3s; }

        /* ── MATCH INDICATOR ── */
        .match-indicator { display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;margin-top:6px;opacity:0;transition:opacity .3s; }
        .match-indicator.show { opacity:1; }
        .match-indicator.ok  { color:var(--success); }
        .match-indicator.bad { color:var(--danger); }

        .btn-update {
            width:100%;padding:13px;border:none;border-radius:12px;
            background:linear-gradient(135deg,var(--blue),var(--navy));
            color:#fff;font-weight:700;font-size:15px;cursor:pointer;
            box-shadow:0 5px 20px rgba(0,56,168,.38);
            transition:all .3s ease;display:flex;align-items:center;justify-content:center;gap:9px;
            margin-top:6px;
        }
        .btn-update:hover { transform:translateY(-2px);box-shadow:0 8px 28px rgba(0,56,168,.48); }
        .btn-update:disabled { opacity:.55;cursor:not-allowed;transform:none; }

        .back-link { display:block;text-align:center;margin-top:18px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;transition:color .2s; }
        .back-link:hover { color:var(--blue); }
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
        <div class="card-icon"><i class="fas fa-lock-open"></i></div>
        <h2>Create New Password</h2>
        <p>Choose a strong password to secure your DCSM admin account.</p>
    </div>

    <div class="card-body">
        <div class="step-label">Step 3 of 3 — Set Password</div>
        <div class="step-indicator">
            <div class="step-dot done"></div>
            <div class="step-dot done"></div>
            <div class="step-dot active"></div>
        </div>

        <?php if ($errorMessage): ?>
        <div class="alert-err"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST" id="resetForm">
            <div class="field">
                <label>New Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock ico"></i>
                    <input type="password" name="new_password" id="newPw" placeholder="At least 8 characters" required autocomplete="new-password">
                    <button type="button" class="tog" onclick="togglePw('newPw','ico1')"><i class="fas fa-eye" id="ico1"></i></button>
                </div>
                <div class="strength-meter">
                    <div class="strength-bars">
                        <div class="sbar" id="sb1"></div>
                        <div class="sbar" id="sb2"></div>
                        <div class="sbar" id="sb3"></div>
                        <div class="sbar" id="sb4"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>
            </div>

            <div class="field">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock ico"></i>
                    <input type="password" name="confirm_password" id="confPw" placeholder="Repeat your new password" required autocomplete="new-password">
                    <button type="button" class="tog" onclick="togglePw('confPw','ico2')"><i class="fas fa-eye" id="ico2"></i></button>
                </div>
                <div class="match-indicator" id="matchIndicator">
                    <i class="fas fa-circle-check"></i> <span id="matchText"></span>
                </div>
            </div>

            <button type="submit" class="btn-update" id="updateBtn">
                <i class="fas fa-shield-check"></i> Update Password
            </button>
        </form>

        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left" style="margin-right:5px"></i>Cancel and return to login
        </a>
    </div>
</div>

<script>
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    ico.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

/* ── STRENGTH METER ── */
const newPw  = document.getElementById('newPw');
const sbars  = [1,2,3,4].map(i => document.getElementById('sb'+i));
const sLabel = document.getElementById('strengthLabel');

function calcStrength(pw) {
    let s = 0;
    if (pw.length >= 8)  s++;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/\d/.test(pw))   s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return Math.min(4, s);
}

const strengthMap = [
    {cls:'',      label:'',          col:''},
    {cls:'weak',  label:'Weak',      col:'#ef4444'},
    {cls:'fair',  label:'Fair',      col:'#f59e0b'},
    {cls:'good',  label:'Good',      col:'#84cc16'},
    {cls:'strong',label:'Strong ✓',  col:'#10b981'},
];

newPw.addEventListener('input', () => {
    const level = newPw.value ? calcStrength(newPw.value) : 0;
    sbars.forEach((b, i) => {
        b.className = 'sbar';
        if (newPw.value && i < level) b.classList.add(strengthMap[level].cls);
    });
    sLabel.textContent = newPw.value ? strengthMap[level].label : '';
    sLabel.style.color = strengthMap[level].col;
    checkMatch();
});

/* ── MATCH INDICATOR ── */
const confPw = document.getElementById('confPw');
const mInd   = document.getElementById('matchIndicator');
const mText  = document.getElementById('matchText');

function checkMatch() {
    if (!confPw.value) { mInd.className = 'match-indicator'; return; }
    const ok = newPw.value === confPw.value;
    mInd.className = 'match-indicator show ' + (ok ? 'ok' : 'bad');
    mInd.querySelector('i').className = ok ? 'fas fa-circle-check' : 'fas fa-circle-xmark';
    mText.textContent = ok ? 'Passwords match' : 'Passwords do not match';
}

confPw.addEventListener('input', checkMatch);

document.getElementById('resetForm').addEventListener('submit', () => {
    const btn = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';
});

<?php if ($showSweetAlert): ?>
Swal.fire({
    icon: 'success', title: 'Password Updated!',
    text: 'Your account credentials have been updated. You may now log in.',
    confirmButtonColor: '#002147', confirmButtonText: 'Go to Login',
    background: '#fff', backdrop: 'rgba(0,33,71,.7)'
}).then(() => { window.location.href = 'login.php'; });
<?php endif; ?>
</script>
</body>
</html>
