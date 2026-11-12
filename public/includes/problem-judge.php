<?php
/** Deterministic output checks. Submitted source never chooses its own verdict. */
function problemOutput(string $output): string
{
    return trim(str_replace(["\r\n", "\r"], "\n", $output));
}

function problemLabel(string $label): string
{
    $label = strtr(trim($label), ['‘' => "'", '’' => "'", 'ʻ' => "'", 'ʼ' => "'", '`' => "'"]);
    return strtolower((string) preg_replace('/\s+/u', ' ', $label));
}

function problemFieldMatches(string $label, array $field): bool
{
    $aliases = array_merge([$field['label']], $field['aliases'] ?? []);
    foreach ($aliases as $alias) {
        if (problemLabel($label) === problemLabel($alias)) return true;
    }
    return false;
}

function problemValuePresent(string $value): bool
{
    return preg_match('/[\p{L}\p{N}]/u', $value) === 1;
}

/** Compare integer tokens as strings, including values larger than PHP_INT_MAX. */
function problemIntegerToken(string $value): ?string
{
    if (preg_match('/^[+-]?[0-9]+$/D', $value) !== 1) return null;
    $digits = ltrim(ltrim($value, '+-'), '0');
    if ($digits === '') return '0';
    return ($value[0] === '-' ? '-' : '') . $digits;
}

function problemNumberListMatches(array $rule, string $output, array $test): bool
{
    $actual = preg_split('/\s+/u', trim($output));
    $expected = preg_split('/\s+/u', trim((string) ($test['expected'] ?? '')));
    if (!$actual || !$expected || count($actual) !== count($expected)) return false;
    if ($rule['kind'] === 'decimal_list') {
        foreach ($actual as $index => $value) {
            if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $value) !== 1
                || !is_finite((float) $value)
                || abs((float) $value - (float) $expected[$index]) > ($rule['tolerance'] ?? 0.000001)) return false;
        }
        return true;
    }
    $actual = array_map('problemIntegerToken', $actual);
    $expected = array_map('problemIntegerToken', $expected);
    if (in_array(null, $actual, true) || in_array(null, $expected, true)) return false;
    if ($rule['kind'] === 'integer_set') {
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
    }
    return $actual === $expected;
}

function problemOutputMatches(array $rule, string $output, array $test = []): bool
{
    $output = problemOutput($output);
    if ($output === '') return false;
    $lines = explode("\n", $output);
    switch ($rule['kind']) {
        case 'number_list':
        case 'decimal_list':
        case 'integer_set':
            return problemNumberListMatches($rule, $output, $test);
        case 'divisibility_digit':
            return preg_match('/^[0-9]$/D', $output) === 1
                && (($test['expected'] ?? '') === '1' ? $output === '1' : $output !== '1');
        case 'exact':
            if (!empty($rule['optional_line_labels'])) {
                foreach ($lines as $index => &$line) {
                    $line = (string) preg_replace('/^' . chr(97 + $index) . '\)\s*/', '', trim($line));
                }
                unset($line);
                $output = implode("\n", $lines);
            }
            return $output === problemOutput((string) ($test['expected'] ?? $rule['expected']));
        case 'repeat':
            $phrase = preg_quote($rule['text'], '/');
            return preg_match('/^' . $phrase . '(?:[ \t]*' . $phrase . '){' . ($rule['count'] - 1) . '}$/uD', $output) === 1;
        case 'numeric':
            return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $output) === 1
                && is_finite((float) $output)
                && abs((float) $output - (float) $rule['expected']) <= $rule['tolerance'];
        case 'numeric_lines':
            if (count($lines) !== $rule['count']) return false;
            foreach ($lines as $line) {
                if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', trim($line)) !== 1
                    || !is_finite((float) trim($line))) return false;
            }
            return true;
        case 'echo':
            if (!empty($rule['prefix'])) {
                return preg_match('/^' . preg_quote($rule['prefix'], '/') . '[ \t\n]+([+-]?\d+)$/uD', $output, $match) === 1
                    && $match[1] === trim($test['stdin']);
            }
            return $output === trim($test['stdin']);
        case 'name_layout':
            if (count($lines) !== 3) return false;
            $parts = array_map(static fn(string $value): string => trim((string) preg_replace('/[ \t]+/', ' ', $value)), $lines);
            return preg_match('/^\p{L}[\p{L}\p{M}\x{2018}\x{2019}\x{02BB}\x{02BC}\x{0027}-]*$/uD', $parts[1]) === 1
                && preg_match('/^\p{L}[\p{L}\p{M}\x{2018}\x{2019}\x{02BB}\x{02BC}\x{0027}-]*$/uD', $parts[2]) === 1
                && $parts[0] === $parts[1] . ' ' . $parts[2];
        case 'poem':
            if (count($lines) !== $rule['line_count'] + 1) return false;
            for ($i = 0; $i < $rule['line_count']; $i++) {
                if (preg_match('/\p{L}/u', $lines[$i]) !== 1) return false;
            }
            $author = explode(':', $lines[$rule['line_count']], 2);
            return count($author) === 2
                && problemFieldMatches($author[0], ['label' => 'Muallif', 'aliases' => $rule['author_aliases'] ?? []])
                && problemValuePresent($author[1]);
        case 'structured':
            $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
            if (count($lines) < $rule['min_rows']) return false;
            $fields = $rule['fields'];
            if (($rule['layout'] ?? 'fields') === 'table') {
                $header = array_map('trim', explode('|', array_shift($lines)));
                if (count($header) !== count($fields) || count($lines) < ($rule['min_entries'] ?? 1)) return false;
                foreach ($fields as $index => $field) {
                    if (!problemFieldMatches($header[$index], $field)) return false;
                }
                foreach ($lines as $line) {
                    $values = array_map('trim', explode('|', $line));
                    if (count($values) !== count($fields)) return false;
                    foreach ($values as $value) if (!problemValuePresent($value)) return false;
                }
                return true;
            }
            $found = [];
            foreach ($lines as $line) {
                $pair = explode(':', $line, 2);
                if (count($pair) !== 2 || !problemValuePresent($pair[1])) return false;
                $matched = false;
                foreach ($fields as $index => $field) {
                    if (problemFieldMatches($pair[0], $field) && !isset($found[$index])) {
                        $found[$index] = true;
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) return false;
            }
            return count($found) === count($fields);
    }
    return false;
}

