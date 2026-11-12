<?php
require_once dirname(__DIR__) . '/includes/auth.php';
codeStepSession();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192 || !codeStepCsrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('So‘rov tasdiqlanmadi. Sahifani yangilang.');
}
$action = $_POST['action'] ?? '';
$next = codeStepNextUrl($_POST['next'] ?? null);
if ($action === 'logout') {
    unset($_SESSION['account']);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: ../?page=home', true, 303);
    exit;
}
if (!in_array($action, ['login', 'register'], true)) {
    http_response_code(400);
    exit('Noto‘g‘ri amal.');
}
$username = is_string($_POST['username'] ?? null) ? strtolower(trim($_POST['username'])) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$error = null;
try {
    $bucket = codeStepAuthBucket($action, $action === 'register' ? '' : $username);
    if (codeStepAuthLimited($bucket, 10)) {
        $error = 'Urinishlar soni ko‘payib ketdi. 15 daqiqadan keyin qayta urinib ko‘ring.';
    } elseif (preg_match('/^[a-z0-9_]{3,32}$/D', $username) !== 1 || strlen($password) < 8 || strlen($password) > 72 || strpos($password, "\0") !== false) {
        $error = $action === 'login' ? 'Login yoki parol noto‘g‘ri.' : 'Login 3–32 ta lotin harfi, raqam yoki pastki chiziqdan iborat bo‘lsin. Parol uzunligi 8–72 ta belgi bo‘lsin.';
    } elseif ($action === 'register') {
        if (!is_string($_POST['password_confirm'] ?? null) || !hash_equals($password, $_POST['password_confirm'])) {
            $error = 'Parollar bir xil emas.';
        } else {
            $db = solutionDb();
            $insert = $db->prepare('INSERT INTO users(username, password_hash) VALUES (?, ?)');
            try {
                $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                $user = ['id' => (int) $db->lastInsertId(), 'username' => $username];
                codeStepAuthFailure($bucket);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') $error = 'Bu login band. Boshqa login tanlang.';
                else throw $exception;
            }
        }
    } else {
        $query = solutionDb()->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
        $query->execute([$username]);
        $record = $query->fetch();
        $hash = $record['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $passwordValid = password_verify($password, $hash);
        if (!$record || !$passwordValid) $error = 'Login yoki parol noto‘g‘ri.';
        else $user = ['id' => (int) $record['id'], 'username' => $record['username']];
    }
    if ($error !== null) {
        codeStepAuthFailure($bucket);
    } else {
        session_regenerate_id(true);
        $_SESSION['account'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: ../' . $next, true, 303);
        exit;
    }
} catch (Throwable $exception) {
    error_log('CodeStep account storage: ' . $exception->getMessage());
    $error = 'Hisob xizmatiga ulanib bo‘lmadi. Keyinroq qayta urinib ko‘ring.';
}
$_SESSION['auth_error'] = $error;
header('Location: ../?page=' . $action . '&next=' . rawurlencode($next), true, 303);
