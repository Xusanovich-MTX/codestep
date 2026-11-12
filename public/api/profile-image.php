<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/profile-store.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
codeStepSession();
$user = codeStepUser();
session_write_close();
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
if (!$user) { http_response_code(401); exit; }
try {
    $image = profileAvatarData($user['id']);
    if (!$image) { http_response_code(404); exit; }
    header('Content-Type: ' . $image['avatar_mime']);
    header('Content-Length: ' . strlen($image['avatar']));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') echo $image['avatar'];
} catch (Throwable $error) {
    error_log('CodeStep read profile image: ' . $error->getMessage());
    http_response_code(503);
}
