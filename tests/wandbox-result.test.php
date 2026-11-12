<?php
declare(strict_types=1);

// Pure response and URL validation tests: no network, programs or configuration changes.
require_once dirname(__DIR__) . '/public/includes/code-execution.php';

$passed = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function test(string $name, callable $run): void
{
    global $passed;
    $run();
    $passed++;
    echo "PASS {$name}\n";
}

function response(array $overrides = []): array
{
    return array_replace([
        'status' => '0',
        'signal' => '',
        'program_output' => "42\n",
        'program_error' => '',
        'compiler_output' => '',
        'compiler_error' => '',
    ], $overrides);
}

function expectResult(array $actual, int $status, bool $error): void
{
    check(($actual['status'] ?? null) === $status, 'Unexpected response status: ' . json_encode($actual));
    check(($actual['error'] ?? null) === $error, 'Unexpected error flag: ' . json_encode($actual));
    check(isset($actual['output']) && is_string($actual['output']), 'Output must always be a string.');
}

test('successful stdout remains byte-for-byte unchanged', function (): void {
    $stdout = "  42\n\nUnicode: o‘quvchi, π\t\n";
    $actual = codeExecutionWandboxResult(response(['program_output' => $stdout]));
    expectResult($actual, 200, false);
    check($actual['output'] === $stdout, 'Whitespace or Unicode changed in successful stdout.');
    check(!isset($actual['diagnostics']), 'Empty diagnostics should not be added.');
});

test('integer exit status zero succeeds', function (): void {
    expectResult(codeExecutionWandboxResult(response(['status' => 0])), 200, false);
});

test('successful empty stdout is a valid execution', function (): void {
    $actual = codeExecutionWandboxResult(['status' => '0', 'program_output' => '']);
    expectResult($actual, 200, false);
    check($actual['output'] === '', 'Empty stdout was replaced by a synthetic message.');
});

test('compiler warnings remain diagnostics and do not fail successful programs', function (): void {
    $warning = "main.cpp:2: warning: unused variable\n";
    $actual = codeExecutionWandboxResult(response(['compiler_error' => $warning]));
    expectResult($actual, 200, false);
    check($actual['output'] === "42\n", 'Compiler warnings contaminated judged stdout.');
    check(($actual['diagnostics'] ?? '') === trim($warning), 'Compiler warning was lost.');
});

test('ordinary stderr remains diagnostics and does not fail successful programs', function (): void {
    $actual = codeExecutionWandboxResult(response(['program_error' => "debug: done\n"]));
    expectResult($actual, 200, false);
    check($actual['output'] === "42\n", 'Stderr contaminated judged stdout.');
    check(($actual['diagnostics'] ?? '') === 'debug: done', 'Stderr was lost.');
});

test('all diagnostic channels survive together', function (): void {
    $actual = codeExecutionWandboxResult(response([
        'compiler_output' => "compiler note\n",
        'compiler_error' => "compiler warning\n",
        'program_error' => "runtime note\n",
    ]));
    expectResult($actual, 200, false);
    check($actual['output'] === "42\n", 'Diagnostics contaminated judged stdout.');
    foreach (['compiler note', 'compiler warning', 'runtime note'] as $text) {
        check(str_contains($actual['diagnostics'] ?? '', $text), "Missing diagnostic: {$text}");
    }
});

test('compiler failure returns a user execution error with compiler details', function (): void {
    $actual = codeExecutionWandboxResult(response([
        'status' => '1', 'program_output' => '', 'compiler_error' => "main.cpp:3: error: missing semicolon\n",
    ]));
    expectResult($actual, 200, true);
    check(str_contains($actual['output'], 'missing semicolon'), 'Compiler error details were lost.');
});

test('runtime failure retains stdout and stderr', function (): void {
    $actual = codeExecutionWandboxResult(response([
        'status' => 2, 'program_output' => 'before crash', 'program_error' => 'ValueError: invalid input',
    ]));
    expectResult($actual, 200, true);
    check(str_contains($actual['output'], 'before crash'), 'Partial stdout was lost.');
    check(str_contains($actual['output'], 'ValueError: invalid input'), 'Runtime details were lost.');
    check(str_contains($actual['output'], 'exit code: 2'), 'Exit status was lost.');
});

test('nonzero exit without diagnostics still fails', function (): void {
    $actual = codeExecutionWandboxResult(['status' => '7', 'program_output' => '']);
    expectResult($actual, 200, true);
    check(str_contains($actual['output'], 'exit code: 7'), 'Nonzero exit must produce a readable explanation.');
});

foreach (['', '0', 0, '137'] as $exitStatus) {
    test('signal failure with status ' . var_export($exitStatus, true), function () use ($exitStatus): void {
        $actual = codeExecutionWandboxResult(response([
            'status' => $exitStatus, 'signal' => 'Killed', 'program_output' => '',
        ]));
        expectResult($actual, 200, true);
        check(str_contains($actual['output'], 'Killed'), 'Signal details were lost.');
    });
}

