<?php
require_once __DIR__ . '/solution-store.php';

function codeStepSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'httponly' => true, 'samesite' => 'Lax', 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function codeStepUser(): ?array
{
    $user = $_SESSION['account'] ?? null;
    return is_array($user) && is_int($user['id'] ?? null) && $user['id'] > 0 && is_string($user['username'] ?? null) && $user['username'] !== '' ? $user : null;
}

function codeStepCsrfValid($token): bool
{
    return is_string($token) && $token !== '' && is_string($_SESSION['csrf_token'] ?? null)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function codeStepNextUrl($next): string
{
    if (!is_string($next) || strlen($next) > 512 || !str_starts_with($next, '?')) return '?page=profile';
    parse_str(substr($next, 1), $query);
    if (($query['page'] ?? '') === 'solutions') return '?page=solutions';
    if (($query['page'] ?? '') !== 'code-editor') return '?page=profile';
    $topic = $query['topic'] ?? '';
    $problem = $query['problem'] ?? '';
    if (!is_string($topic) || !ctype_digit($topic) || !is_string($problem)
        || preg_match('/^\d+\.\d+\.\d+$/D', $problem) !== 1) return '?page=profile';
    return '?page=code-editor&topic=' . (int) $topic . '&problem=' . rawurlencode($problem)
        . '&lang=' . (($query['lang'] ?? '') === 'python' ? 'python' : 'cpp');
}

function codeStepAuthBucket(string $action, string $username): string
{
    return hash('sha256', $action . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $username);
}

function codeStepAuthLimited(string $bucket, int $limit): bool
{
    $query = solutionDb()->prepare('SELECT failures, expires_at FROM auth_throttle WHERE bucket = ?');
    $query->execute([$bucket]);
    $row = $query->fetch();
    return $row && (int) $row['expires_at'] > time() && (int) $row['failures'] >= $limit;
}

function codeStepAuthFailure(string $bucket): void
{
    $now = time();
    $query = solutionDb()->prepare('INSERT INTO auth_throttle(bucket, failures, expires_at) VALUES (?, 1, ?)
        ON CONFLICT(bucket) DO UPDATE SET failures = CASE WHEN expires_at <= ? THEN 1 ELSE failures + 1 END,
        expires_at = CASE WHEN expires_at <= ? THEN excluded.expires_at ELSE expires_at END');
    $query->execute([$bucket, $now + 900, $now, $now]);
}
