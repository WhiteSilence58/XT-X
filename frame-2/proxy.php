<?php
header('Content-Type: application/json');

$vps_url = 'https://api.kafka-frame.de/api/trigger_watcher';
$data = file_get_contents('php://input');

$ch = curl_init($vps_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>