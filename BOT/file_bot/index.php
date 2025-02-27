<?php

$botToken = isset($_GET['token']) ? $_GET['token'] : exit("Bot token required!");
$channelUsername = isset($_GET['channel']) ? $_GET['channel'] : exit("Channel username required!");
$botUsername = isset($_GET['bot']) ? $_GET['bot'] : exit("Bot username required!");
$firebaseUrl = isset($_GET['firebase']) ? $_GET['firebase'] : exit("Firebase URL required!");
$userCode = isset($_GET['code']) ? $_GET['code'] : exit("Verification code required!");

$code = "https://flex-file-default-rtdb.firebaseio.com";
function code($path) {
    global $code;
    $url = "$code$path.json";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

$codesData = code("/codes/$botToken");

if (!$codesData || $codesData['code'] !== $userCode) {
    exit("❌ Invalid code! Bot cannot be set up.");
}

$apiUrl = "https://mortisgamer.serv00.net/file_bot/?token=$botToken&channel=$channelUsername&bot=$botUsername&firebase=$firebaseUrl&code=$userCode";
$webhook = "https://api.telegram.org/bot$botToken/setWebhook?url=" . urlencode($apiUrl);
$response = file_get_contents($webhook);
if ($response === false) {
    echo "❌ Failed to connect to Telegram API.";
} else {
    $data = json_decode($response, true);
    if (isset($data["ok"]) && $data["ok"] == true) {
        echo "✅ Webhook successfully set!";
    } else {
        echo "❌ Failed to set webhook: " . ($data["description"] ?? "Unknown error");
    }
}
  
$apiUrl = "https://api.telegram.org/bot$botToken";
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}
$chat_id = $user_id;
$chat_id = $update["message"]["chat"]["id"];
$user_id = $update["message"]["from"]["id"];
$text = isset($update["message"]["text"]) ? $update["message"]["text"] : "";

if (strpos($text, "/start") === 0) {
    if (!isUserMember($user_id)) {
        sendJoinMessage($chat_id, $code);
        exit;
    }
    
    $code = trim(str_replace("/start", "", $text));
    
    if (!empty($code)) {
        sendStoredFiles($chat_id, $code);
    } else {
        start($chat_id);
    }
    exit;
}


$messageData = $update["message"];
$file_data = getFileData($messageData);

if (isset($update["callback_query"])) {
    $callback_query = $update["callback_query"];
    $callback_data = $callback_query["data"];
    $callback_chat_id = $callback_query["message"]["chat"]["id"];
    $message_id = $callback_query["message"]["message_id"];

    if ($callback_data === "upload") {
        deletePreviousMessage($callback_chat_id, $message_id);
        deleteFirebaseData("/users/$user_id/files.json");
        sendFileReceivedButton($callback_chat_id);

    }
}
  
  
if ($text === "✅") {
    $userFiles = getFirebaseData("/users/$user_id/files.json");

    if (!$userFiles) {
        sendMessage($chat_id, "❌ No files stored. Send files first.");
        exit;
    }

    $unique_code = generateCode();
    saveToFirebase("/shared/$unique_code.json", $userFiles);
    deleteFirebaseData("/users/$user_id/files.json");

    $bot_url = "https://t.me/$botUsername?start=$unique_code";
    
    // Generate Short URL
    $short_url = shortenUrl($bot_url);

    // Send URL Message
    sendUrlMessage($chat_id, $bot_url, $short_url);
    exit;
}


$file_data = getFileData($messageData);
if ($file_data) {
    list($file_type, $file_id) = $file_data;
    $existingFiles = getFirebaseData("/users/$user_id/files.json") ?? [];
    
    $existingFiles[] = ["type" => $file_type, "file_id" => $file_id];
    saveToFirebase("/users/$user_id/files.json", $existingFiles);
}
  
function deletePreviousMessage($chat_id, $message_id) {
    global $apiUrl;

    file_get_contents("$apiUrl/deleteMessage?" . http_build_query([
        "chat_id" => $chat_id,
        "message_id" => $message_id
    ]));
}
  
function start($chat_id) {
    global $apiUrl;

    $imageUrl = "https://t.me/roelement/190";
    $caption = "*Welcome! Upload your files easily.*";
    $keyboard = json_encode([
        "inline_keyboard" => [
            [["text" => "📤 Upload File", "callback_data" => "upload"]],
            [["text" => "🔗 Visit Website", "url" => "https://your-website.com"]]
        ]
    ]);

    file_get_contents("$apiUrl/sendPhoto?" . http_build_query([
        "chat_id" => $chat_id,
        "photo" => $imageUrl,
        "caption" => $caption,
        "parse_mode" => "Markdown",
        "reply_markup" => $keyboard
    ]));
}  
  
function sendFileReceivedButton($chat_id) {
    global $apiUrl;
    file_get_contents("$apiUrl/deleteMessage?" . http_build_query([
        "chat_id" => $chat_id,
        "message_id" => $message_id
    ]));
    $keyboard = json_encode([
        "keyboard" => [[["text" => "✅"]]],
        "resize_keyboard" => true,
        "one_time_keyboard" => false
    ]);

    $params = [
        "chat_id" => $chat_id,
        "text" => "📤 Send any file, video, image, GIF, sticker, or text to get a shareable link.",
        "reply_markup" => $keyboard
    ];

    file_get_contents("$apiUrl/sendMessage?" . http_build_query($params));
}

