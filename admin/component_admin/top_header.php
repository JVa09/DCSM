<?php
/*NOTIFICATIONS DATA*/
if (!function_exists('_th_notif_meta')) {
    function _th_notif_meta($action, $module = '') {
        $a = strtolower($action ?? '');
        $m = strtolower($module ?? '');
        if (str_contains($a,'fail') || str_contains($a,'deny'))
            return ['fa-triangle-exclamation','#ef4444','rgba(239,68,68,.15)'];
        if (str_contains($a,'login') || str_contains($a,'sign'))
            return ['fa-right-to-bracket','#10b981','rgba(16,185,129,.15)'];
        if (str_contains($a,'password') || str_contains($m,'security') || str_contains($a,'security'))
            return ['fa-key','#8b5cf6','rgba(139,92,246,.15)'];
        if (str_contains($a,'delete') || str_contains($a,'reset'))
            return ['fa-trash-can','#ef4444','rgba(239,68,68,.15)'];
        if (str_contains($a,'create') || str_contains($m,'survey'))
            return ['fa-circle-plus','#0038A8','rgba(0,56,168,.15)'];
        if (str_contains($a,'update') || str_contains($a,'edit') || str_contains($m,'profile') || str_contains($m,'settings'))
            return ['fa-pen-to-square','#f59e0b','rgba(245,158,11,.15)'];
        if (str_contains($m,'notification'))
            return ['fa-bell','#10b981','rgba(16,185,129,.15)'];
        return ['fa-circle-info','#94a3b8','rgba(148,163,184,.15)'];
    }
    function _th_time_ago($dt) {
        if (!$dt) return '';
        $diff = time() - strtotime($dt);
        if ($diff < 60)    return 'Just now';
        if ($diff < 3600)  return floor($diff / 60)   . 'm ago';
        if ($diff < 86400) return floor($diff / 3600)  . 'h ago';
        return date('M j', strtotime($dt));
    }
}

$_th_notifs  = [];
$_th_has_new = false;
if (isset($conn)) {
    $_nr = $conn->query(
        "SELECT username, action, module, details, ip_address, created_at
         FROM activity_logs ORDER BY created_at DESC LIMIT 8"
    );
    if ($_nr) {
        while ($_n = $_nr->fetch_assoc()) $_th_notifs[] = $_n;
    }
    $_td = date('Y-m-d');
    $_tr = $conn->query("SELECT 1 FROM activity_logs WHERE DATE(created_at)='$_td' LIMIT 1");
    $_th_has_new = ($_tr && $_tr->num_rows > 0);
}
?>
<style>
/* ══════════════════════════════════════
   TOP HEADER
══════════════════════════════════════ */
:root {
    --th-bg-start: #001529;
    --th-bg-end:   #002650;
    --gold:        #FCD116;
    --header-h:    80px;
    --sidebar-w:   260px;
}

.top-header {
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    height: var(--header-h);
    background: linear-gradient(135deg, var(--th-bg-start) 0%, var(--th-bg-end) 100%);
    color: #fff;
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px 0 calc(var(--sidebar-w) + 28px);
    box-shadow: 0 4px 20px rgba(0,0,0,.3);
    transition: all .3s ease;
}
.top-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: var(--sidebar-w);
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, #FCD116 0%, #0038A8 35%, #FCD116 65%, #0038A8 100%);
    background-size: 300% 100%;
    animation: headerShimmer 5s linear infinite;
}
@keyframes headerShimmer {
    0%   { background-position: 0% 0; }
    100% { background-position: 300% 0; }
}

