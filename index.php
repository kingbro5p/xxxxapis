
<?php
require_once 'env_loader.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Dhaka');

$headers = getallheaders();
$client_token = $headers['Authorization'] ?? '';

// ── Reseller verify ──
$stmt = $conn->prepare("SELECT * FROM api_users WHERE api_token = ? AND status = 1");
$stmt->bind_param("s", $client_token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["status" => "error", "message" => "Invalid Authorization Token"]);
    exit;
}

if ($user['credits'] <= 0) {
    echo json_encode(["status" => "error", "message" => "Insufficient Credits!"]);
    exit;
}

if (date('Y-m-d H:i:s') > $user['expiry_date']) {
    echo json_encode(["status" => "error", "message" => "Your API Key has expired!"]);
    exit;
}

// ── Request body নেওয়া ──
$raw_input = file_get_contents("php://input");
$body      = json_decode($raw_input, true);

// ── Item count বের করা ──
$code    = $body['code']    ?? '';
$package = $body['package'] ?? $body['pacakge'] ?? '';

if (!empty($package)) {
    // package field আছে → package count দিয়ে deduct
    $items          = array_filter(array_map('trim', explode(',', $package)));
    $expected_count = count($items);
} else {
    // package নেই → code count দিয়ে deduct
    $items          = array_filter(array_map('trim', explode(',', $code)));
    $expected_count = count($items);
}

if ($expected_count === 0) {
    echo json_encode(["status" => "error", "message" => "No valid code or package provided"]);
    exit;
}

// ── Credits যথেষ্ট আছে কিনা ──
if ($user['credits'] < $expected_count) {
    echo json_encode([
        "status"  => "error",
        "message" => "Insufficient Credits! Need {$expected_count}, have {$user['credits']}"
    ]);
    exit;
}

// ── Main API তে forward ──
$ch = curl_init($_ENV['SOURCE_API_URL']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: " . $user['source_token'],
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $raw_input);
curl_setopt($ch, CURLOPT_TIMEOUT,    120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response) {
    echo json_encode(["status" => "error", "message" => "Service unavailable"]);
    exit;
}

// ── Request গেলেই full count deduct (main API এর মতো) ──
$conn->query("UPDATE api_users 
              SET credits       = credits - {$expected_count},
                  used_requests = used_requests + {$expected_count}
              WHERE id = " . $user['id']);

// ── Response পাঠানো ──
http_response_code($http_code);
echo $response;
$conn->close();