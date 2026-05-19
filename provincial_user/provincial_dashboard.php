<?php
session_start();

/* Province Selection */
$province = isset($_GET['province']) ? htmlspecialchars($_GET['province']) : 'Cagayan';
$provinceName = "Province of " . $province;

/* Sample Stats */
$provinceStats = [
    'total_feedbacks' => 156,
    'avg_rating' => 4.1,
    'satisfaction_rate' => 85,
    'pending_actions' => 8
];

/* Feedback Data */
$provinceFeedbacks = [
['id'=>'FB-CAG-001','date'=>'Jan 15, 2025','client'=>'Juan dela Cruz','service'=>'Free Public Wi-Fi','rating'=>5,'status'=>'Reviewed'],
['id'=>'FB-CAG-002','date'=>'Jan 14, 2025','client'=>'Maria Santos','service'=>'Tech4ED','rating'=>4,'status'=>'Reviewed'],
['id'=>'FB-CAG-003','date'=>'Jan 14, 2025','client'=>'Pedro Reyes','service'=>'Digital Literacy','rating'=>3,'status'=>'Pending'],
['id'=>'FB-CAG-004','date'=>'Jan 13, 2025','client'=>'Ana Garcia','service'=>'eGovernment','rating'=>5,'status'=>'Reviewed'],
['id'=>'FB-CAG-005','date'=>'Jan 12, 2025','client'=>'Carlos Mendoza','service'=>'Cybersecurity','rating'=>4,'status'=>'Reviewed']
];

/* Services */
$services = [
['name'=>'Free Public Wi-Fi','count'=>65,'rating'=>4.2],
['name'=>'Tech4ED','count'=>42,'rating'=>4.0],
['name'=>'Digital Literacy','count'=>28,'rating'=>4.3],
['name'=>'eGovernment','count'=>15,'rating'=>3.9],
['name'=>'Cybersecurity','count'=>6,'rating'=>4.1]
];

/* Chart Data */
$monthlyLabels = ['Jan','Feb','Mar','Apr','May','Jun'];
$monthlyData = [25,32,28,35,42,38];

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Provincial Dashboard | DICT System</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>

:root{
--primary:#002147;
--secondary:#0038A8;
--accent:#FFD700;
--bg:#f4f6f9;
}

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Segoe UI;
}

body{
display:flex;
background:var(--bg);
}

/* SIDEBAR */

.sidebar{
width:260px;
background:linear-gradient(180deg,var(--primary),var(--secondary));
color:white;
height:100vh;
padding:20px;
position:fixed;
}

.sidebar h2{
font-size:16px;
margin-bottom:20px;
}

.sidebar a{
display:block;
padding:12px;
margin-bottom:6px;
color:white;
text-decoration:none;
border-radius:6px;
}

.sidebar a:hover{
background:rgba(255,255,255,.2);
}

/* MAIN */

.main{
margin-left:260px;
padding:25px;
width:100%;
}

/* HEADER */

.header{
display:flex;
justify-content:space-between;
background:white;
padding:18px;
border-radius:10px;
margin-bottom:20px;
}

/* STATS */

.stats{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:15px;
margin-bottom:25px;
}

.card{
background:white;
padding:20px;
border-radius:10px;
box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.card h3{
font-size:28px;
margin-bottom:5px;
}

/* CHARTS */

.charts{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(350px,1fr));
gap:20px;
margin-bottom:25px;
}

/* TABLE */

.table{
background:white;
border-radius:10px;
overflow:hidden;
}

table{
width:100%;
border-collapse:collapse;
}

th,td{
padding:12px;
border-bottom:1px solid #eee;
}

th{
background:#f9fafc;
text-align:left;
}

.rating{
padding:4px 10px;
border-radius:10px;
font-size:12px;
}

.excellent{background:#d1fae5;}
.average{background:#fef3c7;}
.poor{background:#fee2e2;}

.status{
padding:4px 10px;
border-radius:10px;
font-size:12px;
}

.reviewed{background:#d1fae5;}
.pending{background:#fef3c7;}

button{
padding:6px 10px;
border:none;
background:var(--secondary);
color:white;
border-radius:5px;
cursor:pointer;
}

button:hover{
background:var(--primary);
}

</style>
</head>

<body>

<!-- MAIN -->

<div class="main">

<!-- HEADER -->

<div class="header">

<h2><?php echo $provinceName ?> Dashboard</h2>

<select onchange="changeProvince(this.value)">
<option>Cagayan</option>
<option>Isabela</option>
<option>Nueva Vizcaya</option>
<option>Quirino</option>
</select>

</div>

<!-- STATS -->

<div class="stats">

<div class="card">
<h3><?php echo $provinceStats['total_feedbacks'] ?></h3>
<p>Total Feedbacks</p>
</div>

<div class="card">
<h3><?php echo $provinceStats['avg_rating'] ?></h3>
<p>Average Rating</p>
</div>

<div class="card">
<h3><?php echo $provinceStats['satisfaction_rate'] ?>%</h3>
<p>Satisfaction Rate</p>
</div>

<div class="card">
<h3><?php echo $provinceStats['pending_actions'] ?></h3>
<p>Pending Reviews</p>
</div>

</div>

<!-- CHARTS -->

<div class="charts">

<div class="card">
<h4>Monthly Feedback</h4>
<canvas id="trendChart"></canvas>
</div>

<div class="card">
<h4>Service Performance</h4>
<canvas id="serviceChart"></canvas>
</div>

</div>

<!-- TABLE -->

<div class="table">

<table>

<thead>
<tr>
<th>ID</th>
<th>Date</th>
<th>Client</th>
<th>Service</th>
<th>Rating</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($provinceFeedbacks as $fb): ?>

<tr>

<td><?php echo $fb['id'] ?></td>
<td><?php echo $fb['date'] ?></td>
<td><?php echo $fb['client'] ?></td>
<td><?php echo $fb['service'] ?></td>

<td>

<?php
$class='excellent';
if($fb['rating']==3) $class='average';
if($fb['rating']<=2) $class='poor';
?>

<span class="rating <?php echo $class ?>">
⭐ <?php echo $fb['rating'] ?>
</span>

</td>

<td>
<span class="status <?php echo strtolower($fb['status']) ?>">
<?php echo $fb['status'] ?>
</span>
</td>

<td>
<button onclick="viewFeedback('<?php echo $fb['id']?>')">View</button>
</td>

</tr>

<?php endforeach ?>

</tbody>

</table>

</div>

</div>

<script>

/* CHANGE PROVINCE */

function changeProvince(p){

Swal.fire({
title:"Switch Province",
text:"Loading "+p,
timer:1000,
showConfirmButton:false
}).then(()=>{

window.location="?province="+p

})

}

/* VIEW FEEDBACK */

function viewFeedback(id){

Swal.fire({
title:"Feedback Details",
text:"Opening "+id,
icon:"info"
})

}

/* CHART */

new Chart(document.getElementById('trendChart'),{
type:'line',
data:{
labels:<?php echo json_encode($monthlyLabels) ?>,
datasets:[{
data:<?php echo json_encode($monthlyData) ?>,
borderColor:"#0038A8",
fill:true,
backgroundColor:"rgba(0,56,168,.1)"
}]
}
});

new Chart(document.getElementById('serviceChart'),{
type:'bar',
data:{
labels:["WiFi","Tech4ED","Digital","eGov","Cyber"],
datasets:[{
data:[65,42,28,15,6],
backgroundColor:"#0038A8"
}]
}
});

</script>

</body>
</html>