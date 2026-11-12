<?php
require_once __DIR__ . '/ai-config.php';

function aiChatResult(int $status, string $reply): array
{
    return ['status' => $status, 'reply' => $reply];
}

function aiChatPayload(string $message, string $model, array $history = []): array
{
    $messages = [
        ['role' => 'system', 'content' => "Siz CodeStep dasturlash o'rganish platformasining AI yordamchisisiz. O'zbek tilida sodda, aniq va tushunarli javob bering. Savolga bevosita javob berib, yechim uchun zarur muhim ma'lumot, shart va qadamlarni qoldirmang. Oddiy savolga ortiqcha bo'limlarsiz javob bering; murakkab javobni qisqa sarlavhalar va tartibli ro'yxatlar bilan tuzing. Har bir qadamni qisqa izohlang, taxmin yoki noaniqlik bo'lsa ochiq ayting. Foydalanuvchi masala yechimini so'rasa tayyor to'liq kodni bermang; fikrlash yo'nalishi, kerakli tushuncha va keyingi qadamni tushuntiring. Markdown sarlavha, ro'yxat, qalin matn va kerak bo'lsa qisqa kod bloklaridan foydalaning."],
    ];
    $historyBytes = 0;
    foreach (array_slice($history, -12) as $turn) {
        if (!is_array($turn) || !in_array($turn['role'] ?? null, ['user', 'assistant'], true)) continue;
        $content = $turn['content'] ?? null;
        if (!is_string($content)) continue;
        $content = trim($content);
        $length = strlen($content);
        if ($content === '' || $length > 12000 || $historyBytes + $length > 32000) continue;
        $messages[] = ['role' => $turn['role'], 'content' => $content];
        $historyBytes += $length;
    }
    $messages[] = ['role' => 'user', 'content' => $message];
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_completion_tokens' => 2048,
    ];
    if (in_array($model, ['openai/gpt-oss-20b', 'openai/gpt-oss-120b'], true)) {
        $payload['reasoning_effort'] = 'low';
        $payload['include_reasoning'] = false;
    }
    return $payload;
}

function aiChatProviderResult(int $httpCode, string $body): array
{
    if ($httpCode === 401 || $httpCode === 403) {
        return aiChatResult(503, 'AI API kaliti yaroqsiz yoki ruxsat berilmagan. Administrator Groq kalitini tekshirishi kerak.');
    }
    if ($httpCode === 429) {
        return aiChatResult(429, 'AI so\'rovlar limiti tugadi. Birozdan keyin qayta urinib ko\'ring.');
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('AI chat provider returned HTTP ' . $httpCode . '.');
        return aiChatResult(502, 'AI xizmati javob bera olmadi. Birozdan keyin qayta urinib ko\'ring.');
    }
    $data = json_decode($body, true);
    $reply = $data['choices'][0]['message']['content'] ?? null;
    if (!is_string($reply) || trim($reply) === '') {
        error_log('AI chat provider returned an empty or invalid answer.');
        return aiChatResult(502, 'AI bo\'sh javob qaytardi. Savolingizni qisqaroq yozib, qayta urinib ko\'ring.');
    }
    return aiChatResult(200, trim($reply));
}

function aiChatRequest(string $message, array $config, array $history = []): array
{
    $apiKey = $config['api_key'];
    if ($apiKey === '') {
        error_log('AI chat configuration is missing GROQ_API_KEY.');
        return aiChatResult(503, 'AI yordamchi hali ulanmagan: API kaliti kiritilmagan. Administrator Groq API kalitini sozlashi kerak.');
    }
    if (preg_match('/[\r\n]/', $apiKey) || strpos($apiKey, 'sk-') === 0) {
        return aiChatResult(503, 'AI uchun Groq API kaliti kerak. Administrator kalit sozlamasini tekshirishi kerak.');
    }
    if (preg_match('/^gsk_[A-Za-z0-9_-]+$/D', $apiKey) !== 1) {
        return aiChatResult(503, 'Groq API kaliti formati noto‘g‘ri. .env fayliga Groq Console’dan olingan, gsk_ bilan boshlanadigan kalitni Bearer prefiksisiz kiriting.');
    }
    if (!function_exists('curl_init')) {
        return aiChatResult(503, 'AI xizmatiga ulanish uchun serverda PHP cURL kengaytmasini yoqish kerak.');
    }

    $model = $config['model'];
    if ($model === '') {
        $mc = curl_init('https://api.groq.com/openai/v1/models');
        curl_setopt_array($mc, [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $mr = curl_exec($mc);
        curl_close($mc);
        $md = json_decode($mr, true);
        $chosen = null;
        if (is_array($md['data'] ?? null)) {
            foreach ($md['data'] as $m) {
                $id = $m['id'] ?? '';
                if (stripos($id, 'orpheus') !== false || stripos($id, 'tts') !== false || stripos($id, 'whisper') !== false) continue;
                if (stripos($id, 'llama') !== false || stripos($id, 'mixtral') !== false || stripos($id, 'gemma') !== false || stripos($id, 'qwen') !== false) { $chosen = $id; break; }
            }
            if ($chosen === null) {
                foreach ($md['data'] as $m) {
                    $id = $m['id'] ?? '';
                    if (stripos($id, 'orpheus') !== false || stripos($id, 'tts') !== false || stripos($id, 'whisper') !== false) continue;
                    $chosen = $id; break;
                }
            }
        }
        $model = $chosen ?: 'llama-3.1-8b-instant';
    }

    $body = '';
    $tooLarge = false;
    $curl = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(aiChatPayload($message, $model, $history), JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 262144) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $error = curl_errno($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($tooLarge) {
        return aiChatResult(502, 'AI javobi juda katta. Qisqaroq savol bilan qayta urinib ko\'ring.');
    }
    if ($error === CURLE_OPERATION_TIMEDOUT) {
        return aiChatResult(504, 'AI vaqtida javob bermadi. Qayta urinib ko\'ring.');
    }
    if ($ok === false || $error) {
        error_log('AI chat connection failed (cURL code ' . $error . ').');
        return aiChatResult(502, 'AI xizmatiga ulanib bo\'lmadi. Birozdan keyin qayta urinib ko\'ring.');
    }
    return aiChatProviderResult($httpCode, $body);
}