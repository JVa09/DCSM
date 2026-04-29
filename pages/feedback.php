<?php
include '../functions/db_connection.php';
$regions_query = "SELECT id, region_name FROM regions WHERE is_active = 1 ORDER BY id ASC";
$regions_result = mysqli_query($conn, $regions_query);
$services_query = "SELECT id, service_name FROM services WHERE is_active = 1 ORDER BY id ASC";
$services_result = mysqli_query($conn, $services_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DICT Client Satisfaction Survey</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary:   #002147;
            --secondary: #0038A8;
            --accent:    #FFD700;
            --light:     #eef2f7;
            --white:     #ffffff;
            --success:   #10b981;
            --warning:   #f59e0b;
            --danger:    #ef4444;
            --info:      #3b82f6;
            --border:    #e2e8f0;
            --muted:     #64748b;
            --dark:      #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body { background: var(--light); min-height: 100vh; overflow-x: hidden; }

        /* ── BACKGROUND ── */
        .bg-anim { position: fixed; inset: 0; z-index: -1; overflow: hidden; }
        .bg-anim::before {
            content: ''; position: absolute; inset: -50%;
            background:
                radial-gradient(circle at 20% 80%, rgba(0,56,168,.07)  0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0,33,71,.07)   0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(255,215,0,.04) 0%, transparent 40%);
            animation: bgPulse 25s ease-in-out infinite;
        }
        @keyframes bgPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.07)} }

        /* ── PARTICLES ── */
        .particles { position: fixed; inset: 0; pointer-events: none; z-index: -1; }
        .particle  { position: absolute; border-radius: 50%; opacity: 0; animation: floatUp 15s infinite ease-in-out; }
        .particle:nth-child(1)  { width:11px;height:11px;left:8%;  background:var(--secondary);animation-delay:0s;  animation-duration:13s; }
        .particle:nth-child(2)  { width:19px;height:19px;left:18%; background:var(--accent);  animation-delay:2s;  animation-duration:16s; }
        .particle:nth-child(3)  { width:8px; height:8px; left:28%; background:var(--secondary);animation-delay:4s;  animation-duration:14s; }
        .particle:nth-child(4)  { width:15px;height:15px;left:38%; background:var(--accent);  animation-delay:1s;  animation-duration:18s; }
        .particle:nth-child(5)  { width:10px;height:10px;left:50%; background:var(--success); animation-delay:3s;  animation-duration:15s; }
        .particle:nth-child(6)  { width:21px;height:21px;left:62%; background:var(--accent);  animation-delay:5s;  animation-duration:20s; }
        .particle:nth-child(7)  { width:9px; height:9px; left:72%; background:var(--secondary);animation-delay:2s;  animation-duration:12s; }
        .particle:nth-child(8)  { width:14px;height:14px;left:82%; background:var(--accent);  animation-delay:4s;  animation-duration:17s; }
        .particle:nth-child(9)  { width:11px;height:11px;left:92%; background:var(--success); animation-delay:1s;  animation-duration:14s; }
        .particle:nth-child(10) { width:17px;height:17px;left:14%; background:var(--success); animation-delay:3s;  animation-duration:19s; }
        .particle:nth-child(11) { width:7px; height:7px; left:34%; background:var(--secondary);animation-delay:5s;  animation-duration:13s; }
        .particle:nth-child(12) { width:13px;height:13px;left:58%; background:var(--success); animation-delay:2s;  animation-duration:16s; }
        @keyframes floatUp {
            0%   { transform:translateY(105vh) scale(.8); opacity:0; }
            10%  { opacity:.3; }
            50%  { transform:translateY(45vh)  scale(1.1); }
            90%  { opacity:.3; }
            100% { transform:translateY(-10vh) scale(.8); opacity:0; }
        }

        /* ── HEADER ── */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, #003580 50%, var(--secondary) 100%);
            color: white; padding: 52px 20px 68px; text-align: center;
            position: relative; overflow: hidden;
        }
        .header::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, var(--accent), var(--success), var(--info), var(--accent));
            background-size: 300% 100%; animation: shimmer 4s linear infinite;
        }
        @keyframes shimmer { from{background-position:0% 50%} to{background-position:300% 50%} }
        .header-shine {
            position: absolute; top: -60%; left: -60%; width: 220%; height: 220%;
            background: radial-gradient(circle, rgba(255,255,255,.06) 0%, transparent 55%);
            animation: rotateSlow 22s linear infinite; pointer-events: none;
        }
        @keyframes rotateSlow { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
        .header-float { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
        .hf { position: absolute; font-size: 20px; opacity: .08; animation: iconFloat 7s ease-in-out infinite; }
        .hf:nth-child(1){top:18%;left:7%;   animation-delay:0s}
        .hf:nth-child(2){top:65%;left:4%;   animation-delay:1.2s}
        .hf:nth-child(3){top:22%;right:7%;  animation-delay:2s}
        .hf:nth-child(4){top:68%;right:4%;  animation-delay:.6s}
        .hf:nth-child(5){top:44%;left:2%;   animation-delay:1.8s}
        .hf:nth-child(6){top:42%;right:2%;  animation-delay:.9s}
        @keyframes iconFloat { 0%,100%{transform:translateY(0) rotate(0)} 50%{transform:translateY(-12px) rotate(8deg)} }

        .header-brand {
            display: inline-flex; align-items: center; gap: 9px;
            background: rgba(255,255,255,.12); padding: 9px 20px; border-radius: 50px;
            border: 1px solid rgba(255,255,255,.22); margin-bottom: 18px;
            position: relative; z-index: 1;
        }
        .header-brand span { font-size: 12px; font-weight: 700; letter-spacing: 2px; opacity: .9; }
        .header h1 { font-size: 28px; font-weight: 800; position: relative; z-index: 1; letter-spacing: -.4px; text-shadow: 0 2px 20px rgba(0,0,0,.25); }
        .header p  { margin-top: 9px; font-size: 14px; opacity: .85; position: relative; z-index: 1; }
        .header-badge {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 14px; background: rgba(255,215,0,.18);
            border: 1px solid rgba(255,215,0,.38); color: var(--accent);
            padding: 5px 15px; border-radius: 50px; font-size: 12px; font-weight: 700;
            position: relative; z-index: 1;
        }

        /* ── CONTAINER ── */
        .container {
            max-width: 820px; margin: -38px auto 60px;
            background: var(--white); padding: 48px;
            border-radius: 28px; box-shadow: 0 28px 88px rgba(0,33,71,.13);
            position: relative; z-index: 2;
            animation: slideUp .65s cubic-bezier(.22,1,.36,1);
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(48px)} to{opacity:1;transform:translateY(0)} }

        /* ── PROGRESS BAR ── */
        .progress-wrap { margin-bottom: 44px; }
        .progress-track {
            display: flex; align-items: center; justify-content: space-between;
            position: relative; margin-bottom: 12px;
        }
        .p-line-bg {
            position: absolute; top: 50%; left: 24px; right: 24px; height: 3px;
            background: var(--border); transform: translateY(-50%); border-radius: 2px; z-index: 0;
        }
        .p-line-fill {
            position: absolute; top: 50%; left: 24px; height: 3px;
            background: linear-gradient(90deg, var(--success), var(--secondary));
            transform: translateY(-50%); border-radius: 2px; z-index: 1; width: 0;
            transition: width .55s cubic-bezier(.4,0,.2,1);
        }
        .ps {
            width: 48px; height: 48px; border-radius: 50%; z-index: 2;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 15px; color: var(--muted);
            background: var(--border); border: 3px solid var(--white);
            box-shadow: 0 3px 12px rgba(0,0,0,.1);
            transition: all .4s cubic-bezier(.4,0,.2,1);
        }
        .ps.active {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white; box-shadow: 0 6px 22px rgba(0,56,168,.42);
            transform: scale(1.12); border-color: var(--accent);
        }
        .ps.active::after {
            content: ''; position: absolute; inset: -6px; border-radius: 50%;
            border: 2px solid rgba(255,215,0,.5);
            animation: pulseRing 2s ease-in-out infinite;
        }
        .ps { position: relative; }
        @keyframes pulseRing { 0%,100%{transform:scale(1);opacity:.6} 50%{transform:scale(1.18);opacity:0} }
        .ps.done { background: linear-gradient(135deg, var(--success), #059669); color: white; box-shadow: 0 5px 18px rgba(16,185,129,.38); }
        .step-labels-row { display: flex; justify-content: space-between; }
        .sl { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .8px; text-align: center; flex: 1; transition: color .3s; }
        .sl.active { color: var(--secondary); }
        .sl.done   { color: var(--success); }

        /* ── STEPS ── */
        .step { display: none; }
        .step.active { display: block; animation: fadeSlide .42s cubic-bezier(.22,1,.36,1); }
        @keyframes fadeSlide { from{opacity:0;transform:translateX(26px)} to{opacity:1;transform:translateX(0)} }
        .step h2 {
            color: var(--primary); font-size: 23px; font-weight: 800;
            margin-bottom: 30px; display: flex; align-items: center; gap: 12px;
        }
        .step-icon {
            width: 44px; height: 44px; border-radius: 14px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; box-shadow: 0 5px 16px rgba(0,56,168,.3);
        }

        /* ── FIELDS (Step 1) ── */
        .field { margin-bottom: 20px; }
        .field label { display: block; font-weight: 700; color: var(--primary); font-size: 13px; margin-bottom: 7px; letter-spacing: .3px; }
        .field label .req { color: var(--danger); margin-left: 3px; }
        .fi-wrap { position: relative; }
        .fi-wrap .fi-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; pointer-events: none; }
        .fi-wrap input,
        .fi-wrap select { padding-left: 42px; }
        .field input, .field select {
            width: 100%; padding: 13px 16px; border-radius: 12px;
            border: 2px solid var(--border); font-size: 15px; color: var(--dark);
            background: #f8fafc; transition: all .25s ease; appearance: none;
        }
        .field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; background-color: #f8fafc;
            padding-right: 42px;
        }
        .field input:focus, .field select:focus {
            outline: none; border-color: var(--secondary);
            background: white; box-shadow: 0 0 0 4px rgba(0,56,168,.1);
        }
        .field input.err, .field select.err {
            border-color: var(--danger); background: #fff5f5;
            box-shadow: 0 0 0 4px rgba(239,68,68,.1); animation: shake .4s ease;
        }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }

        /* ── INFO BOX ── */
        .info-box {
            background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            padding: 20px 24px; border-radius: 16px; margin-bottom: 28px;
            border-left: 5px solid #93c5fd; position: relative; overflow: hidden;
        }
        .info-box::before { content:''; position:absolute; top:-20px; right:-20px; width:70px; height:70px; background:rgba(255,255,255,.07); border-radius:50%; }
        .info-box p { line-height: 1.8; color: #dbeafe; font-size: 14px; }
        .info-box strong { color: white; }

        /* ── QUESTION CARD ── */
        .q-card {
            margin-bottom: 24px; padding: 22px 24px;
            background: #fafbfc; border-radius: 18px;
            border: 2px solid var(--border);
            transition: border-color .3s, box-shadow .3s;
            position: relative;
        }
        .q-card:hover { border-color: #c7d2fe; box-shadow: 0 6px 24px rgba(0,56,168,.07); }
        .q-card.answered  { border-color: #86efac; background: #f0fdf4; }
        .q-card.has-error { border-color: var(--danger) !important; background: #fff5f5 !important; animation: shake .4s ease; }

        .q-label { font-weight: 700; color: var(--primary); margin-bottom: 14px; font-size: 15px; line-height: 1.55; display: flex; align-items: flex-start; gap: 8px; }
        .q-num { background: var(--secondary); color: white; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 6px; white-space: nowrap; margin-top: 2px; flex-shrink: 0; }
        .q-dim { color: var(--muted); font-size: 12px; font-weight: 500; }

        .q-check {
            position: absolute; top: 14px; right: 16px;
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--success); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; opacity: 0; transform: scale(.5);
            transition: all .3s cubic-bezier(.34,1.56,.64,1);
        }
        .q-card.answered .q-check { opacity: 1; transform: scale(1); }

        /* ── CHOICE GROUP (CC text options) ── */
        .choice-group { display: flex; flex-direction: column; gap: 9px; }
        .choice-opt {
            display: flex; align-items: center; gap: 13px;
            padding: 13px 17px; border-radius: 12px;
            border: 2px solid var(--border); cursor: pointer;
            background: white; transition: all .22s ease;
        }
        .choice-opt:hover { border-color: #93c5fd; background: #eff6ff; transform: translateX(4px); }
        .choice-opt input[type="radio"] { display: none; }
        .radio-dot {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid #cbd5e1; flex-shrink: 0;
            position: relative; transition: all .22s ease;
        }
        .choice-opt:has(input:checked) { border-color: var(--secondary); background: linear-gradient(135deg,#eff6ff,#dbeafe); }
        .choice-opt:has(input:checked) .radio-dot { border-color: var(--secondary); background: var(--secondary); }
        .choice-opt:has(input:checked) .radio-dot::after { content:''; position:absolute; top:50%;left:50%; transform:translate(-50%,-50%); width:7px;height:7px; background:white; border-radius:50%; }
        .choice-text { font-size: 14px; color: var(--dark); line-height: 1.45; }
        .choice-text em { color: var(--muted); font-size: 12px; }

        /* ── EMOJI RATING (SQD) ── */
        .emoji-rating { display: flex; justify-content: space-between; gap: 9px; margin-top: 8px; flex-wrap: wrap; }
        .emoji-rating input[type="radio"] { display: none; }
        .emoji-rating label {
            flex: 1; min-width: 88px; text-align: center; cursor: pointer;
            padding: 15px 8px 11px; border-radius: 16px;
            border: 2px solid var(--border); background: white;
            transition: all .3s cubic-bezier(.4,0,.2,1); position: relative;
        }
        .emoji-rating label:hover { transform: translateY(-6px); box-shadow: 0 10px 28px rgba(0,0,0,.11); border-color: #c7d2fe; }
        .emoji-rating label .e  { font-size: 36px; display: block; margin-bottom: 7px; transition: transform .3s ease; filter: grayscale(.3); }
        .emoji-rating label .et { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; line-height: 1.3; }
        .emoji-rating label:hover .e { transform: scale(1.15); filter: grayscale(0); }
        .emoji-rating label:has(input:checked) .e { transform: scale(1.2); filter: grayscale(0); animation: emojiPop .5s cubic-bezier(.68,-.55,.265,1.55); }
        @keyframes emojiPop { 0%{transform:scale(1)} 30%{transform:scale(1.45)} 60%{transform:scale(.9)} 80%{transform:scale(1.25)} 100%{transform:scale(1.2)} }
        .emoji-rating label:has(input[value="1"]:checked) { background:linear-gradient(135deg,#fee2e2,#fecaca); border-color:#ef4444; box-shadow:0 0 24px rgba(239,68,68,.22); }
        .emoji-rating label:has(input[value="2"]:checked) { background:linear-gradient(135deg,#ffedd5,#fed7aa); border-color:#f97316; box-shadow:0 0 24px rgba(249,115,22,.22); }
        .emoji-rating label:has(input[value="3"]:checked) { background:linear-gradient(135deg,#fef9c3,#fef08a); border-color:#eab308; box-shadow:0 0 24px rgba(234,179,8,.22); }
        .emoji-rating label:has(input[value="4"]:checked) { background:linear-gradient(135deg,#dcfce7,#bbf7d0); border-color:#22c55e; box-shadow:0 0 24px rgba(34,197,94,.22); }
        .emoji-rating label:has(input[value="5"]:checked) { background:linear-gradient(135deg,#dbeafe,#bfdbfe); border-color:#3b82f6; box-shadow:0 0 28px rgba(59,130,246,.28); }

        /* ── TEXTAREA ── */
        .ta-wrap { position: relative; }
        .ta-wrap textarea {
            width: 100%; padding: 14px 16px; border-radius: 13px;
            border: 2px solid var(--border); font-size: 14px; resize: vertical;
            background: #f8fafc; color: var(--dark); transition: all .25s ease; min-height: 90px;
        }
        .ta-wrap textarea:focus { outline: none; border-color: var(--secondary); background: white; box-shadow: 0 0 0 4px rgba(0,56,168,.1); }
        .char-count { text-align: right; font-size: 11px; color: var(--muted); margin-top: 5px; font-weight: 500; transition: color .2s; }
        .char-count.warn  { color: var(--warning); }
        .char-count.over  { color: var(--danger); }

        /* ── CC3 REASON ── */
        #cc3_reason_wrap {
            margin-top: 14px; padding: 16px 18px;
            background: #fefce8; border-radius: 12px;
            border: 1px solid #fde047; animation: fadeSlide .3s ease;
        }
        #cc3_reason_wrap > label { font-size: 12px; font-weight: 700; color: #854d0e; margin-bottom: 8px; display: block; }

        /* ── DIVIDER ── */
        .divider { display: flex; align-items: center; gap: 14px; margin: 28px 0 22px; }
        .divider span { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; white-space: nowrap; }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--border); }

        /* ── BUTTONS ── */
        .btn-group { display: flex; gap: 14px; margin-top: 38px; }
        .btn {
            padding: 14px 26px; border: none; border-radius: 14px; cursor: pointer;
            font-weight: 700; font-size: 15px; flex: 1;
            transition: all .3s cubic-bezier(.4,0,.2,1); position: relative; overflow: hidden;
        }
        .btn::after { content:''; position:absolute; inset:0; background:rgba(255,255,255,0); transition:background .3s; }
        .btn:hover::after { background:rgba(255,255,255,.1); }
        .btn-primary { background: linear-gradient(135deg,var(--secondary),var(--primary)); color:white; box-shadow:0 6px 20px rgba(0,56,168,.35); }
        .btn-primary:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(0,56,168,.45); }
        .btn-secondary { background: linear-gradient(135deg,#f1f5f9,#e2e8f0); color:#475569; box-shadow:0 3px 12px rgba(0,0,0,.08); }
        .btn-secondary:hover { background:linear-gradient(135deg,#e2e8f0,#cbd5e1); transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.12); }
        .btn-success { background: linear-gradient(135deg,var(--success),#059669); color:white; box-shadow:0 6px 20px rgba(16,185,129,.35); }
        .btn-success:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(16,185,129,.45); }

        /* ── SUCCESS SCREEN ── */
        .success-wrap { text-align: center; padding: 40px 20px; animation: successPop .7s cubic-bezier(.22,1,.36,1); }
        @keyframes successPop { from{opacity:0;transform:scale(.85)} to{opacity:1;transform:scale(1)} }
        .success-icon { font-size: 88px; display: block; animation: bounce 1.6s ease-in-out infinite; }
        @keyframes bounce { 0%,100%{transform:translateY(0)} 40%{transform:translateY(-18px)} 65%{transform:translateY(-8px)} }
        .success-wrap h2 {
            font-size: 34px; font-weight: 800; margin: 20px 0 12px;
            background: linear-gradient(135deg,var(--success),#059669);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .success-wrap p { color: var(--muted); font-size: 16px; line-height: 1.7; max-width: 380px; margin: 0 auto 28px; }
        .success-card {
            display: inline-block; background: linear-gradient(135deg,#f0fdf4,#dcfce7);
            border: 1px solid #86efac; border-radius: 18px; padding: 22px 38px; margin-bottom: 30px;
        }
        .success-row { display: flex; align-items: center; gap: 10px; margin: 8px 0; font-size: 14px; color: var(--primary); }
        .success-row strong { color: var(--secondary); }
        .btn-home {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 15px 32px; background: linear-gradient(135deg,var(--secondary),var(--primary));
            color: white; text-decoration: none; border-radius: 14px;
            font-weight: 700; font-size: 15px; box-shadow: 0 7px 22px rgba(0,56,168,.35);
            transition: all .3s ease;
        }
        .btn-home:hover { transform:translateY(-3px); box-shadow:0 11px 30px rgba(0,56,168,.45); }

        /* ── RESPONSIVE ── */
        @media(max-width:768px){
            .header{ padding:38px 16px 52px }
            .header h1{ font-size:21px }
            .container{ margin:-28px 12px 40px; padding:28px 18px; border-radius:20px }
            .emoji-rating{ flex-direction:column }
            .emoji-rating label{ flex-direction:row; text-align:left; padding:11px 14px; align-items:center; gap:12px; }
            .emoji-rating label .e{ font-size:28px; margin-bottom:0 }
            .btn-group{ flex-direction:column-reverse }
            .ps{ width:40px; height:40px; font-size:13px }
        }
        @media(max-width:480px){
            .container{ padding:20px 14px }
            .step h2{ font-size:19px }
            .q-card{ padding:16px }
        }
    </style>
</head>
<body>

<div class="bg-anim"></div>
<div class="particles">
    <div class="particle"></div><div class="particle"></div><div class="particle"></div>
    <div class="particle"></div><div class="particle"></div><div class="particle"></div>
    <div class="particle"></div><div class="particle"></div><div class="particle"></div>
    <div class="particle"></div><div class="particle"></div><div class="particle"></div>
</div>

<div class="header">
    <div class="header-shine"></div>
    <div class="header-float">
        <span class="hf">📡</span><span class="hf">💡</span><span class="hf">🔐</span>
        <span class="hf">📱</span><span class="hf">⚡</span><span class="hf">🛡️</span>
    </div>
    <div class="header-brand" style="position:relative;z-index:1">
        <span>🇵🇭</span><span>DICT</span>
    </div>
    <h1 style="position:relative;z-index:1">Digital Client Satisfaction Survey</h1>
    <p style="position:relative;z-index:1">Your feedback helps us improve our services for all Filipinos</p>
    <div class="header-badge">✅ Citizen Charter Compliance</div>
</div>

<div class="container">
<form id="surveyForm" novalidate>

    <!-- PROGRESS -->
    <div class="progress-wrap" id="progressWrap">
        <div class="progress-track">
            <div class="p-line-bg"></div>
            <div class="p-line-fill" id="pFill"></div>
            <div class="ps active" id="ps1">1</div>
            <div class="ps"        id="ps2">2</div>
            <div class="ps"        id="ps3">3</div>
        </div>
        <div class="step-labels-row">
            <div class="sl active" id="sl1">Client Info</div>
            <div class="sl"        id="sl2">CC Survey</div>
            <div class="sl"        id="sl3">Service Rating</div>
        </div>
    </div>

    <!-- ══════════════ STEP 1: CLIENT INFO ══════════════ -->
    <div class="step active" data-step="1">
        <h2><div class="step-icon">🪪</div> Client Information</h2>

        <div class="field">
            <label>Age <span class="req">*</span></label>
            <div class="fi-wrap">
                <span class="fi-icon"></span>
                <input type="number" name="age" required min="1" max="120" placeholder="— Enter your age —">
            </div>
        </div>

        <div class="field">
            <label>Sex <span class="req">*</span></label>
            <select name="sex" required>
                <option value="">— Select Sex —</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>

        <div class="field">
            <label>Region of Residence <span class="req">*</span></label>
            <select name="region_id" required>
                <option value="">— Select Region —</option>
                <?php while($row = mysqli_fetch_assoc($regions_result)): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['region_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="field">
            <label>Client Type <span class="req">*</span></label>
            <select name="client_type" required>
                <option value="">— Select Client Type —</option>
                <option value="Citizen">🙋 Citizen</option>
                <option value="Business">💼 Business</option>
                <option value="Government">🏛️ Government</option>
            </select>
        </div>

        <div class="field">
            <label>Service Availed <span class="req">*</span></label>
            <select name="service_id" required>
                <option value="">— Select Service —</option>
                <?php while($row = mysqli_fetch_assoc($services_result)): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['service_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="btn-group">
            <span></span>
            <button type="button" class="btn btn-primary" onclick="nextStep()">Next Step →</button>
        </div>
    </div>

    <!-- ══════════════ STEP 2: CITIZEN'S CHARTER ══════════════ -->
    <div class="step" data-step="2">
        <h2><div class="step-icon">📜</div> Citizen's Charter</h2>

        <div class="info-box">
            <p><strong>Instructions:</strong> Select your answer to the Citizen's Charter (CC) questions. The Citizen's Charter is an official document that reflects the services of a government agency/office including its requirements, fees, and processing times among others.</p>
        </div>

        <!-- CC1 -->
        <div class="q-card" id="q_cc1">
            <div class="q-check">✓</div>
            <div class="q-label"><span class="q-num">CC1</span> Do you know about the Citizen's Charter (official document of an agency's services and requirements)?</div>
            <div class="choice-group">
                <label class="choice-opt">
                    <input type="radio" name="cc1" value="1">
                    <div class="radio-dot"></div>
                    <span class="choice-text">No, I was not aware of the CC <em>(Skip CC2 and CC3)</em></span>
                </label>
                <label class="choice-opt">
                    <input type="radio" name="cc1" value="3">
                    <div class="radio-dot"></div>
                    <span class="choice-text">Yes, but I was only aware when I saw the CC of this office</span>
                </label>
                <label class="choice-opt">
                    <input type="radio" name="cc1" value="5">
                    <div class="radio-dot"></div>
                    <span class="choice-text">Yes, I was already aware before my transaction with this office</span>
                </label>
            </div>
        </div>

        <!-- CC2 -->
        <div class="q-card" id="q_cc2">
            <div class="q-check">✓</div>
            <div class="q-label"><span class="q-num">CC2</span> If yes to CC1, did you see this office's Citizen's Charter?</div>
            <div class="choice-group">
                <label class="choice-opt">
                    <input type="radio" name="cc2" value="1">
                    <div class="radio-dot"></div>
                    <span class="choice-text">No, I did not see this office's CC <em>(Skip CC3)</em></span>
                </label>
                <label class="choice-opt">
                    <input type="radio" name="cc2" value="3">
                    <div class="radio-dot"></div>
                    <span class="choice-text">Yes, but the CC was hard to find</span>
                </label>
                <label class="choice-opt">
                    <input type="radio" name="cc2" value="5">
                    <div class="radio-dot"></div>
                    <span class="choice-text">Yes, the CC was easy to find</span>
                </label>
            </div>
        </div>

        <!-- CC3 -->
        <div class="q-card" id="q_cc3">
            <div class="q-check">✓</div>
            <div class="q-label"><span class="q-num">CC3</span> If yes to CC2, did you use the Citizen's Charter as a guide for the service/s you availed?</div>
            <div class="choice-group">
                <label class="choice-opt">
                    <input type="radio" name="cc3" value="1" id="cc3_no">
                    <div class="radio-dot"></div>
                    <span class="choice-text">No, I was not able to use the CC because…</span>
                </label>
                <label class="choice-opt">
                    <input type="radio" name="cc3" value="5" id="cc3_yes">
                    <div class="radio-dot"></div>
                    <span class="choice-text">Yes, I was able to use the CC as a guide</span>
                </label>
            </div>
            <div id="cc3_reason_wrap" style="display:none">
                <label>Please specify your reason:</label>
                <div class="ta-wrap">
                    <textarea name="cc3_reason" id="cc3_reason" rows="3" maxlength="300"
                        placeholder="e.g., I didn't see it, it was too complicated…"></textarea>
                    <div class="char-count" id="cc3Count">0 / 300</div>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-secondary" onclick="prevStep()">← Previous</button>
            <button type="button" class="btn btn-primary"   onclick="nextStep()">Next Step →</button>
        </div>
    </div>

    <!-- ══════════════ STEP 3: SQD ══════════════ -->
    <div class="step" data-step="3">
        <h2><div class="step-icon">🌟</div> Service Quality Dimensions</h2>

        <?php
        $sqds = [
            ['sqd0','SQD1','I spent an acceptable amount of time to complete my transaction','Responsiveness'],
            ['sqd1','SQD2','The office accurately informed and followed the transaction\'s requirements and steps','Reliability'],
            ['sqd2','SQD3','My online transaction (including steps and payment) was simple and convenient','Access & Facilities'],
            ['sqd3','SQD4','I easily found information about my transaction from the office or its website','Communication'],
            ['sqd4','SQD5','I paid an acceptable amount of fees for my transaction','Costs'],
            ['sqd5','SQD6','I am confident my online transaction was secure','Integrity'],
            ['sqd6','SQD7','The office\'s online support was available, or online support was quick to respond','Assurance'],
            ['sqd7','SQD8','I got what I needed from the government office','Outcome'],
            ['sqd8','SQD9','Denial of my request (if any) was sufficiently explained to me','Outcome — Denial'],
        ];
        foreach($sqds as [$name,$num,$text,$dim]):
        ?>
        <div class="q-card" id="q_<?= $name ?>">
            <div class="q-check">✓</div>
            <div class="q-label">
                <span class="q-num"><?= $num ?></span>
                <span><?= htmlspecialchars($text) ?> <span class="q-dim">(<?= $dim ?>)</span></span>
            </div>
            <div class="emoji-rating">
                <label><input type="radio" name="<?= $name ?>" value="1"><span class="e">😞</span><span class="et">Strongly<br>Disagree</span></label>
                <label><input type="radio" name="<?= $name ?>" value="2"><span class="e">😕</span><span class="et">Disagree</span></label>
                <label><input type="radio" name="<?= $name ?>" value="3"><span class="e">😐</span><span class="et">Neutral</span></label>
                <label><input type="radio" name="<?= $name ?>" value="4"><span class="e">😊</span><span class="et">Agree</span></label>
                <label><input type="radio" name="<?= $name ?>" value="5"><span class="e">🥰</span><span class="et">Strongly<br>Agree</span></label>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="divider"><span>Optional</span></div>

        <div class="q-card" style="border-style:dashed">
            <div class="q-label" style="margin-bottom:10px">✍️ Suggestions for Improvement <span class="q-dim">(Optional)</span></div>
            <div class="ta-wrap">
                <textarea name="suggestions" id="suggestionsTA" rows="4" maxlength="500"
                    placeholder="Share your comments, suggestions, or additional feedback here…"></textarea>
                <div class="char-count" id="suggestionsCount">0 / 500</div>
            </div>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-secondary" onclick="prevStep()">← Previous</button>
            <button type="submit" class="btn btn-success"   id="submitBtn">Submit Survey ✓</button>
        </div>
    </div>

    <!-- ══════════════ STEP 4: SUCCESS ══════════════ -->
    <div class="step" data-step="4">
        <div class="success-wrap">
            <span class="success-icon">🥳</span>
            <h2>Thank You!</h2>
            <p>Your feedback has been submitted successfully. Your input helps DICT serve all Filipinos better.</p>
            <div class="success-card">
                <div class="success-row">🗂️ Reference: <strong id="refNumber"></strong></div>
                <div class="success-row">🗓️ Date: <strong id="submitDate"></strong></div>
            </div>
            <a href="../index.php" class="btn-home">🏡 Back to Home</a>
        </div>
    </div>

</form>
</div>

<script>
/* ─── STATE ─── */
let currentStep = 0;
const steps    = document.querySelectorAll('.step');
const psBtns   = ['ps1','ps2','ps3'].map(id => document.getElementById(id));
const slBtns   = ['sl1','sl2','sl3'].map(id => document.getElementById(id));
const pFill    = document.getElementById('pFill');
const progWrap = document.getElementById('progressWrap');

/* ─── PROGRESS ─── */
const fillAt = ['0px', 'calc(50% - 24px)', 'calc(100% - 48px)'];

function updateProgress() {
    if (currentStep >= 3) { progWrap.style.display = 'none'; return; }
    progWrap.style.display = 'block';
    pFill.style.width = fillAt[currentStep] || '0px';
    psBtns.forEach((ps, i) => {
        ps.className = 'ps';
        ps.textContent = i + 1;
        if      (i < currentStep)  { ps.classList.add('done'); ps.textContent = '✓'; }
        else if (i === currentStep)  ps.classList.add('active');
    });
    slBtns.forEach((sl, i) => {
        sl.className = 'sl';
        if      (i < currentStep)  sl.classList.add('done');
        else if (i === currentStep) sl.classList.add('active');
    });
}

function showStep(idx) {
    steps.forEach((s, i) => s.classList.toggle('active', i === idx));
    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function nextStep() { if (validate()) { currentStep++; showStep(currentStep); } }
function prevStep() { currentStep--; showStep(currentStep); }

/* ─── VALIDATION ─── */
function validate() {
    let ok = true, firstErr = null;

    if (currentStep === 0) {
        steps[0].querySelectorAll('input[required], select[required]').forEach(el => {
            el.classList.remove('err');
            if (!el.value.trim()) {
                el.classList.add('err');
                ok = false;
                if (!firstErr) firstErr = el;
                el.addEventListener('input', () => el.classList.remove('err'), { once: true });
            }
        });
    }

    if (currentStep === 1 || currentStep === 2) {
        const cards = [...steps[currentStep].querySelectorAll('.q-card')]
            .filter(c => getComputedStyle(c).display !== 'none');

        cards.forEach(card => {
            const radios = card.querySelectorAll('input[type="radio"]');
            if (!radios.length) return;

            const name    = radios[0].name;
            const checked = card.querySelector(`input[name="${name}"]:checked`);
            if (!checked) {
                card.classList.add('has-error');
                ok = false;
                if (!firstErr) firstErr = card;
                radios.forEach(r => r.addEventListener('change', () => card.classList.remove('has-error'), { once: true }));
            }

            if (name === 'cc3') {
                const ta = document.getElementById('cc3_reason');
                if (document.getElementById('cc3_no')?.checked && ta && !ta.value.trim()) {
                    ta.style.borderColor = 'var(--danger)';
                    ok = false;
                    if (!firstErr) firstErr = ta;
                }
            }
        });
    }

    if (!ok) {
        Swal.fire({
            icon: 'warning', title: 'Almost there!',
            text: 'Please answer all highlighted questions before continuing.',
            confirmButtonColor: '#002147', confirmButtonText: 'Got it'
        });
        if (firstErr) setTimeout(() => firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
    }
    return ok;
}

/* ─── CC CONDITIONAL LOGIC ─── */
(function initCC() {
    const cc1r  = document.querySelectorAll('input[name="cc1"]');
    const cc2r  = document.querySelectorAll('input[name="cc2"]');
    const cc3r  = document.querySelectorAll('input[name="cc3"]');
    const qCC2  = document.getElementById('q_cc2');
    const qCC3  = document.getElementById('q_cc3');
    const rWrap = document.getElementById('cc3_reason_wrap');
    const rTA   = document.getElementById('cc3_reason');

    function clearRadios(list) { list.forEach(r => { r.checked = false; }); }
    function markCard(card)    { card.classList.toggle('answered', !!card.querySelector('input:checked')); }

    function checkCC2() {
        const sel   = document.querySelector('input[name="cc2"]:checked');
        const cc1v  = document.querySelector('input[name="cc1"]:checked')?.value;
        const show  = cc1v !== '1' && sel && sel.value !== '1';
        qCC3.style.display = show ? 'block' : 'none';
        if (!show) { clearRadios([...cc3r]); rWrap.style.display='none'; rTA.value=''; rTA.removeAttribute('required'); markCard(qCC3); }
    }

    cc1r.forEach(r => r.addEventListener('change', function() {
        if (this.value === '1') {
            qCC2.style.display = 'none'; qCC3.style.display = 'none';
            clearRadios([...cc2r, ...cc3r]);
            rWrap.style.display = 'none'; rTA.value = ''; rTA.removeAttribute('required');
            markCard(qCC2); markCard(qCC3);
        } else {
            qCC2.style.display = 'block';
            checkCC2();
        }
        markCard(qCC1);
    }));

    cc2r.forEach(r => r.addEventListener('change', function() { checkCC2(); markCard(qCC2); }));

    cc3r.forEach(r => r.addEventListener('change', function() {
        if (this.id === 'cc3_no') { rWrap.style.display = 'block'; rTA.setAttribute('required','true'); }
        else                       { rWrap.style.display = 'none';  rTA.removeAttribute('required'); rTA.value = ''; }
        markCard(qCC3);
    }));

    const qCC1 = document.getElementById('q_cc1');
})();

/* ─── ANSWERED STATE on all radio cards ─── */
document.querySelectorAll('.q-card').forEach(card => {
    card.querySelectorAll('input[type="radio"]').forEach(r => {
        r.addEventListener('change', () => {
            card.classList.add('answered');
            card.classList.remove('has-error');
        });
    });
});

/* ─── CHAR COUNTERS ─── */
function bindCounter(taId, countId, max) {
    const ta = document.getElementById(taId);
    const ct = document.getElementById(countId);
    if (!ta || !ct) return;
    ta.addEventListener('input', () => {
        const len = ta.value.length;
        ct.textContent = `${len} / ${max}`;
        ct.className = 'char-count' + (len >= max ? ' over' : len > max * .85 ? ' warn' : '');
    });
}
bindCounter('cc3_reason',    'cc3Count',        300);
bindCounter('suggestionsTA', 'suggestionsCount', 500);

/* ─── MINI CONFETTI on emoji click ─── */
document.querySelectorAll('.emoji-rating label').forEach(lbl => {
    lbl.addEventListener('click', () => {
        const r = lbl.getBoundingClientRect();
        spawnBurst(r.left + r.width / 2, r.top + r.height / 2, 14);
    });
});

/* ─── SUBMIT ─── */
document.getElementById('surveyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validate()) return;

    Swal.fire({
        title: 'Submitting…',
        html: 'Please wait while we save your response.',
        allowOutsideClick: false, showConfirmButton: false,
        background: '#fff', backdrop: 'rgba(0,33,71,.75)',
        didOpen: () => Swal.showLoading()
    });

    fetch('../functions/submit_feedback.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                const ref = data.control_no || ('DICT-' + new Date().getFullYear() + '-' + String(Math.floor(Math.random()*9000)+1000));
                const now = new Date();
                const fmt = { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' };
                document.getElementById('refNumber').textContent  = ref;
                document.getElementById('submitDate').textContent = now.toLocaleDateString('en-US', fmt);
                currentStep = steps.length - 1;
                showStep(currentStep);
                launchConfetti();
                launchFireworks();
                Swal.fire({
                    icon: 'success', title: '🥳 Submitted!',
                    html: `<p style="color:#333">Your feedback was recorded successfully.</p>
                           <p style="margin-top:8px;font-size:13px;color:#64748b">Ref: <strong>${ref}</strong></p>`,
                    timer: 3500, timerProgressBar: true, showConfirmButton: false,
                    background: '#fff', backdrop: 'rgba(0,33,71,.75)'
                });
            } else {
                Swal.fire({ icon:'error', title:'Submission Failed', text: data.message || 'An error occurred. Please try again.', confirmButtonColor:'#002147' });
            }
        })
        .catch(() => {
            Swal.fire({ icon:'error', title:'Connection Error', text:'Could not reach the server. Please check your connection.', confirmButtonColor:'#002147' });
        });
});

/* ─── CONFETTI & FIREWORKS ─── */
function spawnBurst(x, y, count) {
    const colors = ['#FFD700','#0038A8','#10b981','#f59e0b','#8b5cf6','#ec4899'];
    for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        const c  = colors[Math.floor(Math.random() * colors.length)];
        const sz = 5 + Math.random() * 8;
        el.style.cssText = `position:fixed;width:${sz}px;height:${sz}px;background:${c};border-radius:${Math.random()>.5?'50%':'2px'};z-index:9999;pointer-events:none;left:${x}px;top:${y}px`;
        document.body.appendChild(el);
        const ang = Math.random() * Math.PI * 2, dist = 40 + Math.random() * 80;
        el.animate([
            { transform:'translate(0,0) rotate(0)', opacity:1 },
            { transform:`translate(${Math.cos(ang)*dist}px,${Math.sin(ang)*dist}px) rotate(${Math.random()*360}deg)`, opacity:0 }
        ], { duration: 600 + Math.random() * 400, easing:'ease-out' });
        setTimeout(() => el.remove(), 1100);
    }
}

function launchConfetti() {
    if (!document.getElementById('cKF')) {
        const s = document.createElement('style'); s.id = 'cKF';
        s.textContent = '@keyframes cFall{0%{transform:translateY(-20px) rotate(0);opacity:1}100%{transform:translateY(100vh) rotate(720deg);opacity:0}}';
        document.head.appendChild(s);
    }
    const colors = ['#FFD700','#0038A8','#002147','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4'];
    for (let i = 0; i < 160; i++) {
        setTimeout(() => {
            const el = document.createElement('div');
            const c  = colors[Math.floor(Math.random() * colors.length)];
            const w  = 6 + Math.random() * 10;
            el.style.cssText = `position:fixed;left:${Math.random()*100}vw;top:-15px;width:${w}px;height:${w}px;background:${c};border-radius:${Math.random()>.5?'50%':'2px'};z-index:9999;pointer-events:none;animation:cFall ${2+Math.random()*2}s linear forwards`;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 4500);
        }, i * 13);
    }
}

function launchFireworks() {
    const colors = ['#FFD700','#ff6b6b','#4ecdc4','#45b7d1','#96ceb4','#a855f7'];
    for (let i = 0; i < 6; i++) setTimeout(() => firewerk(colors), i * 280);
}
function firewerk(colors) {
    const x = Math.random() * window.innerWidth;
    const y = Math.random() * window.innerHeight * .5;
    const c = colors[Math.floor(Math.random() * colors.length)];
    for (let j = 0; j < 32; j++) {
        const el  = document.createElement('div');
        const ang = (j / 32) * Math.PI * 2;
        const vel = 90 + Math.random() * 120;
        el.style.cssText = `position:fixed;left:${x}px;top:${y}px;width:7px;height:7px;background:${c};border-radius:50%;z-index:9998;pointer-events:none`;
        document.body.appendChild(el);
        el.animate([
            { left:x+'px', top:y+'px', opacity:1 },
            { left:(x+Math.cos(ang)*vel)+'px', top:(y+Math.sin(ang)*vel)+'px', opacity:0 }
        ], { duration:900, easing:'ease-out' });
        setTimeout(() => el.remove(), 900);
    }
}
</script>
</body>
</html>
