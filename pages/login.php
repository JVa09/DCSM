<?php
session_start();
include "../functions/db_connection.php";
require_once "../functions/logger.php";

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $region   = $_POST['region']   ?? '';
    $province = $_POST['province'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, role, region, province, status FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            if (is_null($user['status']) || $user['status'] !== 'active') {
                $login_error = "Your account is pending approval by the Superadmin. Please wait.";
                log_activity($conn, 'Login Blocked', 'Authentication', "User {$user['username']} attempted login but account is PENDING.");
            } elseif ($user['role'] === 'superadmin' || ($user['region'] === $region && $user['province'] === $province)) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['region']   = $user['region'];
                $_SESSION['province'] = $user['province'];
                $loc = ($user['role'] === 'superadmin') ? "National/System Wide" : $province;
                log_activity($conn, 'Login', 'Authentication', "User {$user['username']} logged in successfully ({$loc}).");
                header("Location: ../admin/pages/dashboard.php");
                exit();
            } else {
                $login_error = "Access denied. Region/Province does not match your assigned area.";
                log_activity($conn, 'Login Failed', 'Authentication', "User {$user['username']} failed location check for {$province}.");
            }
        } else {
            $login_error = "Invalid username or password. Please try again.";
            log_activity($conn, 'Login Failed', 'Authentication', "Invalid password attempt for username: {$username}.");
        }
    } else {
        $login_error = "Invalid username or password. Please try again.";
        log_activity($conn, 'Login Failed', 'Authentication', "Attempted login with non-existent username: {$username}.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --navy:   #002147;
            --blue:   #0038A8;
            --gold:   #D4AF37;
            --white:  #ffffff;
            --border: #e2e8f0;
            --muted:  #64748b;
            --danger: #dc2626;
            --bg-in:  #f8fafc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }

        body {
            background: linear-gradient(135deg, #001233 0%, var(--navy) 45%, #003580 75%, var(--blue) 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px; overflow: hidden; position: relative;
        }

        /* ── BUBBLES ── */
        .bubbles { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
        .bubble  { position: absolute; border-radius: 50%; background: rgba(255,255,255,.06); bottom: -160px; animation: riseUp 18s linear infinite; }
        .bubble:nth-child(1)  { width:55px;  height:55px;  left:5%;  animation-delay:0s;  animation-duration:14s; }
        .bubble:nth-child(2)  { width:30px;  height:30px;  left:15%; animation-delay:3s;  animation-duration:18s; }
        .bubble:nth-child(3)  { width:80px;  height:80px;  left:28%; animation-delay:6s;  animation-duration:22s; }
        .bubble:nth-child(4)  { width:40px;  height:40px;  left:42%; animation-delay:1s;  animation-duration:16s; }
        .bubble:nth-child(5)  { width:65px;  height:65px;  left:57%; animation-delay:4s;  animation-duration:20s; }
        .bubble:nth-child(6)  { width:25px;  height:25px;  left:70%; animation-delay:7s;  animation-duration:13s; }
        .bubble:nth-child(7)  { width:90px;  height:90px;  left:82%; animation-delay:2s;  animation-duration:24s; }
        .bubble:nth-child(8)  { width:35px;  height:35px;  left:92%; animation-delay:5s;  animation-duration:17s; }
        @keyframes riseUp { 0%{transform:translateY(0) scale(1);opacity:0} 10%{opacity:1} 90%{opacity:.6} 100%{transform:translateY(-110vh) scale(1.1);opacity:0} }

        /* ── CARD WRAPPER ── */
        .card-wrap {
            display: flex; width: 100%; max-width: 920px;
            background: var(--white); border-radius: 26px;
            box-shadow: 0 32px 80px rgba(0,0,0,.42);
            overflow: hidden; position: relative; z-index: 10;
            animation: cardIn .6s cubic-bezier(.22,1,.36,1);
        }
        @keyframes cardIn { from{opacity:0;transform:translateY(40px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }

        /* ── BRANDING SIDE ── */
        .brand-side {
            flex: 1; min-width: 280px;
            background: linear-gradient(160deg, #001233 0%, var(--navy) 50%, #003580 100%);
            padding: 50px 36px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; color: var(--white);
            position: relative; overflow: hidden;
        }
        .brand-side::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--gold), #fff8, var(--gold));
        }
        .brand-shine {
            position: absolute; top: -40%; left: -40%; width: 180%; height: 180%;
            background: radial-gradient(circle, rgba(255,255,255,.05) 0%, transparent 60%);
            animation: spin 25s linear infinite; pointer-events: none;
        }
        @keyframes spin { from{transform:rotate(0)} to{transform:rotate(360deg)} }
        .brand-logo { width: 110px; height: 110px; border-radius: 50%; background: rgba(255,255,255,.12); border: 3px solid rgba(255,255,255,.2); padding: 10px; margin-bottom: 26px; position: relative; z-index: 1; }
        .brand-logo img { width: 100%; height: 100%; object-fit: contain; border-radius: 50%; }
        .brand-name { font-size: 11px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; opacity: .7; margin-bottom: 12px; position: relative; z-index: 1; }
        .brand-title { font-size: 20px; font-weight: 800; line-height: 1.3; position: relative; z-index: 1; }
        .brand-sub   { font-size: 13px; opacity: .65; margin-top: 10px; line-height: 1.6; position: relative; z-index: 1; }
        .brand-badge {
            margin-top: 28px; display: inline-flex; align-items: center; gap: 7px;
            background: rgba(212,175,55,.18); border: 1px solid rgba(212,175,55,.35);
            color: var(--gold); padding: 6px 16px; border-radius: 50px;
            font-size: 11px; font-weight: 700; letter-spacing: .8px; position: relative; z-index: 1;
        }

        /* ── FORM SIDE ── */
        .form-side {
            flex: 1.3; padding: 48px 44px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .form-head { margin-bottom: 30px; }
        .form-head h2 { color: var(--navy); font-size: 24px; font-weight: 800; margin-bottom: 5px; }
        .form-head p  { color: var(--muted); font-size: 14px; }

        /* ── ERROR ── */
        .alert-error {
            display: flex; align-items: center; gap: 10px;
            background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid var(--danger);
            color: #b91c1c; padding: 12px 14px; border-radius: 10px;
            font-size: 13px; font-weight: 500; margin-bottom: 22px;
            animation: shake .4s ease;
        }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }

        /* ── FIELD ── */
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 11px; font-weight: 700; color: var(--navy); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px; }
        .input-wrap { position: relative; }
        .input-wrap .ico { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--blue); font-size: 13px; pointer-events: none; }
        .input-wrap .toggle-pw { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); background: none; border: none; cursor: pointer; font-size: 14px; padding: 2px; transition: color .2s; }
        .input-wrap .toggle-pw:hover { color: var(--blue); }
        .field input, .field select {
            width: 100%; padding: 11px 14px 11px 38px; border-radius: 10px;
            border: 1.5px solid var(--border); background: var(--bg-in);
            font-size: 14px; color: #1e293b; outline: none; transition: all .25s ease;
            appearance: none;
        }
        .field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; background-color: var(--bg-in);
            padding-right: 36px;
        }
        .field input[type="password"] { padding-right: 42px; }
        .field input:focus, .field select:focus { border-color: var(--blue); background: var(--white); box-shadow: 0 0 0 3px rgba(0,56,168,.1); }

        /* ── REGION/PROVINCE GRID ── */
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }

        /* ── SUBMIT ── */
        .btn-login {
            width: 100%; padding: 13px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, var(--blue), var(--navy));
            color: var(--white); font-weight: 700; font-size: 15px; cursor: pointer;
            box-shadow: 0 5px 20px rgba(0,56,168,.38);
            transition: all .3s ease; letter-spacing: .4px; margin-top: 6px;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,56,168,.48); }
        .btn-login:active { transform: translateY(0); }

        /* ── FOOTER LINKS ── */
        .form-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 18px; }
        .form-footer a { font-size: 13px; color: var(--muted); text-decoration: none; font-weight: 600; transition: color .2s; }
        .form-footer a:hover { color: var(--blue); }

        /* ── RESPONSIVE ── */
        @media(max-width:760px) {
            .card-wrap { flex-direction: column; max-width: 420px; }
            .brand-side { padding: 34px 28px; min-width: unset; }
            .brand-title { font-size: 17px; }
            .form-side { padding: 32px 26px; }
            .field-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="bubbles">
    <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
</div>

<div class="card-wrap">

    <!-- BRANDING -->
    <div class="brand-side">
        <div class="brand-shine"></div>
        <div class="brand-logo">
            <img src="../assets/img/livelogo.gif" alt="DICT Logo">
        </div>
        <div class="brand-name">Republic of the Philippines</div>
        <div class="brand-title">Department of Information and Communications Technology</div>
        <div class="brand-sub">Digital Client Satisfaction Measurement System</div>
        <div class="brand-badge"><i class="fas fa-shield-check"></i> Secured Admin Portal</div>
    </div>

    <!-- FORM -->
    <div class="form-side">
        <div class="form-head">
            <h2>Administrator Login</h2>
            <p>Secure access to the monitoring dashboard.</p>
        </div>

        <?php if (!empty($login_error)): ?>
        <div class="alert-error">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($login_error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label>Username</label>
                <div class="input-wrap">
                    <i class="fas fa-user-tie ico"></i>
                    <input type="text" name="username" placeholder="Enter admin username" required autocomplete="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="fas fa-key ico"></i>
                    <input type="password" name="password" id="passwordInput" placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="toggle-pw" onclick="togglePassword()" id="pwToggle" title="Show/hide password">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>

            <div class="field-row">
                <div class="field" style="margin-bottom:0">
                    <label>Assigned Region</label>
                    <div class="input-wrap">
                        <i class="fas fa-map-location-dot ico"></i>
                        <select id="region" name="region" onchange="regionChanged()">
                            <option value="">Select Region</option>
                            <option value="National Capital Region (NCR)">NCR</option>
                            <option value="Cordillera Administrative Region (CAR)">CAR</option>
                            <option value="Region I - Ilocos Region">Region I</option>
                            <option value="Region II - Cagayan Valley">Region II</option>
                            <option value="Region III - Central Luzon">Region III</option>
                            <option value="Region IV-A - CALABARZON">Region IV-A</option>
                            <option value="Region IV-B - MIMAROPA">Region IV-B</option>
                            <option value="Region V - Bicol Region">Region V</option>
                            <option value="Region VI - Western Visayas">Region VI</option>
                            <option value="Region VII - Central Visayas">Region VII</option>
                            <option value="Region VIII - Eastern Visayas">Region VIII</option>
                            <option value="Region IX - Zamboanga Peninsula">Region IX</option>
                            <option value="Region X - Northern Mindanao">Region X</option>
                            <option value="Region XI - Davao Region">Region XI</option>
                            <option value="Region XII - SOCCSKSARGEN">Region XII</option>
                            <option value="Region XIII - Caraga">Region XIII</option>
                            <option value="BARMM">BARMM</option>
                        </select>
                    </div>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label>Assigned Province</label>
                    <div class="input-wrap">
                        <i class="fas fa-landmark-flag ico"></i>
                        <select id="province" name="province">
                            <option value="">Select Province</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-right-to-bracket" style="margin-right:8px"></i> Login to Dashboard
            </button>

            <div class="form-footer">
                <a href="../index.php"><i class="fas fa-house" style="margin-right:5px"></i>Portal Home</a>
                <a href="forgot_password.php"><i class="fas fa-rotate-left" style="margin-right:5px"></i>Forgot Password?</a>
            </div>
        </form>
    </div>

</div>

<script>
const regionProvinces = {
    "National Capital Region (NCR)":["Manila","Quezon City","Makati","Pasig","Taguig","Parañaque","Pasay","Mandaluyong","San Juan","Marikina","Caloocan","Malabon","Navotas","Valenzuela","Las Piñas","Muntinlupa"],
    "Cordillera Administrative Region (CAR)":["Abra","Apayao","Benguet","Ifugao","Kalinga","Mountain Province"],
    "Region I - Ilocos Region":["Ilocos Norte","Ilocos Sur","La Union","Pangasinan"],
    "Region II - Cagayan Valley":["Cagayan","Isabela","Nueva Vizcaya","Quirino"],
    "Region III - Central Luzon":["Aurora","Bataan","Bulacan","Nueva Ecija","Pampanga","Tarlac","Zambales"],
    "Region IV-A - CALABARZON":["Batangas","Cavite","Laguna","Quezon","Rizal"],
    "Region IV-B - MIMAROPA":["Marinduque","Occidental Mindoro","Oriental Mindoro","Palawan","Romblon"],
    "Region V - Bicol Region":["Albay","Camarines Norte","Camarines Sur","Catanduanes","Masbate","Sorsogon"],
    "Region VI - Western Visayas":["Aklan","Antique","Capiz","Guimaras","Iloilo","Negros Occidental"],
    "Region VII - Central Visayas":["Bohol","Cebu","Negros Oriental","Siquijor"],
    "Region VIII - Eastern Visayas":["Biliran","Eastern Samar","Leyte","Northern Samar","Samar","Southern Leyte"],
    "Region IX - Zamboanga Peninsula":["Zamboanga del Norte","Zamboanga del Sur","Zamboanga Sibugay"],
    "Region X - Northern Mindanao":["Bukidnon","Camiguin","Lanao del Norte","Misamis Occidental","Misamis Oriental"],
    "Region XI - Davao Region":["Davao de Oro","Davao del Norte","Davao del Sur","Davao Occidental","Davao Oriental"],
    "Region XII - SOCCSKSARGEN":["Cotabato","Sarangani","South Cotabato","Sultan Kudarat"],
    "Region XIII - Caraga":["Agusan del Norte","Agusan del Sur","Dinagat Islands","Surigao del Norte","Surigao del Sur"],
    "BARMM":["Basilan","Lanao del Sur","Maguindanao","Sulu","Tawi-Tawi"]
};

function regionChanged() {
    const sel   = document.getElementById('region').value;
    const prov  = document.getElementById('province');
    prov.innerHTML = '<option value="">Select Province</option>';
    (regionProvinces[sel] || []).forEach(p => {
        const o = document.createElement('option'); o.value = p; o.text = p; prov.appendChild(o);
    });
}

function togglePassword() {
    const inp  = document.getElementById('passwordInput');
    const icon = document.getElementById('pwIcon');
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}
</script>
</body>
</html>
