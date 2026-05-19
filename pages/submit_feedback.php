// Inside submit_feedback.php
$submission_source = 'Online';

$stmt = $conn->prepare("INSERT INTO csm_submissions (age, sex, region_id, client_type, service_id, submission_source, cc1, cc2, cc3, cc3_reason, sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, suggestions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("isisisiiissiiiiiiiii", 
    $age, $sex, $region_id, $client_type, $service_id, $submission_source, 
    $cc1, $cc2, $cc3, $cc3_reason, $sqd0, $sqd1, $sqd2, $sqd3, $sqd4, $sqd5, $sqd6, $sqd7, $sqd8, $suggestions
);
// ... execute and check success