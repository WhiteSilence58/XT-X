<?php
require_once 'includes/config.php';

echo "Testing VPS API Connection...\n\n";
echo "VPS URL: " . VPS_API_URL . "\n";
echo "API Key: " . substr(VPS_API_KEY, 0, 20) . "...\n\n";

// Test Health
$ch = curl_init(VPS_API_URL . '/api/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";

if ($error) {
    echo "cURL Error: $error\n";
}

$result = json_decode($response, true);

if ($result && $result['success']) {
    echo "\n✅ VPS API is reachable and working!\n";
} else {
    echo "\n❌ VPS API connection failed!\n";
}
?>