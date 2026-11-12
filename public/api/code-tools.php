<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function codeToolsRespond(int $status, array $body): void
{
    http_response_code($status);
    unset($body['status']);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    codeToolsRespond(405, ['error' => true, 'message' => 'Faqat POST so\'rovi qabul qilinadi.']);
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])
    || !is_string($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    codeToolsRespond(403, ['error' => true, 'message' => 'So\'rov tasdiqlanmadi. Sahifani yangilang.']);
}
session_write_close();
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 393216) {
    codeToolsRespond(413, ['error' => true, 'message' => 'So\'rov hajmi chegaradan oshdi.']);
}
$contentType = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);
if ($contentType === 'application/json') {
    $raw = file_get_contents('php://input', false, null, 0, 393217);
    if ($raw === false || strlen($raw) > 393216) {
        codeToolsRespond(413, ['error' => true, 'message' => 'So\'rov hajmi chegaradan oshdi.']);
    }
    $request = json_decode($raw, true);
} else {
    $request = $_POST;
}
if (!is_array($request) || !isset($request['code']) || !is_string($request['code'])
    || strpos($request['code'], "\0") !== false || preg_match('//u', $request['code']) !== 1) {
    codeToolsRespond(400, ['error' => true, 'message' => 'Kod UTF-8 matn ko\'rinishida bo\'lishi kerak.']);
}
if (strlen($request['code']) > 65536 || substr_count($request['code'], "\n") > 8000) {
    codeToolsRespond(413, ['error' => true, 'message' => 'Kod hajmi 64 KB yoki 8000 qatordan oshmasligi kerak.']);
}
$language = $request['language'] ?? '';
$action = $request['action'] ?? 'check';
$style = $request['cpp_style'] ?? 'kr';
if (!in_array($language, ['python', 'cpp'], true) || !in_array($action, ['check', 'format', 'syntax'], true)
    || ($action === 'syntax' && $language !== 'python')
    || !in_array($style, ['kr', 'allman'], true)) {
    codeToolsRespond(400, ['error' => true, 'message' => 'Til, amal yoki C++ uslubi noto\'g\'ri.']);
}
require dirname(__DIR__, 2) . '/tools/code-tools.php';
$result = codeToolsRun(['code' => $request['code'], 'language' => $language, 'action' => $action, 'cpp_style' => $style]);
codeToolsRespond($result['status'] ?? 200, $result);
