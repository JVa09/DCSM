<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php"); exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../unauthorized.php"); exit();
}
if (!isset($_SESSION['username'])) $_SESSION['username'] = "Admin";

$username = $_SESSION['username'];

$pq = $conn->prepare("SELECT role FROM admin_users WHERE username = ?");
$pq->bind_param("s", $username);
$pq->execute();
$pd = $pq->get_result()->fetch_assoc();
$is_superadmin = ($pd['role'] === 'superadmin');

/* ── STATS ── */
$total = $active = $used = $revoked = 0;
$sr = $conn->query("SELECT status, COUNT(*) c FROM vouchers GROUP BY status");
if ($sr) while ($r = $sr->fetch_assoc()) {
    $total += (int)$r['c'];
    if ($r['status'] === 'active')  $active  = (int)$r['c'];
    if ($r['status'] === 'used')    $used    = (int)$r['c'];
    if ($r['status'] === 'revoked') $revoked = (int)$r['c'];
}

/* ── LIST ── */
$fs = (isset($_GET['status']) && in_array($_GET['status'], ['active','used','revoked'])) ? $_GET['status'] : 'all';
$where_sql = $fs !== 'all' ? "WHERE status = '" . $conn->real_escape_string($fs) . "'" : '';
$vr = $conn->query("SELECT * FROM vouchers $where_sql ORDER BY created_at DESC LIMIT 300");
$vouchers = [];
if ($vr) while ($v = $vr->fetch_assoc()) $vouchers[] = $v;

