<?php
/* === SESSION TIMEOUT === */
$timeout_q = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('session_timeout_enabled', 'session_timeout')");
$t_settings = [];
if ($timeout_q) {
    while ($r = $timeout_q->fetch_assoc()) {
        $t_settings[$r['setting_key']] = $r['setting_value'];
    }
}

$timeout_enabled = ($t_settings['session_timeout_enabled'] ?? '0') === '1';
$timeout_mins    = (int)($t_settings['session_timeout'] ?? 30);
$timeout_secs    = $timeout_mins * 60;

if ($timeout_enabled && isset($_SESSION['username'])) {
    if (isset($_SESSION['last_action'])) {
        if ((time() - $_SESSION['last_action']) >= $timeout_secs) {
            session_unset(); session_destroy();
            header("Location: ../../index.php?session=expired");
            exit();
        }
    }
    $_SESSION['last_action'] = time();
}

$current_page    = basename($_SERVER['PHP_SELF']);
$app_root        = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$admin_base      = $app_root . "/admin/pages/";
$settings_pages  = ['settings.php','profile.php','notifications.php','security.php','system.php'];
$is_settings_active = in_array($current_page, $settings_pages);

$_sb_raw_username = $_SESSION['username'] ?? 'Admin';
$username = htmlspecialchars($_sb_raw_username);
$role     = htmlspecialchars(ucfirst($_SESSION['role'] ?? 'admin'));

$_sb_avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($_sb_raw_username) . "&background=FCD116&color=002147&bold=true&size=80";
if (isset($conn)) {
    $_sb_q = $conn->prepare("SELECT profile_picture FROM admin_users WHERE username = ?");
    if ($_sb_q) {
        $_sb_q->bind_param("s", $_sb_raw_username);
        $_sb_q->execute();
        $_sb_r = $_sb_q->get_result()->fetch_assoc();
        $_sb_q->close();
        if (!empty($_sb_r['profile_picture'])) {
            $_sb_avatar_url = "../../uploads/avatars/" . htmlspecialchars($_sb_r['profile_picture']) . "?t=" . time();
        }
    }
}
?>

<style>
    :root {
        --sb-bg-start: #001529;
        --sb-bg-end:   #002650;
        --sb-border:   rgba(255,255,255,.07);
        --sb-text:     rgba(255,255,255,.62);
        --sb-text-hover: #ffffff;
        --sb-active-bg:  rgba(252,209,22,.1);
        --sb-active-border: #FCD116;
        --sb-active-text:   #FCD116;
        --sb-hover-bg:   rgba(255,255,255,.06);
        --sb-section:    rgba(255,255,255,.3);
        --gold: #FCD116;
        --sidebar-w: 260px;
        --header-h:  80px;
    }

    .sidebar {
        width: var(--sidebar-w);
        background: linear-gradient(180deg, var(--sb-bg-start) 0%, var(--sb-bg-end) 100%);
        color: var(--sb-text);
        position: fixed;
        height: 100vh;
        left: 0; top: 0;
        box-shadow: 4px 0 24px rgba(0,0,0,.25);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        transition: transform .3s cubic-bezier(.4,0,.2,1);
        overflow: hidden;
    }

    /* Subtle top pattern line */
    .sidebar::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #FCD116, #0038A8, #FCD116);
        background-size: 200% 100%;
        animation: shimmer 4s linear infinite;
    }
    @keyframes shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

