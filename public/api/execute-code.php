<?php
// User programs run inside WebAssembly or a separately managed isolated runner.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const CODESTEP_MAX_CODE_BYTES = 65536;
const CODESTEP_MAX_STDIN_BYTES = 16384;
const CODESTEP_MAX_REQUEST_BYTES = 262144;
const CODESTEP_MAX_OUTPUT_BYTES = 65536;

function executionResponse(int $status, string $output, bool $error = true, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(['output' => $output, 'error' => $error] + $extra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    executionResponse(405, 'Faqat POST so‘rovi qabul qilinadi.');
}

require_once dirname(__DIR__) . '/includes/auth.php';
codeStepSession();
$executionUser = codeStepUser();
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? null;
session_write_close();
if (!is_string($sessionToken) || !is_string($csrfToken) || $csrfToken === ''
    || !hash_equals($sessionToken, $csrfToken)) {
    executionResponse(403, 'So‘rov tasdiqlanmadi. Sahifani yangilab, qayta urinib ko‘ring.');
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > CODESTEP_MAX_REQUEST_BYTES) {
    executionResponse(413, 'So‘rov hajmi juda katta. Kodni va kiritish ma’lumotlarini qisqartiring.');
}
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType === 'application/json') {
    $rawBody = file_get_contents('php://input', false, null, 0, CODESTEP_MAX_REQUEST_BYTES + 1);
    if ($rawBody === false || strlen($rawBody) > CODESTEP_MAX_REQUEST_BYTES) {
        executionResponse(413, 'So‘rov hajmi juda katta.');
    }
    $request = json_decode($rawBody, true);
    if (!is_array($request) || json_last_error() !== JSON_ERROR_NONE || substr(ltrim($rawBody), 0, 1) !== '{') {
        executionResponse(400, 'So‘rov yaroqli JSON obyekt bo‘lishi kerak.');
    }
} elseif ($contentType === 'application/x-www-form-urlencoded' || $contentType === 'multipart/form-data') {
    $request = $_POST;
} else {
    executionResponse(415, 'So‘rov JSON yoki forma ko‘rinishida yuborilishi kerak.');
}

$code = $request['code'] ?? null;
$language = $request['language'] ?? null;
$stdin = array_key_exists('stdin', $request) ? $request['stdin'] : '';
if (!is_string($code) || !is_string($language) || !is_string($stdin)) {
    executionResponse(400, 'Kod, dasturlash tili va kiritish ma’lumotlari matn bo‘lishi kerak.');
}
if (!in_array($language, ['cpp', 'python'], true)) {
    executionResponse(400, 'Faqat C++ va Python tillari qo‘llab-quvvatlanadi.');
}
if (trim($code) === '') {
    executionResponse(400, 'Bajarish uchun kod yozing.');
}
if (strlen($code) > CODESTEP_MAX_CODE_BYTES || strlen($stdin) > CODESTEP_MAX_STDIN_BYTES) {
    executionResponse(413, 'Kod hajmi 64 KB, kiritish ma’lumotlari esa 16 KB dan oshmasligi kerak.');
}
if (strpos($code, "\0") !== false || strpos($stdin, "\0") !== false
    || preg_match('//u', $code) !== 1 || preg_match('//u', $stdin) !== 1) {
    executionResponse(400, 'Kod va kiritish ma’lumotlari UTF-8 matn bo‘lishi kerak.');
}

