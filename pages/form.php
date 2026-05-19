<?php
session_start();
/* ── Physical SQDs (SQD0–SQD8) ── */
$sqds = [
    "SQD0" => "I am satisfied with the service that I availed.",
    "SQD1" => "I spent a reasonable amount of time for my transaction.",
    "SQD2" => "The office followed the transaction's requirements and steps based on the information provided.",
    "SQD3" => "The steps (including payment) I needed to do for my transaction were easy and simple.",
    "SQD4" => "I easily found information about my transaction from the office or its website.",
    "SQD5" => "I paid a reasonable amount of fees for my transaction.",
    "SQD6" => "I feel the office was fair to everyone, or \"walang palakasan\", during my transaction.",
    "SQD7" => "I was treated courteously by the staff, and (if asked for help) the staff was helpful.",
    "SQD8" => "I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me."
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DICT CSM Physical Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @media print { 
            @page { size: letter; margin: 0.4in; } 
            * { -webkit-print-color-adjust: exact !important; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Arial", sans-serif; }
        body { background: white; color: black; line-height: 1.2; font-size: 9.5pt; padding: 10px; }

        /* --- HEADER LOGOS & TEXT --- */
        .header-top { display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 5px; }
        .logo-left { height: 60px; position: absolute; left: 40px; }
        .logo-right { height: 55px; position: absolute; right: 40px; }
        .header-text { text-align: center; border-bottom: 1px solid black; padding-bottom: 5px; width: 65%; margin: 0 auto; }
        .header-text p { font-size: 8pt; margin-bottom: 1px; }
        .header-text h1 { font-size: 10pt; font-weight: bold; }

        /* --- TOP SECTION ELEMENTS --- */
        .main-title { text-align: center; font-weight: bold; font-size: 11pt; margin: 5px 0; }
        .intro-text { font-size: 8.5pt; margin: 0 20px 10px 20px; text-align: justify; line-height: 1.3; }

        /* --- DATA INPUT AREA --- */
        .input-container { margin: 0 20px 15px 20px; }
        .input-row { display: flex; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
        .input-item { display: flex; align-items: center; margin-right: 20px; }
        .box { width: 13px; height: 13px; border: 1px solid black; margin: 0 5px; display: inline-block; }
        .underline { border-bottom: 1px solid black; flex-grow: 1; min-width: 80px; margin-left: 5px; height: 14px; }
        
        /* --- SECTION INSTRUCTIONS --- */
        .section-instr { font-weight: bold; font-size: 8.5pt; margin: 10px 20px 5px 20px; border-top: 1px solid black; padding-top: 5px; text-align: left; }

        /* --- CC QUESTIONS --- */
        .cc-question { margin: 8px 20px 3px 40px; font-weight: normal; font-size: 9pt; }
        .cc-options { margin-left: 60px; margin-bottom: 5px; }
        .cc-opt { display: flex; align-items: center; margin-bottom: 2px; font-size: 8.5pt; }

        /* --- SQD TABLE --- */
        .sqd-table { width: calc(100% - 40px); border-collapse: collapse; margin: 10px auto; border: 1.2px solid black; table-layout: fixed; }
        .sqd-table th, .sqd-table td { border: 1px solid black; padding: 4px; text-align: center; vertical-align: middle; overflow: hidden; }
        
        /* Flexbox to keep Emoji and Label in one line at the top */
       /* --- SQD TABLE HEADER STYLING --- */
.sqd-table th { 
    background: #f2f2f2; 
    font-size: 7pt; 
    font-weight: bold; 
    height: 55px; /* Fixed height to keep emojis in a straight line */
    vertical-align: top; /* Ensures the flex container starts at the top */
    padding: 0; 
}

.header-content {
    display: flex;
    flex-direction: column;
    align-items: center;      /* Horizontal Center */
    justify-content: flex-start; /* Vertical Top */
    gap: 2px;
    padding-top: 5px;        /* Spacing from top border */
    height: 100%;            /* Fill the TH height */
}


        
        /* Column Width Distribution */
        .col-label { width: 40%; }
        .col-rating { width: 10%; }
        .col-na { width: 10%; }

        .sqd-text { text-align: left !important; padding-left: 10px !important; font-size: 8pt !important; line-height: 1.1; }
        
        /* Smaller icons to facilitate same-line feel if needed, currently stacked but tight */
.emoji-icon { 
    font-size: 18pt; 
    color: #333; 
}

.emoji-label { 
    font-size: 6.5pt; 
    font-weight: bold; 
    display: block; 
    line-height: 1.1; 
    text-align: center;
}
        .footer-suggest { margin: 15px 20px 5px 20px; font-weight: normal; font-size: 9pt; }
        .line-fill { border-bottom: 1px solid black; height: 18px; margin: 0 20px; width: calc(100% - 40px); }
    </style>
</head>
<body onload="window.print()">

    <div class="header-top">
        <img src="../assets/img/DICTLOGO.png" class="logo-left">
        <div class="header-text">
            <p>REPUBLIC OF THE PHILIPPINES</p>
            <h1>DEPARTMENT OF INFORMATION AND COMMUNICATIONS TECHNOLOGY</h1>
        </div>
        <img src="../assets/img/logo-bagong-pilipinas.png" class="logo-right">
    </div>
    
    <div class="main-title">HELP US SERVE YOU BETTER!</div>

    <div class="intro-text">
        This Client Satisfaction Measurement (CSM) tracks the customer experience of government offices. Your feedback on your <u>recently concluded transaction</u> will help this office provide a better service. Personal information shared will be kept confidential and you always have the option to not answer this form.
    </div>

    <div class="input-container">
        <div class="input-row">
            <span style="margin-right:10px;">Client type:</span>
            <span class="input-item"><div class="box"></div> Citizen</span>
            <span class="input-item"><div class="box"></div> Business</span>
            <span class="input-item"><div class="box"></div> Government (Employee or another agency)</span>
        </div>
        <div class="input-row">
            <span style="margin-right:5px;">Date:</span><div class="underline" style="max-width:150px;"></div>
            <span style="margin-left:30px;">Sex:</span>
            <span class="input-item"><div class="box"></div> Male</span>
            <span class="input-item"><div class="box"></div> Female</span>
            <span style="margin-left:30px;">Age:</span><div class="underline" style="max-width:80px;"></div>
        </div>
        <div class="input-row">
            <span style="margin-right:5px;">Region of residence:</span><div class="underline" style="max-width:250px;"></div>
            <span style="margin-left:30px; margin-right:5px;">Service Availed:</span><div class="underline"></div>
        </div>
    </div>

    <div class="section-instr">
        INSTRUCTIONS: Check mark (✓) your answer to the Citizen's Charter (CC) questions.
    </div>

    <!-- CC Questions -->
    <div class="cc-question">CC1. Which of the following best describes your awareness of a CC?</div>
    <div class="cc-options">
        <div class="cc-opt"><div class="box"></div> 1. I know what a CC is and I saw this office's CC.</div>
        <div class="cc-opt"><div class="box"></div> 2. I know what a CC is but I did NOT see this office's CC.</div>
        <div class="cc-opt"><div class="box"></div> 3. I learned of the CC only when I saw this office's CC.</div>
        <div class="cc-opt"><div class="box"></div> 4. I do not know what a CC is and I did not see one in this office. (Answer 'N/A' on CC2 and CC3)</div>
    </div>

    <div class="cc-question">CC2. If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was...?</div>
    <div class="cc-options">
        <div class="cc-opt"><div class="box"></div> 1. Easy to see.</div>
        <div class="cc-opt"><div class="box"></div> 2. Somewhat easy to see.</div>
        <div class="cc-opt"><div class="box"></div> 3. Difficult to see.</div>
        <div class="cc-opt"><div class="box"></div> 4. Not visible at all</div>
        <div class="cc-opt"><div class="box"></div> 5. N/A</div>
    </div>

    <div class="cc-question">CC3. If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?</div>
    <div class="cc-options">
        <div class="cc-opt"><div class="box"></div> 1. Helped very much.</div>
        <div class="cc-opt"><div class="box"></div> 2. Somewhat helped.</div>
        <div class="cc-opt"><div class="box"></div> 3. Did not help.</div>
        <div class="cc-opt"><div class="box"></div> 4. N/A</div>
    </div>

    <div class="section-instr" style="margin-top:10px;">
        INSTRUCTIONS: For SQD 0-8, please put a check mark (✓) on the column that best corresponds to your answer.
    </div>

    <table class="sqd-table">
        <thead>
            <tr>
                <th class="col-label"></th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-frown-open emoji-icon"></i>
                        <span class="emoji-label">Strongly Disagree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-frown emoji-icon"></i>
                        <span class="emoji-label">Disagree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-meh emoji-icon"></i>
                        <span class="emoji-label">Neither Agree nor Disagree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-smile emoji-icon"></i>
                        <span class="emoji-label">Agree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-laugh-beam emoji-icon"></i>
                        <span class="emoji-label">Strongly Agree</span>
                    </div>
                </th>
                <th class="col-na">
                    <div class="header-content">
                        <span class="emoji-label" style="font-size:8.5pt;">N/A</span>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($sqds as $key => $text): ?>
            <tr>
                <td class="sqd-text"><strong><?= $key ?>.</strong> <?= $text ?></td>
                <td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-suggest">Suggestions on how we can further improve our services (optional):</div>
    <div class="line-fill"></div>

</body>
</html>