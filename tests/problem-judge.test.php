<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public/includes/problem-rules.php';
require_once dirname(__DIR__) . '/public/includes/problem-judge.php';

// No compiler or network is needed: the execution boundary is injected below.
$checks = 0;
$failures = [];
function judgeTest(string $name, callable $test): void
{
    global $checks, $failures;
    $checks++;
    try {
        $test();
    } catch (Throwable $error) {
        $failures[] = $name . ': ' . $error->getMessage();
    }
}
function judgeAssert(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) throw new RuntimeException($message);
}
function judgeRule(int $exercise): array
{
    $rule = problemRule($exercise <= 20 ? 14 : 15, '1.1.' . $exercise);
    if ($rule === null) throw new RuntimeException('Missing rule ' . $exercise);
    return $rule;
}
function judgeFixture(array $rule): string
{
    switch ($rule['kind']) {
        case 'exact':
        case 'numeric':
            return (string) $rule['expected'];
        case 'repeat':
            return implode(' ', array_fill(0, $rule['count'], $rule['text']));
        case 'numeric_lines':
            return "12\n-3\n0\n4.5";
        case 'name_layout':
            return "Alisher Karimov\nAlisher\nKarimov";
        case 'poem':
            return "Tongda quyosh kuladi\nBog‘da gullar ochiladi\nYengil shamol esadi\nYurak quvonch sezadi\nMuallif: Namuna muallifi";
        case 'echo':
            return ($rule['prefix'] ?? '') . (isset($rule['prefix']) ? ' ' : '') . trim($rule['tests'][0]['stdin']);
        case 'structured':
            if ($rule['layout'] === 'table') {
                $rows = [implode(' | ', array_column($rule['fields'], 'label'))];
                for ($entry = 1; $entry <= $rule['min_entries']; $entry++) {
                    $rows[] = implode(' | ', array_map(static fn(int $index): string => 'Qiymat ' . $entry . '-' . $index, array_keys($rule['fields'])));
                }
                return implode("\n", $rows);
            }
            return implode("\n", array_map(static fn(array $field, int $index): string => $field['label'] . ': Qiymat ' . $index, $rule['fields'], array_keys($rule['fields'])));
    }
    throw new RuntimeException('No fixture for ' . $rule['kind']);
}
function judgeSuccess(string $output): array
{
    return ['status' => 200, 'error' => false, 'output' => $output];
}

for ($exercise = 1; $exercise <= 55; $exercise++) {
    judgeTest('Configuration and output contract ' . $exercise, static function () use ($exercise): void {
        $rule = judgeRule($exercise);
        judgeAssert(count($rule['requirements']) > 0, 'Acceptance criteria must be visible');
        judgeAssert(count($rule['tests']) > 0, 'At least one execution is required');
        $test = $rule['tests'][0];
        foreach (['', " \t\r\n", 'Salom dunyo'] as $output) {
            judgeAssert(!problemOutputMatches($rule, $output, $test), 'Unrelated or empty output was accepted');
        }
        judgeAssert(problemOutputMatches($rule, judgeFixture($rule), $test), 'Valid output fixture was rejected');
    });
}

judgeTest('Unknown topic, wrong topic and malformed number have no rules', static function (): void {
    foreach ([[999, '1.1.1'], [14, '1.1.21'], [15, '1.1.1'], [15, '1.1.021'], [14, '1.1.0'], [15, '1.1.56']] as [$topic, $number]) {
        judgeAssert(problemRule($topic, $number) === null, 'Unexpected rule for ' . $topic . ':' . $number);
    }
});

judgeTest('Binary values, widths and signed decoding match independent fixtures', static function (): void {
    $expectedBits = ['0000010011100110', '0000100111101101', '000111001011011', '00001110100100010', '000001110111100110', '111110001000011010', '1111101111111111', '1110101110010000', '10100100001001', '111110000100101001'];
    foreach ($expectedBits as $index => $bits) {
        $rule = judgeRule($index + 1);
        judgeAssert($rule['expected'] === $bits, 'Incorrect binary answer ' . ($index + 1));
        judgeAssert(!problemOutputMatches($rule, substr($bits, 1)), 'Short bit width accepted');
        judgeAssert(!problemOutputMatches($rule, '0' . $bits), 'Long bit width accepted');
        judgeAssert(!problemOutputMatches($rule, 'Natija: ' . $bits), 'Prompt accepted');
    }
    $negativeRule = judgeRule(6);
    $positiveMagnitude = str_pad(decbin(7654), 18, '0', STR_PAD_LEFT);
    judgeAssert(!problemOutputMatches($negativeRule, $positiveMagnitude), 'Magnitude accepted for a negative two’s complement value');
    judgeAssert(!problemOutputMatches($negativeRule, '-' . $positiveMagnitude), 'Minus-prefixed magnitude accepted');
    $expectedDecimals = ['-1031', '-4123', '-8263', '-4867', '-14341'];
    for ($exercise = 11; $exercise <= 20; $exercise++) {
        $rule = judgeRule($exercise);
        judgeAssert($rule['expected'] === $expectedDecimals[($exercise - 11) % 5], 'Incorrect signed decoding');
        judgeAssert(!problemOutputMatches($rule, ltrim($rule['expected'], '-')), 'Unsigned magnitude accepted');
    }
});