/* ── BRAND / LOGO AREA ── */
    .sb-brand {
        /* Reduced top padding because we want the logo to sit higher */
        padding: 15px 20px 18px; 
        border-bottom: 1px solid var(--sb-border);
        display: flex;
        align-items: center;
        justify-content: center; /* Center the logo */
        gap: 12px;
        flex-shrink: 0;
        position: relative;
        /* This makes the logo area sit on top of the fixed top-header */
        z-index: 1010; 
        background: var(--sb-bg-start);
    }

    .sb-brand img {
        width: 180px; /* Increased size slightly for impact */
        height: auto;
        /* REMOVED: filter: brightness(0) invert(1); */
        filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3)); /* Adds depth instead of whiteness */
        opacity: 1;
        transition: transform 0.3s ease;
    }

    .sb-brand img:hover {
        transform: scale(1.05);
    }

    /* Adjust the scroll area padding to account for the moved logo */
    .sb-profile {
        margin-top: 20px;
    }

    /* ── USER PROFILE CHIP ── */
    .sb-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        margin: 12px 14px;
        background: rgba(255,255,255,.05);
        border: 1px solid var(--sb-border);
        border-radius: 12px;
        flex-shrink: 0;
    }
    .sb-profile img {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 2px solid rgba(252,209,22,.5);
        flex-shrink: 0;
    }
    .sb-profile-info { min-width: 0; }
    .sb-profile-name {
        font-size: .82rem;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sb-profile-role {
        display: inline-block;
        margin-top: 3px;
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .7px;
        color: var(--gold);
        background: rgba(252,209,22,.12);
        padding: 2px 8px;
        border-radius: 20px;
        border: 1px solid rgba(252,209,22,.2);
    }

    /* ── SCROLL AREA ── */
    .sb-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 8px 0 16px;
        scrollbar-width: none;
    }
    .sb-scroll::-webkit-scrollbar { display: none; }

    /* ── SECTION LABELS ── */
    .sb-section-label {
        font-size: .58rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: var(--sb-section);
        padding: 14px 20px 6px;
        user-select: none;
    }

    /* ── NAV LIST ── */
    .sidebar-menu {
        list-style: none;
        padding: 0 10px;
        margin: 0;
    }
    .sidebar-menu li { margin-bottom: 2px; }

    /* ── MENU LINKS ── */
    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 14px;
        color: var(--sb-text);
        text-decoration: none;
        border-radius: 10px;
        font-size: .875rem;
        font-weight: 500;
        transition: all .2s ease;
        position: relative;
        border-left: 3px solid transparent;
    }

    .sidebar-menu a .nav-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        background: rgba(255,255,255,.06);
        flex-shrink: 0;
        transition: all .2s ease;
    }

    .sidebar-menu a .nav-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* HOVER */
    .sidebar-menu a:hover {
        color: var(--sb-text-hover);
        background: var(--sb-hover-bg);
        border-left-color: rgba(252,209,22,.35);
    }
    .sidebar-menu a:hover .nav-icon {
        background: rgba(255,255,255,.12);
    }

    /* ACTIVE */
    .sidebar-menu a.active {
        color: var(--sb-active-text) !important;
        background: var(--sb-active-bg) !important;
        border-left-color: var(--sb-active-border) !important;
        font-weight: 700;
    }
    .sidebar-menu a.active .nav-icon {
        background: rgba(252,209,22,.2) !important;
        color: var(--gold) !important;
    }

    /* ── SUBMENU ── */
    .submenu {
        list-style: none;
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height .35s cubic-bezier(.4,0,.2,1), padding .35s ease;
        margin: 0 0 0 14px;
    }
    .has-submenu.open .submenu {
        max-height: 300px;
        padding: 4px 0 6px;
    }
    .submenu li a {
        padding: 8px 14px 8px 46px;
        font-size: .825rem;
        color: rgba(255,255,255,.5);
        border-radius: 8px;
        border-left: 3px solid transparent;
    }
    .submenu li a:hover  { color: rgba(255,255,255,.85); background: rgba(255,255,255,.04); }
    .submenu li a.active { color: var(--gold) !important; background: rgba(252,209,22,.08) !important; border-left-color: rgba(252,209,22,.5) !important; font-weight:600; }

    /* Chevron arrow */
    .menu-arrow {
        font-size: .65rem !important;
        margin-left: auto;
        opacity: .5;
        transition: transform .3s ease, opacity .3s;
        flex-shrink: 0;
    }
    .has-submenu.open .menu-arrow { transform: rotate(90deg); opacity: 1; color: var(--gold); }
    .has-submenu.open > a { color: rgba(255,255,255,.85); }

    /* ── DIVIDER ── */
    .sb-divider {
        height: 1px;
        background: var(--sb-border);
        margin: 10px 14px;
    }

    /* ── LOGOUT ── */
    .sb-logout {
        padding: 12px 10px 20px;
        flex-shrink: 0;
        border-top: 1px solid var(--sb-border);
    }
    .logout-link {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 14px;
        border-radius: 10px;
        background: rgba(239,68,68,.1);
        border: 1px solid rgba(239,68,68,.2);
        color: #fca5a5 !important;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s ease;
        text-decoration: none;
    }
    .logout-link .nav-icon {
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: .9rem; background: rgba(239,68,68,.15); flex-shrink: 0;
        transition: background .2s;
    }
    .logout-link:hover {
        background: rgba(239,68,68,.2);
        border-color: rgba(239,68,68,.4);
        color: #fff !important;
        transform: translateX(3px);
    }
    .logout-link:hover .nav-icon { background: rgba(239,68,68,.3); }

    /* ── MOBILE ── */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        .sidebar.active {
            transform: translateX(0);
        }
    }
    
</style>

