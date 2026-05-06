<?php
require_once 'env_loader.php';
header('Content-Type: application/json');

$key = $_GET['apicheck'] ?? '';
$stmt = $conn->prepare("SELECT credits, used_requests, expiry_date FROM api_users WHERE api_token = ?");
$stmt->bind_param("s", $key);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    echo json_encode([
        "status" => "success",
        "credits" => (int)$res['credits'],
        "used" => (int)$res['used_requests'],
        "expiry" => $res['expiry_date']
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Token"]);
}
