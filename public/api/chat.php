<?php
require_once __DIR__ . '/../includes/ai-chat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$respond = static function (array $result): void {
    http_response_code($result['status']);
    echo json_encode(['reply' => $result['reply']], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    $respond(aiChatResult(405, 'Bu manzil faqat POST so‘rovini qabul qiladi.'));
}

$raw = file_get_contents('php://input', false, null, 0, 80001);
if (strlen($raw) > 80000) {
    $respond(aiChatResult(413, 'So‘rov juda katta. Qisqaroq xabar yuboring.'));
}
$input = json_decode($raw, true);
if (!is_array($input)) {
    $respond(aiChatResult(400, 'So‘rov formati noto‘g‘ri.'));
}
$message = trim(is_string($input['message'] ?? null) ? $input['message'] : '');
if ($message === '') {
    $respond(aiChatResult(400, 'Xabar bo‘sh.'));
}
if (strlen($message) > 12000) {
    $respond(aiChatResult(413, 'Xabar juda uzun. 12 000 baytdan qisqaroq xabar yuboring.'));
}
$history = is_array($input['history'] ?? null) ? array_slice($input['history'], -12) : [];

$respond(aiChatRequest($message, aiChatConfig(), $history));
