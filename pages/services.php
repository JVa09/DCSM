<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DICT Services | Digital Client Satisfaction System</title>

<style>
:root {
    --primary: #0038A8;
    --secondary: #a80000;
    --accent: #FFD700;
    --light-bg: #f4f6f9;
    --text-dark: #1f2937;
    --text-light: #6b7280;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
    background-color: var(--light-bg);
    color: var(--text-dark);
}

/* HEADER */
.header {
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    color: white;
    padding: 45px 20px;
    text-align: center;
}

.header h1 {
    font-size: 32px;
    font-weight: 600;
}

.header p {
    margin-top: 8px;
    font-size: 15px;
    opacity: 0.9;
}

/* CONTAINER */
.container {
    max-width: 1200px;
    margin: 60px auto;
    padding: 0 20px;
}

/* SECTION TITLE */
.section-title {
    text-align: center;
    margin-bottom: 50px;
}

.section-title h2 {
    font-size: 28px;
    color: var(--primary);
}

.section-title p {
    margin-top: 10px;
    color: var(--text-light);
}

/* SERVICE LIST */
.service-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

/* SERVICE CARD */
.service-card {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 20px rgba(0,0,0,0.04);
    transition: 0.3s ease;
    position: relative;
}

.service-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    width: 5px;
    height: 100%;
   background: linear-gradient(180deg, var(--primary), var(--secondary));
    border-radius: 12px 0 0 12px;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}

.service-card h3 {
    margin-bottom: 15px;
    font-size: 20px;
    color: var(--primary);
}

.service-card p {
    font-size: 15px;
    line-height: 1.7;
    color: var(--text-light);
}

/* FOOTER */
footer {
    margin-top: 70px;
    background-color: #00265b;
    color: #d1d5db;
    text-align: center;
    padding: 25px;
    font-size: 14px;
}

footer span {
    color: var(--accent);
}
</style>
</head>

<body>

<header class="header">
    <h1>DICT Services</h1>
    <p>Department of Information and Communications Technology<br>
    Republic of the Philippines</p>
</header>

<div class="container">

    <div class="section-title">
        <h2>Public Services</h2>
        <p>Delivering efficient, secure, and accessible digital services to Filipinos nationwide.</p>
    </div>

    <div class="service-list">

        <div class="service-card">
            <h3>Free Public Wi-Fi Access</h3>
            <p>Provision of internet connectivity in public areas including government buildings, schools, hospitals, and transport terminals.</p>
        </div>

        <div class="service-card">
            <h3>ICT Technical Assistance</h3>
            <p>Support services for government agencies in ICT infrastructure development, systems integration, and digital transformation.</p>
        </div>

        <div class="service-card">
            <h3>Cybersecurity Advisory</h3>
            <p>Guidance and incident response support to strengthen cybersecurity posture of government institutions and stakeholders.</p>
        </div>

        <div class="service-card">
            <h3>Digital Skills Training</h3>
            <p>Workshops and capacity-building programs to improve digital literacy and ICT competency among citizens and public servants.</p>
        </div>

        <div class="service-card">
            <h3>Government Cloud Services</h3>
            <p>Secure hosting and cloud solutions to support digital government systems and data management initiatives.</p>
        </div>

        <div class="service-card">
            <h3>Startup and Innovation Support</h3>
            <p>Programs and initiatives that assist technology startups and innovation-driven enterprises in the Philippines.</p>
        </div>

    </div>

</div>

<footer>
    © 2026 <span>Department of Information and Communications Technology</span><br>
    All Rights Reserved | Republic of the Philippines
</footer>

</body>
</html>