judgeTest('Fixed output preserves line count and internal spaces', static function (): void {
    judgeAssert(problemOutputMatches(judgeRule(23), "50\r\n10\r\n"), 'CRLF or final newline rejected');
    judgeAssert(!problemOutputMatches(judgeRule(23), '50 10'), 'Single line accepted instead of two');
    judgeAssert(!problemOutputMatches(judgeRule(23), "50\n\n10"), 'Extra internal line accepted');
    judgeAssert(!problemOutputMatches(judgeRule(53), '1  13 49'), 'Double space accepted');
    judgeAssert(!problemOutputMatches(judgeRule(53), "1\n13\n49"), 'Columns accepted instead of one row');
});

judgeTest('Five repetitions stay on one line and reject extra text', static function (): void {
    $rule = judgeRule(21);
    foreach ([4, 6] as $count) judgeAssert(!problemOutputMatches($rule, implode(' ', array_fill(0, $count, $rule['text']))), 'Wrong repeat count accepted');
    judgeAssert(!problemOutputMatches($rule, implode("\n", array_fill(0, 5, $rule['text']))), 'Multiline repetitions accepted');
    judgeAssert(!problemOutputMatches($rule, judgeFixture($rule) . ' Salom'), 'Extra text accepted');
});

judgeTest('Numeric tolerance accepts pi approximation but rejects wrong or nonfinite output', static function (): void {
    $rule = judgeRule(22);
    judgeAssert(problemOutputMatches($rule, '3.14'), 'Two decimal approximation rejected');
    foreach (['3.1', '3.15', 'INF', 'NaN', '1e999', 'pi = 3.14', '3,14'] as $output) {
        judgeAssert(!problemOutputMatches($rule, $output), 'Invalid pi output accepted: ' . $output);
    }
});

judgeTest('Required decimal precision is exact for exercises 48 and 49', static function (): void {
    foreach ([48 => ['3.140', '3.14159', '3.1', '3,14'], 49 => ['2.70', '2.718', '3', '2,7']] as $exercise => $outputs) {
        foreach ($outputs as $output) judgeAssert(!problemOutputMatches(judgeRule($exercise), $output), 'Wrong precision accepted: ' . $output);
    }
});

judgeTest('Four arbitrary numbers need four numeric lines', static function (): void {
    foreach ([24, 54] as $exercise) {
        $rule = judgeRule($exercise);
        judgeAssert(problemOutputMatches($rule, "-2\n0\n3.5\n1e2"));
        foreach (['1 2 3 4', "1\n2\n3", "1\n2\n3\n4\n5", "1\n2\nthree\n4", "1\n2\n3\n1e999"] as $output) {
            judgeAssert(!problemOutputMatches($rule, $output), 'Invalid numeric column accepted');
        }
    }
});

judgeTest('Name layout matches repeated names and visible single-word requirement', static function (): void {
    $rule = judgeRule(25);
    judgeAssert(!problemOutputMatches($rule, "Alisher Karimov\nAlisher\nAliyev"), 'Different surname accepted');
    judgeAssert(!problemOutputMatches($rule, "Alisher Karimov\nAlisher\nKarimov\nExtra"), 'Fourth line accepted');
    judgeAssert(!problemOutputMatches($rule, "Ali Vali Karimov\nAli Vali\nKarimov"), 'Multiword first name violates displayed single-word rule');
});

judgeTest('Poem checks four nonempty text lines and a named author', static function (): void {
    $rule = judgeRule(37);
    judgeAssert(problemOutputMatches($rule, "Bahor\nQuyosh\nGullar\nHayot\nMuallif: Alisher"), 'Visible criteria allow one-word poem lines');
    judgeAssert(!problemOutputMatches($rule, "Birinchi satr\nIkkinchi satr\nUchinchi satr\nMuallif: Alisher"), 'Only three poem lines accepted');
    judgeAssert(!problemOutputMatches($rule, "Birinchi satr\nIkkinchi satr\nUchinchi satr\nOxirgi satr\nMuallif:"), 'Missing author accepted');
});

