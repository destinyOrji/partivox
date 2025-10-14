<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'auth_token_in_storage' => 'Check localStorage in browser console',
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>