require_once dirname(__DIR__) . '/includes/code-execution.php';
if (array_key_exists('topic', $request) || array_key_exists('problem', $request)) {
    $requestedTopic = $request['topic'] ?? null;
    $requestedProblem = $request['problem'] ?? null;
    if ((!is_int($requestedTopic) && !is_string($requestedTopic))
        || !ctype_digit((string) $requestedTopic) || !is_string($requestedProblem)) {
        executionResponse(400, 'Mavzu va masala raqami noto‘g‘ri.');
    }
    $topicId = (int) $requestedTopic;

    if ($topicId >= 1000) {
        $contestId = $topicId - 1000;
        $db = solutionDb();
        solutionEnsureFeatureSchema($db);
        $cpStmt = $db->prepare("SELECT * FROM contest_problems WHERE contest_id = ? AND problem_order = ?");
        $cpStmt->execute([$contestId, $requestedProblem]);
        $cp = $cpStmt->fetch();
        if (!$cp) {
            executionResponse(404, 'Musobaqa masalasi topilmadi.');
        }

        $testStdin = (trim($stdin) !== '') ? $stdin : (string)$cp['sample_input'];
        $expectedOutput = trim((string)$cp['sample_output']);

        $result = codeExecutionRun($language, $code, $testStdin);
        $extra = isset($result['diagnostics']) ? ['diagnostics' => $result['diagnostics']] : [];

        $cleanActual = trim((string)($result['output'] ?? ''));
        $normActual = preg_replace('/\s+/', ' ', $cleanActual);
        $normExpected = preg_replace('/\s+/', ' ', $expectedOutput);

        $isSuccess = !$result['error'] && $result['status'] === 200 && ($normExpected === '' || $normActual === $normExpected);

        $verdictStatus = $isSuccess ? 'accepted' : ($result['error'] ? 'error' : 'wrong_answer');
        $verdictMessage = $isSuccess
            ? 'Tabriklaymiz! Masala muvaffaqiyatli yechildi (+ ' . $cp['points'] . ' ball).'
            : ($result['error']
                ? 'Dastur xatolik bilan yakunlandi. Kod sintaksisini tekshiring.'
                : 'Javob xato. Kutilgan: "' . $expectedOutput . '", olingan: "' . $cleanActual . '"');

        $extra['verdict'] = [
            'status' => $verdictStatus,
            'message' => $verdictMessage,
            'checks_passed' => $isSuccess ? 1 : 0,
            'total_checks' => 1,
            'scope' => 'contest',
            'topic' => $topicId,
            'problem' => $requestedProblem,
        ];
        $extra['test_stdin'] = $testStdin;

        if ($executionUser) {
            try {
                $extra['saved_run_id'] = solutionSaveRun($executionUser['id'], $topicId, $requestedProblem, $language, $code, [
                    'status' => $result['status'],
                    'output' => $result['output'],
                    'error' => $result['error'],
                    'verdict' => $extra['verdict'],
                    'test_stdin' => $testStdin
                ]);

                $regStmt = $db->prepare("INSERT OR IGNORE INTO contest_registrations (contest_id, user_id) VALUES (?, ?)");
                $regStmt->execute([$contestId, (int)$executionUser['id']]);

                $subScore = $isSuccess ? (int)$cp['points'] : 0;
                $subStmt = $db->prepare("INSERT INTO contest_submissions (contest_id, user_id, problem_order, language, code, verdict, score) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $subStmt->execute([$contestId, (int)$executionUser['id'], $requestedProblem, $language, $code, $verdictStatus, $subScore]);

                $extra['save_message'] = $isSuccess ? 'Yechim qabul qilindi va musobaqa natijalariga qo‘shildi!' : 'Urinish musobaqa tarixiga qayd etildi.';
            } catch (Throwable $error) {
                error_log('CodeStep contest submission: ' . $error->getMessage());
                $extra['save_message'] = 'Urinish tekshirildi.';
            }
        }
        executionResponse($result['status'], $result['output'], $result['error'], $extra);
    }

    require_once dirname(__DIR__) . '/includes/programming-topics.php';
    require_once dirname(__DIR__) . '/includes/problem-rules.php';
    require_once dirname(__DIR__) . '/includes/problem-judge.php';
    $topics = programmingTopicsIndex(programmingTopicsCatalog());
    $found = false;
    foreach ($topics[$topicId]['problems'] ?? [] as $problem) {
        if ($problem['number'] === $requestedProblem) $found = true;
    }
    if (!$found) executionResponse(404, 'Masala topilmadi. Mavzudan masalani qayta oching.');
    $rule = problemRule($topicId, $requestedProblem);
    if ($rule === null) {
        $result = codeExecutionRun($language, $code, $stdin);
        $extra = isset($result['diagnostics']) ? ['diagnostics' => $result['diagnostics']] : [];
        $isSuccess = !$result['error'] && $result['status'] === 200 && trim($result['output']) !== '';
        $extra['verdict'] = [
            'status' => $isSuccess ? 'accepted' : 'wrong_answer',
            'message' => $isSuccess
                ? 'Yechim qabul qilindi. Dastur kiritilgan qiymat(lar) bilan muvaffaqiyatli bajarildi.'
                : ($result['error']
                    ? 'Dastur xatolik bilan yakunlandi. Kod yoki kiritish ma’lumotlarini tekshiring.'
                    : 'Dastur chiqish ma’lumotisiz yakunlandi. Kirish ma’lumotlari (stdin) maydoniga qiymat kiriting.'),
            'checks_passed' => $isSuccess ? 1 : 0,
            'total_checks' => 1,
            'scope' => 'output',
            'topic' => $topicId,
            'problem' => $requestedProblem,
        ];
        $extra['test_stdin'] = $stdin;
        if ($executionUser) {
            try {
                $extra['saved_run_id'] = solutionSaveRun($executionUser['id'], $topicId, $requestedProblem, $language, $code, $result);
                $extra['save_message'] = 'Urinish va natija Yechimlarim tarixiga saqlandi.';
            } catch (Throwable $error) {
                error_log('CodeStep run storage: ' . $error->getMessage());
                $extra['saved_run_id'] = null;
            }
        }
        executionResponse($result['status'], $result['output'], $result['error'], $extra);
    }
    // Up to three separately bounded isolated executions for input-reading tasks.
    @set_time_limit(count($rule['tests'] ?? []) > 1 ? 100 : 35);
    $result = problemJudge($rule, $language, $code, $stdin);
    $extra = isset($result['diagnostics']) ? ['diagnostics' => $result['diagnostics']] : [];
    if (isset($result['verdict'])) {
        $extra['verdict'] = $result['verdict'] + ['topic' => $topicId, 'problem' => $requestedProblem];
        $extra['test_stdin'] = $result['test_stdin'];
    }
    if ($executionUser) {
        try {
            $extra['saved_run_id'] = solutionSaveRun($executionUser['id'], $topicId, $requestedProblem, $language, $code, $result);
            $extra['save_message'] = 'Urinish va natija Yechimlarim tarixiga saqlandi.';
        } catch (Throwable $error) {
            error_log('CodeStep run storage: ' . $error->getMessage());
            $extra['save_message'] = 'Natija olindi, lekin tarixga saqlanmadi. Kodni bazaga alohida saqlang yoki keyinroq qayta tekshiring.';
            $extra['saved_run_id'] = null;
        }
    }
    executionResponse($result['status'], $result['output'], $result['error'], $extra);
}
$result = codeExecutionRun($language, $code, $stdin);
executionResponse($result['status'], $result['output'], $result['error'], isset($result['diagnostics']) ? ['diagnostics' => $result['diagnostics']] : []);
