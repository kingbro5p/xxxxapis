<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$key  = isset($_GET['key'])  ? trim($_GET['key'])  : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'reseller';

if (empty($key)) {
    echo json_encode(['status' => 'error', 'message' => 'No key provided']);
    exit();
}

// type অনুযায়ী আলাদা URL
if ($type === 'main') {
    // আপনার নিজের API
    $url = 'http://api.ucbot.store/status/' . urlencode($key);
} else {
    // Reseller দের পুরনো endpoint
    $url = 'http://localhost/apicheck.php?apicheck=' . urlencode($key);
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_USERAGENT      => 'RxHoster-Admin/1.0',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $response === false) {
    echo json_encode(['status' => 'error', 'message' => 'cURL error: ' . $curlErr]);
    exit();
}

$data = json_decode($response, true);

if ($type === 'main') {
    // আপনার API এর response convert করা
    if ($data && isset($data['data'])) {
        $inner = $data['data'];
        echo json_encode([
            'status'            => ($inner['account_status'] === 'active') ? 'success' : 'error',
            'available_credits' => $inner['credits']['limit_left']      ?? 0,
            'Used_credits'      => $inner['credits']['used_this_month'] ?? 0,
            'total_credits'     => $inner['credits']['max_limit']       ?? 0,
            'expiry_date'       => $inner['expiry_date']                ?? null,
            'message'           => $inner['account_status']             ?? 'unknown',
        ]);
    } else {
        echo $response;
    }
} else {
    // Reseller এর response সরাসরি পাঠিয়ে দাও
    echo $response;
}
exit();