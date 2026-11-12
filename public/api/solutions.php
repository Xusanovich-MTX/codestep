<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/programming-topics.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function solutionResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
codeStepSession();
$user = codeStepUser();
$csrfValid = codeStepCsrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
session_write_close();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    solutionResponse(405, ['error' => true, 'message' => 'Faqat POST so‘rovi qabul qilinadi.']);
}
if (!$user) solutionResponse(401, ['error' => true, 'message' => 'Bazaga saqlash uchun hisobingizga kiring.']);
if (!$csrfValid) solutionResponse(403, ['error' => true, 'message' => 'Seans yangilangan. Sahifani yangilang va qayta urinib ko‘ring.']);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) solutionResponse(413, ['error' => true, 'message' => 'Kod hajmi juda katta.']);
$topic = $_POST['topic'] ?? null;
$problem = $_POST['problem'] ?? null;
$language = $_POST['language'] ?? null;
$code = $_POST['code'] ?? null;
$revision = $_POST['revision'] ?? null;
if (!is_string($topic) || !ctype_digit($topic) || !is_string($problem)
    || !is_string($language) || !in_array($language, ['cpp', 'python'], true) || !is_string($code)
    || !is_string($revision) || !ctype_digit($revision) || strlen($revision) > 9) {
    solutionResponse(400, ['error' => true, 'message' => 'Masala, til yoki kod versiyasi noto‘g‘ri.']);
}
$topics = programmingTopicsIndex(programmingTopicsCatalog());
$found = false;
foreach ($topics[(int) $topic]['problems'] ?? [] as $candidate) {
    if ($candidate['number'] === $problem) $found = true;
}
if (!$found) solutionResponse(404, ['error' => true, 'message' => 'Masala topilmadi.']);
try {
    if (($_POST['action'] ?? '') === 'load') {
        $draft = solutionDrafts($user['id'], (int) $topic, $problem)[$language] ?? null;
        solutionResponse(200, ['error' => false, 'draft' => $draft]);
    }
    if (isset($_POST['action']) && $_POST['action'] !== 'save') solutionResponse(400, ['error' => true, 'message' => 'Noto‘g‘ri amal.']);
    $saved = solutionSaveDraft($user['id'], (int) $topic, $problem, $language, $code, (int) $revision);
    solutionResponse(200, ['error' => false] + $saved);
} catch (SolutionConflict $error) {
    solutionResponse(409, ['error' => true, 'message' => 'Bu masala boshqa oynada yangilangan. Kodingizni yuklab oling, so‘ng bazadagi nusxani oching.', 'current_revision' => $error->currentRevision]);
} catch (InvalidArgumentException $error) {
    solutionResponse(400, ['error' => true, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('CodeStep draft storage: ' . $error->getMessage());
    solutionResponse(503, ['error' => true, 'message' => 'Bazaga saqlanmadi. Kod brauzerda qoldi; qayta urinib ko‘ring.']);
}