/* ── BRAND ── */
.th-brand { display: flex; align-items: center; gap: 14px; min-width: 0; }
.th-logo-icon {
    width: 42px; height: 42px; border-radius: 10px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.th-logo-icon i {
    font-size: 1.25rem;
    background: conic-gradient(
        #0038A8 0deg 90deg, #CE1126 90deg 180deg,
        #FCD116 180deg 270deg, #ffffff 270deg 360deg
    );
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 1px 3px rgba(0,0,0,.4));
}
.th-brand-text { min-width: 0; }
.th-brand-title {
    font-family: 'Inter','Segoe UI',system-ui,sans-serif;
    font-size: 1rem; font-weight: 800; color: #fff;
    letter-spacing: -.2px; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; line-height: 1.2;
}
.th-brand-sub {
    font-size: .67rem; font-weight: 600;
    color: rgba(255,255,255,.45);
    text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;
}
.th-divider { width: 1px; height: 32px; background: rgba(255,255,255,.12); flex-shrink: 0; }

/* ── RIGHT ACTIONS ── */
.th-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

/* Clock */
.th-clock {
    display: flex; flex-direction: column; align-items: flex-end;
    padding: 0 14px 0 6px;
    border-right: 1px solid rgba(255,255,255,.1);
    margin-right: 8px;
}
#pst-time {
    font-family: 'Courier New','Consolas',monospace;
    font-size: 1.05rem; font-weight: 700; color: var(--gold);
    letter-spacing: .5px; line-height: 1.2;
}
#pst-date {
    font-size: .65rem; font-weight: 600;
    color: rgba(255,255,255,.45);
    text-transform: uppercase; letter-spacing: .7px;
    margin-top: 2px; white-space: nowrap;
}

/* Icon buttons */
.th-icon-btn {
    width: 38px; height: 38px; border-radius: 10px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.65); font-size: .9rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all .2s ease;
    text-decoration: none; position: relative; flex-shrink: 0;
}
.th-icon-btn:hover {
    background: rgba(255,255,255,.13);
    border-color: rgba(255,255,255,.2);
    color: #fff; transform: translateY(-1px);
}
.th-icon-btn.active {
    background: rgba(255,255,255,.13);
    border-color: rgba(255,255,255,.22);
    color: #fff;
}

/* Notification dot */
.th-notif-dot {
    position: absolute; top: 7px; right: 7px;
    width: 8px; height: 8px; border-radius: 50%;
    background: #ef4444; border: 1.5px solid #002650;
    animation: dot-pulse 2s ease-in-out infinite;
}
@keyframes dot-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.45); }
    50%      { box-shadow: 0 0 0 4px rgba(239,68,68,0); }
}

/* ══════════════════════════════════════
   NOTIFICATION PANEL
══════════════════════════════════════ */
.th-notif-wrap { position: relative; }

.th-notif-panel {
    position: absolute;
    top: calc(100% + 14px); right: 0;
    width: 360px;
    background: #0d1f3c;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 18px;
    box-shadow: 0 24px 64px rgba(0,0,0,.55);
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-10px) scale(.97);
    transition: opacity .22s ease, transform .22s ease;
    z-index: 1100;
}
.th-notif-panel.open {
    opacity: 1;
    pointer-events: all;
    transform: translateY(0) scale(1);
}

/* Panel header */
.np-head {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 18px 13px;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.np-head-title { font-size: .9rem; font-weight: 700; color: #fff; flex: 1; }
.np-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 7px;
    border-radius: 99px; background: #ef4444;
    font-size: .72rem; font-weight: 800; color: #fff;
}
.np-mark {
    font-size: .72rem; font-weight: 600;
    color: rgba(255,255,255,.4); cursor: pointer;
    background: none; border: none; padding: 0;
    transition: color .2s; font-family: inherit;
}
.np-mark:hover { color: var(--gold); }

/* Panel list */
.np-list { max-height: 344px; overflow-y: auto; }
.np-list::-webkit-scrollbar { width: 4px; }
.np-list::-webkit-scrollbar-track { background: transparent; }
.np-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }

.np-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 13px 18px;
    border-bottom: 1px solid rgba(255,255,255,.055);
    transition: background .15s; cursor: default;
}
.np-item:last-child { border-bottom: none; }
.np-item:hover { background: rgba(255,255,255,.04); }

