<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

/* =========================
   🔐 AUTHENTICATION CHECK
========================= */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php"); 
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../unauthorized.php"); 
    exit();
}
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Admin";
}

$username = $_SESSION['username'];

// 1. Fetch Profile Data
$profile_query = $conn->prepare("SELECT full_name, email, role FROM admin_users WHERE username = ?");
$profile_query->bind_param("s", $username);
$profile_query->execute();
$profile_data = $profile_query->get_result()->fetch_assoc();

// Determine if the user is a Superadmin (Used to lock certain settings)
$is_superadmin = ($profile_data['role'] === 'superadmin');

// 2. Fetch System Settings
$settings_query = "SELECT setting_key, setting_value FROM system_settings";
$result = mysqli_query($conn, $settings_query);

$settings = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// 3. Fetch Active Regions dynamically for the dropdown
$regions_query = $conn->query("SELECT region_name FROM regions WHERE is_active = 1");
$active_regions = [];
if($regions_query) {
    while($r = $regions_query->fetch_assoc()){
        $active_regions[] = $r['region_name'];
    }
}

// Helper function to safely check toggle switches
function isChecked($key, $settings) {
    return (isset($settings[$key]) && $settings[$key] === '1') ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings | DICT Admin Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{ --primary:#002147; --secondary:#0038A8; --accent:#FFD700; --light-bg:#f4f6f9; --text-dark:#1f2937; --text-light:#6b7280; --success:#10b981; --danger:#ef4444; }
*{ margin:0; padding:0; box-sizing:border-box; font-family:"Segoe UI",Arial,sans-serif; }
body{ background:var(--light-bg); display:flex; min-height:100vh; color:var(--text-dark); }
.logout-btn{ margin-top:25px; background:rgba(239,68,68,.2); color:#fca5a5; }
.main-content{ margin-left:260px; padding:35px; width:calc(100% - 260px); }
 .top-header {
    position: fixed;       
    top: 0;                
    left: 0;
    width: 100%;           
    background-color: #002147; 
    color: white;
    padding: 10px 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
    z-index: 1000;

    display: flex;                
    justify-content: space-between; 
    align-items: center;         
}
top-header h1{ font-size:20px; }

body {
    margin: 0;
    padding-top: 70px; /* Adjust based on header height to prevent overlap */
}
.wave {
    display: inline-block;
    animation: waveAnim 1.5s infinite;
    transform-origin: 70% 70%;
}

@keyframes waveAnim {
    0% { transform: rotate(0deg); }
    15% { transform: rotate(15deg); }
    30% { transform: rotate(-10deg); }
    45% { transform: rotate(15deg); }
    60% { transform: rotate(-5deg); }
    75% { transform: rotate(10deg); }
    100% { transform: rotate(0deg); }
}
.user-info{ font-size:14px; }
.settings-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(420px,1fr)); gap:25px; }
.settings-card{ background:white; padding:25px; border-radius:14px; box-shadow:0 6px 20px rgba(0,0,0,.06); transition:.25s; position: relative;}
.settings-card:hover{ transform:translateY(-4px); box-shadow:0 10px 25px rgba(0,0,0,.08); }
.settings-card h3{ font-size:18px; color:var(--primary); margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center;}
.profile-section{ display:flex; align-items:center; gap:15px; margin-bottom:20px; }
.profile-avatar{ width:70px; height:70px; background:var(--secondary); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:28px; font-weight:bold; }
.profile-info h4{ font-size:17px; }
.profile-info p{ font-size:13px; color:var(--text-light); text-transform: capitalize; }
.form-group{ margin-bottom:18px; }
.form-group label{ font-weight:500; font-size:14px; display:block; margin-bottom:6px; }
.form-group input, .form-group select{ width:100%; padding:11px; border-radius:7px; border:1px solid #e5e7eb; font-size:14px; transition:.2s; }
.form-group input:focus, .form-group select:focus{ outline:none; border-color:var(--secondary); box-shadow:0 0 0 2px rgba(0,56,168,.15); }
.btn{ padding:11px 20px; background:var(--secondary); border:none; border-radius:7px; color:white; font-weight:500; cursor:pointer; transition:.25s; width: 100%; }
.btn:hover{ background:var(--primary); }
.btn:disabled{ background: #9ca3af; cursor: not-allowed; }
.toggle-row{ display:flex; justify-content:space-between; align-items:center; padding:14px 0; border-bottom:1px solid #f0f0f0; }
.toggle-row:last-child{ border-bottom:none; }
.toggle-label{ font-weight:500; }
.toggle-desc{ font-size:13px; color:var(--text-light); }
.switch{ position:relative; display:inline-block; width:50px; height:25px; }
.switch input{ opacity:0; width:0; height:0; }
.slider{ position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#ccc; transition:.3s; border-radius:25px; }
.slider:before{ position:absolute; content:""; height:19px; width:19px; left:3px; bottom:3px; background:white; transition:.3s; border-radius:50%; }
input:checked + .slider{ background:var(--success); }
input:checked + .slider:before{ transform:translateX(24px); }

/* Lock Overlay for non-superadmins */
.locked-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.6); z-index:10; border-radius:14px; cursor:not-allowed; }

@media(max-width:768px){ .sidebar{ width:70px; padding:15px 10px; } .sidebar-logo h2, .sidebar-menu a span:not(.icon){ display:none; } .main-content{ margin-left:70px; width:calc(100% - 70px); } .settings-grid{ grid-template-columns:1fr; } }
</style>
</head>

<body>
<?php include '../component_admin/sidebar.php';?>
<?php include '../component_admin/top_header.php'; ?>
<main class="main-content">
    <h1 style="background: linear-gradient(90deg, #002147, #0038A8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0;"> System Settings</h1>
</div>

<div class="settings-grid" style="margin-top: 20px;">

<div class="settings-card">
    <h3>👤 Profile Settings</h3>
    <div class="profile-section">
        <div class="profile-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        <div class="profile-info">
            <h4><?php echo htmlspecialchars($profile_data['full_name'] ?? 'Admin User'); ?></h4>
            <p><?php echo htmlspecialchars($profile_data['role'] ?? 'System Administrator'); ?></p>
        </div>
    </div>
    
    <div class="form-group">
        <label>Full Name</label>
        <input type="text" id="prof_name" value="<?php echo htmlspecialchars($profile_data['full_name'] ?? ''); ?>">
    </div>
    <div class="form-group">
        <label>Email Address</label>
        <input type="email" id="prof_email" value="<?php echo htmlspecialchars($profile_data['email'] ?? ''); ?>">
    </div>
    <div class="form-group">
        <label>Username</label>
        <input type="text" value="<?php echo htmlspecialchars($username); ?>" disabled style="background:#f3f4f6; color:#9ca3af;">
    </div>
    <div class="form-group" style="border-top: 1px dashed #e5e7eb; padding-top: 15px;">
        <label>New Password (Leave blank to keep current)</label>
        <input type="password" id="prof_pass" placeholder="Enter new password">
    </div>
    <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" id="prof_pass_confirm" placeholder="Type password again">
    </div>
    <button class="btn" onclick="saveProfile()">Save Profile</button>
</div>

<div class="settings-card">
    <?php if(!$is_superadmin) echo '<div class="locked-overlay" title="Only Superadmins can edit this."></div>'; ?>
    <h3>
        🔔 Notification Settings 
        <?php if(!$is_superadmin) echo '<span style="font-size:12px; color:#ef4444;">🔒 Superadmin Only</span>'; ?>
    </h3>
    <div class="toggle-row">
        <div><div class="toggle-label">Email Notifications</div><div class="toggle-desc">Receive alerts for new feedback</div></div>
        <label class="switch"><input type="checkbox" id="notif_email" <?php echo isChecked('email_notifications', $settings); ?>><span class="slider"></span></label>
    </div>
    <div class="toggle-row">
        <div><div class="toggle-label">Low Rating Alerts</div><div class="toggle-desc">Alert when rating is below 3</div></div>
        <label class="switch"><input type="checkbox" id="notif_low" <?php echo isChecked('low_rating_alerts', $settings); ?>><span class="slider"></span></label>
    </div>
    <div class="toggle-row">
        <div><div class="toggle-label">Weekly Reports</div><div class="toggle-desc">Receive weekly reports</div></div>
        <label class="switch"><input type="checkbox" id="notif_weekly" <?php echo isChecked('weekly_reports', $settings); ?>><span class="slider"></span></label>
    </div>
    <div class="toggle-row">
        <div><div class="toggle-label">System Updates</div><div class="toggle-desc">Get notified about system updates</div></div>
        <label class="switch"><input type="checkbox" id="notif_sys" <?php echo isChecked('system_updates', $settings); ?>><span class="slider"></span></label>
    </div>
    <button class="btn" style="margin-top:20px;" onclick="saveNotifications()" <?php echo !$is_superadmin ? 'disabled' : ''; ?>>Save Notifications</button>
</div>

<div class="settings-card">
    <?php if(!$is_superadmin) echo '<div class="locked-overlay" title="Only Superadmins can edit this."></div>'; ?>
    <h3>
        ⚙️ System Configuration
        <?php if(!$is_superadmin) echo '<span style="font-size:12px; color:#ef4444;">🔒 Superadmin Only</span>'; ?>
    </h3>
    <div class="form-group">
        <label>System Name</label>
        <input type="text" id="sys_name" value="<?php echo htmlspecialchars($settings['system_name'] ?? ''); ?>">
    </div>
    <div class="form-group">
        <label>Default Region</label>
        <select id="sys_region">
            <option value="All Regions" <?php echo ($settings['default_region'] ?? '') == 'All Regions' ? 'selected' : ''; ?>>All Regions</option>
            <?php foreach($active_regions as $reg): ?>
                <option value="<?php echo htmlspecialchars($reg); ?>" <?php echo ($settings['default_region'] ?? '') == $reg ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($reg); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Items Per Page (Tables)</label>
        <select id="sys_items">
            <option value="10" <?php echo ($settings['items_per_page'] ?? '') == '10' ? 'selected' : ''; ?>>10</option>
            <option value="25" <?php echo ($settings['items_per_page'] ?? '') == '25' ? 'selected' : ''; ?>>25</option>
            <option value="50" <?php echo ($settings['items_per_page'] ?? '') == '50' ? 'selected' : ''; ?>>50</option>
        </select>
    </div>
    <div class="form-group">
        <label>Date Format</label>
        <select id="sys_date">
            <option value="M/d/Y" <?php echo ($settings['date_format'] ?? '') == 'M/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
            <option value="d/M/Y" <?php echo ($settings['date_format'] ?? '') == 'd/M/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
            <option value="Y-m-d" <?php echo ($settings['date_format'] ?? '') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
        </select>
    </div>
    <button class="btn" onclick="saveSystem()" <?php echo !$is_superadmin ? 'disabled' : ''; ?>>Save System Config</button>
</div>

<div class="settings-card">
    <?php if(!$is_superadmin) echo '<div class="locked-overlay" title="Only Superadmins can edit this."></div>'; ?>
    <h3>
        🔒 Security Settings
        <?php if(!$is_superadmin) echo '<span style="font-size:12px; color:#ef4444;">🔒 Superadmin Only</span>'; ?>
    </h3>
    <div class="toggle-row">
        <div><div class="toggle-label">Two Factor Authentication</div><div class="toggle-desc">Add extra security</div></div>
        <label class="switch"><input type="checkbox" id="sec_2fa" <?php echo isChecked('two_factor_auth', $settings); ?>><span class="slider"></span></label>
    </div>
    <div class="toggle-row">
        <div><div class="toggle-label">Session Timeout</div><div class="toggle-desc">Auto logout on inactivity</div></div>
        <label class="switch"><input type="checkbox" id="sec_timeout_toggle" <?php echo isChecked('session_timeout_enabled', $settings); ?>><span class="slider"></span></label>
    </div>
    <div class="toggle-row">
        <div><div class="toggle-label">Login Alerts</div><div class="toggle-desc">Notify on new login attempts</div></div>
        <label class="switch"><input type="checkbox" id="sec_alerts" <?php echo isChecked('login_alerts', $settings); ?>><span class="slider"></span></label>
    </div>
    <div class="form-group" style="margin-top:15px;">
        <label>Session Timeout Limit (minutes)</label>
        <input type="number" id="sec_timeout_mins" value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '30'); ?>">
    </div>
    <button class="btn" onclick="saveSecurity()" <?php echo !$is_superadmin ? 'disabled' : ''; ?>>Update Security</button>
</div>

</div>
</main>

<script>
// Helper function to send data with a loading state
function sendData(formData) {
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    // Make sure this path is correct based on your folder structure!
    fetch('../../functions/process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === 'success'){
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Your settings have been updated.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => { location.reload(); }); // Reload to update UI elements like name
        } else {
            Swal.fire('Error', data, 'error');
        }
    })
    .catch(err => Swal.fire('Error', 'Network connection failed.', 'error'));
}