<aside class="sidebar" id="mainSidebar">

    <!-- Brand -->
    <div class="sb-brand">
        <img src="../../assets/img/2.png" alt="DICT Logo">
    </div>

    <!-- User Profile -->
    <div class="sb-profile">
        <img src="<?= $_sb_avatar_url ?>" alt="Avatar">
        <div class="sb-profile-info">
            <div class="sb-profile-name"><?= $username ?></div>
            <span class="sb-profile-role"><?= $role ?></span>
        </div>
    </div>

    <!-- Scrollable Nav -->
    <div class="sb-scroll">

        <p class="sb-section-label">Main Menu</p>
        <ul class="sidebar-menu" id="sidebarMenu">

            <li>
                <a href="<?= $admin_base ?>dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fas fa-th-large"></i></span>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>

            <li>
                <a href="<?= $admin_base ?>feedbacks.php" class="<?= $current_page === 'feedbacks.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fas fa-comment-dots"></i></span>
                    <span class="nav-label">Feedbacks</span>
                </a>
            </li>

            <li>
                <a href="<?= $admin_base ?>clients.php" class="<?= $current_page === 'clients.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fas fa-users"></i></span>
                    <span class="nav-label">Clients</span>
                </a>
            </li>

            <li>
                <a href="<?= $admin_base ?>reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                    <span class="nav-label">Reports</span>
                </a>
            </li>

            <li>
                <a href="<?= $admin_base ?>vouchers.php" class="<?= $current_page === 'vouchers.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fas fa-ticket"></i></span>
                    <span class="nav-label">Vouchers</span>
                </a>
            </li>

        </ul>

        <div class="sb-divider"></div>

        <p class="sb-section-label">Management</p>
        <ul class="sidebar-menu">

            <li>
                <a href="<?= $admin_base ?>activity_logs.php" class="<?= $current_page === 'activity_logs.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fas fa-history"></i></span>
                    <span class="nav-label">Activity Logs</span>
                </a>
            </li>

            <li>
                <a href="<?= $admin_base ?>manage_admins.php" class="<?= $current_page === 'manage_admins.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span class="nav-label">Manage Admins</span>
                </a>
            </li>

            <li id="settingsSubmenu" class="has-submenu <?= $is_settings_active ? 'open' : '' ?>">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span>
                    <span class="nav-label">Settings</span>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="<?= $admin_base ?>profile.php"       class="<?= $current_page==='profile.php'       ? 'active':'' ?>"><i class="fas fa-user-circle" style="margin-right:8px;font-size:.8rem;"></i>Profile</a></li>
                    <li><a href="<?= $admin_base ?>notifications.php"  class="<?= $current_page==='notifications.php'  ? 'active':'' ?>"><i class="fas fa-bell"         style="margin-right:8px;font-size:.8rem;"></i>Notifications</a></li>
                    <li><a href="<?= $admin_base ?>system.php"         class="<?= $current_page==='system.php'         ? 'active':'' ?>"><i class="fas fa-desktop"      style="margin-right:8px;font-size:.8rem;"></i>System</a></li>
                    <li><a href="<?= $admin_base ?>security.php"       class="<?= $current_page==='security.php'       ? 'active':'' ?>"><i class="fas fa-lock"         style="margin-right:8px;font-size:.8rem;"></i>Security</a></li>
                </ul>
            </li>

        </ul>

    </div><!-- /sb-scroll -->

    <!-- Logout -->
    <div class="sb-logout">
        <a onclick="confirmLogout()" class="logout-link">
            <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
            <span>Logout</span>
        </a>
    </div>

</aside>

<script>
/* ── SUBMENU ACCORDION ── */
const settingsSubmenu = document.getElementById('settingsSubmenu');
const sidebarMenu     = document.getElementById('sidebarMenu');

window.addEventListener('load', () => {
    const scrollPos = sessionStorage.getItem('sidebarScrollPos');
    if (scrollPos) document.querySelector('.sb-scroll').scrollTop = +scrollPos;

    const saved = localStorage.getItem('settingsSubmenuOpen');
    if (saved === 'true') {
        settingsSubmenu.classList.add('open');
    } else if (saved === 'false') {
        <?php if (!$is_settings_active): ?>
            settingsSubmenu.classList.remove('open');
        <?php endif; ?>
    }
});

document.querySelector('.sb-scroll').addEventListener('click', e => {
    if (e.target.closest('a')) {
        sessionStorage.setItem('sidebarScrollPos', document.querySelector('.sb-scroll').scrollTop);
    }
});

function toggleSubmenu(event, el) {
    event.preventDefault();
    const parent = el.parentElement;
    parent.classList.toggle('open');
    localStorage.setItem('settingsSubmenuOpen', parent.classList.contains('open'));
}

/* ── SECURITY ── */
function preventBack() { window.history.forward(); }
setTimeout(preventBack, 0);
window.onunload = function() { null; };

function confirmLogout() {
    Swal.fire({
        title: 'Confirm Logout',
        text: 'You will be securely logged out of the system.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Logout Now',
        cancelButtonText: 'Stay'
    }).then(result => {
        if (result.isConfirmed) {
            localStorage.removeItem('settingsSubmenuOpen');
            sessionStorage.removeItem('sidebarScrollPos');
            window.location.href = '../../functions/logout.php';
        }
    });
}

/* ── INACTIVITY TIMEOUT ── */
<?php if ($timeout_enabled && isset($_SESSION['username'])): ?>
const _timeout = <?= $timeout_secs ?> * 1000;
let _timer;
const _logout = () => window.location.href = '../../functions/logout.php?reason=inactivity';
const _reset  = () => { clearTimeout(_timer); _timer = setTimeout(_logout, _timeout); };
['onload','onmousemove','onmousedown','ontouchstart','onclick','onkeypress','onscroll']
    .forEach(e => { window[e] = _reset; });
_reset();
<?php endif; ?>
</script>
