<?php
require_once dirname(__DIR__) . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function passwordResponse(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
codeStepSession();
$user = codeStepUser();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    passwordResponse(405, ['error' => true, 'message' => 'Faqat POST so‘rovi qabul qilinadi.']);
}
if (!$user) passwordResponse(401, ['error' => true, 'message' => 'Hisobingizga kiring.']);
if (!codeStepCsrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) passwordResponse(403, ['error' => true, 'message' => 'Seans yangilangan. Sahifani yangilang.']);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) passwordResponse(413, ['error' => true, 'message' => 'So‘rov hajmi juda katta.']);
$current = $_POST['current_password'] ?? null;
$next = $_POST['new_password'] ?? null;
$confirm = $_POST['password_confirm'] ?? null;
if (!is_string($current) || !is_string($next) || !is_string($confirm)
    || strlen($current) > 72 || strlen($next) < 8 || strlen($next) > 72
    || strpos($current, "\0") !== false || strpos($next, "\0") !== false) {
    passwordResponse(400, ['error' => true, 'message' => 'Parollarni to‘ldiring. Yangi parol kamida 8 ta belgidan iborat bo‘lsin va juda uzun bo‘lmasin.']);
}
if (!hash_equals($next, $confirm)) passwordResponse(400, ['error' => true, 'message' => 'Yangi parollar bir xil emas.']);
try {
    $bucket = codeStepAuthBucket('password', (string) $user['id']);
    if (codeStepAuthLimited($bucket, 10)) passwordResponse(429, ['error' => true, 'message' => 'Urinishlar soni ko‘payib ketdi. 15 daqiqadan keyin qayta urinib ko‘ring.']);
    $db = solutionDb();
    $query = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $query->execute([$user['id']]);
    $oldHash = $query->fetchColumn();
    $query->closeCursor();
    if (!is_string($oldHash) || !password_verify($current, $oldHash)) {
        codeStepAuthFailure($bucket);
        passwordResponse(400, ['error' => true, 'message' => 'Joriy parol noto‘g‘ri.']);
    }
    $newHash = password_hash($next, PASSWORD_DEFAULT);
    $query = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
    $query->execute([$newHash, $user['id'], $oldHash]);
    if ($query->rowCount() !== 1) passwordResponse(409, ['error' => true, 'message' => 'Parol boshqa oynada yangilangan. Hisobga qayta kiring.']);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    passwordResponse(200, ['error' => false, 'message' => 'Parol yangilandi. Keyingi kirishda yangi paroldan foydalaning.', 'csrf_token' => $_SESSION['csrf_token']]);
} catch (Throwable $error) {
    error_log('CodeStep password update: ' . $error->getMessage());
    passwordResponse(503, ['error' => true, 'message' => 'Parolni yangilab bo‘lmadi. Keyinroq qayta urinib ko‘ring.']);
}