$use_rate = $total > 0 ? round(($used / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vouchers | DICT Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --navy:     #001529;
    --navy-mid: #002147;
    --blue:     #0038A8;
    --gold:     #FCD116;
    --bg:       #f0f4f8;
    --surface:  #ffffff;
    --border:   #e2e8f0;
    --text:     #1e293b;
    --muted:    #64748b;
    --success:  #10b981;
    --warning:  #f59e0b;
    --danger:   #ef4444;
    --violet:   #8b5cf6;
    --r-sm: 8px; --r-md: 12px; --r-lg: 16px; --r-xl: 20px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.12);
    --g-blue:   linear-gradient(135deg, #0038A8 0%, #001529 100%);
    --g-green:  linear-gradient(135deg, #10b981 0%, #059669 100%);
    --g-amber:  linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --g-violet: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    --g-red:    linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    --sidebar-w: 260px;
    --header-h:  80px;
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html, body { scrollbar-width:none; -ms-overflow-style:none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display:none; }
body { font-family:'Inter','Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

.main-content { margin-left:var(--sidebar-w); padding:calc(var(--header-h) + 32px) 32px 48px; width:calc(100% - var(--sidebar-w)); min-height:100vh; scrollbar-width:none; }
.main-content::-webkit-scrollbar { display:none; }

/* ── PAGE HEADER ── */
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; gap:16px; flex-wrap:wrap; }
.page-title-group { display:flex; align-items:center; gap:12px; }
.page-title-icon { width:44px; height:44px; border-radius:var(--r-md); background:var(--g-blue); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.1rem; box-shadow:0 4px 12px rgba(0,56,168,.3); flex-shrink:0; }
.page-title-text { background:linear-gradient(135deg,var(--navy-mid) 0%,var(--blue) 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; font-size:1.65rem; font-weight:800; line-height:1.2; }
.page-subtitle { font-size:.8rem; color:var(--muted); font-weight:500; margin-top:3px; }

/* ── STAT CARDS ── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:20px; margin-bottom:28px; }
.stat-card { background:var(--surface); border-radius:var(--r-xl); padding:22px 24px; display:flex; align-items:center; gap:16px; box-shadow:var(--shadow-md); border:1px solid var(--border); position:relative; overflow:hidden; transition:transform .25s,box-shadow .25s; }
.stat-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:var(--r-xl) var(--r-xl) 0 0; }
.s-blue::before   { background:var(--g-blue); }
.s-green::before  { background:var(--g-green); }
.s-amber::before  { background:var(--g-amber); }
.s-red::before    { background:var(--g-red); }
.s-violet::before { background:var(--g-violet); }
.stat-icon-box { width:46px; height:46px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex-shrink:0; }
.s-blue   .stat-icon-box { background:rgba(0,56,168,.1);    color:var(--blue); }
.s-green  .stat-icon-box { background:rgba(16,185,129,.1);  color:var(--success); }
.s-amber  .stat-icon-box { background:rgba(245,158,11,.1);  color:var(--warning); }
.s-red    .stat-icon-box { background:rgba(239,68,68,.1);   color:var(--danger); }
.s-violet .stat-icon-box { background:rgba(139,92,246,.1);  color:var(--violet); }
.stat-body { flex:1; min-width:0; }
.stat-value { font-size:1.6rem; font-weight:800; color:var(--text); line-height:1.1; letter-spacing:-.4px; }
.stat-label { font-size:.7rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; margin-top:4px; }

/* ── TOOLBAR ── */
.toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; gap:12px; flex-wrap:wrap; }
.filter-tabs { display:flex; gap:6px; background:var(--surface); border:1px solid var(--border); border-radius:var(--r-md); padding:4px; }
.ftab { padding:6px 14px; border-radius:var(--r-sm); font-size:.78rem; font-weight:600; color:var(--muted); cursor:pointer; text-decoration:none; transition:all .18s; white-space:nowrap; }
.ftab:hover { background:#f1f5f9; color:var(--text); }
.ftab.active { background:var(--g-blue); color:#fff; box-shadow:0 2px 8px rgba(0,56,168,.3); }
.btn-generate { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:var(--r-md); font-size:.88rem; font-weight:700; font-family:inherit; border:none; cursor:pointer; transition:transform .18s,opacity .18s; background:var(--g-blue); color:#fff; box-shadow:0 4px 14px rgba(0,56,168,.3); }
.btn-generate:hover { transform:translateY(-2px); opacity:.92; }

/* ── TABLE CARD ── */
.table-card { background:var(--surface); border-radius:var(--r-xl); box-shadow:var(--shadow-md); border:1px solid var(--border); overflow:hidden; }
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:.85rem; }
thead tr { background:linear-gradient(135deg,var(--navy) 0%,#002147 100%); }
thead th { padding:14px 18px; text-align:left; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:rgba(255,255,255,.8); white-space:nowrap; }
tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:#f8fafc; }
tbody td { padding:13px 18px; vertical-align:middle; }

.code-badge { font-family:'Courier New',monospace; font-size:.88rem; font-weight:700; background:var(--bg); border:1px solid var(--border); padding:4px 10px; border-radius:6px; letter-spacing:1px; color:var(--navy-mid); cursor:pointer; transition:background .15s; user-select:all; }
.code-badge:hover { background:#dbeafe; border-color:#93c5fd; }

.status-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:99px; font-size:.72rem; font-weight:700; }
.sp-active  { background:rgba(16,185,129,.1);  color:var(--success); }
.sp-used    { background:rgba(0,56,168,.1);     color:var(--blue); }
.sp-revoked { background:rgba(239,68,68,.1);    color:var(--danger); }
.sp-expired { background:rgba(245,158,11,.1);   color:var(--warning); }
.dot { width:6px; height:6px; border-radius:50%; background:currentColor; }

.action-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:var(--r-sm); font-size:.75rem; font-weight:600; font-family:inherit; border:none; cursor:pointer; transition:all .15s; }
.btn-revoke { background:rgba(245,158,11,.1); color:var(--warning); border:1px solid rgba(245,158,11,.25); }
.btn-revoke:hover { background:rgba(245,158,11,.2); }
.btn-del { background:rgba(239,68,68,.08); color:var(--danger); border:1px solid rgba(239,68,68,.2); }
.btn-del:hover { background:rgba(239,68,68,.18); }
.btn-copy { background:rgba(0,56,168,.08); color:var(--blue); border:1px solid rgba(0,56,168,.15); }
.btn-copy:hover { background:rgba(0,56,168,.15); }

.empty-state { text-align:center; padding:56px 24px; color:var(--muted); }
.empty-state i { font-size:2.5rem; margin-bottom:14px; display:block; opacity:.25; }
.empty-state p { font-size:.88rem; }

/* ── MODAL ── */
.modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); z-index:2000; align-items:center; justify-content:center; }
.modal-backdrop.open { display:flex; animation:fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.modal { background:var(--surface); border-radius:var(--r-xl); box-shadow:var(--shadow-lg); width:100%; max-width:480px; margin:16px; animation:slideUp .25s ease; }
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-head { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.modal-head-title { font-size:1rem; font-weight:700; color:var(--text); }
.modal-close { width:30px; height:30px; border-radius:50%; background:var(--bg); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:.85rem; transition:background .15s; }
.modal-close:hover { background:var(--border); }
.modal-body { padding:24px; }
.modal-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }

.fg { display:flex; flex-direction:column; gap:6px; margin-bottom:18px; }
.fg label { font-size:.7rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; }
.fg input, .fg select { padding:10px 13px; border:1.5px solid var(--border); border-radius:var(--r-md); font-size:.88rem; font-family:inherit; color:var(--text); background:#fafbfc; outline:none; transition:border-color .2s,box-shadow .2s; }
.fg input:focus, .fg select:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(0,56,168,.08); background:#fff; }
.qty-group { display:flex; gap:8px; }
.qty-btn { flex:1; padding:9px 4px; border-radius:var(--r-md); border:1.5px solid var(--border); background:#fafbfc; color:var(--muted); font-size:.84rem; font-weight:600; font-family:inherit; cursor:pointer; text-align:center; transition:all .15s; }
.qty-btn:hover { border-color:var(--blue); color:var(--blue); background:rgba(0,56,168,.04); }
.qty-btn.active { background:var(--g-blue); border-color:transparent; color:#fff; box-shadow:0 2px 8px rgba(0,56,168,.3); }
.btn-primary { background:var(--g-blue); color:#fff; box-shadow:0 4px 14px rgba(0,56,168,.3); padding:11px 24px; border-radius:var(--r-md); font-size:.88rem; font-weight:700; font-family:inherit; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:transform .18s,opacity .18s; }
.btn-primary:hover { transform:translateY(-1px); opacity:.92; }
.btn-secondary { background:#f1f5f9; color:var(--muted); padding:11px 20px; border-radius:var(--r-md); font-size:.88rem; font-weight:600; font-family:inherit; border:1px solid var(--border); cursor:pointer; transition:background .15s; }
.btn-secondary:hover { background:var(--border); }

/* Generated codes result */
.codes-result { background:#f0fdf4; border:1px solid #86efac; border-radius:var(--r-lg); padding:16px; margin-top:18px; max-height:260px; overflow-y:auto; }
.codes-result-head { font-size:.75rem; font-weight:700; color:var(--success); text-transform:uppercase; letter-spacing:.6px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; }
.code-list { display:flex; flex-direction:column; gap:6px; }
.code-item { display:flex; align-items:center; justify-content:space-between; padding:7px 10px; background:#fff; border-radius:var(--r-sm); border:1px solid #bbf7d0; }
.code-item span { font-family:'Courier New',monospace; font-size:.9rem; font-weight:700; color:var(--navy-mid); letter-spacing:1px; }

/* ── BURGER ── */
.burger-btn { display:none; position:fixed; top:20px; left:16px; z-index:1300; width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15); color:#fff; font-size:1.1rem; cursor:pointer; align-items:center; justify-content:center; transition:background .2s; }
.burger-btn:hover { background:rgba(255,255,255,.18); }

@media(max-width:768px) {
    .main-content { margin-left:0; width:100%; padding:calc(var(--header-h)+24px) 16px 32px; }
    .burger-btn { display:flex; }
    .stats-grid { grid-template-columns:1fr 1fr; }
    .toolbar { flex-direction:column; align-items:stretch; }
}
@media(max-width:480px) { .stats-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>

<?php include '../component_admin/sidebar.php'; ?>
<?php include '../component_admin/top_header.php'; ?>

<button class="burger-btn" id="menuToggle"><i class="fas fa-bars"></i></button>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-title-group">
            <div class="page-title-icon"><i class="fas fa-ticket"></i></div>
            <div>
                <div class="page-title-text">Voucher Management</div>
                <div class="page-subtitle">Generate and manage access codes for the feedback form</div>
            </div>
        </div>
        <button class="btn-generate" onclick="openModal()">
            <i class="fas fa-plus"></i> Generate Vouchers
        </button>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card s-blue">
            <div class="stat-icon-box"><i class="fas fa-ticket"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total Vouchers</div>
            </div>
        </div>
        <div class="stat-card s-green">
            <div class="stat-icon-box"><i class="fas fa-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $active ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card s-violet">
            <div class="stat-icon-box"><i class="fas fa-clipboard-check"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $used ?></div>
                <div class="stat-label">Used</div>
            </div>
        </div>
        <div class="stat-card s-red">
            <div class="stat-icon-box"><i class="fas fa-ban"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $revoked ?></div>
                <div class="stat-label">Revoked</div>
            </div>
        </div>
        <div class="stat-card s-amber">
            <div class="stat-icon-box"><i class="fas fa-chart-pie"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $use_rate ?>%</div>
                <div class="stat-label">Usage Rate</div>
            </div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="filter-tabs">
            <a href="?status=all"     class="ftab <?= $fs==='all'     ? 'active':'' ?>">All (<?= $total ?>)</a>
            <a href="?status=active"  class="ftab <?= $fs==='active'  ? 'active':'' ?>">Active (<?= $active ?>)</a>
            <a href="?status=used"    class="ftab <?= $fs==='used'    ? 'active':'' ?>">Used (<?= $used ?>)</a>
            <a href="?status=revoked" class="ftab <?= $fs==='revoked' ? 'active':'' ?>">Revoked (<?= $revoked ?>)</a>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Voucher Code</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th>Expires</th>
                        <th>Used At</th>
                        <th>Control No.</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($vouchers)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-ticket"></i>
                                <p>No vouchers found. Click <strong>Generate Vouchers</strong> to create some.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vouchers as $i => $v):
                        $is_expired = $v['expires_at'] && strtotime($v['expires_at']) < strtotime(date('Y-m-d'));
                        $display_status = ($v['status'] === 'active' && $is_expired) ? 'expired' : $v['status'];
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.8rem;"><?= $i + 1 ?></td>
                        <td>
                            <span class="code-badge" onclick="copyCode('<?= htmlspecialchars($v['code']) ?>')" title="Click to copy">
                                <?= htmlspecialchars($v['code']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-pill sp-<?= $display_status ?>">
                                <span class="dot"></span>
                                <?= ucfirst($display_status) ?>
                            </span>
                        </td>
                        <td style="font-size:.82rem;"><?= htmlspecialchars($v['created_by'] ?? '—') ?></td>
                        <td style="font-size:.82rem;color:var(--muted);"><?= $v['created_at'] ? date('M j, Y g:i A', strtotime($v['created_at'])) : '—' ?></td>
                        <td style="font-size:.82rem;<?= $is_expired ? 'color:var(--danger);' : 'color:var(--muted);' ?>"><?= $v['expires_at'] ? date('M j, Y', strtotime($v['expires_at'])) : '<span style="color:var(--muted);">No expiry</span>' ?></td>
                        <td style="font-size:.82rem;color:var(--muted);"><?= $v['used_at'] ? date('M j, Y g:i A', strtotime($v['used_at'])) : '—' ?></td>
                        <td style="font-size:.82rem;"><?= $v['used_by_control_no'] ? '<span style="font-family:monospace;color:var(--blue);">' . htmlspecialchars($v['used_by_control_no']) . '</span>' : '—' ?></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button class="action-btn btn-copy" onclick="copyCode('<?= htmlspecialchars($v['code']) ?>')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                                <?php if ($v['status'] === 'active' && !$is_expired): ?>
                                <button class="action-btn btn-revoke" onclick="revokeVoucher(<?= $v['id'] ?>, '<?= htmlspecialchars($v['code']) ?>')">
                                    <i class="fas fa-ban"></i> Revoke
                                </button>
                                <?php endif; ?>
                                <?php if ($is_superadmin): ?>
                                <button class="action-btn btn-del" onclick="deleteVoucher(<?= $v['id'] ?>, '<?= htmlspecialchars($v['code']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- GENERATE MODAL -->
<div class="modal-backdrop" id="genModal">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-head-title"><i class="fas fa-ticket" style="color:var(--blue);margin-right:8px;"></i>Generate Vouchers</div>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="fg">
                <label>Quantity</label>
                <div class="qty-group" id="qtyGroup">
                    <?php foreach ([1,5,10,25,50] as $n): ?>
                    <button type="button" class="qty-btn <?= $n===1?'active':'' ?>" onclick="selectQty(<?= $n ?>)"><?= $n ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="genQty" value="1">
            </div>
            <div class="fg">
                <label>Expiry Date <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional — leave blank for no expiry)</span></label>
                <input type="date" id="genExpiry" min="<?= date('Y-m-d') ?>">
            </div>
            <div id="genResult" style="display:none;">
                <div class="codes-result">
                    <div class="codes-result-head">
                        <span id="genResultLabel"></span>
                        <button class="action-btn btn-copy" onclick="copyAllCodes()" style="font-size:.7rem;">
                            <i class="fas fa-copy"></i> Copy All
                        </button>
                    </div>
                    <div class="code-list" id="codeList"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal()">Close</button>
            <button class="btn-primary" id="genBtn" onclick="generateVouchers()">
                <i class="fas fa-wand-magic-sparkles"></i> Generate
            </button>
        </div>
    </div>
</div>

<script>
/* ── MODAL ── */
function openModal() {
    document.getElementById('genModal').classList.add('open');
    document.getElementById('genResult').style.display = 'none';
    document.getElementById('codeList').innerHTML = '';
    document.getElementById('genExpiry').value = '';
    selectQty(1);
}
function closeModal() {
    document.getElementById('genModal').classList.remove('open');
    location.reload();
}
document.getElementById('genModal').addEventListener('click', e => {
    if (e.target === document.getElementById('genModal')) closeModal();
});

/* ── QTY SELECTOR ── */
function selectQty(n) {
    document.getElementById('genQty').value = n;
    document.querySelectorAll('.qty-btn').forEach(b => b.classList.toggle('active', parseInt(b.textContent) === n));
}

/* ── GENERATE ── */
function generateVouchers() {
    const qty    = document.getElementById('genQty').value;
    const expiry = document.getElementById('genExpiry').value;
    const btn    = document.getElementById('genBtn');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';

    const fd = new FormData();
    fd.append('action', 'generate');
    fd.append('qty', qty);
    if (expiry) fd.append('expires_at', expiry);

    fetch('../../admin/functions_admin/voucher_actions.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate More';
            if (data.success) {
                document.getElementById('genResultLabel').textContent = `${data.count} voucher(s) generated`;
                const list = document.getElementById('codeList');
                list.innerHTML = '';
                data.codes.forEach(c => {
                    list.innerHTML += `
                        <div class="code-item">
                            <span>${c}</span>
                            <button class="action-btn btn-copy" onclick="copyCode('${c}')"><i class="fas fa-copy"></i></button>
                        </div>`;
                });
                document.getElementById('genResult').style.display = 'block';
            } else {
                Swal.fire('Error', data.message || 'Failed to generate.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate';
            Swal.fire('Error', 'Network error.', 'error');
        });
}

/* ── COPY ── */
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        Swal.fire({ icon:'success', title:'Copied!', text:`${code} copied to clipboard.`, timer:1500, showConfirmButton:false });
    });
}
function copyAllCodes() {
    const codes = [...document.querySelectorAll('.code-item span')].map(s => s.textContent).join('\n');
    navigator.clipboard.writeText(codes).then(() => {
        Swal.fire({ icon:'success', title:'All Copied!', text:'All codes copied to clipboard.', timer:1500, showConfirmButton:false });
    });
}

/* ── REVOKE ── */
function revokeVoucher(id, code) {
    Swal.fire({
        title: 'Revoke Voucher?',
        html: `Voucher <strong style="font-family:monospace;">${code}</strong> will be revoked and cannot be used for feedback.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-ban"></i> Revoke',
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'revoke');
        fd.append('id', id);
        fetch('../../admin/functions_admin/voucher_actions.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon:'success', title:'Revoked', timer:1500, showConfirmButton:false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
    });
}

/* ── DELETE ── */
function deleteVoucher(id, code) {
    Swal.fire({
        title: 'Delete Permanently?',
        html: `Voucher <strong style="font-family:monospace;">${code}</strong> will be permanently deleted.`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash"></i> Delete',
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('../../admin/functions_admin/voucher_actions.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon:'success', title:'Deleted', timer:1500, showConfirmButton:false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
    });
}

/* ── SIDEBAR ── */
document.getElementById('menuToggle').onclick = () =>
    document.querySelector('.sidebar').classList.toggle('active');
document.querySelectorAll('.sidebar-menu a').forEach(a =>
    a.addEventListener('click', () => {
        if (window.innerWidth <= 768) document.querySelector('.sidebar').classList.remove('active');
    })
);
</script>
</body>
</html>