judgeTest('Structured fields reject missing, empty, duplicate and unknown labels', static function (): void {
    $rule = judgeRule(26);
    $rows = explode("\n", judgeFixture($rule));
    judgeAssert(!problemOutputMatches($rule, implode("\n", array_slice($rows, 1))), 'Missing field accepted');
    $empty = $rows;
    $empty[0] = $rule['fields'][0]['label'] . ': ';
    judgeAssert(!problemOutputMatches($rule, implode("\n", $empty)), 'Empty value accepted');
    $duplicate = $rows;
    $duplicate[1] = $rows[0];
    judgeAssert(!problemOutputMatches($rule, implode("\n", $duplicate)), 'Duplicate label accepted');
    $unknown = $rows;
    $unknown[0] = 'Noma’lum: Qiymat';
    judgeAssert(!problemOutputMatches($rule, implode("\n", $unknown)), 'Unknown field accepted');
    $aliases = $rows;
    foreach ($rule['fields'] as $index => $field) $aliases[$index] = end($field['aliases']) . ': Qiymat ' . $index;
    judgeAssert(problemOutputMatches($rule, implode("\n", $aliases)), 'Documented aliases rejected');
    $arbitraryValues = [];
    foreach ($rule['fields'] as $index => $field) $arbitraryValues[] = $field['label'] . ': ' . ($index % 2 === 0 ? 'test' : 'xxx');
    judgeAssert(problemOutputMatches($rule, implode("\n", $arbitraryValues)), 'Structure-only checker imposed an undisclosed content blacklist');
});

judgeTest('Structured tables require headers, complete cells and minimum entries', static function (): void {
    $rule = judgeRule(44);
    $rows = explode("\n", judgeFixture($rule));
    judgeAssert(!problemOutputMatches($rule, implode("\n", array_slice($rows, 0, 2))), 'Only one entry accepted');
    $missingCell = $rows;
    $missingCell[1] = 'Muallif nomi |';
    judgeAssert(!problemOutputMatches($rule, implode("\n", $missingCell)), 'Empty table cell accepted');
    $missingColumn = $rows;
    $missingColumn[2] = 'Faqat bitta qiymat';
    judgeAssert(!problemOutputMatches($rule, implode("\n", $missingColumn)), 'Incomplete row accepted');
    $wrongHeader = $rows;
    $wrongHeader[0] = 'Kitob nomi | Muallif';
    judgeAssert(!problemOutputMatches($rule, implode("\n", $wrongHeader)), 'Wrong header order accepted');
});

judgeTest('Input tasks echo the supplied number and place message before it', static function (): void {
    foreach ([50, 51, 52] as $exercise) {
        $rule = judgeRule($exercise);
        foreach ($rule['tests'] as $test) {
            judgeAssert(problemOutputMatches($rule, $test['expected'], $test), 'Valid echo rejected');
            judgeAssert(!problemOutputMatches($rule, '999999', $test), 'Different input value accepted');
        }
    }
    $rule = judgeRule(51);
    $test = ['stdin' => "-125\n"];
    judgeAssert(problemOutputMatches($rule, "Siz raqam kiritdingiz\n-125", $test), 'Prefix on preceding line rejected');
    judgeAssert(!problemOutputMatches($rule, '-125 Siz raqam kiritdingiz', $test), 'Message after number accepted');
    judgeAssert(!problemOutputMatches($rule, '-125', $test), 'Missing prefix accepted');
});

judgeTest('Exercise 55 permits correct optional labels and preserves all six rows', static function (): void {
    $rule = judgeRule(55);
    $labelled = "a) 5 10\nb) 100 t\nc) x 25\nd) 7 sm\ne) 1949 v\nf) x y";
    judgeAssert(problemOutputMatches($rule, $labelled), 'Optional labels rejected');
    judgeAssert(!problemOutputMatches($rule, str_replace('c) x 25', 'd) x 25', $labelled)), 'Incorrect label accepted');
    judgeAssert(!problemOutputMatches($rule, str_replace("\n", ' ', $rule['expected'])), 'Single-line output accepted');
    judgeAssert(!problemOutputMatches($rule, str_replace('1949 v', '1949 V', $rule['expected'])), 'Changed data accepted');
});

judgeTest('Judge reports exact accepted and structure-only structure_passed', static function (): void {
    foreach ([1, 25, 26, 37, 44, 53] as $exercise) {
        $rule = judgeRule($exercise);
        $calls = 0;
        $result = problemJudge($rule, 'python', 'fixture', 'user input is not trusted', static function (string $language, string $code, string $stdin) use ($rule, &$calls): array {
            $calls++;
            judgeAssert($language === 'python' && $code === 'fixture', 'Execution arguments changed');
            judgeAssert($stdin === $rule['tests'][0]['stdin'], 'Server test stdin was not used');
            return judgeSuccess(judgeFixture($rule));
        });
        $expectedStatus = ($rule['scope'] ?? '') === 'structure_only' ? 'structure_passed' : 'accepted';
        judgeAssert($result['status'] === 200 && $result['error'] === false, 'Successful execution failed');
        judgeAssert($result['verdict']['status'] === $expectedStatus, 'Wrong success scope');
        judgeAssert($result['verdict']['checks_passed'] === 1 && $calls === 1, 'Incorrect check count');
    }
});

