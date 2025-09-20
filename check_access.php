<?php
require_once 'config.php';
require_once 'libs/telegram.php';

header('Content-Type: application/json');

$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? null;
$session_id = $data['session_id'] ?? session_id();
$page = $data['page'] ?? '';
$referrer = $data['referrer'] ?? '';
$ua = $data['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'];

// Check if IP is whitelisted
$stmt = $pdo->prepare("SELECT 1 FROM whitelisted_ips WHERE ip = ?");
$stmt->execute([$ip]);
$whitelisted = $stmt->fetchColumn();

// Check if IP is blocked
$stmt = $pdo->prepare("SELECT 1 FROM blocked_ips WHERE ip = ?");
$stmt->execute([$ip]);
$blocked = $stmt->fetchColumn();

// Check if session is revoked
$stmt = $pdo->prepare("SELECT 1 FROM revoked_sessions WHERE session_id = ?");
$stmt->execute([$session_id]);
$revoked = $stmt->fetchColumn();

// Check if user is revoked
$user_revoked = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT revoked FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_revoked = $stmt->fetchColumn();
}

// Block/revoke logic: if whitelisted, override block/revoke
$is_blocked = ($blocked || $revoked || $user_revoked) && !$whitelisted;

// Mark suspicious if UA is empty or IP is localhost, unless whitelisted
$suspicious = ($ua == '' || $ip == '127.0.0.1' || $blocked || $revoked || $user_revoked) && !$whitelisted;

// Log access
$stmt = $pdo->prepare("INSERT INTO access_logs (user_id, session_id, ip, page, ua, suspicious) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $session_id, $ip, $page, $ua, $suspicious ? 1 : 0]);

if ($suspicious) {
    sendTelegramMessage("Suspicious activity detected:\nUser: $user_id\nSession: $session_id\nIP: $ip\nPage: $page\nUA: $ua");
}

echo json_encode([
    'blocked' => $is_blocked ? true : false
]);
