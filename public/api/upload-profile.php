<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/profile-store.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function uploadProfileResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
codeStepSession();
$user = codeStepUser();
$validCsrf = codeStepCsrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
session_write_close();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    uploadProfileResponse(405, ['error' => 'Faqat POST so‘rovi qabul qilinadi.']);
}
if (!$user) uploadProfileResponse(401, ['error' => 'Rasm yuklash uchun hisobingizga kiring.']);
if (!$validCsrf) uploadProfileResponse(403, ['error' => 'Seans yangilangan. Sahifani yangilab qayta urinib ko‘ring.']);
$maxSize = 2 * 1024 * 1024;
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxSize + 65536) uploadProfileResponse(413, ['error' => 'Rasm hajmi 2 MB dan oshmasligi kerak.']);
$upload = $_FILES['profile_image'] ?? null;
if (!is_array($upload) || !is_int($upload['error'] ?? null)) uploadProfileResponse(400, ['error' => 'Rasm tanlang.']);
if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($upload['size'] ?? 0) > $maxSize) {
    uploadProfileResponse(413, ['error' => 'Rasm hajmi 2 MB dan oshmasligi kerak.']);
}
if ($upload['error'] !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
    uploadProfileResponse(400, ['error' => 'Rasm yuklanmadi. Qayta urinib ko‘ring.']);
}
$bytes = file_get_contents($upload['tmp_name'], false, null, 0, $maxSize + 1);
if (!is_string($bytes) || $bytes === '') uploadProfileResponse(400, ['error' => 'Rasm fayli bo‘sh yoki o‘qilmadi.']);
if (strlen($bytes) > $maxSize) uploadProfileResponse(413, ['error' => 'Rasm hajmi 2 MB dan oshmasligi kerak.']);
$info = @getimagesizefromstring($bytes);
$allowed = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
if (!is_array($info) || !isset($allowed[$info[2]]) || ($info['mime'] ?? '') !== $allowed[$info[2]]) {
    uploadProfileResponse(415, ['error' => 'Faqat haqiqiy JPG, PNG yoki WebP rasm yuklash mumkin.']);
}
if ($info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096) uploadProfileResponse(400, ['error' => 'Rasm o‘lchami 4096 × 4096 pikseldan oshmasin.']);
try {
    profileAvatar($user['id'], $bytes, $allowed[$info[2]]);
    uploadProfileResponse(200, ['image' => 'api/profile-image.php?v=' . bin2hex(random_bytes(8))]);
} catch (Throwable $error) {
    error_log('CodeStep profile image: ' . $error->getMessage());
    uploadProfileResponse(503, ['error' => 'Rasmni saqlab bo‘lmadi. Keyinroq qayta urinib ko‘ring.']);
}
