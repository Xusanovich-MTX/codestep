<?php
require __DIR__ . '/code-tools.php';

function verifyTool(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$formatted = codeToolsRun(['code' => "x=1\n", 'language' => 'python', 'action' => 'format']);
verifyTool(($formatted['code'] ?? null) === "x = 1\n", 'PHP to Python format: ' . json_encode($formatted));
$checked = codeToolsRun(['code' => "print(missing_name)\n", 'language' => 'python', 'action' => 'check']);
verifyTool(($checked['diagnostics'][0]['rule'] ?? null) === 'F821', 'PHP to flake8 diagnostics');
$cpp = codeToolsRun(['code' => 'int main(){return 0;}', 'language' => 'cpp', 'action' => 'format', 'cpp_style' => 'allman']);
verifyTool(strpos($cpp['code'] ?? '', "int main()\n{") !== false, 'PHP to clang-format: ' . json_encode($cpp));
$invalid = codeToolsRun(['code' => 'x', 'language' => 'python; whoami', 'action' => 'check']);
verifyTool(($invalid['status'] ?? null) === 400, 'Invalid language rejected');
echo "PASS: 4 PHP/static tool integration checks" . PHP_EOL;