$malformed = [
    'missing status' => ['program_output' => ''],
    'missing output' => ['status' => '0'],
    'null status' => response(['status' => null]),
    'boolean status' => response(['status' => false]),
    'float status' => response(['status' => 0.0]),
    'array status' => response(['status' => []]),
    'nonnumeric status' => response(['status' => 'ok']),
    'negative status' => response(['status' => '-1']),
    'overlong status' => response(['status' => '12345678901']),
    'status containing newline' => response(['status' => "0\n"]),
    'empty status without signal' => response(['status' => '']),
    'null output' => response(['program_output' => null]),
    'numeric output' => response(['program_output' => 42]),
    'array output' => response(['program_output' => []]),
    'null signal' => response(['signal' => null]),
    'array signal' => response(['signal' => []]),
    'numeric signal' => response(['signal' => 9]),
    'array program error' => response(['program_error' => []]),
    'null program error' => response(['program_error' => null]),
    'numeric compiler error' => response(['compiler_error' => 12]),
    'boolean compiler output' => response(['compiler_output' => false]),
];
foreach ($malformed as $name => $data) {
    test('malformed response: ' . $name, function () use ($data): void {
        expectResult(codeExecutionWandboxResult($data), 502, true);
    });
}

test('exactly 64 KiB of stdout is accepted', function (): void {
    $stdout = str_repeat('x', 65536);
    $actual = codeExecutionWandboxResult(response(['program_output' => $stdout]));
    expectResult($actual, 200, false);
    check($actual['output'] === $stdout, 'Boundary-sized output was truncated.');
});

foreach (['program_output', 'program_error', 'compiler_output', 'compiler_error'] as $field) {
    test('64 KiB limit applies to ' . $field, function () use ($field): void {
        expectResult(codeExecutionWandboxResult(response([
            'program_output' => '', $field => str_repeat('x', 65537),
        ])), 502, true);
    });
}

test('64 KiB limit covers the sum of output channels', function (): void {
    expectResult(codeExecutionWandboxResult(response([
        'program_output' => str_repeat('x', 32768),
        'program_error' => str_repeat('x', 32768),
        'compiler_error' => 'x',
    ])), 502, true);
});

test('output size limit counts UTF-8 bytes', function (): void {
    expectResult(codeExecutionWandboxResult(response([
        'program_output' => str_repeat('π', 32769),
    ])), 502, true);
});

test('failure output limit includes the added exit explanation', function (): void {
    expectResult(codeExecutionWandboxResult(response([
        'status' => '1', 'program_output' => str_repeat('x', 65536),
    ])), 502, true);
});

test('failure output limit includes compiler and runtime labels', function (): void {
    expectResult(codeExecutionWandboxResult(response([
        'status' => '1',
        'program_output' => '',
        'compiler_error' => str_repeat('x', 32768),
        'program_error' => str_repeat('x', 32768),
    ])), 502, true);
});

test('failure with space for its explanation retains useful diagnostics', function (): void {
    $diagnostic = str_repeat('x', 64000);
    $actual = codeExecutionWandboxResult(response([
        'status' => '1', 'program_output' => '', 'compiler_error' => $diagnostic,
    ]));
    expectResult($actual, 200, true);
    check(str_contains($actual['output'], $diagnostic), 'Valid compiler diagnostics were truncated.');
    check(strlen($actual['output']) <= 65536, 'Final failure output exceeded the byte limit.');
});

$validUrls = [
    'HTTPS remote runner' => 'https://runner.example.test/execute',
    'HTTPS port and query' => 'https://runner.example.test:8443/execute?version=2',
    'HTTP localhost' => 'http://localhost:8765/execute',
    'HTTP IPv4 loopback' => 'http://127.0.0.1:8765/execute',
    'HTTP IPv6 loopback' => 'http://[::1]:8765/execute',
];
foreach ($validUrls as $name => $url) {
    test('valid runner URL: ' . $name, function () use ($url): void {
        check(codeExecutionRunnerUrlValid($url), 'Expected valid runner URL: ' . $url);
    });
}

$invalidUrls = [
    'HTTP remote runner' => 'http://runner.example.test/execute',
    'HTTP private network' => 'http://192.168.1.50/execute',
    'local file' => 'file:///etc/passwd',
    'FTP runner' => 'ftp://runner.example.test/execute',
    'credentials' => 'https://user:password@runner.example.test/execute',
    'username only' => 'https://user@runner.example.test/execute',
    'fragment' => 'https://runner.example.test/execute#fragment',
    'CRLF' => "https://runner.example.test/execute\r\nX-Injected: yes",
    'space' => 'https://runner.example.test/bad path',
    'NUL' => "https://runner.example.test/execute\0",
    'scheme-relative' => '//runner.example.test/execute',
    'relative path' => '/execute',
    'empty URL' => '',
    'lookalike localhost suffix' => 'http://localhost.example.test/execute',
    'invalid port' => 'https://runner.example.test:99999/execute',
];
foreach ($invalidUrls as $name => $url) {
    test('invalid runner URL: ' . $name, function () use ($url): void {
        check(!codeExecutionRunnerUrlValid($url), 'Unsafe or malformed runner URL was accepted.');
    });
}

echo "All {$passed} Wandbox response and runner URL tests passed.\n";
