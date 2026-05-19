<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "../../functions/db_connection.php";

/* === AUTHENTICATION CHECK === */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) { header("Location: ../../pages/login.php"); exit(); }
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') { header("Location: ../../unauthorized.php"); exit(); }
if (!isset($_SESSION['username'])) $_SESSION['username'] = "Admin";

/* === DATA SCOPING & FILTERS === */
$base_filter_simple = "1=1";
$base_filter_alias  = "1=1";
$admin_region_name  = "All Regions (National)";
$is_superadmin      = ($_SESSION['role'] === 'superadmin');

if (!$is_superadmin && !empty($_SESSION['region'])) {
    $admin_region      = $conn->real_escape_string($_SESSION['region']);
    $admin_region_name = htmlspecialchars($_SESSION['region']);
    $reg_check = $conn->query("SELECT id FROM regions WHERE region_name = '$admin_region' LIMIT 1");
    if ($reg_check && $reg_check->num_rows > 0) {
        $reg_id = $reg_check->fetch_assoc()['id'];
        $base_filter_simple = "region_id = $reg_id";
        $base_filter_alias  = "cs.region_id = $reg_id";
    } else {
        $base_filter_simple = "0=1";
        $base_filter_alias  = "0=1";
    }
}

$where_simple = [$base_filter_simple];
$where_alias  = [$base_filter_alias];

$f_start   = $_GET['start_date']  ?? '';
$f_end     = $_GET['end_date']    ?? '';
$f_service = $_GET['service_id']  ?? '';
$f_type    = $_GET['client_type'] ?? '';
$f_region  = $_GET['region_id']   ?? '';

if (!empty($f_start))   { $cs = $conn->real_escape_string($f_start); $where_simple[] = "DATE(created_at) >= '$cs'"; $where_alias[] = "DATE(cs.created_at) >= '$cs'"; }
if (!empty($f_end))     { $ce = $conn->real_escape_string($f_end);   $where_simple[] = "DATE(created_at) <= '$ce'"; $where_alias[] = "DATE(cs.created_at) <= '$ce'"; }
if (!empty($f_service)) { $cv = (int)$f_service; $where_simple[] = "service_id = $cv"; $where_alias[] = "cs.service_id = $cv"; }
if (!empty($f_type))    { $ct = $conn->real_escape_string($f_type);  $where_simple[] = "client_type = '$ct'"; $where_alias[] = "cs.client_type = '$ct'"; }
if ($is_superadmin && !empty($f_region)) { $cr = (int)$f_region; $where_simple[] = "region_id = $cr"; $where_alias[] = "cs.region_id = $cr"; }

$filter_simple = implode(" AND ", $where_simple);
$filter_alias  = implode(" AND ", $where_alias);

$all_regions       = $conn->query("SELECT id, region_name FROM regions ORDER BY region_name ASC");
$services_dropdown = $conn->query("SELECT id, service_name FROM services WHERE is_active = 1");

