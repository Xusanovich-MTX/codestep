<?php
/** Static-tool process boundary. No submitted code is executed. PHP 7.4+. */
function codeToolsRun(array $request): array
{
    if (!function_exists('proc_open')) {
        return ['error' => true, 'status' => 503, 'message' => 'Serverda kod vositalari uchun proc_open yoqilmagan.'];
    }
    $configPath = __DIR__ . '/code-tools.config.json';
    $config = is_file($configPath) ? json_decode((string) file_get_contents($configPath), true) : [];
    $python = getenv('CODESTEP_PYTHON') ?: ($config['python'] ?? 'python');
    if (!is_string($python) || $python === '' || strpos($python, "\0") !== false) {
        return ['error' => true, 'status' => 503, 'message' => 'Python yo\'li noto\'g\'ri sozlangan.'];
    }
    $streams = [];
    $process = null;
    try {
        for ($index = 0; $index < 3; ++$index) {
            $stream = tmpfile();
            if ($stream === false) {
                throw new RuntimeException('Temporary stream unavailable');
            }
            $streams[] = $stream;
        }
        $payload = json_encode($request, JSON_UNESCAPED_UNICODE);
        if ($payload === false || fwrite($streams[0], $payload) !== strlen($payload)) {
            throw new RuntimeException('Input write failed');
        }
        rewind($streams[0]);
        // Array argv bypasses the shell, including on Windows. -I -S excludes
        // user/global Python packages; only our explicit local dependencies load.
        $process = @proc_open(
            [$python, '-I', '-S', __DIR__ . '/code_tools.py'],
            [0 => $streams[0], 1 => $streams[1], 2 => $streams[2]],
            $pipes,
            __DIR__,
            null,
            ['bypass_shell' => true, 'create_no_window' => true]
        );
        if (!is_resource($process)) {
            return ['error' => true, 'status' => 503, 'message' => 'Python topilmadi. tools/setup-code-tools.py orqali serverni sozlang.'];
        }
        $deadline = microtime(true) + 12;
        do {
            $status = proc_get_status($process);
            $outputSize = fstat($streams[1]);
            $errorSize = fstat($streams[2]);
            if (($outputSize['size'] ?? 0) > 1048576 || ($errorSize['size'] ?? 0) > 131072) {
                proc_terminate($process);
                return ['error' => true, 'status' => 413, 'message' => 'Kod vositasi javob hajmi chegarasidan oshdi.'];
            }
            if (!$status['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                return ['error' => true, 'status' => 504, 'message' => 'Tekshirish vaqt chegarasidan oshdi. Kod hajmini kamaytiring.'];
            }
            usleep(20000);
        } while (true);
        rewind($streams[1]);
        $result = json_decode((string) stream_get_contents($streams[1], 1048576), true);
        if (!is_array($result) || !isset($result['message'])) {
            return ['error' => true, 'status' => 503, 'message' => 'Kod vositalari ishlamadi. tools/setup-code-tools.py orqali o\'rnatilishini tekshiring.'];
        }
        return $result;
    } catch (Throwable $error) {
        return ['error' => true, 'status' => 500, 'message' => 'Kod vositasini ishga tushirib bo\'lmadi.'];
    } finally {
        if (is_resource($process)) {
            proc_close($process);
        }
        foreach ($streams as $stream) {
            fclose($stream);
        }
    }
}