function problemJudge(array $rule, string $language, string $code, string $stdin, ?callable $execute = null): array
{
    $execute = $execute ?? 'codeExecutionRun';
    $tests = $rule['tests'] ?? [['stdin' => $stdin]];
    // Fresh server inputs prevent a constant answer passing input-reading tasks.
    if ($rule['kind'] === 'echo') {
        $tests = [
            ['stdin' => (string) random_int(10, 999) . "\n"],
            ['stdin' => "0\n"],
            ['stdin' => (string) random_int(-999, -10) . "\n"],
        ];
    }
    $total = count($tests);
    if ($total < 1 || $total > 3) {
        return ['status' => 503, 'output' => 'Masala tekshiruvi sozlanmagan.', 'error' => true];
    }
    $started = microtime(true);
    $methodReview = !empty($rule['method_review_required']);
    $verdict = ['status' => 'wrong_answer', 'message' => 'Natija masala talablariga mos emas. Qabul qilish talablarini tekshiring.',
        'checks_passed' => 0, 'total_checks' => $total, 'scope' => $methodReview ? 'structure_only' : ($rule['scope'] ?? 'output')];
    $lastOutput = '';
    $lastInput = '';
    $details = [];
    foreach ($tests as $test) {
        if (microtime(true) - $started > 65) {
            return ['status' => 504, 'output' => 'Tekshiruv vaqti tugadi. Qayta urinib ko‘ring.', 'error' => true];
        }
        $lastInput = $test['stdin'] ?? $stdin;
        $result = $execute($language, $code, $lastInput);
        if (($result['status'] ?? 500) !== 200 || ($result['error'] ?? true)) return $result;
        $lastOutput = $result['output'];
        $details = isset($result['diagnostics']) ? ['diagnostics' => $result['diagnostics']] : [];
        if (!problemOutputMatches($rule, $lastOutput, $test)) {
            return ['status' => 200, 'output' => $lastOutput, 'error' => false, 'verdict' => $verdict, 'test_stdin' => $lastInput] + $details;
        }
        $verdict['checks_passed']++;
    }
    $structureOnly = $methodReview || ($rule['scope'] ?? '') === 'structure_only';
    $verdict['status'] = $structureOnly ? 'structure_passed' : 'accepted';
    $verdict['message'] = $methodReview
        ? 'Natija testlarga mos. Yechish usuli va operatorlarga qo‘yilgan cheklovlar avtomatik tekshirilmadi.'
        : ($structureOnly
            ? 'Chiqish tuzilishi talablarga mos. Ixtiyoriy ma’lumotlarning mazmuni avtomatik tasdiqlanmadi.'
            : 'Yechim qabul qilindi. Natija masala talablariga mos.');
    return ['status' => 200, 'output' => $lastOutput, 'error' => false, 'verdict' => $verdict, 'test_stdin' => $lastInput] + $details;
}
