<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$theme = $_POST['theme'] ?? 'dark';

if (!in_array($theme, ['dark', 'light'])) {
    $theme = 'dark';
}

// Mavzuni cookie-ga saqlash (30 kun)
setcookie('theme', $theme, time() + (86400 * 30), "/");

echo json_encode(['success' => true, 'theme' => $theme]);
?>