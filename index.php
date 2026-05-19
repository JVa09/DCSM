<?php
include "functions/db_connection.php";

// // Overall average score (across 9 SQD dimensions, each 1-5)
// $avg_row = mysqli_fetch_assoc(mysqli_query($conn,
//     "SELECT AVG((IFNULL(sqd0,0)+IFNULL(sqd1,0)+IFNULL(sqd2,0)+IFNULL(sqd3,0)+
//                  IFNULL(sqd4,0)+IFNULL(sqd5,0)+IFNULL(sqd6,0)+IFNULL(sqd7,0)+IFNULL(sqd8,0))/9.0) AS avg
//      FROM csm_submissions"
// ));
// $average_score = round($avg_row['avg'] ?? 0, 2);

// Total submissions
$total = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS n FROM csm_submissions"
))['n'];

// Submissions this month
$month_total = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS n FROM csm_submissions
     WHERE MONTH(submission_date)=MONTH(CURDATE()) AND YEAR(submission_date)=YEAR(CURDATE())"
))['n'];

// // Count with average score >= 4 (satisfied)
// $sat_count = (int) mysqli_fetch_assoc(mysqli_query($conn,
//     "SELECT COUNT(*) AS n FROM csm_submissions
//      WHERE (IFNULL(sqd0,0)+IFNULL(sqd1,0)+IFNULL(sqd2,0)+IFNULL(sqd3,0)+
//             IFNULL(sqd4,0)+IFNULL(sqd5,0)+IFNULL(sqd6,0)+IFNULL(sqd7,0)+IFNULL(sqd8,0))/9.0 >= 4"
// ))['n'];
// $sat_pct = $total > 0 ? round(($sat_count / $total) * 100) : 0;

// Score per SQD dimension (for breakdown bar)
// $dim_row = mysqli_fetch_assoc(mysqli_query($conn,
//     "SELECT AVG(sqd0) d0,AVG(sqd1) d1,AVG(sqd2) d2,AVG(sqd3) d3,AVG(sqd4) d4,
//             AVG(sqd5) d5,AVG(sqd6) d6,AVG(sqd7) d7,AVG(sqd8) d8
//      FROM csm_submissions"
// ));

// function ratingLabel($s) {
//     if ($s >= 4.5) return ['Outstanding',      '#059669'];
//     if ($s >= 4.0) return ['Very Satisfied',   '#10b981'];
//     if ($s >= 3.5) return ['Satisfied',        '#84cc16'];
//     if ($s >= 3.0) return ['Fair',             '#eab308'];
//     if ($s >  0  ) return ['Needs Improvement','#ef4444'];
//     return                 ['No Data Yet',     '#94a3b8'];
// }
// [$ratingText, $ratingColor] = ratingLabel($average_score);

// function renderStars($r) {
//     $f = floor($r); $h = ($r - $f) >= .5; $e = 5 - $f - ($h?1:0);
//     $o = '<div class="stars">';
//     for ($i=0;$i<$f;$i++)  $o .= '<i class="fas fa-star"></i>';
//     if ($h)                 $o .= '<i class="fas fa-star-half-alt"></i>';
//     for ($i=0;$i<$e;$i++) $o .= '<i class="far fa-star"></i>';
//     return $o . '</div>';
// }

