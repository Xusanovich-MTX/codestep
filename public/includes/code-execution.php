<?php
/** Execute code using one explicitly selected isolated service per request. */
function codeExecutionResult(int $status, string $output, bool $error = true): array
{
    return ['status' => $status, 'output' => $output, 'error' => $error];
}

function codeExecutionConfig(): array
{
    $path = dirname(__DIR__, 2) . '/tools/execution.config.json';
    if (!is_file($path)) return [];
    $config = json_decode((string) file_get_contents($path), true);
    return is_array($config) ? $config : [];
}

function codeExecutionProvider(): string
{
    $url = getenv('CODESTEP_RUNNER_URL');
    if (is_string($url) && trim($url) !== '') return 'private';
    $provider = getenv('CODESTEP_EXECUTION_PROVIDER');
    if (is_string($provider) && $provider !== '') return $provider;
    $cfg = codeExecutionConfig()['provider'] ?? null;
    if (is_string($cfg) && $cfg !== '') return $cfg;
    return 'piston';
}

function codeExecutionRunnerUrlValid(string $url): bool
{
    if (preg_match('/[\x00-\x20\x7f]/', $url)) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) return false;
    return $parts['scheme'] === 'https'
        || ($parts['scheme'] === 'http' && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '[::1]'], true));
}