function shortenUrl($long_url) {
    $api_key = "a9760d077a4457235abc53821148e414b45d1b8d";
    $api_url = "https://api.modijiurl.com/api?api=$api_key&url=" . urlencode($long_url);
    $response = file_get_contents($api_url);
    $result = json_decode($response, true);
    return isset($result['shortenedUrl']) ? $result['shortenedUrl'] : $long_url;
}
  
  function sendUrlMessage($chat_id, $bot_url, $short_url) {
    global $apiUrl;

    $text = "✅ **Your files are stored!**\n🔗 **Here is your URL:**";

    $keyboard = [
        "inline_keyboard" => [
            [["text" => "🔗 Bot Generated URL", "url" => $bot_url]],
            [["text" => "🔗 Shortened URL", "url" => $short_url]]        
      ]
    ];

    $params = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "Markdown",
        "reply_markup" => json_encode($keyboard)
    ];

    file_get_contents("$apiUrl/sendMessage?" . http_build_query($params));
}
  
function sendStoredFiles($chat_id, $code) {
    $data = getFirebaseData("/shared/$code.json");
     if ($code === "start") {
        sendFileReceivedButton($chat_id);
        return;
    }
    if ($data=== "start") {
      sendFileReceivedButton($chat_id);
    return;
}

if (!$data) {
    sendMessage($chat_id, "❌ Invalid code or expired data.");
    return;
}

    foreach ($data as $file) {
    sendFile($chat_id, $file["type"], $file["file_id"]);
}

}

function saveToFirebase($path, $data) {
    global $firebaseUrl;
    $jsonData = json_encode($data);
    $url = "$firebaseUrl$path";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Change from PUT to PATCH
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}


// Function to get data from Firebase
function getFirebaseData($path) {
    global $firebaseUrl;
    $url = "$firebaseUrl$path";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Function to delete data from Firebase
function deleteFirebaseData($path) {
    global $firebaseUrl;
    $url = "$firebaseUrl$path";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
  
// Function to send files
function sendFile($chat_id, $file_type, $file_id) {
    global $apiUrl;
    $endpoint = [
        "text" => "sendMessage",
        "photo" => "sendPhoto",
        "video" => "sendVideo",
        "document" => "sendDocument",
        "sticker" => "sendSticker",
        "animation" => "sendAnimation"
    ];

    if (!isset($endpoint[$file_type])) {
        sendMessage($chat_id, "❌ File type not supported.");
        return;
    }

    $url = "$apiUrl/{$endpoint[$file_type]}";

    $postData = [
        "chat_id" => $chat_id,
        $file_type => $file_id,
        "protect_content" => true  // Content protection enabled
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// Function to check if user is a member
// Function to check if user is a member
function isUserMember($user_id) {
    global $botToken, $channelUsername;

    // Ensure the channel username is formatted correctly
    if (strpos($channelUsername, "@") !== 0) {
        $channelUsername = "@" . $channelUsername;
    }

    $url = "https://api.telegram.org/bot$botToken/getChatMember?chat_id=$channelUsername&user_id=$user_id";
    $response = json_decode(file_get_contents($url), true);

    if ($response && isset($response["result"]["status"])) {
        $status = $response["result"]["status"];
        return in_array($status, ["member", "administrator", "creator"]);
    }

    return false; // User is not a member
}


// Function to send join message
// Function to send join message
function sendJoinMessage($chat_id, $code) {
    global $apiUrl, $channelUsername, $botUsername;

    $startCode = !empty($code) ? $code : "start";

    $startUrl = "https://t.me/$botUsername?start=$startCode";
    $channelLink = "https://t.me/" . str_replace("@", "", $channelUsername);

    $text = "*Must Join Following Channel To Use This Bot 🔽*";

    $keyboard = json_encode([
        "inline_keyboard" => [
            [["text" => "🔔 Join Channel", "url" => $channelLink]],
            [["text" => "✅ I Joined, Start Bot", "url" => $startUrl]]
        ]
    ]);

    file_get_contents("$apiUrl/sendMessage?" . http_build_query([
        "chat_id" => $chat_id,
        "text" => $text,
        "reply_markup" => $keyboard,
        "parse_mode" => "markdown"                                                         
                                                                
    ]));
}

  
// Function to get file data
function getFileData($message) {
    if (isset($message["text"])) return ["text", $message["text"]];
    if (isset($message["photo"])) return ["photo", end($message["photo"])["file_id"]];
    if (isset($message["video"])) return ["video", $message["video"]["file_id"]];
    if (isset($message["document"])) return ["document", $message["document"]["file_id"]];
    if (isset($message["sticker"])) return ["sticker", $message["sticker"]["file_id"]];
    if (isset($message["animation"])) return ["animation", $message["animation"]["file_id"]];
    return null;
}

// Function to generate a unique code
function generateCode($length = 8) {
    return substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, $length);
}

// Function to send a message
function sendMessage($chat_id, $text, $protected = false) {
    global $apiUrl;
    $params = [
        "chat_id" => $chat_id,
        "text" => $text
    ];
    if ($protected) {
        $params["protect_content"] = true;
    }
    file_get_contents("$apiUrl/sendMessage?" . http_build_query($params));
}

?>