function saveProfile(){
    let pass = document.getElementById('prof_pass').value;
    let conf = document.getElementById('prof_pass_confirm').value;

    // Prevent password typos!
    if(pass !== "" && pass !== conf){
        Swal.fire('Password Mismatch', 'The new passwords you entered do not match. Please try again.', 'warning');
        return;
    }

    let fd = new FormData();
    fd.append('type', 'profile');
    fd.append('full_name', document.getElementById('prof_name').value);
    fd.append('email', document.getElementById('prof_email').value);
    fd.append('new_password', pass);
    sendData(fd);
}

function saveNotifications(){
    let fd = new FormData();
    fd.append('type', 'notifications');
    fd.append('email_notifications', document.getElementById('notif_email').checked);
    fd.append('low_rating_alerts', document.getElementById('notif_low').checked);
    fd.append('weekly_reports', document.getElementById('notif_weekly').checked);
    fd.append('system_updates', document.getElementById('notif_sys').checked);
    sendData(fd);
}

function saveSystem(){
    let fd = new FormData();
    fd.append('type', 'system');
    fd.append('system_name', document.getElementById('sys_name').value);
    fd.append('default_region', document.getElementById('sys_region').value);
    fd.append('items_per_page', document.getElementById('sys_items').value);
    fd.append('date_format', document.getElementById('sys_date').value);
    sendData(fd);
}

function saveSecurity(){
    let fd = new FormData();
    fd.append('type', 'security');
    fd.append('two_factor_auth', document.getElementById('sec_2fa').checked);
    fd.append('session_timeout_enabled', document.getElementById('sec_timeout_toggle').checked);
    fd.append('login_alerts', document.getElementById('sec_alerts').checked);
    fd.append('session_timeout', document.getElementById('sec_timeout_mins').value);
    sendData(fd);
}

document.getElementById("menuToggle").onclick = function() {
    document.querySelector(".sidebar").classList.toggle("active");
};
</script>
<script>
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar').classList.remove('active');
        }
    });
});

function updatePST() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        document.getElementById('pst-time').textContent = timeStr;
        document.getElementById('pst-date').textContent = dateStr;
    }
    setInterval(updatePST, 1000);
    updatePST();
</script>

</body>
</html>