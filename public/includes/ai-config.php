<?php
/** Read chat settings from the project .env, outside the public web root. */
function aiChatEnvironmentFile(string $path): array
{
    if (!is_readable($path)) return [];
    $settings = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line, "\xEF\xBB\xBF \t\r\n");
        if (!preg_match('/^(?:export\s+)?(GROQ_API_KEY|OPENAI_API_KEY|GROQ_MODEL)\s*=\s*(.*)$/', $line, $match)) continue;
        $value = trim($match[2]);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $end = strpos($value, $quote, 1);
            if ($end === false || !preg_match('/^\s*(?:#.*)?$/', substr($value, $end + 1))) continue;
            $value = substr($value, 1, $end - 1);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value);
        }
        $settings[$match[1]] = trim($value);
    }
    return $settings;
}

function aiChatConfig(?string $path = null): array
{
    $local = aiChatEnvironmentFile($path ?? dirname(__DIR__, 2) . '/.env');
    $read = static function (string $name) use ($local): string {
        foreach ([getenv($name), $_SERVER[$name] ?? null, $local[$name] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '') return trim($value);
        }
        return '';
    };
    $apiKey = $read('GROQ_API_KEY') ?: $read('OPENAI_API_KEY');
    return [
        'api_key' => $apiKey,
        'model' => $read('GROQ_MODEL') ?: 'openai/gpt-oss-20b',
    ];
}