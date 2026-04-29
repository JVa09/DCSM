<?php
session_start();
include "../../functions/db_connection.php";

$message = '';
$message_type = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and clean data
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $role      = $_POST['role'];
    
    // FIX: If they are a superadmin, or the field is empty, force it to be NULL in the database
    $region    = !empty($_POST['region']) ? $_POST['region'] : NULL;
    $province  = !empty($_POST['province']) ? $_POST['province'] : NULL;
    
    $status    = 'active'; // Default status for new accounts

    // 1. Check if username or email already exists
    $check_stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "Username or Email already taken!";
        $message_type = "error";
    } else {
        // 2. Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 3. Insert into database
        $stmt = $conn->prepare("INSERT INTO admin_users (full_name, email, username, password, role, region, province, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $full_name, $email, $username, $hashed_password, $role, $region, $province, $status);

        if ($stmt->execute()) {
            $message = "Account created successfully!";
            $message_type = "success";
        } else {
            $message = "Database error: " . $conn->error;
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration | DICT DCSM</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
        body{
            background:linear-gradient(135deg,#0038A8,#CE1126);
            display:flex; justify-content:center; align-items:center;
            min-height:100vh; padding:40px 20px; position:relative; overflow-x:hidden;
        }
        .login-container{
            background:rgba(255,255,255,0.95); backdrop-filter:blur(10px);
            padding:30px; width:100%; max-width:500px; border-radius:16px;
            box-shadow:0 15px 35px rgba(0,0,0,0.25); text-align:center; z-index:10;
        }
        .login-logo{width:70px; margin-bottom:10px;}
        .login-container h2{color:#0038A8; font-size:1.2rem; margin-bottom:5px;}
        .gradient-line{height:4px; width:100px; margin:10px auto 20px auto; border-radius:5px; background:linear-gradient(to right,#0038A8,#CE1126);}
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left; }
        .form-group{ margin-bottom: 12px; }
        .full-width { grid-column: span 2; }
        
        .form-group label{font-size:12px; font-weight:600; color:#333; display:block; margin-bottom:4px;}
        .form-group input, .form-group select{
            width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;
            background:#f8f9fa; font-size:13px; transition:.25s;
        }
        .form-group input:focus, .form-group select:focus{outline:none; border-color:#0038A8; background:#fff;}
        .form-group select:disabled { background: #e9ecef; cursor: not-allowed; opacity: 0.7; }
        
        .login-btn{
            width:100%; padding:12px; margin-top:15px; border:none; border-radius:8px;
            font-size:15px; font-weight:600; background:linear-gradient(to right,#FCD116,#ffe36e);
            cursor:pointer; transition:.3s;
        }
        .login-btn:hover{transform:translateY(-2px); box-shadow:0 5px 15px rgba(0,0,0,0.2);}
        
        .success-message{color:#28a745; background:#e8f5e9; padding:10px; border-radius:8px; margin-bottom:15px; font-size:13px; font-weight: bold;}
        .error-message{color:#dc3545; background:#ffebee; padding:10px; border-radius:8px; margin-bottom:15px; font-size:13px; font-weight: bold;}
        .back-link{margin-top:15px; display:block; text-decoration:none; color:#0038A8; font-size:13px;}

        @media (max-width: 480px) { .form-grid { grid-template-columns: 1fr; } .full-width { grid-column: span 1; } }
    </style>
</head>
<body>

<div class="login-container">
    <img src="../../assets/img/dictlogo.png" alt="DICT Logo" class="login-logo">
    <h2>System Registration</h2>
    <div class="gradient-line"></div>

    <?php if($message): ?>
        <div class="<?php echo $message_type; ?>-message"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-grid">
            <div class="form-group full-width">
                <label>Full Name</label>
                <input type="text" name="full_name" placeholder="Juan Dela Cruz" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="juan@dict.gov.ph" required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" id="role" onchange="roleChanged()" required>
                    <option value="admin">Regional Admin</option>
                    <option value="superadmin">National Superadmin</option>
                </select>
            </div>

            <div class="form-group">
                <label>Region</label>
                <select id="region" name="region" onchange="regionChanged()" required>
                    <option value="">Select Region</option>
                    <option value="National Capital Region (NCR)">NCR</option>
                    <option value="Cordillera Administrative Region (CAR)">CAR</option>
                    <option value="Region I - Ilocos Region">Region I - Ilocos Region</option>
                    <option value="Region II - Cagayan Valley">Region II - Cagayan Valley</option>
                    <option value="Region III - Central Luzon">Region III - Central Luzon</option>
                    <option value="Region IV-A - CALABARZON">Region IV-A - CALABARZON</option>
                    <option value="Region IV-B - MIMAROPA">Region IV-B - MIMAROPA</option>
                    <option value="Region V - Bicol Region">Region V - Bicol Region</option>
                    <option value="Region VI - Western Visayas">Region VI - Western Visayas</option>
                    <option value="Region VII - Central Visayas">Region VII - Central Visayas</option>
                    <option value="Region VIII - Eastern Visayas">Region VIII - Eastern Visayas</option>
                    <option value="Region IX - Zamboanga Peninsula">Region IX - Zamboanga Peninsula</option>
                    <option value="Region X - Northern Mindanao">Region X - Northern Mindanao</option>
                    <option value="Region XI - Davao Region">Region XI - Davao Region</option>
                    <option value="Region XII - SOCCSKSARGEN">Region XII - SOCCSKSARGEN</option>
                    <option value="Region XIII - Caraga">Region XIII - Caraga</option>
                    <option value="BARMM">BARMM</option>
                </select>
            </div>

            <div class="form-group full-width">
                <label>Province</label>
                <select id="province" name="province" required>
                    <option value="">Select Province</option>
                </select>
            </div>
        </div>

        <button type="submit" class="login-btn">Register Account</button>
    </form>

    <a href="login.php" class="back-link">← Back to Login</a>
</div>

<script>
// FIX: Copied the exact list from login.php to ensure consistency
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

function regionChanged(){
    let region = document.getElementById("region").value;
    let province = document.getElementById("province");
    province.innerHTML = "<option value=''>Select Province</option>";
    if(regionProvinces[region]){
        regionProvinces[region].forEach(function(p){
            let option = document.createElement("option");
            option.value = p; option.text = p;
            province.appendChild(option);
        });
    }
}

// FIX: Automatically disable Region and Province if Superadmin is selected
function roleChanged() {
    let role = document.getElementById("role").value;
    let regionSelect = document.getElementById("region");
    let provinceSelect = document.getElementById("province");

    if (role === 'superadmin') {
        regionSelect.value = "";
        provinceSelect.innerHTML = "<option value=''>Select Province</option>";
        
        regionSelect.disabled = true;
        provinceSelect.disabled = true;
        
        regionSelect.required = false;
        provinceSelect.required = false;
    } else {
        regionSelect.disabled = false;
        provinceSelect.disabled = false;
        
        regionSelect.required = true;
        provinceSelect.required = true;
    }
}

// Run once on page load just to set initial state correctly
roleChanged();
</script>

</body>
</html>