/* ═══════════════════════════════════════════
   JSON EXPORT ENDPOINT (for ExcelJS)
═══════════════════════════════════════════ */
if (isset($_GET['export']) && $_GET['export'] === 'json_data') {
    header('Content-Type: application/json');

    if (!function_exists('getPercent')) {
        function getPercent($count, $total) {
            return $total > 0 ? number_format(($count / $total) * 100, 2) . '%' : '0.00%';
        }
    }

    $sqd_labels = [1=>'Responsiveness',2=>'Reliability',3=>'Access and Facilities',4=>'Communication',5=>'Costs',6=>'Integrity',7=>'Assurance',8=>'Outcome'];

    $reports_to_generate = [['title'=>'OVERALL SUMMARY REPORT','tab_name'=>'Summary Report','filter'=>$filter_simple]];

    if (empty($f_service)) {
        $svc_q = $conn->query("SELECT id, service_name FROM services WHERE is_active = 1 ORDER BY service_name ASC");
        if ($svc_q) {
            while ($s = $svc_q->fetch_assoc()) {
                $tab = substr(str_replace(['/','\\',' ?','*',':','[',']'], '', $s['service_name']), 0, 31);
                $reports_to_generate[] = ['title'=>strtoupper($s['service_name']).' REPORT','tab_name'=>$tab,'filter'=>$filter_simple." AND cs.service_id=".$s['id']];
            }
        }
    }

    function gatherReportData($conn, $curr_filter) {
        $f = str_replace('cs.', '', $curr_filter);
        $data = [];
        $data['total_responses'] = $conn->query("SELECT COUNT(id) as t FROM csm_submissions WHERE $f")->fetch_assoc()['t'] ?? 0;
        if (!$data['total_responses']) return $data;

        $data['age_groups'] = ['19 or lower'=>0,'20-34'=>0,'35-49'=>0,'50-64'=>0,'65 or higher'=>0,'Did not specify'=>0];
        $ar = $conn->query("SELECT CASE WHEN age<=19 THEN '19 or lower' WHEN age BETWEEN 20 AND 34 THEN '20-34' WHEN age BETWEEN 35 AND 49 THEN '35-49' WHEN age BETWEEN 50 AND 64 THEN '50-64' WHEN age>=65 THEN '65 or higher' ELSE 'Did not specify' END as ag, COUNT(*) as c FROM csm_submissions WHERE $f GROUP BY ag");
        while ($r = $ar->fetch_assoc()) $data['age_groups'][$r['ag']] = (int)$r['c'];

        $data['type_groups'] = [];
        $tr = $conn->query("SELECT IFNULL(client_type,'Did not specify') as ct, COUNT(*) as c FROM csm_submissions WHERE $f GROUP BY ct");
        while ($r = $tr->fetch_assoc()) $data['type_groups'][$r['ct'] ?: 'Did not specify'] = (int)$r['c'];

        $data['sex_groups'] = [];
        $sr = $conn->query("SELECT IFNULL(sex,'Did not specify') as g, COUNT(*) as c FROM csm_submissions WHERE $f GROUP BY g");
        while ($r = $sr->fetch_assoc()) $data['sex_groups'][$r['g'] ?: 'Did not specify'] = (int)$r['c'];

        $cc = ['cc1'=>[1=>['p'=>0,'o'=>0],2=>['p'=>0,'o'=>0],3=>['p'=>0,'o'=>0],4=>['p'=>0,'o'=>0],'na'=>['p'=>0,'o'=>0]],'cc2'=>[1=>['p'=>0,'o'=>0],2=>['p'=>0,'o'=>0],3=>['p'=>0,'o'=>0],4=>['p'=>0,'o'=>0],5=>['p'=>0,'o'=>0],'na'=>['p'=>0,'o'=>0]],'cc3'=>[1=>['p'=>0,'o'=>0],2=>['p'=>0,'o'=>0],3=>['p'=>0,'o'=>0],4=>['p'=>0,'o'=>0],'na'=>['p'=>0,'o'=>0]]];
        $cr = $conn->query("SELECT cc1,cc2,cc3,IFNULL(submission_source,'Online') as src FROM csm_submissions WHERE $f");
        if ($cr) { while($row=$cr->fetch_assoc()){ $s=($row['src']==='Physical')?'p':'o'; $v1=(int)$row['cc1']; if($v1>=1&&$v1<=4)$cc['cc1'][$v1][$s]++;else$cc['cc1']['na'][$s]++; $v2=(int)$row['cc2']; if($v2>=1&&$v2<=5)$cc['cc2'][$v2][$s]++;else$cc['cc2']['na'][$s]++; $v3=(int)$row['cc3']; if($v3>=1&&$v3<=4)$cc['cc3'][$v3][$s]++;else$cc['cc3']['na'][$s]++; } }
        $data['cc_counts']=$cc;
        $data['tot_cc1_p']=0;$data['tot_cc1_o']=0;for($i=1;$i<=4;$i++){$data['tot_cc1_p']+=$cc['cc1'][$i]['p'];$data['tot_cc1_o']+=$cc['cc1'][$i]['o'];}$data['tot_cc1']=$data['tot_cc1_p']+$data['tot_cc1_o'];
        $data['tot_cc2_p']=0;$data['tot_cc2_o']=0;for($i=1;$i<=4;$i++){$data['tot_cc2_p']+=$cc['cc2'][$i]['p'];$data['tot_cc2_o']+=$cc['cc2'][$i]['o'];}$data['tot_cc2']=$data['tot_cc2_p']+$data['tot_cc2_o'];
        $data['tot_cc3_p']=0;$data['tot_cc3_o']=0;for($i=1;$i<=3;$i++){$data['tot_cc3_p']+=$cc['cc3'][$i]['p'];$data['tot_cc3_o']+=$cc['cc3'][$i]['o'];}$data['tot_cc3']=$data['tot_cc3_p']+$data['tot_cc3_o'];

        $sqd = [0=>[5=>0,4=>0,3=>0,2=>0,1=>0,'na'=>0]] + array_fill(1,8,[5=>0,4=>0,3=>0,2=>0,1=>0,'na'=>0]);
        $sq = $conn->query("SELECT sqd0,sqd1,sqd2,sqd3,sqd4,sqd5,sqd6,sqd7,sqd8,IFNULL(submission_source,'Online') as src FROM csm_submissions WHERE $f");
        if($sq){while($row=$sq->fetch_assoc()){
            if($row['src']==='Physical'){$v=(int)$row['sqd0'];if($v>=1&&$v<=5)$sqd[0][$v]++;else$sqd[0]['na']++;}
            for($i=1;$i<=8;$i++){$v=(int)$row["sqd$i"];if($v>=1&&$v<=5)$sqd[$i][$v]++;else$sqd[$i]['na']++;}
        }}
        $data['sqd_counts']=$sqd;
        return $data;
    }

    $workbookData = [];
    foreach ($reports_to_generate as $rep) {
        $data = gatherReportData($conn, $rep['filter']);
        $ws = []; $ws[]=[$rep['title']]; $ws[]=['Generated On:',date('F d, Y')]; $ws[]=['Scope:',$admin_region_name]; $ws[]=['Total Survey Responses:',(string)$data['total_responses']]; $ws[]=[];
        if (!$data['total_responses']) { $ws[]=['No responses have been recorded for this service yet.']; }
        if ($data['total_responses'] > 0) {
            $ws[]=['AGE DEMOGRAPHICS']; $ws[]=['Category','Count','Percentage']; $t=0; foreach($data['age_groups'] as $l=>$c){$ws[]=[$l,$c,getPercent($c,$data['total_responses'])];$t+=$c;} $ws[]=['TOTAL',$t,getPercent($t,$data['total_responses'])]; $ws[]=[];
            $ws[]=['CITIZEN / CLIENT TYPE']; $ws[]=['Category','Count','Percentage']; $t=0; foreach($data['type_groups'] as $l=>$c){$ws[]=[$l,$c,getPercent($c,$data['total_responses'])];$t+=$c;} $ws[]=['TOTAL',$t,getPercent($t,$data['total_responses'])]; $ws[]=[];
            $ws[]=['SEX DEMOGRAPHICS']; $ws[]=['Category','Count','Percentage']; $t=0; foreach($data['sex_groups'] as $l=>$c){$ws[]=[$l,$c,getPercent($c,$data['total_responses'])];$t+=$c;} $ws[]=['TOTAL',$t,getPercent($t,$data['total_responses'])]; $ws[]=[];
            $cc=$data['cc_counts'];
            $ws[]=["Citizen's Charter Answers",'Physical Responses','Online Responses','Percentage','Remarks'];
            $ws[]=['CC1. Which of the following describes your awareness of the CC?']; $ws[]=['1. I know what a CC is and I saw this office\'s CC',$cc['cc1'][1]['p'],$cc['cc1'][1]['o'],getPercent($cc['cc1'][1]['p']+$cc['cc1'][1]['o'],$data['tot_cc1']),'"No Answer" - '.($cc['cc1']['na']['p']+$cc['cc1']['na']['o'])]; $ws[]=['2. I know what a CC is but I did not see this office\'s CC.',$cc['cc1'][2]['p'],$cc['cc1'][2]['o'],getPercent($cc['cc1'][2]['p']+$cc['cc1'][2]['o'],$data['tot_cc1']),'']; $ws[]=['3. I learned of the CC only when I saw this office\'s CC.',$cc['cc1'][3]['p'],$cc['cc1'][3]['o'],getPercent($cc['cc1'][3]['p']+$cc['cc1'][3]['o'],$data['tot_cc1']),'']; $ws[]=['4. I do not know what a CC is and I did not see this office\'s CC.',$cc['cc1'][4]['p'],$cc['cc1'][4]['o'],getPercent($cc['cc1'][4]['p']+$cc['cc1'][4]['o'],$data['tot_cc1']),'']; $ws[]=['TOTAL',$data['tot_cc1_p'],$data['tot_cc1_o'],getPercent($data['tot_cc1'],$data['tot_cc1']),'']; $ws[]=[];
            $ws[]=['CC2. If aware of CC, would you say that the CC of this office was …']; $ws[]=['1. Easy to see',$cc['cc2'][1]['p'],$cc['cc2'][1]['o'],getPercent($cc['cc2'][1]['p']+$cc['cc2'][1]['o'],$data['tot_cc2']),'"No Answer" - '.($cc['cc2']['na']['p']+$cc['cc2']['na']['o'])]; $ws[]=['2. Somewhat easy to see',$cc['cc2'][2]['p'],$cc['cc2'][2]['o'],getPercent($cc['cc2'][2]['p']+$cc['cc2'][2]['o'],$data['tot_cc2']),'"N/A" - '.($cc['cc2'][5]['p']+$cc['cc2'][5]['o'])]; $ws[]=['3. Difficult to see',$cc['cc2'][3]['p'],$cc['cc2'][3]['o'],getPercent($cc['cc2'][3]['p']+$cc['cc2'][3]['o'],$data['tot_cc2']),'']; $ws[]=['4. Not visible at all',$cc['cc2'][4]['p'],$cc['cc2'][4]['o'],getPercent($cc['cc2'][4]['p']+$cc['cc2'][4]['o'],$data['tot_cc2']),'']; $ws[]=['TOTAL',$data['tot_cc2_p'],$data['tot_cc2_o'],getPercent($data['tot_cc2'],$data['tot_cc2']),'']; $ws[]=[];
            $ws[]=['CC3. If aware of CC, how much did the CC help you in your transaction?']; $ws[]=['1. Helped very much',$cc['cc3'][1]['p'],$cc['cc3'][1]['o'],getPercent($cc['cc3'][1]['p']+$cc['cc3'][1]['o'],$data['tot_cc3']),'"No Answer" - '.($cc['cc3']['na']['p']+$cc['cc3']['na']['o'])]; $ws[]=['2. Somewhat helped',$cc['cc3'][2]['p'],$cc['cc3'][2]['o'],getPercent($cc['cc3'][2]['p']+$cc['cc3'][2]['o'],$data['tot_cc3']),'"N/A" - '.($cc['cc3'][4]['p']+$cc['cc3'][4]['o'])]; $ws[]=['3. Did not help',$cc['cc3'][3]['p'],$cc['cc3'][3]['o'],getPercent($cc['cc3'][3]['p']+$cc['cc3'][3]['o'],$data['tot_cc3']),'']; $ws[]=['TOTAL',$data['tot_cc3_p'],$data['tot_cc3_o'],getPercent($data['tot_cc3'],$data['tot_cc3']),'']; $ws[]=[];
            $ws[]=['SERVICE QUALITY DIMENSIONS']; $ws[]=['Metric','Strongly Agree','Agree','Neither Agree','Disagree','Strongly Disagree','No Answer','Total','Satisfaction Rate'];
            $c0=$data['sqd_counts'][0]; $t0=array_sum($c0); $v0=$t0-$c0['na']; $sc0=($v0>0)?getPercent($c0[5]+$c0[4],$v0):'0.00%';
            $ws[]=['SQD0. I am satisfied with the service that I availed. (Physical submissions only)',$c0[5],$c0[4],$c0[3],$c0[2],$c0[1],$c0['na'],$t0,$sc0];
            foreach([1,2,3,4,5,6,7,8] as $i){ $c=$data['sqd_counts'][$i]; $t=array_sum($c); $v=$t-$c['na']; $sc=($v>0)?getPercent($c[5]+$c[4],$v):'0.00%'; $ws[]=[$sqd_labels[$i],$c[5],$c[4],$c[3],$c[2],$c[1],$c['na'],$t,$sc]; }
        }
        $workbookData[$rep['tab_name']] = $ws;
    }
    echo json_encode($workbookData);
    exit();
}

