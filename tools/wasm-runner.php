<?php
/** Runs fixed WebAssembly helpers. Submitted source is never run natively. */
function wasmRunnerConfig(): array
{
    $path = __DIR__ . '/wasm-runner.config.json';
    $config = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($config) ? $config : [];
}

function wasmRunnerEnabled(): bool
{
    return getenv('CODESTEP_LOCAL_RUNNER') !== '0'
        && (wasmRunnerConfig()['enabled'] ?? false) === true;
}

function wasmRunnerFailure(int $status, string $output): array
{
    return ['status' => $status, 'output' => $output, 'error' => true];
}

function wasmRunnerRun(array $request): array
{
    if (!wasmRunnerEnabled() || !function_exists('proc_open')) {
        return wasmRunnerFailure(503, 'Mahalliy WebAssembly muhiti ishga tushirilmagan.');
    }
    $language = $request['language'] ?? '';
    $code = $request['code'] ?? null;
    $stdin = $request['stdin'] ?? '';
    if (!in_array($language, ['cpp', 'python'], true) || !is_string($code) || !is_string($stdin)
        || trim($code) === ''
        || strpos($code, "\0") !== false || strpos($stdin, "\0") !== false
        || preg_match('//u', $code) !== 1 || preg_match('//u', $stdin) !== 1) {
        return wasmRunnerFailure(400, 'Til, kod yoki kiritish ma\'lumotlari noto\'g\'ri.');
    }
    if (strlen($code) > 65536 || strlen($stdin) > 16384) {
        return wasmRunnerFailure(413, 'Kod yoki kiritish ma\'lumotlari hajmi chegaradan oshdi.');
    }
    if ($language === 'cpp') {
        $config = wasmRunnerConfig();
        $executable = $config['node'] ?? null;
        $helper = __DIR__ . '/cpp-wasm-runner.cjs';
        $arguments = [$helper];
    } else {
        $configPath = __DIR__ . '/code-tools.config.json';
        $config = is_file($configPath) ? json_decode((string) file_get_contents($configPath), true) : [];
        $executable = getenv('CODESTEP_PYTHON') ?: ($config['python'] ?? null);
        $helper = __DIR__ . '/python-wasi-runner.py';
        $arguments = ['-I', '-S', $helper];
    }
    if (!is_string($executable) || strpos($executable, "\0") !== false
        || !is_file($executable) || !is_file($helper)) {
        return wasmRunnerFailure(503, 'WebAssembly vositalari to\'liq o\'rnatilmagan. Server sozlamalarini tekshiring.');
    }
    $streams = [];
    $process = null;
    $lock = null;
    try {
        // Keep the lock file in place: unlinking it would let two requests lock
        // different inodes. The OS releases this lock if a PHP worker exits.
        $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'codestep-wasm-' . hash('sha256', __DIR__) . '.lock';
        $lock = @fopen($lockPath, 'c+b');
        if ($lock === false) {
            return wasmRunnerFailure(503, 'Kod bajarish muhitini tayyorlab bo\'lmadi.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            return wasmRunnerFailure(429, 'Boshqa dastur bajarilmoqda. Birozdan so\'ng qayta urinib ko\'ring.');
        }
        for ($index = 0; $index < 3; ++$index) {
            $stream = tmpfile();
            if ($stream === false) {
                throw new RuntimeException('Temporary stream unavailable');
            }
            $streams[] = $stream;
        }
        $payload = json_encode(['code' => $code, 'stdin' => $stdin], JSON_UNESCAPED_UNICODE);
        if ($payload === false || fwrite($streams[0], $payload) !== strlen($payload)) {
            throw new RuntimeException('Input write failed');
        }
        rewind($streams[0]);
        // These fixed helpers provide the WASM boundary. No compiler or user
        // program is launched as a native host executable, and no shell is used.
        $process = @proc_open(
            array_merge([$executable], $arguments),
            [0 => $streams[0], 1 => $streams[1], 2 => $streams[2]],
            $pipes,
            __DIR__,
            null,
            ['bypass_shell' => true, 'create_no_window' => true]
        );
        if (!is_resource($process)) {
            return wasmRunnerFailure(503, 'WebAssembly muhitini ishga tushirib bo\'lmadi.');
        }
        $deadline = microtime(true) + 30;
        do {
            $status = proc_get_status($process);
            $outputSize = fstat($streams[1]);
            $errorSize = fstat($streams[2]);
            if (($outputSize['size'] ?? 0) > 65536 * 6 + 4096 || ($errorSize['size'] ?? 0) > 131072) {
                proc_terminate($process);
                return wasmRunnerFailure(502, 'Kod bajarish javobi hajm chegarasidan oshdi.');
            }
            if (!$status['running']) {
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                return wasmRunnerFailure(504, 'Dastur bajarilish vaqt chegarasidan oshdi.');
            }
            usleep(20000);
        } while (true);
        rewind($streams[1]);
        $result = json_decode((string) stream_get_contents($streams[1], 65536 * 6 + 4096), true);
        if (!is_array($result) || !isset($result['output']) || !is_string($result['output'])
            || !array_key_exists('error', $result) || !is_bool($result['error'])
            || strlen($result['output']) > 65536 || preg_match('//u', $result['output']) !== 1) {
            return wasmRunnerFailure(502, 'WebAssembly muhiti yaroqli javob qaytarmadi.');
        }
        return ['status' => 200, 'output' => $result['output'], 'error' => $result['error']];
    } catch (Throwable $error) {
        return wasmRunnerFailure(500, 'Kod bajarish muhitida ichki xato yuz berdi.');
    } finally {
        if (is_resource($process)) {
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
        foreach ($streams as $stream) {
            fclose($stream);
        }
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