/** Bounded HTTPS request. Never retry through an unverified or different service. */
function codeExecutionPost(string $url, array $payload, array $headers = [], int $timeout = 25): array
{
    if (!codeExecutionRunnerUrlValid($url)) {
        return codeExecutionResult(503, 'Kod bajarish xizmati manzili noto‘g‘ri sozlangan.');
    }
    if (!function_exists('curl_init')) {
        return codeExecutionResult(503, 'Tashqi kod bajarish xizmati uchun serverda cURL kengaytmasi kerak.');
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) return codeExecutionResult(400, 'Kod va kiritish ma’lumotlari UTF-8 matn bo‘lishi kerak.');
    $body = '';
    $tooLarge = false;
    // A 64 KiB Unicode result may be escaped into six ASCII bytes per byte.
    $maxBody = 65536 * 6 + 4096;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        CURLOPT_USERAGENT => 'CodeStep/2.0',
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBody): int {
            if (strlen($body) + strlen($chunk) > $maxBody) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $errorNumber = curl_errno($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($tooLarge) return codeExecutionResult(502, 'Kod bajarish xizmati juda katta javob qaytardi.');
    if ($errorNumber === CURLE_OPERATION_TIMEDOUT) {
        return codeExecutionResult(504, 'Kod bajarish xizmati vaqtida javob bermadi. Qayta urinib ko‘ring.');
    }
    if ($ok === false) {
        error_log('CodeStep runner transport failed (cURL code ' . $errorNumber . ').');
        return codeExecutionResult(502, 'Kod bajarish xizmatiga xavfsiz ulanib bo‘lmadi. Internet va server HTTPS sozlamalarini tekshiring.');
    }
    if ($httpStatus === 429) return codeExecutionResult(429, 'Kod bajarish xizmati band. Birozdan so‘ng qayta urinib ko‘ring.');
    if ($httpStatus !== 200) {
        error_log('CodeStep runner returned HTTP ' . $httpStatus . '.');
        return codeExecutionResult(502, 'Kod bajarish xizmati hozir javob bera olmayapti. Keyinroq qayta urinib ko‘ring.');
    }
    $data = json_decode($body, true);
    if (!is_array($data) || substr(ltrim($body), 0, 1) !== '{' || json_last_error() !== JSON_ERROR_NONE) {
        return codeExecutionResult(502, 'Kod bajarish xizmati yaroqli JSON javob qaytarmadi.');
    }
    return ['status' => 200, 'data' => $data];
}

function codeExecutionPistonResult(array $data): array
{
    $run = $data['run'] ?? null;
    if (!is_array($run)) {
        return codeExecutionResult(502, 'Piston API javobi noto‘g‘ri formatda qaytardi.');
    }
    $stdout = $run['stdout'] ?? '';
    $stderr = $run['stderr'] ?? '';
    $output = $run['output'] ?? ($stdout . ($stdout !== '' && $stderr !== '' ? "\n" : '') . $stderr);
    if (!is_string($stdout) || !is_string($stderr) || !is_string($output)) {
        return codeExecutionResult(502, 'Piston API chiqishi noto‘g‘ri formatda.');
    }
    $code = $run['code'] ?? null;
    $signal = $run['signal'] ?? null;
    if (strlen($output) > 65536) {
        $output = substr($output, 0, 65536);
    }
    if ((is_int($code) && $code === 0) && empty($signal)) {
        return codeExecutionResult(200, $output, false);
    }
    $msg = $output;
    if ($msg === '') {
        $msg = 'Dastur xato bilan tugadi.';
        if (is_int($code)) $msg .= ' (exit code: ' . $code . ')';
        if (is_string($signal) && $signal !== '') $msg .= ' (signal: ' . $signal . ')';
    }
    if (strlen($msg) > 65536) $msg = substr($msg, 0, 65536);
    return codeExecutionResult(200, $msg, true);
}

function codeExecutionWandboxResult(array $data): array
{
    $status = (string) ($data['status'] ?? '0');
    $signal = (string) ($data['signal'] ?? '');
    $compilerError = trim((string) ($data['compiler_error'] ?? ''));
    if ($compilerError === '' && isset($data['compiler_message']) && $status !== '0' && !isset($data['program_output'])) {
        $compilerError = trim((string) $data['compiler_message']);
    }
    $programError = trim((string) ($data['program_error'] ?? ''));
    $programOutput = (string) ($data['program_output'] ?? '');

    if ($compilerError !== '') {
        $msg = "Kompilyatsiya xatosi:\n" . $compilerError;
        if (strlen($msg) > 65536) $msg = substr($msg, 0, 65536);
        return codeExecutionResult(200, $msg, true);
    }

    if ($status !== '0' || $signal !== '' || $programError !== '') {
        $msg = $programOutput !== '' ? $programOutput . "\n" : '';
        if ($programError !== '') {
            $msg .= "Xatolik:\n" . $programError;
        } elseif ($signal !== '') {
            $msg .= "Dastur signal tufayli to‘xtatildi: " . $signal;
        } else {
            $msg .= "Dastur xato bilan tugadi (kod: " . $status . ").";
        }
        if (strlen($msg) > 65536) $msg = substr($msg, 0, 65536);
        return codeExecutionResult(200, $msg, true);
    }

    if (strlen($programOutput) > 65536) {
        $programOutput = substr($programOutput, 0, 65536);
    }
    return codeExecutionResult(200, $programOutput, false);
}

function codeExecutionRun(string $language, string $code, string $stdin): array
{
    if (!in_array($language, ['cpp', 'python'], true) || trim($code) === '') {
        return codeExecutionResult(400, 'Python yoki C++ tilini tanlab, kod yozing.');
    }
    if (strlen($code) > 65536 || strlen($stdin) > 16384) return codeExecutionResult(413, 'Kod yoki kirish ma’lumotlari hajmi chegaradan oshdi.');
    $provider = codeExecutionProvider();
    if ($provider === 'private') {
        $runnerUrl = trim((string) getenv('CODESTEP_RUNNER_URL'));
        $headers = [];
        $token = getenv('CODESTEP_RUNNER_TOKEN');
        if (is_string($token) && $token !== '') {
            if (preg_match('/[\r\n]/', $token)) return codeExecutionResult(503, 'Kod bajarish xizmati kaliti noto‘g‘ri sozlangan.');
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $response = codeExecutionPost($runnerUrl, [
            'language' => $language, 'code' => $code, 'stdin' => $stdin,
            'limits' => ['wall_time_seconds' => 10, 'memory_bytes' => 134217728, 'output_bytes' => 65536],
        ], $headers, 15);
        if (!isset($response['data'])) return $response;
        $result = $response['data'];
        if (!isset($result['output'], $result['error']) || !is_string($result['output'])
            || !is_bool($result['error']) || strlen($result['output']) > 65536) {
            return codeExecutionResult(502, 'Kod bajarish xizmati natijani noto‘g‘ri formatda qaytardi.');
        }
        return codeExecutionResult(200, $result['output'], $result['error']);
    }
    if ($provider === 'wandbox') {
        $compiler = codeExecutionConfig()['compilers'][$language] ?? ($language === 'cpp' ? 'gcc-13.2.0' : 'cpython-3.12.7');
        if (!is_string($compiler) || !preg_match('/^[a-z0-9.+_-]{1,80}$/D', $compiler)) {
            return codeExecutionResult(503, 'Wandbox kompilyatori noto‘g‘ri sozlangan.');
        }
        $payload = ['compiler' => $compiler, 'code' => $code, 'stdin' => $stdin, 'save' => false];
        if ($language === 'cpp') $payload['compiler-option-raw'] = "-std=c++20";
        $response = codeExecutionPost('https://wandbox.org/api/compile.json', $payload, [], 30);
        return isset($response['data']) ? codeExecutionWandboxResult($response['data']) : $response;
    }
    if ($provider === 'piston') {
        if ($language === 'cpp') {
            $payload = [
                'language' => 'c++',
                'version' => '10.2.0',
                'files' => [['name' => 'main.cpp', 'content' => $code]],
                'stdin' => $stdin,
                'compile_timeout' => 10000,
                'run_timeout' => 5000,
            ];
        } else {
            $payload = [
                'language' => 'python',
                'version' => '3.10.0',
                'files' => [['name' => 'main.py', 'content' => $code]],
                'stdin' => $stdin,
                'run_timeout' => 5000,
            ];
        }
        $response = codeExecutionPost('https://emkc.org/api/v2/piston/execute', $payload, [], 20);
        return isset($response['data']) ? codeExecutionPistonResult($response['data']) : $response;
    }
    if ($provider === 'local') {
        require_once dirname(__DIR__, 2) . '/tools/wasm-runner.php';
        if (function_exists('wasmRunnerEnabled') && wasmRunnerEnabled()) {
            $result = wasmRunnerRun(['language' => $language, 'code' => $code, 'stdin' => $stdin]);
            return codeExecutionResult($result['status'], $result['output'], $result['error']);
        }
    }
    if ($language === 'cpp') {
        $payload = [
            'language' => 'c++',
            'version' => '10.2.0',
            'files' => [['name' => 'main.cpp', 'content' => $code]],
            'stdin' => $stdin,
            'compile_timeout' => 10000,
            'run_timeout' => 5000,
        ];
    } else {
        $payload = [
            'language' => 'python',
            'version' => '3.10.0',
            'files' => [['name' => 'main.py', 'content' => $code]],
            'stdin' => $stdin,
            'run_timeout' => 5000,
        ];
    }
    $response = codeExecutionPost('https://emkc.org/api/v2/piston/execute', $payload, [], 20);
    return isset($response['data']) ? codeExecutionPistonResult($response['data']) : $response;
}