// $dims = [
//     ['Responsiveness', round($dim_row['d0'] ?? 0, 1)],
//     ['Reliability',    round($dim_row['d1'] ?? 0, 1)],
//     ['Access',         round($dim_row['d2'] ?? 0, 1)],
//     ['Communication',  round($dim_row['d3'] ?? 0, 1)],
//     ['Costs',          round($dim_row['d4'] ?? 0, 1)],
//     ['Integrity',      round($dim_row['d5'] ?? 0, 1)],
//     ['Assurance',      round($dim_row['d6'] ?? 0, 1)],
//     ['Outcome',        round($dim_row['d7'] ?? 0, 1)],
// ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DICT | Digital CSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --navy:    #002147;
            --blue:    #0038A8;
            --gold:    #D4AF37;
            --white:   #ffffff;
            --bg:      #f1f5f9;
            --surface: #ffffff;
            --border:  #e2e8f0;
            --text:    #0f172a;
            --muted:   #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger:  #ef4444;
            --info:    #3b82f6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', -apple-system, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .tab-content { 
    display: none; 
    padding-bottom: 40px; /* Reduced from 70px to keep it tighter */
    min-height: 60vh;    /* Optional: ensures footer doesn't jump on short pages */
}
        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, #001233 0%, var(--navy) 40%, #003580 70%, #004ec4 100%);
            padding: 90px 20px 130px; text-align: center; color: white;
            position: relative; overflow: hidden; border-bottom: 5px solid var(--gold);
        }
        .hero::before {
            content: ''; position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .hero-glow {
            position: absolute; top: -100px; left: 50%; transform: translateX(-50%);
            width: 800px; height: 500px; border-radius: 50%;
            background: radial-gradient(ellipse, rgba(0,78,196,.4) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero .container { position: relative; z-index: 1; }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(212,175,55,.18); border: 1px solid rgba(212,175,55,.4);
            color: var(--gold); padding: 6px 18px; border-radius: 50px;
            font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
            margin-bottom: 24px;
        }
        .hero h2 {
            font-size: clamp(26px, 5vw, 46px); font-weight: 800;
            line-height: 1.15; letter-spacing: -1px; margin-bottom: 18px;
            text-shadow: 0 2px 30px rgba(0,0,0,.3);
        }
        .hero h2 span { color: var(--gold); }
        .hero p { max-width: 600px; margin: 0 auto 36px; font-size: 16px; line-height: 1.7; opacity: .88; }
        .hero-cta { display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; margin-bottom: 52px; }
        .btn-survey {
            background: var(--gold); color: var(--navy);
            padding: 16px 40px; border-radius: 10px;
            font-weight: 800; font-size: 15px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 9px;
            transition: all .3s ease; letter-spacing: .3px;
            box-shadow: 0 8px 28px rgba(212,175,55,.4);
        }
        .btn-survey:hover { background: white; transform: translateY(-3px); box-shadow: 0 12px 36px rgba(0,0,0,.25); }
        .btn-outline {
            border: 2px solid rgba(255,255,255,.4); color: white;
            padding: 14px 28px; border-radius: 10px;
            font-weight: 600; font-size: 15px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all .3s ease;
        }
        .btn-outline:hover { border-color: white; background: rgba(255,255,255,.1); }
        .hero-kpis { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; }
        .hero-kpi {
            background: rgba(255,255,255,.1); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.18); border-radius: 14px;
            padding: 16px 28px; min-width: 160px; text-align: center;
            transition: all .3s ease;
        }
        .hero-kpi:hover { background: rgba(255,255,255,.15); transform: translateY(-4px); }
        .hero-kpi strong { display: block; font-size: 26px; font-weight: 800; color: var(--gold); line-height: 1.1; }
        .hero-kpi span   { font-size: 12px; opacity: .8; font-weight: 500; margin-top: 4px; display: block; }

        /* ── CONTAINER ── */
        .container { max-width: 1140px; margin: 0 auto; padding: 0 24px; }

        /* ── CSAT OVERVIEW CARD ── */
        .csat-card {
            background: var(--surface); border-radius: 22px;
            box-shadow: 0 24px 60px rgba(0,33,71,.12);
            margin: -55px auto 60px; position: relative; z-index: 5;
            display: grid; grid-template-columns: 320px 1fr; overflow: hidden;
        }
        .csat-left {
            background: linear-gradient(160deg, var(--navy) 0%, var(--blue) 100%);
            color: white; padding: 44px 40px; text-align: center;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
        }
        .csat-left .score-label { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; opacity: .7; margin-bottom: 4px; }
        .csat-left .score-num   { font-size: 72px; font-weight: 900; line-height: 1; color: var(--gold); }
        .csat-left .score-denom { font-size: 22px; opacity: .6; font-weight: 600; }
        .csat-left .stars { font-size: 22px; color: var(--gold); margin: 6px 0; letter-spacing: 3px; }
        .csat-left .rating-badge {
            padding: 5px 16px; border-radius: 50px; font-size: 12px; font-weight: 700;
            margin-top: 6px; letter-spacing: .5px;
        }
        .csat-left .reviews-note { font-size: 13px; opacity: .7; margin-top: 4px; }
        .csat-right { padding: 36px 40px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .kpi-card {
            background: #f8fafc; border-radius: 14px; padding: 18px 16px;
            text-align: center; border: 1px solid var(--border); transition: all .3s ease;
        }
        .kpi-card:hover { border-color: var(--blue); box-shadow: 0 6px 20px rgba(0,56,168,.1); transform: translateY(-2px); }
        .kpi-icon { font-size: 22px; margin-bottom: 8px; }
        .kpi-val  { font-size: 26px; font-weight: 800; color: var(--navy); line-height: 1.1; }
        .kpi-lbl  { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .6px; margin-top: 4px; }
        .dim-section h4 { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }
        .dim-bars { display: flex; flex-direction: column; gap: 9px; }
        .dim-row { display: flex; align-items: center; gap: 10px; }
        .dim-name { font-size: 12px; font-weight: 600; color: var(--text); min-width: 110px; }
        .dim-track { flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .dim-fill  { height: 100%; background: linear-gradient(90deg, var(--blue), #4f86f7); border-radius: 4px; transition: width 1.2s cubic-bezier(.4,0,.2,1); }
        .dim-score { font-size: 12px; font-weight: 700; color: var(--navy); min-width: 28px; text-align: right; }

        /* ── SECTION HEADER ── */
        .section-hdr { text-align: center; margin: 60px 0 40px; }
        .section-hdr h2 { font-size: 30px; color: var(--navy); font-weight: 800; margin-bottom: 8px; }
        .section-hdr p  { color: var(--muted); font-size: 16px; max-width: 520px; margin: 0 auto 16px; }
        .section-hdr .line { width: 50px; height: 4px; background: var(--gold); border-radius: 2px; margin: 0 auto; }

        /* ── CARDS ── */
        .card {
            background: var(--surface); padding: 28px; border-radius: 16px;
            border: 1px solid var(--border); transition: all .3s ease;
        }
        .card:hover { border-color: rgba(0,56,168,.3); box-shadow: 0 12px 36px rgba(0,33,71,.1); transform: translateY(-4px); }
        .card h3 { color: var(--navy); font-size: 18px; font-weight: 700; margin-bottom: 12px; }
        .card p  { color: var(--muted); line-height: 1.7; font-size: 14px; }
        .card-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(440px, 1fr)); gap: 20px; margin-bottom: 28px; }
        .card-grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 28px; }
        .card-grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 28px; }

        /* ── ICON BOX ── */
        .icon-box {
            width: 52px; height: 52px; border-radius: 14px;
            background: linear-gradient(135deg, var(--blue), var(--navy));
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 22px; margin-bottom: 16px;
            box-shadow: 0 6px 18px rgba(0,56,168,.3);
        }

        /* ── BADGE ── */
        .badge {
            display: inline-block; font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .8px;
            padding: 3px 10px; border-radius: 50px; margin-bottom: 10px;
            background: rgba(0,56,168,.1); color: var(--blue);
        }
        .badge.gold   { background: rgba(212,175,55,.15); color: #92740a; }
        .badge.green  { background: rgba(16,185,129,.12); color: #059669; }
        .badge.purple { background: rgba(139,92,246,.12); color: #7c3aed; }
        .badge.red    { background: rgba(239,68,68,.1);   color: #dc2626; }
        .badge.teal   { background: rgba(20,184,166,.12); color: #0d9488; }

        /* ── MANDATE CARD ── */
        .mandate-card {
            background: linear-gradient(135deg, var(--navy) 0%, #003580 100%);
            color: white; border-radius: 16px; padding: 36px 40px; margin-bottom: 24px;
            position: relative; overflow: hidden;
        }
        .mandate-card::after { content:''; position:absolute; top:-30px; right:-30px; width:140px; height:140px; border-radius:50%; background:rgba(255,255,255,.05); }
        .mandate-card h3 { font-size: 20px; font-weight: 800; margin-bottom: 12px; color: var(--gold); }
        .mandate-card p  { line-height: 1.8; opacity: .9; font-size: 14px; }
        .values-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-top: 20px; }
        .value-item {
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15);
            border-radius: 12px; padding: 16px 18px; text-align: center;
        }
        .value-item .vi-letter { font-size: 28px; font-weight: 900; color: var(--gold); display: block; line-height: 1; }
        .value-item .vi-word   { font-size: 13px; font-weight: 700; letter-spacing: .5px; margin-top: 4px; }

        /* ── CONTACT ── */
        .contact-hq {
            background: linear-gradient(135deg, var(--blue) 0%, var(--navy) 100%);
            color: white; border-radius: 16px; padding: 32px 36px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 28px; flex-wrap: wrap;
        }
        .contact-hq .hq-icon { font-size: 40px; flex-shrink: 0; }
        .contact-hq h3 { font-size: 20px; font-weight: 800; color: var(--gold); margin-bottom: 6px; }
        .contact-hq p  { opacity: .88; font-size: 14px; line-height: 1.7; margin: 0; }
        .contact-card { display: flex; flex-direction: column; gap: 10px; }
        .contact-card .cc-badge { align-self: flex-start; }
        .contact-card .cc-addr { display: flex; align-items: flex-start; gap: 9px; color: var(--muted); font-size: 13px; line-height: 1.5; }
        .contact-card .cc-addr i { color: var(--blue); margin-top: 2px; flex-shrink: 0; }
        .contact-card .cc-info { display: flex; flex-direction: column; gap: 4px; }
        .contact-card .cc-info a { font-size: 13px; color: var(--muted); text-decoration: none; display: flex; align-items: center; gap: 7px; }
        .contact-card .cc-info a:hover { color: var(--blue); }

        /* ── TABS ── */
        .tab-content { display: none; padding-bottom: 70px; }
        .tab-content.active { display: block; animation: fadeUp .45s cubic-bezier(.22,1,.36,1); }
        @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

        /* ── FOOTER ── */
        footer {
            background: linear-gradient(135deg, #001233 0%, var(--navy) 100%);
            color: white; padding: 50px 20px 30px;
            border-top: 4px solid var(--gold);
        }
        .footer-inner { max-width: 1140px; margin: 0 auto; display: grid; grid-template-columns: 1fr auto; gap: 40px; align-items: center; }
        .footer-brand { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
        .footer-brand-name { font-size: 15px; font-weight: 800; color: var(--gold); }
        .footer-brand-sub  { font-size: 12px; opacity: .7; margin-top: 2px; }
        .footer-copy { font-size: 12px; opacity: .6; line-height: 1.7; }
        .footer-links { display: flex; flex-direction: column; gap: 10px; text-align: right; }
        .footer-links a { font-size: 13px; color: rgba(255,255,255,.7); text-decoration: none; transition: color .2s; }
        .footer-links a:hover { color: var(--gold); }

        /* ── SCROLL REVEAL ── */
        .reveal { opacity: 0; transform: translateY(24px); transition: opacity .6s ease, transform .6s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-delay-1 { transition-delay: .1s; }
        .reveal-delay-2 { transition-delay: .2s; }
        .reveal-delay-3 { transition-delay: .3s; }

        /* ── RESPONSIVE ── */
        @media(max-width:900px) {
            .csat-card { grid-template-columns: 1fr; }
            .csat-left { padding: 36px 28px; }
            .kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media(max-width:640px) {
            .hero { padding: 70px 16px 110px; }
            .csat-card { margin: -40px 12px 44px; }
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .card-grid-2, .card-grid-3, .card-grid-4 { grid-template-columns: 1fr; }
            .footer-inner { grid-template-columns: 1fr; }
            .footer-links { text-align: left; }
            .hero-kpi { min-width: 130px; }
        }
    </style>
</head>
<body>

<?php include 'components/header.html'; ?>

<!-- ══════════════ HOME ══════════════ -->
<div id="home" class="tab-content active">

    <section class="hero">
        <div class="hero-glow"></div>
        <div class="container">
            <div class="hero-eyebrow">
                <i class="fas fa-shield-check"></i> ARTA Citizen Charter Compliance
            </div>
            <h2>Digital <span>Client Satisfaction</span><br>Measurement System</h2>
            <p>Your feedback shapes better public service delivery for every Filipino. Help us improve by sharing your experience with DICT.</p>
            <div class="hero-cta">
                <a href="pages/feedback.php" class="btn-survey">
                    <i class="fas fa-edit"></i> Take the Survey
                </a>
                <a href="javascript:void(0)" onclick="switchTab(null,'about')" class="btn-outline">
                    <i class="fas fa-info-circle"></i> Learn More
                </a>
<a href="pages/form.php" class="btn-survey" target="_blank">
    <i class="fas fa-file-download"></i> Download form
</a>
            </div>
            <div class="hero-kpis">
                <div class="hero-kpi">
                    <strong><?= number_format($total) ?></strong>
                    <span>Total Responses Received</span>
                </div>
                <div class="hero-kpi">
                    <strong><?= number_format($month_total) ?></strong>
                    <span>Contributions This Month</span>
                </div>
                <div class="hero-kpi">
                    <strong style="color: var(--gold);"><i class="fas fa-check-circle"></i></strong>
                    <span>ARTA Compliant System</span>
                </div>
            </div>
        </div>
    </section>

    <div class="container">

        <!-- CSAT OVERVIEW -->
        <div class="csat-card reveal">
            <div class="csat-left">
                <div class="icon-box" style="width:70px; height:70px; font-size:32px; background:var(--gold); color:var(--navy); margin-bottom:20px;">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <h3 style="color:var(--gold); font-size:24px;">Your Voice Matters</h3>
                <p style="font-size:14px; opacity:0.9; line-height:1.6;">We use your honest feedback to evaluate our performance and implement necessary service improvements.</p>
            </div>
            <div class="csat-right">
                <div class="dim-section">
                    <h4><i class="fas fa-info-circle" style="margin-right:6px;color:var(--blue)"></i> How we measure satisfaction</h4>
                    <p style="margin-bottom:20px; font-size:14px; color:var(--muted);">In compliance with ARTA guidelines, we evaluate our services across 8 Service Quality Dimensions:</p>
                    
                    <div class="card-grid-3" style="grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom:0;">
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Responsiveness</div>
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Reliability</div>
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Access & Facilities</div>
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Communication</div>
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Costs & Fees</div>
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Integrity</div>
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Assurance</div>
                        <div style="font-size:13px; color:var(--navy); font-weight:600;"><i class="fas fa-check text-success"></i> Outcome</div>
                    </div>
                </div>
            </div>
        </div>

            </div>


<!-- ══════════════ ABOUT ══════════════ -->
<div id="about" class="tab-content">
    <div class="container">
        <div class="section-hdr reveal">
            <h2>About DICT</h2>
            <p>The primary ICT policy and planning body of the Philippine government.</p>
            <div class="line"></div>
        </div>

        <div class="mandate-card reveal">
            <h3><i class="fas fa-landmark" style="margin-right:10px"></i>Our Mandate</h3>
            <p>The <strong>Department of Information and Communications Technology (DICT)</strong> is the primary policy, planning, coordinating, implementing, and administrative entity of the Executive Branch of the government that will plan, develop, and promote the national ICT development agenda <em>(Republic Act No. 10844)</em>.</p>
            <div class="values-grid" style="margin-top:28px">
                <div class="value-item"><span class="vi-letter">D</span><div class="vi-word">DIGNITY</div></div>
                <div class="value-item"><span class="vi-letter">I</span><div class="vi-word">INTEGRITY</div></div>
                <div class="value-item"><span class="vi-letter">C</span><div class="vi-word">COMPETENCY & COMPASSION</div></div>
                <div class="value-item"><span class="vi-letter">T</span><div class="vi-word">TRANSPARENCY</div></div>
            </div>
        </div>

        <div class="card-grid-2">
            <div class="card reveal reveal-delay-1">
                <div class="icon-box"><i class="fas fa-eye"></i></div>
                <h3>Vision</h3>
                <p style="font-style:italic;color:#1e40af;font-weight:500;margin-bottom:12px">"An innovative, safe and happy nation that thrives through and is enabled by Information and Communications Technology."</p>
                <p>DICT aspires for the Philippines to develop and flourish through innovation and constant development of ICT in the pursuit of a progressive, safe, secured, contented and happy Filipino nation.</p>
            </div>
            <div class="card reveal reveal-delay-2">
                <div class="icon-box"><i class="fas fa-bullseye"></i></div>
                <h3>Mission</h3>
                <p style="font-weight:700;color:var(--navy);margin-bottom:12px">"DICT OF THE PEOPLE AND FOR THE PEOPLE."</p>
                <ul style="padding-left:18px;color:var(--muted);font-size:14px;line-height:2">
                    <li>Provide every Filipino access to vital ICT infostructure and services.</li>
                    <li>Ensure sustainable growth of ICT-enabled industries and job creation.</li>
                    <li>Establish a One Digitized Government, One Nation.</li>
                    <li>Support the administration in fully achieving its goals.</li>
                    <li>Lead the transition towards a world-class digital economy.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════ SERVICES ══════════════ -->
<div id="services" class="tab-content">
    <div class="container">
        <div class="section-hdr reveal">
            <h2>Frontline Public Services</h2>
            <p>Official technical services provided to citizens and government agencies.</p>
            <div class="line"></div>
        </div>
        <div class="card-grid-3">
            <div class="card reveal reveal-delay-1">
                <span class="badge gold">Security</span>
                <div class="icon-box"><i class="fas fa-file-signature"></i></div>
                <h3>PNPKI</h3>
                <p>Digital certificates that allow users to exchange data securely and sign documents electronically with full legal validity under Philippine law.</p>
            </div>
            <div class="card reveal reveal-delay-2">
                <span class="badge">Infrastructure</span>
                <div class="icon-box"><i class="fas fa-server"></i></div>
                <h3>GovCloud</h3>
                <p>A centralized cloud-hosting service for government agencies to ensure data sovereignty, security compliance, and reduced operational costs.</p>
            </div>
            <div class="card reveal reveal-delay-3">
                <span class="badge red">Protection</span>
                <div class="icon-box" style="background:linear-gradient(135deg,#dc2626,#991b1b)"><i class="fas fa-shield-halved"></i></div>
                <h3>Cyber Response</h3>
                <p>Emergency technical assistance for government agencies facing data breaches or active cyber attacks, provided by NCERT specialists.</p>
            </div>
            <div class="card reveal">
                <span class="badge green">Connectivity</span>
                <div class="icon-box" style="background:linear-gradient(135deg,#059669,#065f46)"><i class="fas fa-wifi"></i></div>
                <h3>Free Wi-Fi for All</h3>
                <p>Establishing public internet access points across the archipelago to bridge the digital divide and ensure connectivity for every Filipino.</p>
            </div>
            <div class="card reveal reveal-delay-1">
                <span class="badge">E-Governance</span>
                <div class="icon-box"><i class="fas fa-mobile-screen"></i></div>
                <h3>eGovPH Super App</h3>
                <p>A unified platform integrating all government services — from national IDs to business permits — into a single, easy-to-use application.</p>
            </div>
            <div class="card reveal reveal-delay-2">
                <span class="badge purple">Training</span>
                <div class="icon-box" style="background:linear-gradient(135deg,#7c3aed,#5b21b6)"><i class="fas fa-graduation-cap"></i></div>
                <h3>ILCDB / Tech4ED</h3>
                <p>ICT Literacy and Competency Development Bureau conducts specialized trainings to upskill the Filipino workforce in the digital economy.</p>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════ PROGRAMS ══════════════ -->
<div id="programs" class="tab-content">
    <div class="container">
        <div class="section-hdr reveal">
            <h2>Programs &amp; Projects</h2>
            <p>National initiatives driving the country's digital transformation and inclusive growth.</p>
            <div class="line"></div>
        </div>
        <div class="card-grid-4">
            <?php
            $programs = [
                ['GECS',           'Disaster Resiliency','fa-broadcast-tower','The Government Emergency Communications System provides mobile connectivity units for rapid deployment during disasters and national emergencies.','teal'],
                ['GovNet',         'Connectivity',       'fa-network-wired',  'The Government Network interconnects agencies to enable faster data sharing and more efficient public service delivery across the country.',''],
                ['eLGU',           'E-Governance',       'fa-landmark',       'A flagship project under the eGovPH stack that automates local government processes like business permits and real property taxes.',''],
                ['ILCDB',          'Capacity Building',  'fa-brain',          'The ICT Literacy and Competency Development Bureau conducts specialized trainings to upskill the Filipino workforce.','purple'],
                ['eGovPH Super App','E-Governance',      'fa-mobile-alt',     'A unified platform integrating government services — IDs, permits, benefits — into a single, easy-to-use mobile application.',''],
                ['DTC / Tech4ED',  'Digital Literacy',   'fa-laptop-code',    'Digital Transformation Centers serve as hubs providing ICT access and digital skills training to underserved communities.','green'],
                ['SPARK',          'Innovation',         'fa-lightbulb',      'The Startup Production and Development Program fuels the local tech ecosystem by supporting digital startups and entrepreneurship.','gold'],
                ['IIDB',           'Industry Growth',    'fa-chart-line',     'The ICT Industry Development Bureau promotes growth of the IT-BPM sector and development of Digital Cities across the Philippines.',''],
                ['NCERT',          'Cybersecurity',      'fa-user-shield',    'The National Computer Emergency Response Team ensures protection of critical infostructure against evolving cyber threats.','red'],
                ['PNPKI',          'Security',           'fa-key',            'The Philippine National Public Key Infrastructure provides digital certificates for secure and legally binding electronic transactions.','gold'],
                ['NIPPSB',         'Policy',             'fa-file-contract',  'Formulates the strategic roadmap and technical standards for the national ICT agenda to ensure uniform digital adoption.',''],
                ['Free Wi-Fi',     'Connectivity',       'fa-wifi',           'Establishing internet access points in public spaces across the archipelago to bridge the digital divide for every Filipino.','green'],
            ];
            foreach($programs as $i => [$name,$cat,$icon,$desc,$badgeClass]):
            $delay = ($i % 3 === 0) ? '' : ($i % 3 === 1 ? ' reveal-delay-1' : ' reveal-delay-2');
            ?>
            <div class="card reveal<?= $delay ?>">
                <span class="badge <?= $badgeClass ?>"><?= $cat ?></span>
                <div class="icon-box"><i class="fas <?= $icon ?>"></i></div>
                <h3><?= $name ?></h3>
                <p><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════ CONTACTS ══════════════ -->
<div id="contacts" class="tab-content">
    <div class="container">
        <div class="section-hdr reveal">
            <h2>Connect With Us</h2>
            <p>Main headquarters and regional office contact information.</p>
            <div class="line"></div>
        </div>

        <div class="contact-hq reveal">
            <div class="hq-icon"><i class="fas fa-building-columns"></i></div>
            <div>
                <h3>Central Office — Headquarters</h3>
                <p>DICT Building, C.P. Garcia Avenue, Diliman, Quezon City 1101</p>
                <p style="margin-top:6px">
                    <i class="fas fa-envelope" style="color:var(--gold);margin-right:6px"></i>info@dict.gov.ph
                    &nbsp;&nbsp;
                    <i class="fas fa-phone" style="color:var(--gold);margin-right:6px"></i>(02) 8920-0101
                </p>
            </div>
        </div>

        <div class="section-hdr" style="margin-top:10px;margin-bottom:28px">
            <h2 style="font-size:22px">Regional Offices</h2>
            <div class="line"></div>
        </div>

        <?php
        $offices = [
            ['Luzon Cluster 1',   'Region I, II, CAR',   'DICT Bldg., Diversion Road, Brgy. Pengue-Ruyu, Tuguegarao City, Cagayan', '(078) 377-1011','lc1@dict.gov.ph'],
            ['Luzon Cluster 2',   'Region III',           'DICT Bldg., Capitol Compound, City of San Fernando, Pampanga',             '(045) 455-1012','lc2@dict.gov.ph'],
            ['Luzon Cluster 3',   'Region IV-A, IV-B',   '2/F Hernandez Bldg., Brgy. Marawoy, Lipa City, Batangas',                 '(043) 740-1013','lc3@dict.gov.ph'],
            ['Luzon Cluster 4',   'Region V',             'DICT Bldg., Legazpi City, Albay',                                         '(052) 201-1014','lc4@dict.gov.ph'],
            ['Visayas Cluster 1', 'Region VI, VII, VIII', 'DICT Bldg., Iloilo City, Iloilo',                                         '(033) 337-1015','vc1@dict.gov.ph'],
            ['Visayas Cluster 2', 'Region VII, VIII',     'DICT Bldg., Wireless, Mandaue City, Cebu',                                '(032) 231-1016','vc2@dict.gov.ph'],
            ['Mindanao Cluster 1','Region IX, X, BASULTA','DICT Bldg., Zamboanga City, Zamboanga del Sur',                           '(062) 991-1017','mc1@dict.gov.ph'],
            ['Mindanao Cluster 2','Region X, XII, XIII',  'DICT Bldg., Cagayan de Oro City, Misamis Oriental',                       '(088) 857-1018','mc2@dict.gov.ph'],
            ['Mindanao Cluster 3','Region XI, XII',        'DICT Bldg., F. Torres St., Davao City',                                  '(082) 221-1019','mc3@dict.gov.ph'],
        ];
        ?>
        <div class="card-grid-3">
        <?php foreach($offices as $i => [$cluster,$region,$addr,$phone,$email]):
            $delay = ($i % 3 === 0) ? '' : ($i % 3 === 1 ? ' reveal-delay-1' : ' reveal-delay-2');
        ?>
            <div class="card contact-card reveal<?= $delay ?>">
                <span class="badge cc-badge"><?= $region ?></span>
                <h3 style="margin-bottom:12px"><?= $cluster ?></h3>
                <div class="cc-addr"><i class="fas fa-map-marker-alt"></i> <?= $addr ?></div>
                <div class="cc-info" style="margin-top:8px">
                    <a href="tel:<?= preg_replace('/[^0-9+]/','',$phone) ?>"><i class="fas fa-phone" style="color:var(--blue)"></i><?= $phone ?></a>
                    <a href="mailto:<?= $email ?>"><i class="fas fa-envelope" style="color:var(--blue)"></i><?= $email ?></a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════ FOOTER ══════════════ -->
<footer>
    <div class="footer-inner">
        <div>
            <div class="footer-brand">
                <img src="assets/img/livelogo.gif" alt="DICT" style="height:44px;">
                <div>
                    <div class="footer-brand-name">DICT Philippines</div>
                    <div class="footer-brand-sub">Department of Information and Communications Technology</div>
                </div>
            </div>
            <p class="footer-copy">© <?= date('Y') ?> Republic of the Philippines. All Rights Reserved.<br>
            DICT Building, C.P. Garcia Avenue, Diliman, Quezon City 1101</p>
        </div>
        <div class="footer-links">
            <a href="javascript:void(0)" onclick="switchTab(null,'home')"><i class="fas fa-home" style="margin-right:6px"></i>Home</a>
            <a href="javascript:void(0)" onclick="switchTab(null,'about')"><i class="fas fa-info-circle" style="margin-right:6px"></i>About</a>
            <a href="javascript:void(0)" onclick="switchTab(null,'services')"><i class="fas fa-cogs" style="margin-right:6px"></i>Services</a>
            <a href="javascript:void(0)" onclick="switchTab(null,'programs')"><i class="fas fa-sitemap" style="margin-right:6px"></i>Programs</a>
            <a href="javascript:void(0)" onclick="switchTab(null,'contacts')"><i class="fas fa-phone" style="margin-right:6px"></i>Contact</a>
        </div>
    </div>
</footer>

<script>
/* ── TAB SWITCHING ── */
function switchTab(event, tabId) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');
    if (event && event.currentTarget) event.currentTarget.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── SCROLL REVEAL ── */
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => { 
        if (e.isIntersecting) { 
            e.target.classList.add('visible'); 
            revealObserver.unobserve(e.target); 
        } 
    });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
</script>

</body>
</html>
