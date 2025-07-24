<?php
$url = "http://localhost/Verify_email/backend/app/reply.php";

$data = [
    "account_id" => 1,
    "to" => "someone@example.com",
    "subject" => "Test Subject from PHP",
    "body" => "This is a test reply from PHP directly."
];

// Initiate cURL session
$ch = curl_init($url);

// Encode the data to JSON
$jsonData = json_encode($data);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

// Execute the request
$response = curl_exec($ch);

// Handle errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
} else {
    echo "Response from reply.php: " . $response;
}

// Close cURL session
curl_close($ch);
