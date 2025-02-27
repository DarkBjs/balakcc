<?php

$botToken = isset($_GET['token']) ? $_GET['token'] : exit(json_encode(["error" => "Bot token required!"]));
$expiryParam = isset($_GET['day']) ? $_GET['day'] : "1n"; // Default to '1n' (no deletion)

// Function to generate a unique code for the bot token
function generateCode($botToken) {
    return substr(hash('sha256', $botToken . time()), 0, 10);
}

// Function to calculate expiration timestamp
function calculateExpiry($expiryParam) {
    switch (substr($expiryParam, -1)) {
        case 'd':
            return strtotime("+" . intval($expiryParam) . " days");
        case 'm':
            return strtotime("+" . intval($expiryParam) . " months");
        case 'y':
            return strtotime("+" . intval($expiryParam) . " years");
        case 'n':
            return null; // No expiration
        default:
            exit(json_encode(["error" => "Invalid expiry format! Use 1d, 1m, 1y, or 1n."]));
    }
}

// Load existing data
$file = "codes.json";
$codesData = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

// If token already exists, return the existing code and expiry date
if (isset($codesData[$botToken])) {
    echo json_encode([
        "token" => $botToken,
        "code" => $codesData[$botToken]["code"],
        "expiry" => $codesData[$botToken]["expiry"] ? date("Y-m-d H:i:s", $codesData[$botToken]["expiry"]) : "Never"
    ]);
    exit;
}

// Generate a new code and expiry
$generatedCode = generateCode($botToken);
$expiryTime = calculateExpiry($expiryParam);

// Store the new token, code, and expiry in JSON
$codesData[$botToken] = [
    "code" => $generatedCode,
    "expiry" => $expiryTime
];

file_put_contents($file, json_encode($codesData, JSON_PRETTY_PRINT));

// Return JSON response
echo json_encode([
    "token" => $botToken,
    "code" => $generatedCode,
    "expiry" => $expiryTime ? date("Y-m-d H:i:s", $expiryTime) : "Never"
]);

?>
