<?php
$botToken = "7629301926:AAFcFkiDk2a6_SGMOmGmKG-gaD5Hk85ABpY";
$apiUrl = "https://api.telegram.org/bot$botToken/";
$channelId = "@flex_coder"; // Channel username or ID
$modijiApiKey = "a9760d077a4457235abc53821148e414b45d1b8d";
$botUsername = "FLEX_FILE_ROBOT"; 

define('FIREBASE_DATABASE_URL', 'https://flex-file-default-rtdb.firebaseio.com/');
define('FIREBASE_SECRET', 'AIzaSyDTIo0l30HOkUX77J2N9b3wLUUZdX4VlKc'); 
  
function firebaseGet($path) {
    $url = FIREBASE_DATABASE_URL . $path . ".json?auth=" . FIREBASE_SECRET;
    return json_decode(file_get_contents($url), true);
}

function firebasePut($path, $data) {
    $url = FIREBASE_DATABASE_URL . $path . ".json?auth=" . FIREBASE_SECRET;
    $options = [
        'http' => [
            'method'  => 'PUT',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    $context  = stream_context_create($options);
    return json_decode(file_get_contents($url, false, $context), true);
}

$update = json_decode(file_get_contents("php://input"), true);
$chatId = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? null;

if ($chatId && preg_match("/^\\/start(?:\\s+(\\S+))?$/", $text, $matches)) {
    $code = $matches[1] ?? null;

    $checkUrl = $apiUrl . "getChatMember?chat_id=$channelId&user_id=$chatId";
    $response = json_decode(file_get_contents($checkUrl), true);
    $status = $response['result']['status'] ?? "left";

    if (!in_array($status, ["member", "administrator", "creator"])) {
        $joinMessage = "🔔 Join our channel to continue:";
        $keyboard = [
            "inline_keyboard" => [
                [["text" => "📢 Join Channel", "url" => "https://t.me/Flex_Coder"]],
                [["text" => "✅ Check", "callback_data" => "check_join"]]
            ]
        ];
        file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($joinMessage) . "&reply_markup=" . json_encode($keyboard));
        exit;
    }
  
if ($chatId && $text === "/get") {
    $message = "✅ Here is your requested text!";
    file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($message));
}
   
    if ($code) {
        $usedCodes = firebaseGet("used_codes/$code");

        if ($usedCodes) {
            file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⚠️ This link has already been used. Generate a new one."));
            exit;
        }

        // Store user progress in Firebase
        firebasePut("user_data/$chatId", ["code" => $code]);

        file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("📧 Enter your token:"));
        exit;
    }

    // Generate a new unique code
    $newCode = uniqid();
    $uniqueUrl = "https://t.me/$botUsername?start=$newCode";

    // Shorten the unique URL using Modiji API
    $shortenApiUrl = "https://api.modijiurl.com/api?api=$modijiApiKey&url=" . urlencode($uniqueUrl);
    $apiResponse = json_decode(file_get_contents($shortenApiUrl), true);

    if ($apiResponse && isset($apiResponse['shortenedUrl'])) {
        $shortenedUrl = $apiResponse['shortenedUrl'];
        $message = "🎉 Here is your unique link:\n🔗 $shortenedUrl\n\n⚡ Click the link to continue.";
    } else {
        $message = "❌ Failed to generate a shortened URL. Please try again.";
    }

    // Store generated code in Firebase
    firebasePut("user_data/$chatId", ["code" => $newCode]);

    file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($message));
    exit;
}

// Handle token input after generating a link
if ($chatId && preg_match('/^[a-zA-Z0-9:_-]+$/', $text)) {
    $userData = firebaseGet("user_data/$chatId");

    if (!$userData) {
        file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⚠️ You haven't generated a link yet. Please use /start first."));
        exit;
    }

    $code = $userData['code'];

    // Mark the code as used
    firebasePut("used_codes/$code", true);

    // Remove user from pending data
    firebasePut("user_data/$chatId", null);

    // Call API with token
    $apiResponse = file_get_contents("https://mortisgamer.serv00.net/file_bot/generate.php?token=" . urlencode($text) . "&day=1m");
    $decodedResponse = json_decode($apiResponse, true);

    if ($decodedResponse && isset($decodedResponse['code']) && isset($decodedResponse['expiry'])) {
        $generatedCode = $decodedResponse['code'];
        $expiryDate = $decodedResponse['expiry'];

        $message = "✅ **Your Code:** `$generatedCode`\n📅 **Expiry:** $expiryDate";

        file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($message) . "&parse_mode=Markdown");
    } else {
        file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ Failed to generate a code. Please try again later."));
    }
    exit;
}
?>