judgeTest('Repeated constant cannot pass all varied echo inputs', static function (): void {
    foreach ([50, 51, 52] as $exercise) {
        $rule = judgeRule($exercise);
        $seen = [];
        $constant = null;
        $result = problemJudge($rule, 'cpp', 'constant fixture', '', static function (string $language, string $code, string $stdin) use ($rule, &$seen, &$constant): array {
            $seen[] = $stdin;
            $constant ??= trim($stdin);
            return judgeSuccess((isset($rule['prefix']) ? $rule['prefix'] . ' ' : '') . $constant);
        });
        judgeAssert($result['verdict']['status'] === 'wrong_answer', 'Constant output passed all inputs');
        judgeAssert($result['verdict']['checks_passed'] === 1, 'First matching test should pass');
        judgeAssert(count($seen) === 2 && $seen[0] !== $seen[1], 'Judge did not vary input');
        judgeAssert($result['verdict']['total_checks'] === 3, 'Expected three echo checks');
    }
});

judgeTest('Reading each server input passes all positive zero and negative echo cases', static function (): void {
    foreach ([50, 51, 52] as $exercise) {
        $rule = judgeRule($exercise);
        $seen = [];
        $result = problemJudge($rule, 'python', 'echo fixture', '999999', static function (string $language, string $code, string $stdin) use ($rule, &$seen): array {
            $seen[] = (int) trim($stdin);
            return judgeSuccess((isset($rule['prefix']) ? $rule['prefix'] . ' ' : '') . trim($stdin));
        });
        judgeAssert($result['verdict']['status'] === 'accepted' && $result['verdict']['checks_passed'] === 3, 'Correct echo rejected');
        judgeAssert(count($seen) === 3 && $seen[0] > 0 && $seen[1] === 0 && $seen[2] < 0, 'Missing varied input cases');
    }
});

judgeTest('Unrelated successful execution is a wrong answer', static function (): void {
    $result = problemJudge(judgeRule(1), 'cpp', 'unrelated', '', static fn(): array => judgeSuccess('Salom dunyo'));
    judgeAssert($result['status'] === 200 && $result['error'] === false, 'Output mismatch should not be an infrastructure error');
    judgeAssert($result['verdict']['status'] === 'wrong_answer' && $result['verdict']['checks_passed'] === 0, 'Unrelated output accepted');
});

judgeTest('Runtime and infrastructure failures never become accepted verdicts', static function (): void {
    foreach ([
        ['status' => 200, 'error' => true, 'output' => 'Runtime error'],
        ['status' => 422, 'error' => true, 'output' => 'Compile error'],
        ['status' => 503, 'error' => true, 'output' => 'Service unavailable'],
        ['status' => 504, 'error' => true, 'output' => 'Time limit exceeded'],
        ['status' => 500, 'error' => false, 'output' => 'Internal failure'],
    ] as $failure) {
        $calls = 0;
        $result = problemJudge(judgeRule(50), 'cpp', 'failure fixture', '', static function () use ($failure, &$calls): array {
            $calls++;
            return $failure;
        });
        judgeAssert($result === $failure, 'Execution failure was transformed into a normal verdict');
        judgeAssert(!in_array($result['verdict']['status'] ?? null, ['accepted', 'structure_passed'], true), 'Failure accepted');
        judgeAssert($calls === 1, 'Execution continued after failure');
    }
});

judgeTest('Invalid test configuration refuses execution', static function (): void {
    foreach ([[], array_fill(0, 4, ['stdin' => '', 'expected' => '1'])] as $tests) {
        $rule = judgeRule(1);
        $rule['tests'] = $tests;
        $calls = 0;
        $result = problemJudge($rule, 'cpp', 'not run', '', static function () use (&$calls): array {
            $calls++;
            return judgeSuccess('1');
        });
        judgeAssert($result['status'] === 503 && $result['error'] === true, 'Invalid configuration was not refused');
        judgeAssert($calls === 0, 'Executor ran despite invalid configuration');
    }
});

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    fwrite(STDERR, count($failures) . ' of ' . $checks . ' checks failed.' . PHP_EOL);
    exit(1);
}
echo 'PASS: ' . $checks . ' checks, including all 55 exercise contracts and injected judge execution.' . PHP_EOL;
