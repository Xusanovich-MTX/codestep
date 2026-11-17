<?php
require_once __DIR__ . '/solution-store.php';

function codeStepIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
        return true;
    }
    $cfVisitor = $_SERVER['HTTP_CF_VISITOR'] ?? '';
    if (is_string($cfVisitor) && str_contains($cfVisitor, '"https"')) {
        return true;
    }
    return false;
}

function codeStepClientIp(): string
{
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && is_string($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $candidates[] = trim($part);
        }
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP']) && is_string($_SERVER['HTTP_X_REAL_IP'])) {
        $candidates[] = trim($_SERVER['HTTP_X_REAL_IP']);
    }
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'local';
}

function codeStepSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'path' => '/',
            'secure' => codeStepIsHttps(),
        ]);
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
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
    if (($query['page'] ?? '') === 'contests') return '?page=contests';
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
    return hash('sha256', $action . ':' . codeStepClientIp() . ':' . $username);
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