/* === SUMMARY STATS === */
$total_feedbacks  = $conn->query("SELECT COUNT(*) as t FROM csm_submissions WHERE $filter_simple")->fetch_assoc()['t'] ?? 0;
$avg_rating       = $conn->query("SELECT AVG(CASE WHEN IFNULL(submission_source,'Online')='Physical' THEN (sqd0+sqd1+sqd2+sqd3+sqd4+sqd5+sqd6+sqd7+sqd8)/9.0 ELSE (sqd1+sqd2+sqd3+sqd4+sqd5+sqd6+sqd7+sqd8)/8.0 END) as a FROM csm_submissions WHERE $filter_simple")->fetch_assoc()['a'] ?? 0;
$satisfaction_rate= ($total_feedbacks > 0) ? number_format(($avg_rating / 5) * 100, 1) : "0.0";
$comments_count   = $conn->query("SELECT COUNT(*) as c FROM csm_submissions WHERE suggestions IS NOT NULL AND TRIM(suggestions)!='' AND $filter_simple")->fetch_assoc()['c'] ?? 0;

/* === CHART DATA === */
$monthly_data = array_fill(1, 12, 0);
$mq = $conn->query("SELECT MONTH(created_at) as m, COUNT(*) as c FROM csm_submissions WHERE YEAR(created_at)=YEAR(CURDATE()) AND $filter_simple GROUP BY m");
while ($r = $mq->fetch_assoc()) $monthly_data[$r['m']] = (int)$r['c'];

$srv_labels=[]; $srv_data=[];
$sq=$conn->query("SELECT s.service_name,COUNT(cs.id) as c FROM csm_submissions cs JOIN services s ON cs.service_id=s.id WHERE $filter_alias GROUP BY s.service_name");
while($r=$sq->fetch_assoc()){$srv_labels[]=$r['service_name'];$srv_data[]=$r['c'];}

$reg_labels=[]; $reg_data=[];
$rq=$conn->query("SELECT r.region_name,COUNT(cs.id) as c FROM csm_submissions cs JOIN regions r ON cs.region_id=r.id WHERE $filter_alias GROUP BY r.region_name ORDER BY c DESC LIMIT 5");
while($r=$rq->fetch_assoc()){$reg_labels[]=$r['region_name'];$reg_data[]=$r['c'];}

$cli_labels=[]; $cli_data=[];
$cq=$conn->query("SELECT client_type,COUNT(*) as c FROM csm_submissions WHERE $filter_simple GROUP BY client_type");
while($r=$cq->fetch_assoc()){$cli_labels[]=$r['client_type'];$cli_data[]=$r['c'];}

$gender_labels=[]; $gender_data=[];
$gq=$conn->query("SELECT sex,COUNT(*) as c FROM csm_submissions WHERE $filter_simple AND sex IS NOT NULL AND sex!='' GROUP BY sex");
while($r=$gq->fetch_assoc()){$gender_labels[]=$r['sex'];$gender_data[]=$r['c'];}

$age_labels=['<18','18-24','25-34','35-44','45-54','55+']; $age_data=[0,0,0,0,0,0];
$aq=$conn->query("SELECT CASE WHEN age<18 THEN '<18' WHEN age BETWEEN 18 AND 24 THEN '18-24' WHEN age BETWEEN 25 AND 34 THEN '25-34' WHEN age BETWEEN 35 AND 44 THEN '35-44' WHEN age BETWEEN 45 AND 54 THEN '45-54' ELSE '55+' END as ag,COUNT(*) as c FROM csm_submissions WHERE $filter_simple AND age IS NOT NULL GROUP BY ag");
while($r=$aq->fetch_assoc()){$idx=array_search($r['ag'],$age_labels);if($idx!==false)$age_data[$idx]=(int)$r['c'];}

/* === TABLE (SERVICE PERFORMANCE) === */
$limit       = (int)(($conn->query("SELECT setting_value FROM system_settings WHERE setting_key='items_per_page'")->fetch_assoc()['setting_value']) ?? 10);
$page        = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$offset      = ($page - 1) * $limit;
$total_rows  = $conn->query("SELECT COUNT(DISTINCT s.id) as t FROM csm_submissions cs JOIN services s ON cs.service_id=s.id WHERE $filter_alias")->fetch_assoc()['t'] ?? 0;
$total_pages = max(1, ceil($total_rows / $limit));