.np-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: .82rem; flex-shrink: 0; margin-top: 1px;
}
.np-body { flex: 1; min-width: 0; }
.np-action {
    font-size: .81rem; font-weight: 600; color: rgba(255,255,255,.9);
    line-height: 1.3; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
.np-meta { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.np-user {
    font-size: .71rem; font-weight: 600; color: var(--gold);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;
}
.np-time { font-size: .68rem; color: rgba(255,255,255,.3); white-space: nowrap; }

.np-empty {
    padding: 36px 20px; text-align: center;
    color: rgba(255,255,255,.3); font-size: .84rem;
}
.np-empty i { font-size: 1.8rem; display: block; margin-bottom: 10px; opacity: .25; }

/* Panel footer */
.np-foot {
    padding: 12px 18px;
    border-top: 1px solid rgba(255,255,255,.08);
    text-align: center;
}
.np-foot a {
    font-size: .78rem; font-weight: 600;
    color: rgba(255,255,255,.45); text-decoration: none;
    transition: color .2s;
    display: inline-flex; align-items: center; gap: 5px;
}
.np-foot a:hover { color: var(--gold); }

/* ══════════════════════════════════════
   GLOBAL DARK MODE
══════════════════════════════════════ */
html[data-theme="dark"] {
    --bg:      #0f172a;
    --surface: #1e293b;
    --border:  #334155;
    --text:    #e2e8f0;
    --muted:   #94a3b8;
    color-scheme: dark;
}
html[data-theme="dark"] body { background: #0f172a; color: #e2e8f0; }
html[data-theme="dark"] .main-content { background: #0f172a; }

/* Cards & surfaces */
html[data-theme="dark"] .settings-card,
html[data-theme="dark"] .account-info-card,
html[data-theme="dark"] .danger-card,
html[data-theme="dark"] .card,
html[data-theme="dark"] .stat-card {
    background: #1e293b !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .card-head,
html[data-theme="dark"] .card-header,
html[data-theme="dark"] .card-body,
html[data-theme="dark"] .card-footer {
    background: #1e293b !important;
    border-color: #334155 !important;
}

/* Text headings */
html[data-theme="dark"] .card-head-title,
html[data-theme="dark"] .card-header-title,
html[data-theme="dark"] .tr-title,
html[data-theme="dark"] .tgl-label,
html[data-theme="dark"] .tc-title,
html[data-theme="dark"] .stat-val,
html[data-theme="dark"] .hi-value,
html[data-theme="dark"] .info-chip-value,
html[data-theme="dark"] .di-value,
html[data-theme="dark"] .dar-title,
html[data-theme="dark"] .fg label,
html[data-theme="dark"] .form-group label,
html[data-theme="dark"] .banner-sys-name { color: #e2e8f0 !important; }

/* Text muted */
html[data-theme="dark"] .card-head-sub,
html[data-theme="dark"] .card-header-sub,
html[data-theme="dark"] .tr-desc,
html[data-theme="dark"] .tgl-desc,
html[data-theme="dark"] .tc-desc,
html[data-theme="dark"] .stat-label,
html[data-theme="dark"] .stat-sub,
html[data-theme="dark"] .card-footer-info,
html[data-theme="dark"] .hi-label,
html[data-theme="dark"] .info-chip-label,
html[data-theme="dark"] .di-label,
html[data-theme="dark"] .di-hint,
html[data-theme="dark"] .dar-desc,
html[data-theme="dark"] .range-hint,
html[data-theme="dark"] .page-subtitle,
html[data-theme="dark"] .notif-section-label { color: #94a3b8 !important; }

/* Page title gradient */
html[data-theme="dark"] .page-title-text {
    background: linear-gradient(135deg, #e2e8f0 0%, #93c5fd 100%) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
}

/* Inputs & selects */
html[data-theme="dark"] .input-wrap input,
html[data-theme="dark"] .input-wrap select {
    background: #0f172a !important;
    color: #e2e8f0 !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .input-wrap input::placeholder { color: #475569 !important; }
html[data-theme="dark"] .input-wrap input:focus,
html[data-theme="dark"] .input-wrap select:focus {
    background: #1e293b !important;
    border-color: #3b82f6 !important;
}
html[data-theme="dark"] .input-wrap input:disabled,
html[data-theme="dark"] .input-wrap select:disabled {
    background: #162032 !important; color: #475569 !important;
}
html[data-theme="dark"] .input-suffix { color: #94a3b8 !important; }

/* Toggle rows */
html[data-theme="dark"] .toggle-row {
    background: #162032 !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .toggle-row.is-on {
    background: rgba(245,158,11,.07) !important;
    border-color: rgba(245,158,11,.22) !important;
}
html[data-theme="dark"] #darkModeToggleRow.is-on {
    background: rgba(99,102,241,.08) !important;
    border-color: rgba(99,102,241,.28) !important;
}

/* Toggle cards (notifications.php) */
html[data-theme="dark"] .toggle-card { background: #162032 !important; border-color: #334155 !important; }
html[data-theme="dark"] .toggle-card.tc-green.is-on  { background: rgba(16,185,129,.06) !important; border-color: rgba(16,185,129,.25) !important; }
html[data-theme="dark"] .toggle-card.tc-red.is-on    { background: rgba(239,68,68,.06) !important;   border-color: rgba(239,68,68,.25) !important; }
html[data-theme="dark"] .toggle-card.tc-blue.is-on   { background: rgba(0,56,168,.07) !important;    border-color: rgba(0,56,168,.28) !important; }
html[data-theme="dark"] .toggle-card.tc-violet.is-on { background: rgba(139,92,246,.06) !important;  border-color: rgba(139,92,246,.25) !important; }

/* Button groups */
html[data-theme="dark"] .btn-group-item {
    background: #162032 !important; border-color: #334155 !important; color: #94a3b8 !important;
}
html[data-theme="dark"] .btn-group-item.active {
    background: linear-gradient(135deg, #0038A8 0%, #001529 100%) !important;
    color: #fff !important; border-color: transparent !important;
}

/* Info / health / delivery chips */
html[data-theme="dark"] .info-chip,
html[data-theme="dark"] .delivery-item,
html[data-theme="dark"] .health-item {
    background: #162032 !important; border-color: #334155 !important;
}
html[data-theme="dark"] .info-chip:hover,
html[data-theme="dark"] .health-item:hover { background: #1a2a42 !important; }

/* Danger zone */
html[data-theme="dark"] .danger-action-row {
    background: rgba(239,68,68,.03) !important;
    border-color: rgba(239,68,68,.18) !important;
}
html[data-theme="dark"] .danger-head {
    background: rgba(239,68,68,.05) !important;
    border-color: rgba(239,68,68,.15) !important;
}

/* Stat pills */
html[data-theme="dark"] .stat-pill.pill-green { background: rgba(16,185,129,.15) !important; }
html[data-theme="dark"] .stat-pill.pill-red   { background: rgba(239,68,68,.15)  !important; }
html[data-theme="dark"] .stat-pill.pill-amber { background: rgba(245,158,11,.15) !important; }
html[data-theme="dark"] .stat-pill.pill-blue  { background: rgba(59,130,246,.15) !important; }
html[data-theme="dark"] .stat-pill.pill-gray  { background: rgba(148,163,184,.1) !important; color: #94a3b8 !important; }

/* Policy requirements */
html[data-theme="dark"] .policy-req {
    background: #162032 !important; border-color: #334155 !important; color: #94a3b8 !important;
}
html[data-theme="dark"] .policy-req.on {
    background: rgba(16,185,129,.1) !important;
    border-color: rgba(16,185,129,.25) !important; color: #6ee7b7 !important;
}

/* IP / user chips, table */
html[data-theme="dark"] .ip-chip { background: #162032 !important; border-color: #334155 !important; color: #94a3b8 !important; }
html[data-theme="dark"] .user-chip { color: #e2e8f0 !important; }
html[data-theme="dark"] tbody tr { border-color: #334155 !important; }
html[data-theme="dark"] tbody tr:hover { background: #162032 !important; }
html[data-theme="dark"] tbody td { color: #e2e8f0 !important; }
html[data-theme="dark"] .ts-cell .date { color: #e2e8f0 !important; }
html[data-theme="dark"] .ts-cell .time { color: #94a3b8 !important; }

/* Misc */
html[data-theme="dark"] .section-divider { border-color: #334155 !important; }
html[data-theme="dark"] .empty-state { color: #94a3b8 !important; }
html[data-theme="dark"] .locked-overlay { background: rgba(15,23,42,.78) !important; }
html[data-theme="dark"] .locked-overlay .lock-title { color: #e2e8f0 !important; }
html[data-theme="dark"] .locked-overlay .lock-sub { color: #94a3b8 !important; }
html[data-theme="dark"] .form-group label { color: #94a3b8 !important; }

/* Dark mode toggle slider color */
#sys_darkmode:checked + .slider { background: #6366f1 !important; }

/* ══════════════════════════════════════
   DARK MODE — EXTENDED COVERAGE
   (dashboard, tables, forms, all pages)
══════════════════════════════════════ */

/* All headings inside card surfaces — covers every variant across all pages */
html[data-theme="dark"] .card-head h1,
html[data-theme="dark"] .card-head h2,
html[data-theme="dark"] .card-head h3,
html[data-theme="dark"] .card-head h4,
html[data-theme="dark"] .card-body h1,
html[data-theme="dark"] .card-body h2,
html[data-theme="dark"] .card-body h3,
html[data-theme="dark"] .table-card .card-head h3,
html[data-theme="dark"] .table-card-head h3,
html[data-theme="dark"] .table-card-head h2,
html[data-theme="dark"] .filter-card-head h3,
html[data-theme="dark"] .filter-card-head h2,
html[data-theme="dark"] .section-title h2,
html[data-theme="dark"] .section-title h3,
html[data-theme="dark"] .title-text h1,
html[data-theme="dark"] .title-text h2 { color: #e2e8f0 !important; }

html[data-theme="dark"] .title-text p,
html[data-theme="dark"] .card-head p,
html[data-theme="dark"] .section-title p { color: #94a3b8 !important; }

/* Table card head & filter card head surfaces */
html[data-theme="dark"] .table-card-head,
html[data-theme="dark"] .filter-card-head,
html[data-theme="dark"] .filter-card-body {
    background: #1e293b !important;
    border-color: #334155 !important;
}

/* Page header h1 (reports, activity logs, etc.) */
html[data-theme="dark"] .page-header h1 { color: #e2e8f0 !important; }
html[data-theme="dark"] .page-header p  { color: #94a3b8 !important; }

/* Outlined export/action buttons */
html[data-theme="dark"] .btn-export.outlined {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #e2e8f0 !important;
}
html[data-theme="dark"] .btn-export.outlined:hover { background: #162032 !important; }

/* Sat bar background */
html[data-theme="dark"] .sat-bar { background: #334155 !important; }

/* Rank badge */
html[data-theme="dark"] .rank-badge { background: #334155 !important; color: #e2e8f0 !important; }

/* Pag bar (reports uses .pag-bar not .pagination-bar) */
html[data-theme="dark"] .pag-bar {
    background: #162032 !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .pag-bar .pag-info { color: #94a3b8 !important; }
html[data-theme="dark"] .pag-bar .pag-btn {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #e2e8f0 !important;
}
html[data-theme="dark"] .pag-bar .pag-btn:hover  { background: #0038A8 !important; border-color: #0038A8 !important; color: #fff !important; }
html[data-theme="dark"] .pag-bar .pag-btn.active { background: #002147 !important; border-color: #002147 !important; color: #fff !important; }

/* Dashboard stat cards */
html[data-theme="dark"] .stat-value { color: #e2e8f0 !important; }

/* Section labels (dashboard dividers) */
html[data-theme="dark"] .section-label { color: #94a3b8 !important; }

/* Table chrome */
html[data-theme="dark"] thead tr { background: #162032 !important; }
html[data-theme="dark"] th {
    color: #94a3b8 !important;
    border-color: #334155 !important;
    background: #162032 !important;
}
html[data-theme="dark"] td { border-color: rgba(51,65,85,.5) !important; }
html[data-theme="dark"] .table-card,
html[data-theme="dark"] .table-scroll { background: #1e293b !important; border-color: #334155 !important; }

/* Control number chip */
html[data-theme="dark"] .ctrl-no {
    background: rgba(99,102,241,.18) !important;
    color: #a5b4fc !important;
}

/* Pagination */
html[data-theme="dark"] .pagination-bar {
    background: #162032 !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .pag-info { color: #94a3b8 !important; }
html[data-theme="dark"] .pag-btn {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #e2e8f0 !important;
}
html[data-theme="dark"] .pag-btn:hover {
    background: #0038A8 !important;
    border-color: #0038A8 !important;
    color: #fff !important;
}
html[data-theme="dark"] .pag-btn.active {
    background: #002147 !important;
    border-color: #002147 !important;
    color: #fff !important;
}

/* Time chip, badge pill, action meta */
html[data-theme="dark"] .time-chip { color: #94a3b8 !important; }
html[data-theme="dark"] .badge-pill { background: #334155 !important; color: #e2e8f0 !important; }
html[data-theme="dark"] .btn-view {
    background: #0038A8 !important;
    color: #fff !important;
}

/* Rating badges */
html[data-theme="dark"] .excellent { background: rgba(16,185,129,.15) !important; color: #6ee7b7 !important; }
html[data-theme="dark"] .good      { background: rgba(59,130,246,.15)  !important; color: #93c5fd !important; }
html[data-theme="dark"] .average   { background: rgba(245,158,11,.15)  !important; color: #fcd34d !important; }

/* Generic inputs / selects / textareas not inside .input-wrap */
html[data-theme="dark"] input:not([type="radio"]):not([type="checkbox"]):not([type="submit"]),
html[data-theme="dark"] select,
html[data-theme="dark"] textarea {
    background: #0f172a !important;
    color: #e2e8f0 !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] input::placeholder,
html[data-theme="dark"] textarea::placeholder { color: #475569 !important; }
html[data-theme="dark"] input:focus,
html[data-theme="dark"] select:focus,
html[data-theme="dark"] textarea:focus {
    background: #1e293b !important;
    border-color: #3b82f6 !important;
}
html[data-theme="dark"] select option { background: #1e293b !important; color: #e2e8f0 !important; }

/* Filter / search cards */
html[data-theme="dark"] .filter-card,
html[data-theme="dark"] .filter-section,
html[data-theme="dark"] .search-card {
    background: #1e293b !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .filter-card label,
html[data-theme="dark"] .filter-section label { color: #94a3b8 !important; }

/* Back button */
html[data-theme="dark"] .back-btn {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #e2e8f0 !important;
}
html[data-theme="dark"] .back-btn:hover {
    background: #002147 !important;
    border-color: #002147 !important;
    color: #fff !important;
}

/* Action badges (activity logs) */
html[data-theme="dark"] .action-badge { opacity: .9 !important; }

/* Welcome banner sub text */
html[data-theme="dark"] .sub-greeting { color: rgba(255,255,255,.65) !important; }

/* Empty state */
html[data-theme="dark"] .empty-state p { color: #94a3b8 !important; }
html[data-theme="dark"] .empty-icon { background: #162032 !important; }

/* Survey / multi-step form */
html[data-theme="dark"] .cc-block,
html[data-theme="dark"] .sqd-block,
html[data-theme="dark"] .suggestions-wrap {
    background: #162032 !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .q-title,
html[data-theme="dark"] .sqd-q-text { color: #e2e8f0 !important; }
html[data-theme="dark"] .cc-opt label,
html[data-theme="dark"] .sqd-opt label {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #94a3b8 !important;
}
html[data-theme="dark"] .cc-opt input:checked + label {
    background: rgba(0,56,168,.2) !important;
    border-color: #3b82f6 !important;
    color: #93c5fd !important;
}
html[data-theme="dark"] .card-footer { background: #162032 !important; }

/* Manage admins / register page */
html[data-theme="dark"] .admin-card,
html[data-theme="dark"] .admin-row,
html[data-theme="dark"] .admin-item {
    background: #1e293b !important;
    border-color: #334155 !important;
}

/* View client page */
html[data-theme="dark"] .detail-card,
html[data-theme="dark"] .detail-section,
html[data-theme="dark"] .sqd-result-row {
    background: #1e293b !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .detail-label { color: #94a3b8 !important; }
html[data-theme="dark"] .detail-value { color: #e2e8f0 !important; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
    .top-header { padding: 0 16px 0 64px; }
    .th-brand-sub, .th-clock, .th-divider { display: none; }
    .th-brand-title { font-size: .88rem; }
    .th-profile-info { display: none; }
    .th-profile { padding: 6px; background: transparent; border-color: transparent; }
    .th-notif-panel { right: -54px; width: 320px; }
}
@media (max-width: 480px) {
    .th-brand-title { font-size: .8rem; }
    .th-logo-icon { width: 34px; height: 34px; }
    .th-logo-icon i { font-size: 1rem; }
    .th-notif-panel { width: calc(100vw - 28px); right: -64px; }
}
</style>

<header class="top-header">

    <!-- ── BRAND ── -->
    <div class="th-brand">
        <div class="th-logo-icon"><i class="fas fa-chart-pie"></i></div>
        <div class="th-divider"></div>
        <div class="th-brand-text">
            <div class="th-brand-title">Digital Client Satisfaction Measurement</div>
            <div class="th-brand-sub">Department of Information and Communications Technology</div>
        </div>
    </div>

    <!-- ── RIGHT SIDE ── -->
    <div class="th-right">

        <!-- Live Clock -->
        <div class="th-clock">
            <span id="pst-time">--:--:-- --</span>
            <span id="pst-date">Loading…</span>
        </div>

        <!-- Notification Bell + Dropdown -->
        <div class="th-notif-wrap">
            <div class="th-icon-btn" id="th-notif-btn" onclick="toggleNotifPanel()" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($_th_has_new): ?>
                <span class="th-notif-dot" id="th-notif-dot"></span>
                <?php endif; ?>
            </div>

            <div class="th-notif-panel" id="th-notif-panel">

                <!-- Panel Header -->
                <div class="np-head">
                    <span class="np-head-title">
                        <i class="fas fa-bell" style="color:var(--gold);margin-right:7px;font-size:.78rem;"></i>
                        Recent Activity
                    </span>
                    <?php if (!empty($_th_notifs)): ?>
                    <span class="np-count"><?= count($_th_notifs) ?></span>
                    <?php endif; ?>
                    <button class="np-mark" onclick="clearNotifDot();event.stopPropagation();">Mark read</button>
                </div>

                <!-- Panel List -->
                <div class="np-list">
                    <?php if (empty($_th_notifs)): ?>
                    <div class="np-empty">
                        <i class="fas fa-shield-check"></i>
                        <p>No recent activity found</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($_th_notifs as $_n):
                            [$_icon, $_color, $_bg] = _th_notif_meta($_n['action'] ?? '', $_n['module'] ?? '');
                            $_details = htmlspecialchars($_n['details'] ?? $_n['action'] ?? '—');
                            $_module  = htmlspecialchars($_n['module'] ?? '');
                        ?>
                        <div class="np-item">
                            <div class="np-icon" style="background:<?= $_bg ?>;color:<?= $_color ?>;">
                                <i class="fas <?= $_icon ?>"></i>
                            </div>
                            <div class="np-body">
                                <div class="np-action" title="<?= $_details ?>"><?= $_details ?></div>
                                <div class="np-meta">
                                    <span class="np-user">
                                        <i class="fas fa-user" style="font-size:.58rem;opacity:.55;margin-right:2px;"></i>
                                        <?= htmlspecialchars($_n['username'] ?? '—') ?>
                                    </span>
                                    <?php if ($_module): ?>
                                    <span style="font-size:.65rem;padding:1px 6px;border-radius:99px;background:<?= $_bg ?>;color:<?= $_color ?>;font-weight:700;letter-spacing:.3px;"><?= $_module ?></span>
                                    <?php endif; ?>
                                    <span class="np-time"><?= _th_time_ago($_n['created_at'] ?? '') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Panel Footer -->
                <?php if (!empty($_th_notifs)): ?>
                <div class="np-foot">
                    <a href="activity_logs.php">
                        <i class="fas fa-list-check"></i> View all activity
                    </a>
                </div>
                <?php endif; ?>

            </div><!-- /th-notif-panel -->
        </div><!-- /th-notif-wrap -->

        <!-- Dark Mode Toggle -->
        <div class="th-icon-btn" id="th-dm-btn" onclick="toggleDarkMode()" title="Toggle Dark Mode">
            <i class="fas fa-moon" id="th-dm-icon"></i>
        </div>

    </div><!-- /th-right -->
</header>

<script>
/* ── LIVE CLOCK ── */
(function tickClock() {
    const now = new Date();
    const t   = document.getElementById('pst-time');
    const d   = document.getElementById('pst-date');
    if (t) t.textContent = now.toLocaleTimeString('en-US', {
        hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
    if (d) d.textContent = now.toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    });
    setTimeout(tickClock, 1000);
})();

/* ── DARK MODE ── */
(function initDarkMode() {
    if (localStorage.getItem('dcsm-theme') !== 'dark') return;
    document.documentElement.setAttribute('data-theme', 'dark');
    const icon  = document.getElementById('th-dm-icon');
    const btn   = document.getElementById('th-dm-btn');
    const check = document.getElementById('sys_darkmode');
    const row   = document.getElementById('darkModeToggleRow');
    if (icon)  icon.className = 'fas fa-sun';
    if (btn)   btn.classList.add('active');
    if (check) check.checked = true;
    if (row)   row.classList.add('is-on');
})();

function toggleDarkMode() {
    const html  = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const icon  = document.getElementById('th-dm-icon');
    const btn   = document.getElementById('th-dm-btn');
    const check = document.getElementById('sys_darkmode');
    const row   = document.getElementById('darkModeToggleRow');

    if (isDark) {
        html.removeAttribute('data-theme');
        localStorage.setItem('dcsm-theme', 'light');
        if (icon)  icon.className = 'fas fa-moon';
        if (btn)   btn.classList.remove('active');
        if (check) check.checked = false;
        if (row)   row.classList.remove('is-on');
    } else {
        html.setAttribute('data-theme', 'dark');
        localStorage.setItem('dcsm-theme', 'dark');
        if (icon)  icon.className = 'fas fa-sun';
        if (btn)   btn.classList.add('active');
        if (check) check.checked = true;
        if (row)   row.classList.add('is-on');
    }
}

/* ── NOTIFICATION PANEL ── */
function toggleNotifPanel() {
    const panel = document.getElementById('th-notif-panel');
    const btn   = document.getElementById('th-notif-btn');
    const isOpen = panel.classList.contains('open');

    if (isOpen) {
        panel.classList.remove('open');
        btn.classList.remove('active');
    } else {
        panel.classList.add('open');
        btn.classList.add('active');
        clearNotifDot();
    }
}

function clearNotifDot() {
    const dot = document.getElementById('th-notif-dot');
    if (dot) dot.style.display = 'none';
    localStorage.setItem('dcsm-notif-seen', Date.now());
}

/* Close notification panel on outside click */
document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.th-notif-wrap');
    if (!wrap) return;
    if (!wrap.contains(e.target)) {
        const panel = document.getElementById('th-notif-panel');
        const btn   = document.getElementById('th-notif-btn');
        if (panel) panel.classList.remove('open');
        if (btn)   btn.classList.remove('active');
    }
});
</script>