$table_q = $conn->query("
    SELECT s.service_name, COUNT(cs.id) as total_fb,
    AVG(CASE WHEN IFNULL(cs.submission_source,'Online')='Physical' THEN (cs.sqd0+cs.sqd1+cs.sqd2+cs.sqd3+cs.sqd4+cs.sqd5+cs.sqd6+cs.sqd7+cs.sqd8)/9.0 ELSE (cs.sqd1+cs.sqd2+cs.sqd3+cs.sqd4+cs.sqd5+cs.sqd6+cs.sqd7+cs.sqd8)/8.0 END) as avg_r,
    SUM(CASE WHEN IFNULL(cs.submission_source,'Online')='Physical' THEN IF((cs.sqd0+cs.sqd1+cs.sqd2+cs.sqd3+cs.sqd4+cs.sqd5+cs.sqd6+cs.sqd7+cs.sqd8)/9.0>=4,1,0) ELSE IF((cs.sqd1+cs.sqd2+cs.sqd3+cs.sqd4+cs.sqd5+cs.sqd6+cs.sqd7+cs.sqd8)/8.0>=4,1,0) END) as sat_c
    FROM csm_submissions cs JOIN services s ON cs.service_id=s.id
    WHERE $filter_alias GROUP BY s.service_name ORDER BY total_fb DESC
    LIMIT $limit OFFSET $offset
");

$qp = $_GET; unset($qp['page']);
$qs = ($q = http_build_query($qp)) ? '&'.$q : '';

/* Build export URL */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$base_url  = $protocol . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
$ep        = $_GET; $ep['export'] = 'json_data';
$json_url  = $base_url . "?" . http_build_query($ep);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ── TOKENS ── */
        :root {
            --navy:     #002147;
            --navy-mid: #003366;
            --blue:     #0038A8;
            --gold:     #FCD116;
            --bg:       #eef1f8;
            --surface:  #ffffff;
            --border:   #e2e8f4;
            --text:     #0f172a;
            --muted:    #64748b;
            --faint:    #94a3b8;
            --success:  #10b981;
            --danger:   #ef4444;

            --g-blue:   linear-gradient(135deg,#3b82f6,#6366f1);
            --g-amber:  linear-gradient(135deg,#f59e0b,#ef4444);
            --g-teal:   linear-gradient(135deg,#10b981,#06b6d4);
            --g-violet: linear-gradient(135deg,#8b5cf6,#ec4899);
            --g-green:  linear-gradient(135deg,#10b981,#059669);

            --r-sm: 10px; --r-md: 14px; --r-lg: 20px;
            --sh-sm: 0 2px 8px rgba(0,0,0,.07);
            --sh-lg: 0 16px 40px rgba(0,0,0,.13);
            --sidebar-w: 260px;
            --header-h:  80px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html, body { scrollbar-width:none; -ms-overflow-style:none; }
        ::-webkit-scrollbar { display:none; }

        body {
            font-family:'Inter','Segoe UI',system-ui,sans-serif;
            background:var(--bg); color:var(--text);
            padding-top:var(--header-h);
        }

        .main-content {
            margin-left:var(--sidebar-w);
            min-height:calc(100vh - var(--header-h));
            padding:32px 36px 52px;
            transition:margin .3s;
        }

        .burger-btn {
            display:none; position:fixed; top:20px; left:18px; z-index:1300;
            background:transparent; border:none; color:#fff;
            font-size:1.35rem; cursor:pointer; padding:4px 8px;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            display:flex; align-items:flex-start; justify-content:space-between;
            margin-bottom:28px; gap:16px; flex-wrap:wrap;
        }
        .page-header h1 { font-size:1.55rem; font-weight:800; color:var(--navy); letter-spacing:-.5px; }
        .page-header p  { font-size:.875rem; color:var(--muted); margin-top:3px; }

        .header-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }

        .btn-export {
            display:inline-flex; align-items:center; gap:8px;
            padding:10px 18px; border-radius:var(--r-sm); border:none;
            font-size:.83rem; font-weight:700; font-family:inherit;
            cursor:pointer; text-decoration:none; transition:transform .2s, box-shadow .2s;
        }
        .btn-export.green {
            background:var(--g-green); color:#fff;
            box-shadow:0 4px 12px rgba(16,185,129,.3);
        }
        .btn-export.green:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,185,129,.4); }
        .btn-export.outlined {
            background:var(--surface); color:var(--navy);
            border:1px solid var(--border);
            box-shadow:var(--sh-sm);
        }
        .btn-export.outlined:hover { background:#f1f5f9; transform:translateY(-2px); }

        /* ── SECTION LABEL ── */
        .section-label {
            font-size:.7rem; font-weight:800; text-transform:uppercase;
            letter-spacing:1.2px; color:var(--muted); margin-bottom:14px;
            display:flex; align-items:center; gap:8px;
        }
        .section-label::after { content:''; flex:1; height:1px; background:var(--border); }

        /* ── STAT CARDS ── */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-bottom:28px; }
        .stat-card {
            background:var(--surface); border-radius:var(--r-lg);
            padding:12px 14px 10px; border:1px solid var(--border);
            box-shadow:var(--sh-sm); position:relative; overflow:hidden;
            transition:transform .25s, box-shadow .25s;
        }
        .stat-card:hover { transform:translateY(-4px); box-shadow:var(--sh-lg); }
        .stat-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:4px;
            border-radius:var(--r-lg) var(--r-lg) 0 0;
        }
        .stat-card.s-teal::before   { background:var(--g-teal); }
        .stat-card.s-amber::before  { background:var(--g-amber); }
        .stat-card.s-blue::before   { background:var(--g-blue); }
        .stat-card.s-violet::before { background:var(--g-violet); }

        .stat-card .blob { position:absolute; width:100px; height:100px; border-radius:50%; right:-26px; bottom:-26px; opacity:.07; }
        .s-teal   .blob { background:var(--g-teal); }
        .s-amber  .blob { background:var(--g-amber); }
        .s-blue   .blob { background:var(--g-blue); }
        .s-violet .blob { background:var(--g-violet); }

        .stat-icon-wrap { width:30px; height:30px; border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#fff; margin-bottom:8px; }
        .s-teal   .stat-icon-wrap { background:var(--g-teal); }
        .s-amber  .stat-icon-wrap { background:var(--g-amber); }
        .s-blue   .stat-icon-wrap { background:var(--g-blue); }
        .s-violet .stat-icon-wrap { background:var(--g-violet); }

        .stat-value { font-size:1.5rem; font-weight:900; color:var(--navy); line-height:1; letter-spacing:-.5px; }
        .stat-label { font-size:.65rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; margin-top:3px; }

        /* ── FILTER CARD ── */
        .filter-card { background:var(--surface); border-radius:var(--r-lg); border:1px solid var(--border); box-shadow:var(--sh-sm); margin-bottom:28px; overflow:hidden; }
        .filter-card-head { padding:16px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
        .fch-icon { width:32px; height:32px; border-radius:var(--r-sm); background:var(--g-blue); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
        .filter-card-head h3 { font-size:.9rem; font-weight:700; color:var(--navy); }
        .filter-card-body { padding:20px 22px; }
        .filter-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; align-items:flex-end; }
        .filter-group { display:flex; flex-direction:column; gap:5px; }
        .filter-group label { font-size:.67rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; }
        .filter-group select, .filter-group input[type="date"] {
            padding:9px 12px; border:1px solid var(--border); border-radius:var(--r-sm);
            font-size:.85rem; font-family:inherit; color:var(--text); background:#f8fafc;
            outline:none; transition:border .15s, box-shadow .15s;
        }
        .filter-group select:focus, .filter-group input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); background:#fff; }
        .filter-group select:disabled { opacity:.5; cursor:not-allowed; }
        .btn-reset { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 16px; border-radius:var(--r-sm); border:1px solid var(--border); background:#f1f5f9; color:var(--muted); font-size:.83rem; font-weight:600; font-family:inherit; cursor:pointer; text-decoration:none; transition:all .15s; }
        .btn-reset:hover { background:var(--border); color:var(--text); }

        /* ── CHART CARDS ── */
        .charts-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; margin-bottom:28px; }
        .card { background:var(--surface); border-radius:var(--r-lg); border:1px solid var(--border); box-shadow:var(--sh-sm); overflow:hidden; display:flex; flex-direction:column; }
        .card-head { padding:16px 22px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
        .card-head-icon { width:34px; height:34px; border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; font-size:.9rem; color:#fff; flex-shrink:0; }
        .card-head h3 { font-size:.93rem; font-weight:700; color:var(--navy); }
        .card-body { padding:18px 22px; flex:1; }
        .chart-wrapper { height:260px; position:relative; }
        .chart-wrapper canvas { width:100%!important; height:100%!important; }

        /* ── TABLE CARD ── */
        .table-card { background:var(--surface); border-radius:var(--r-lg); border:1px solid var(--border); box-shadow:var(--sh-sm); overflow:hidden; }
        .table-card-head { padding:16px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
        .tch-icon { width:34px; height:34px; border-radius:var(--r-sm); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.9rem; flex-shrink:0; background:linear-gradient(135deg,var(--blue),var(--navy)); }
        .table-card-head h3 { font-size:.93rem; font-weight:700; color:var(--navy); flex:1; }
        .badge-pill { font-size:.7rem; font-weight:700; padding:3px 11px; border-radius:20px; background:var(--navy); color:#fff; }

        .table-scroll { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:600px; }
        thead tr { background:#f7f9fc; }
        th { padding:12px 16px; font-size:.68rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.9px; border-bottom:1px solid var(--border); white-space:nowrap; }
        td { padding:13px 16px; font-size:.875rem; color:var(--text); border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr { transition:background .15s; }
        tbody tr:hover { background:#f8fafd; }

        .rank-badge { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:26px; background:#f1f5f9; border-radius:7px; font-size:.8rem; font-weight:800; color:var(--navy); padding:0 8px; }
        .rank-badge.top1 { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; }
        .rank-badge.top2 { background:linear-gradient(135deg,#94a3b8,#64748b); color:#fff; }
        .rank-badge.top3 { background:linear-gradient(135deg,#cd7f32,#a0522d); color:#fff; }

        .rating-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:20px; font-size:.78rem; font-weight:700; }
        .excellent { background:#dcfce7; color:#15803d; }
        .good      { background:#dbeafe; color:#1d4ed8; }
        .average   { background:#fef9c3; color:#a16207; }

        .sat-bar-wrap { display:flex; align-items:center; gap:8px; }
        .sat-bar { height:6px; border-radius:3px; background:var(--border); flex:1; overflow:hidden; }
        .sat-bar-fill { height:100%; border-radius:3px; background:var(--g-teal); }
        .sat-pct { font-size:.78rem; font-weight:700; color:var(--success); width:44px; text-align:right; }

        /* ── PAGINATION ── */
        .pag-bar { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border); background:#f7f9fc; }
        .pag-info { font-size:.8rem; color:var(--muted); }
        .pag-btns { display:flex; gap:5px; }
        .pag-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border-radius:var(--r-sm); border:1px solid var(--border); background:var(--surface); color:var(--text); font-size:.8rem; font-weight:600; font-family:inherit; text-decoration:none; transition:all .15s; }
        .pag-btn:hover  { background:var(--navy); color:#fff; border-color:var(--navy); }
        .pag-btn.active { background:var(--navy); color:#fff; border-color:var(--navy); pointer-events:none; }
        .pag-btn.off    { opacity:.3; pointer-events:none; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align:center; padding:52px 20px; }
        .empty-state .ei { width:68px; height:68px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:1.7rem; color:var(--faint); }
        .empty-state p { font-size:.9rem; color:var(--muted); }

        /* ── PRINT ── */
        .print-only { display:none; }
        @media print {
            @page { size:A4 portrait; margin:10mm; }
            body { background:#fff!important; color:#000; font-size:10pt; }
            .sidebar,.top-header,.filter-card,.stats-grid,.charts-grid,.header-actions,.pag-bar,.burger-btn { display:none!important; }
            .main-content { margin:0!important; width:100%!important; padding:0!important; }
            .print-only { display:block!important; }
            .print-header { text-align:center; border-bottom:2px solid #002147; margin-bottom:20px; padding-bottom:10px; }
            .print-title { font-size:18pt; font-weight:bold; color:#002147; }
            .print-meta { display:flex; justify-content:space-between; margin-bottom:20px; font-size:9pt; }
            .print-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
            .print-table th,.print-table td { border:.5pt solid #ccc; padding:8px; text-align:left; }
            .print-table th { background:#f0f4f8!important; -webkit-print-color-adjust:exact; font-weight:bold; }
            .print-section-title { background:#002147!important; color:#fff!important; padding:5px 10px; -webkit-print-color-adjust:exact; margin-top:15px; }
        }

        /* ── RESPONSIVE ── */
        @media (max-width:1200px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .charts-grid { grid-template-columns:1fr; } }
        @media (max-width:768px)  { .burger-btn { display:block; } .main-content { margin-left:0; padding:20px 16px 48px; } .stats-grid { grid-template-columns:1fr; gap:14px; } .pag-info { display:none; } }
        @media (max-width:480px)  { .stat-value { font-size:1.75rem; } .header-actions { width:100%; } }

        /* ── PREVIEW MODAL ── */
        .prev-popup  { border-radius:20px!important; overflow:hidden!important; }
        .prev-no-title { display:none!important; }
        .prev-html-wrap { padding:0!important; margin:0!important; }

        .prev-hero {
            background:linear-gradient(135deg,#001233,#002147 55%,#003580);
            padding:22px 28px 20px; position:relative; overflow:hidden;
        }
        .prev-hero::before {
            content:''; position:absolute; top:-50%; left:-40%; width:180%; height:200%;
            background:radial-gradient(circle,rgba(255,255,255,.04) 0%,transparent 60%);
            pointer-events:none;
        }
        .prev-hero::after {
            content:''; position:absolute; bottom:0; left:0; right:0; height:3px;
            background:linear-gradient(90deg,#D4AF37,rgba(255,255,255,.35),#D4AF37);
        }
        .prev-hero-row  { display:flex; align-items:center; gap:14px; position:relative; z-index:1; }
        .prev-hero-icon {
            width:44px; height:44px; border-radius:11px;
            background:rgba(255,255,255,.12); border:1.5px solid rgba(255,255,255,.22);
            display:flex; align-items:center; justify-content:center;
            font-size:18px; color:#D4AF37; flex-shrink:0;
        }
        .prev-hero-text h3 { font-size:16px; font-weight:800; color:#fff; margin:0; }
        .prev-hero-text p  { font-size:11px; color:rgba(255,255,255,.6); margin:2px 0 0; }
        .prev-chips { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; position:relative; z-index:1; }
        .prev-chip  {
            display:inline-flex; align-items:center; gap:5px;
            background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
            border-radius:20px; padding:3px 12px;
            font-size:11px; font-weight:600; color:rgba(255,255,255,.82);
        }
        .prev-chip i { font-size:9px; color:#D4AF37; }

        .prev-tab-bar {
            display:flex; overflow-x:auto; background:#f8fafc;
            border-bottom:2px solid #e2e8f0; padding:0 20px; scrollbar-width:none;
        }
        .prev-tab-bar::-webkit-scrollbar { display:none; }
        .ptab {
            padding:11px 18px; font-size:12px; font-weight:700; color:#64748b;
            background:none; border:none; border-bottom:3px solid transparent;
            cursor:pointer; white-space:nowrap; transition:all .2s;
            font-family:'Inter','Segoe UI',sans-serif; margin-bottom:-2px; flex-shrink:0;
        }
        .ptab:hover  { color:#002147; background:rgba(0,33,71,.05); }
        .ptab.active { color:#002147; border-bottom-color:#002147; background:#fff; }

        .prev-body { overflow:auto; max-height:42vh; }
        .ppanel { display:block; }
        .ppanel.hidden { display:none; }

        .prev-tbl { width:100%; border-collapse:collapse; font-size:12.5px; font-family:'Inter','Segoe UI',sans-serif; }
        .prev-tbl td { padding:8px 14px; border:1px solid #e2e8f0; color:#1e293b; vertical-align:middle; white-space:nowrap; }
        .prev-tbl tbody tr:hover td { background:#f0f7ff!important; }
        .prev-tbl .pr-sec td  { background:#002147; color:#fff; font-weight:800; font-size:11.5px; text-transform:uppercase; letter-spacing:.5px; border-color:#001233; }
        .prev-tbl .pr-hdr td  { background:#f1f5f9; color:#475569; font-weight:700; border-bottom:2px solid #cbd5e1; font-size:11px; text-transform:uppercase; letter-spacing:.4px; }
        .prev-tbl .pr-tot td  { background:#fef9c3; color:#78350f; font-weight:800; border-top:2px solid #fde68a; }
        .prev-tbl .pr-even td { background:#fafbfc; }
        .prev-tbl .pr-gap td  { background:#f4f6fa; height:8px; border:none; padding:0; }

        .prev-footer {
            display:flex; align-items:center; justify-content:space-between;
            padding:11px 22px; background:#f8fafc; border-top:1px solid #e2e8f0;
            font-size:11.5px; color:#64748b;
        }
        .prev-footer strong { color:#002147; }
    </style>
</head>
<body>

<?php include '../component_admin/sidebar.php'; ?>
<?php include '../component_admin/top_header.php'; ?>

<button class="burger-btn" id="menuToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>

<main class="main-content">

    <!-- Print-only header -->
    <div class="print-only">
        <div class="print-header"><p class="print-title">CSM Analytics &amp; Performance Report</p></div>
        <div class="print-meta">
            <span><strong>Generated:</strong> <?= date('F d, Y – h:i A') ?></span>
            <span><strong>Scope:</strong> <?= $admin_region_name ?></span>
            <span><strong>Filters:</strong> <?php $ft=[];if($f_start)$ft[]="From ".date('M d, Y',strtotime($f_start));if($f_end)$ft[]="To ".date('M d, Y',strtotime($f_end));if($f_type)$ft[]="Type: ".htmlspecialchars($f_type);echo $ft?implode(' | ',$ft):'None'; ?></span>
        </div>
        <table class="print-table"><thead><tr><th>Satisfaction Rate</th><th>Avg Rating</th><th>Total Submissions</th><th>Written Comments</th></tr></thead>
        <tbody><tr><td><?= $satisfaction_rate ?>%</td><td><?= number_format($avg_rating,1) ?> / 5.0</td><td><?= number_format($total_feedbacks) ?></td><td><?= number_format($comments_count) ?></td></tr></tbody></table>
    </div>

    <!-- ══ PAGE HEADER ══ -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-line" style="color:var(--blue);margin-right:8px;"></i>Reports &amp; Analytics</h1>
            <p>Explore performance trends, demographics, and export structured reports.</p>
        </div>
        <div class="header-actions">
            <button class="btn-export green" onclick="downloadRealExcel('<?= $json_url ?>')">
                <i class="fas fa-file-excel"></i> Export XLSX
            </button>
            <button class="btn-export outlined" onclick="previewInSheets('<?= $json_url ?>')">
                <i class="fas fa-table"></i> Preview Data
            </button>
            <!-- <button class="btn-export outlined" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button> -->
        </div>
    </div>

    <!-- ══ STAT CARDS ══ -->
    <p class="section-label"><i class="fas fa-tachometer-alt"></i> Key Metrics</p>
    <div class="stats-grid">
        <div class="stat-card s-teal">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-smile-beam"></i></div>
            <div class="stat-value"><?= $satisfaction_rate ?>%</div>
            <div class="stat-label">Satisfaction Rate</div>
        </div>
        <div class="stat-card s-amber">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-star"></i></div>
            <div class="stat-value"><?= number_format($avg_rating,1) ?></div>
            <div class="stat-label">Average Rating <span style="font-size:.65rem;color:var(--faint);font-weight:500;">/ 5</span></div>
        </div>
        <div class="stat-card s-blue">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-clipboard-check"></i></div>
            <div class="stat-value counter" data-target="<?= $total_feedbacks ?>">0</div>
            <div class="stat-label">Total Feedbacks</div>
        </div>
        <div class="stat-card s-violet">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-comments"></i></div>
            <div class="stat-value counter" data-target="<?= $comments_count ?>">0</div>
            <div class="stat-label">Written Comments</div>
        </div>
    </div>

    <!-- ══ FILTER ══ -->
    <p class="section-label"><i class="fas fa-filter"></i> Filters</p>
    <div class="filter-card">
        <div class="filter-card-head">
            <div class="fch-icon"><i class="fas fa-sliders-h"></i></div>
            <h3>Filter Report Data</h3>
        </div>
        <div class="filter-card-body">
            <form method="GET" id="filterForm" class="filter-grid">
                <div class="filter-group">
                    <label>Region</label>
                    <select name="region_id" onchange="this.form.submit()" <?= !$is_superadmin ? 'disabled' : '' ?>>
                        <option value="">All Regions</option>
                        <?php while ($r = $all_regions->fetch_assoc()): $sel = ($f_region==$r['id']||(!$is_superadmin&&($_SESSION['region']??'')==$r['region_name']))?'selected':''; ?>
                            <option value="<?= $r['id'] ?>" <?= $sel ?>><?= htmlspecialchars($r['region_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Client Type</label>
                    <select name="client_type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="Citizen"    <?= $f_type==='Citizen'   ?'selected':'' ?>>Citizen</option>
                        <option value="Business"   <?= $f_type==='Business'  ?'selected':'' ?>>Business</option>
                        <option value="Government" <?= $f_type==='Government'?'selected':'' ?>>Government</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Service</label>
                    <select name="service_id" onchange="this.form.submit()">
                        <option value="">All Services</option>
                        <?php $services_dropdown->data_seek(0); while($s=$services_dropdown->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" <?= $f_service==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['service_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($f_start) ?>" onchange="this.form.submit()">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($f_end) ?>" onchange="this.form.submit()">
                </div>
                <div class="filter-group" style="justify-content:flex-end;">
                    <a href="reports.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ CHARTS ROW 1: Trend + Service ══ -->
    <p class="section-label"><i class="fas fa-chart-pie"></i> Analytics Charts</p>
    <div class="charts-grid">
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:var(--g-blue);"><i class="fas fa-chart-line"></i></div>
                <h3>Monthly Feedback Trend (<?= date('Y') ?>)</h3>
            </div>
            <div class="card-body"><div class="chart-wrapper"><canvas id="monthlyChart"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:var(--g-amber);"><i class="fas fa-concierge-bell"></i></div>
                <h3>Feedback by Service</h3>
            </div>
            <div class="card-body"><div class="chart-wrapper"><canvas id="serviceChart"></canvas></div></div>
        </div>
    </div>

    <!-- ══ CHARTS ROW 2: Age + Gender ══ -->
    <div class="charts-grid">
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:var(--g-violet);"><i class="fas fa-users"></i></div>
                <h3>Age Distribution</h3>
            </div>
            <div class="card-body"><div class="chart-wrapper"><canvas id="ageChart"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:linear-gradient(135deg,#ec4899,#8b5cf6);"><i class="fas fa-venus-mars"></i></div>
                <h3>Gender Breakdown</h3>
            </div>
            <div class="card-body"><div class="chart-wrapper"><canvas id="genderChart"></canvas></div></div>
        </div>
    </div>

    <!-- ══ CHARTS ROW 3: Region + Client Type ══ -->
    <div class="charts-grid" style="margin-bottom:28px;">
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:var(--g-teal);"><i class="fas fa-map-marker-alt"></i></div>
                <h3>Top 5 Regions by Feedback</h3>
            </div>
            <div class="card-body"><div class="chart-wrapper"><canvas id="regionChart"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:linear-gradient(135deg,#3b82f6,#10b981);"><i class="fas fa-id-badge"></i></div>
                <h3>Client Type Distribution</h3>
            </div>
            <div class="card-body"><div class="chart-wrapper"><canvas id="clientChart"></canvas></div></div>
        </div>
    </div>

    <!-- ══ SERVICE PERFORMANCE TABLE ══ -->
    <p class="section-label"><i class="fas fa-trophy"></i> Service Performance</p>
    <div class="table-card">
        <div class="table-card-head">
            <div class="tch-icon"><i class="fas fa-list-ol"></i></div>
            <h3>Service Performance Ranking</h3>
            <span class="badge-pill"><?= number_format($total_rows) ?> services</span>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Service</th>
                        <th>Total Feedbacks</th>
                        <th>Avg Rating</th>
                        <th>Satisfaction Rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($table_q && $table_q->num_rows > 0):
                    $rank = $offset + 1;
                    while ($row = $table_q->fetch_assoc()):
                        $ra = floatval($row['avg_r']);
                        $sr = ($ra > 0) ? ($ra / 5) * 100 : 0;
                        $cls = ($ra >= 4.5) ? 'excellent' : (($ra >= 3.5) ? 'good' : 'average');
                        $rbadge = $rank===1?'top1':($rank===2?'top2':($rank===3?'top3':''));
                ?>
                <tr>
                    <td><span class="rank-badge <?= $rbadge ?>">#<?= $rank ?></span></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($row['service_name']) ?></td>
                    <td style="font-weight:700;color:var(--navy);"><?= number_format($row['total_fb']) ?></td>
                    <td><span class="rating-badge <?= $cls ?>"><i class="fas fa-star" style="font-size:.65rem;"></i><?= number_format($ra,2) ?></span></td>
                    <td>
                        <div class="sat-bar-wrap">
                            <div class="sat-bar"><div class="sat-bar-fill" style="width:<?= min(100,round($sr)) ?>%;"></div></div>
                            <span class="sat-pct"><?= number_format($sr,1) ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php $rank++; endwhile; else: ?>
                <tr><td colspan="5">
                    <div class="empty-state">
                        <div class="ei"><i class="fas fa-chart-bar"></i></div>
                        <p>No service data found for the selected filters.</p>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_rows > 0): ?>
        <div class="pag-bar">
            <span class="pag-info">Showing <?= min($offset+1,$total_rows) ?>–<?= min($offset+$limit,$total_rows) ?> of <?= number_format($total_rows) ?> services</span>
            <div class="pag-btns">
                <a class="pag-btn <?= $page<=1?'off':'' ?>" href="?page=<?= $page-1 ?><?= $qs ?>"><i class="fas fa-chevron-left"></i></a>
                <?php $rs=max(1,$page-2);$re=min($total_pages,$page+2);
                if($rs>1){echo '<a class="pag-btn" href="?page=1'.$qs.'">1</a>';if($rs>2)echo '<span class="pag-btn off">…</span>';}
                for($i=$rs;$i<=$re;$i++)echo '<a class="pag-btn '.($i===$page?'active':'').'" href="?page='.$i.$qs.'">'.$i.'</a>';
                if($re<$total_pages){if($re<$total_pages-1)echo '<span class="pag-btn off">…</span>';echo '<a class="pag-btn" href="?page='.$total_pages.$qs.'">'.$total_pages.'</a>';}?>
                <a class="pag-btn <?= $page>=$total_pages?'off':'' ?>" href="?page=<?= $page+1 ?><?= $qs ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
const reportScope = <?= json_encode($admin_region_name) ?>;

/* ── COUNTERS ── */
document.querySelectorAll('.counter').forEach(el => {
    const target = parseInt(el.dataset.target)||0;
    if(!target){el.textContent='0';return;}
    const inc=target/40;let cur=0;
    const tick=()=>{cur=Math.min(cur+inc,target);el.textContent=Math.ceil(cur).toLocaleString();if(cur<target)setTimeout(tick,22);};
    tick();
});

/* ── CHARTS ── */
Chart.defaults.font.family="'Inter','Segoe UI',system-ui,sans-serif";
Chart.defaults.color='#64748b';
const anim={duration:1100,easing:'easeOutQuart'};
const tip={backgroundColor:'#0f172a',titleColor:'#fff',bodyColor:'rgba(255,255,255,.75)',padding:10,cornerRadius:8};

/* Monthly trend */
const mCtx=document.getElementById('monthlyChart').getContext('2d');
const mGrad=mCtx.createLinearGradient(0,0,0,260);mGrad.addColorStop(0,'rgba(99,102,241,.22)');mGrad.addColorStop(1,'rgba(99,102,241,0)');
new Chart(mCtx,{type:'line',data:{labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],datasets:[{label:'Submissions',data:<?= json_encode(array_values($monthly_data)) ?>,borderColor:'#6366f1',backgroundColor:mGrad,borderWidth:2.5,pointBackgroundColor:'#fff',pointBorderColor:'#6366f1',pointBorderWidth:2.5,pointRadius:4,pointHoverRadius:6,fill:true,tension:0.45}]},options:{animation:anim,responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9',drawBorder:false},ticks:{font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}}}});

/* Service pie */
new Chart(document.getElementById('serviceChart'),{type:'pie',data:{labels:<?= json_encode($srv_labels) ?>,datasets:[{data:<?= json_encode($srv_data) ?>,backgroundColor:['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ec4899','#0ea5e9'],borderWidth:3,borderColor:'#fff',hoverOffset:8}]},options:{animation:anim,responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{usePointStyle:true,pointStyleWidth:10,font:{size:11},padding:14}},tooltip:tip}}});

/* Age bar */
const aCtx=document.getElementById('ageChart').getContext('2d');
const aGrad=aCtx.createLinearGradient(0,0,0,260);aGrad.addColorStop(0,'#8b5cf6');aGrad.addColorStop(1,'#ec4899');
new Chart(aCtx,{type:'bar',data:{labels:<?= json_encode($age_labels) ?>,datasets:[{label:'Users',data:<?= json_encode($age_data) ?>,backgroundColor:aGrad,borderRadius:7,borderSkipped:false,barPercentage:0.55}]},options:{animation:anim,responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:tip},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9',drawBorder:false},ticks:{font:{size:11}}},x:{grid:{display:false},ticks:{font:{size:11}}}}}});

/* Gender doughnut */
new Chart(document.getElementById('genderChart'),{type:'doughnut',data:{labels:<?= json_encode($gender_labels) ?>,datasets:[{data:<?= json_encode($gender_data) ?>,backgroundColor:['#3b82f6','#ec4899','#f59e0b'],borderWidth:3,borderColor:'#fff',hoverOffset:8}]},options:{animation:anim,responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyleWidth:10,font:{size:12},padding:16}},tooltip:tip}}});

/* Region horizontal bar */
const rCtx=document.getElementById('regionChart').getContext('2d');
const rGrad=rCtx.createLinearGradient(260,0,0,0);rGrad.addColorStop(0,'#10b981');rGrad.addColorStop(1,'#06b6d4');
new Chart(rCtx,{type:'bar',data:{labels:<?= json_encode($reg_labels) ?>,datasets:[{label:'Feedbacks',data:<?= json_encode($reg_data) ?>,backgroundColor:rGrad,borderRadius:6,borderSkipped:false,barPercentage:0.55}]},options:{indexAxis:'y',animation:anim,responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:tip},scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9',drawBorder:false},ticks:{font:{size:11}}},y:{grid:{display:false},ticks:{font:{size:11}}}}}});

/* Client type doughnut */
new Chart(document.getElementById('clientChart'),{type:'doughnut',data:{labels:<?= json_encode($cli_labels) ?>,datasets:[{data:<?= json_encode($cli_data) ?>,backgroundColor:['#3b82f6','#10b981','#f59e0b','#8b5cf6'],borderWidth:3,borderColor:'#fff',hoverOffset:8}]},options:{animation:anim,responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyleWidth:10,font:{size:12},padding:16}},tooltip:tip}}});

/* ── EXCEL EXPORT (ExcelJS) ── */
async function downloadRealExcel(jsonUrl) {
    Swal.fire({
        title: 'Generating Excel Report…',
        html: '<p style="color:#64748b;font-size:14px;margin-top:4px">Applying styles and formatting — please wait.</p>',
        allowOutsideClick: false, didOpen: () => Swal.showLoading()
    });
    try {
        const data = await (await fetch(jsonUrl)).json();
        const wb = new ExcelJS.Workbook();
        wb.creator = 'DICT DCSM Dashboard'; wb.created = new Date();

        const NAVY    = 'FF002147', NAVY2 = 'FF003366', GOLD = 'FFD4AF37';
        const WHITE   = 'FFFFFFFF', LIGHT = 'FFF8FAFC', ALT = 'FFF0F4F8';
        const BORD    = 'FFE2E8F0', BORD_HDR = 'FF001F4E';
        const AMB_BG  = 'FFFFF8E1', AMB_FG = 'FF78350F', AMB_BRD = 'FFFDE68A';

        const border = (c = BORD) => ({ top:{style:'thin',color:{argb:c}}, left:{style:'thin',color:{argb:c}}, bottom:{style:'thin',color:{argb:c}}, right:{style:'thin',color:{argb:c}} });
        const fill   = (argb) => ({ type:'pattern', pattern:'solid', fgColor:{argb} });

        /* ── Cover Sheet ── */
        const cov = wb.addWorksheet('Cover Page');
        cov.columns = [{width:58},{width:36}];

        const addCovRow = (vals, opts = {}) => {
            const r = cov.addRow(vals);
            if (opts.mergeTo) cov.mergeCells(r.number, 1, r.number, opts.mergeTo);
            if (opts.height) r.height = opts.height;
            if (opts.fillColor) r.eachCell(c => c.fill = fill(opts.fillColor));
            if (opts.fontColor) r.eachCell(c => c.font = { ...(opts.font||{}), color:{argb:opts.fontColor} });
            return r;
        };

        const r1 = addCovRow(['DICT Digital Client Satisfaction Measurement System'], { mergeTo:2, height:46, fillColor:NAVY, fontColor:WHITE, font:{size:16,bold:true,name:'Calibri'} });
        r1.getCell(1).alignment = { horizontal:'center', vertical:'middle' };

        const r2 = addCovRow(['CSM Analytics & Performance Report'], { mergeTo:2, height:30, fillColor:NAVY2, fontColor:WHITE, font:{size:13,bold:true} });
        r2.getCell(1).alignment = { horizontal:'center', vertical:'middle' };

        const r3 = addCovRow([''], { mergeTo:2, height:4, fillColor:GOLD });

        cov.addRow([]);

        [
            ['Generated On:',  new Date().toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})],
            ['Generated At:',  new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})],
            ['Scope:',         reportScope],
            ['Report Sheets:', Object.keys(data).length],
        ].forEach(([k,v]) => {
            const mr = cov.addRow([k, v]);
            mr.height = 24;
            mr.getCell(1).font = { bold:true, color:{argb:NAVY}, size:11 };
            mr.getCell(2).font = { size:11 };
            mr.eachCell(c => { c.border = border(); c.alignment = { vertical:'middle' }; });
        });

        cov.addRow([]);
        const nr = cov.addRow(['Note:', 'This report was auto-generated by the DICT DCSM Admin Dashboard. Data reflects submitted CSM responses within the selected filter parameters.']);
        nr.height = 40; nr.getCell(1).font = { bold:true, color:{argb:NAVY}, size:10 };
        nr.getCell(2).font = { size:10, italic:true, color:{argb:'FF64748B'} };
        nr.getCell(2).alignment = { wrapText:true, vertical:'middle' };
        nr.eachCell(c => c.border = border());

        cov.addRow([]);
        const dr = cov.addRow(['', 'Department of Information and Communications Technology']);
        dr.getCell(2).font = { bold:true, color:{argb:NAVY}, size:11 };
        dr.getCell(2).alignment = { horizontal:'right' };

        /* ── Data Sheets ── */
        for (let sn in data) {
            const ws = wb.addWorksheet(sn);
            ws.columns = [{width:58},{width:18},{width:18},{width:18},{width:22},{width:16},{width:16},{width:16},{width:16}];

            let ri = 0, parity = 0;
            data[sn].forEach(rd => {
                const prd = rd.map(c => (typeof c==='string' && c.endsWith('%') && !isNaN(parseFloat(c))) ? parseFloat(c)/100 : c);
                const row = ws.addRow(prd.length ? prd : ['']);
                ri++;

                if (rd.length === 0) { row.height = 8; return; }

                const isSection = rd.length === 1 && rd[0] !== '';
                const isHeader  = rd.includes('Count') || rd.includes('Strongly Agree') || rd.includes('Category') || rd.includes('Metric') || rd.includes('Physical Responses');
                const isTotal   = rd[0] === 'TOTAL' || rd[0] === 'Overall';
                const numCols   = Math.max(rd.length, 1);

                if (isSection) {
                    ws.mergeCells(ri, 1, ri, 9);
                    row.getCell(1).fill      = fill(NAVY);
                    row.getCell(1).font      = { color:{argb:WHITE}, bold:true, size:11, name:'Calibri' };
                    row.getCell(1).alignment = { horizontal:'center', vertical:'middle' };
                    row.height = 30;
                } else if (isHeader) {
                    row.height = 30;
                    row.eachCell((cell, cn) => {
                        if (cn > numCols) return;
                        cell.fill      = fill(NAVY2);
                        cell.font      = { color:{argb:WHITE}, bold:true, size:10, name:'Calibri' };
                        cell.alignment = { horizontal:'center', vertical:'middle', wrapText:true };
                        cell.border    = border(BORD_HDR);
                    });
                    ws.autoFilter = { from:{row:ri,column:1}, to:{row:ri,column:numCols} };
                    ws.views = [{ state:'frozen', xSplit:0, ySplit:ri }];
                } else if (isTotal) {
                    row.height = 22;
                    row.eachCell((cell, cn) => {
                        if (cn > numCols) return;
                        cell.fill      = fill(AMB_BG);
                        cell.font      = { color:{argb:AMB_FG}, bold:true, size:10 };
                        cell.alignment = { vertical:'middle', wrapText:true };
                        cell.border    = border(AMB_BRD);
                        if (typeof rd[cn-1]==='string' && rd[cn-1].endsWith('%') && !isNaN(parseFloat(rd[cn-1]))) cell.numFmt='0.00%';
                    });
                } else {
                    parity++;
                    const bg = parity % 2 === 0 ? ALT : WHITE;
                    row.height = 20;
                    row.eachCell((cell, cn) => {
                        if (cn > numCols) return;
                        cell.fill      = fill(bg);
                        cell.font      = { size:10, name:'Calibri' };
                        cell.alignment = { vertical:'middle', wrapText:true };
                        cell.border    = border();
                        if (typeof rd[cn-1]==='string' && rd[cn-1].endsWith('%') && !isNaN(parseFloat(rd[cn-1]))) cell.numFmt='0.00%';
                    });
                }
            });
        }

        const buf = await wb.xlsx.writeBuffer();
        saveAs(new Blob([buf],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}), 'DCSM_Report_' + new Date().toISOString().slice(0,10) + '.xlsx');
        Swal.fire({ icon:'success', title:'Report Downloaded!', text:'Your Excel file has been saved.', timer:2500, timerProgressBar:true, showConfirmButton:false });
    } catch(e) {
        console.error(e);
        Swal.fire('Error', 'Failed to generate Excel file. ' + e.message, 'error');
    }
}

/* ── DATA PREVIEW ── */
async function previewInSheets(jsonUrl) {
    Swal.fire({ title:'Loading preview…', didOpen:()=>Swal.showLoading() });
    let data;
    try { data = await (await fetch(jsonUrl)).json(); }
    catch(e) { return Swal.fire('Error','Could not load data preview.','error'); }

    const sheets = Object.keys(data);
    const totalDataRows = sheets.reduce((a, sn) => a + data[sn].filter(r => r.length > 1 || (r.length === 1 && r[0] && !['Count','Strongly Agree','Category','Metric','TOTAL','Overall'].includes(r[0]))).length, 0);

    const hero = `
        <div class="prev-hero">
            <div class="prev-hero-row">
                <div class="prev-hero-icon"><i class="fas fa-file-excel"></i></div>
                <div class="prev-hero-text">
                    <h3>Data Preview</h3>
                    <p>Review your report before exporting to Excel</p>
                </div>
            </div>
            <div class="prev-chips">
                <span class="prev-chip"><i class="fas fa-table"></i> ${sheets.length} sheet${sheets.length>1?'s':''}</span>
                <span class="prev-chip"><i class="fas fa-database"></i> ${totalDataRows.toLocaleString()} rows</span>
                <span class="prev-chip"><i class="fas fa-map-marker-alt"></i> ${reportScope}</span>
                <span class="prev-chip"><i class="fas fa-calendar"></i> ${new Date().toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</span>
            </div>
        </div>`;

    const tabBar = `<div class="prev-tab-bar">${sheets.map((sn,i) => {
        const sid = sn.replace(/[^a-zA-Z0-9]/g,'_');
        return `<button class="ptab${i===0?' active':''}" onclick="switchPTab('${sid}',this)">${sn}</button>`;
    }).join('')}</div>`;

    const panels = sheets.map((sn, i) => {
        const sid = sn.replace(/[^a-zA-Z0-9]/g,'_');
        let parity = 0;
        let rows = data[sn].map(row => {
            if (row.length === 0) return '<tr class="pr-gap"><td colspan="9"></td></tr>';
            const isSection = row.length === 1 && row[0] !== '';
            const isHeader  = row.includes('Count') || row.includes('Strongly Agree') || row.includes('Category') || row.includes('Metric') || row.includes('Physical Responses');
            const isTotal   = row[0] === 'TOTAL' || row[0] === 'Overall';
            let cls = isSection ? 'pr-sec' : isHeader ? 'pr-hdr' : isTotal ? 'pr-tot' : '';
            if (!cls) { parity++; cls = parity % 2 === 0 ? 'pr-even' : ''; }
            return `<tr class="${cls}">${row.map(c => `<td>${c??''}</td>`).join('')}</tr>`;
        }).join('');
        return `<div class="ppanel${i===0?'':' hidden'}" id="ppanel_${sn.replace(/[^a-zA-Z0-9]/g,'_')}"><table class="prev-tbl"><tbody>${rows}</tbody></table></div>`;
    }).join('');

    const footer = `<div class="prev-footer"><span><i class="fas fa-info-circle" style="margin-right:4px;color:#94a3b8"></i>Click a sheet tab to switch views</span><span>Scope: <strong>${reportScope}</strong></span></div>`;

    Swal.fire({
        title: '',
        html: hero + tabBar + `<div class="prev-body">${panels}</div>` + footer,
        width: '92%', padding: '0',
        showCloseButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-file-excel" style="margin-right:6px"></i>Download Excel',
        confirmButtonColor: '#10b981',
        cancelButtonText: 'Close',
        cancelButtonColor: '#64748b',
        customClass: { popup:'prev-popup', title:'prev-no-title', htmlContainer:'prev-html-wrap' }
    }).then(r => { if (r.isConfirmed) downloadRealExcel(jsonUrl); });
}

window.switchPTab = function(id, btn) {
    document.querySelectorAll('.ppanel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.ptab').forEach(b => b.classList.remove('active'));
    document.getElementById('ppanel_' + id).classList.remove('hidden');
    btn.classList.add('active');
};

/* ── SIDEBAR TOGGLE ── */
document.getElementById('menuToggle').addEventListener('click',()=>document.querySelector('.sidebar').classList.toggle('active'));
document.querySelectorAll('.sidebar-menu a').forEach(l=>l.addEventListener('click',()=>{if(window.innerWidth<=768)document.querySelector('.sidebar').classList.remove('active');}));
</script>
</body>
</html>
