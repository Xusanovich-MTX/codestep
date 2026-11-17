<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

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

$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$next = codeStepNextUrl($_POST['next'] ?? null);

if ($action === 'logout') {
    unset($_SESSION['account']);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: ../?page=home', true, 303);
    exit;
}

if ($action === 'forgot_password') {
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['auth_error'] = 'To‘g‘ri email manzilini kiriting.';
        header('Location: ../?page=forgot-password', true, 303);
        exit;
    }

    try {
        $db = solutionDb();
        solutionEnsureFeatureSchema($db);
        $query = $db->prepare('SELECT id, username, email FROM users WHERE email = ?');
        $query->execute([$email]);
        $user = $query->fetch();

        if (!$user) {
            $_SESSION['auth_error'] = 'Ushbu email bilan ro‘yxatdan o‘tgan profil topilmadi.';
            header('Location: ../?page=forgot-password', true, 303);
            exit;
        }

        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(24));
        $expiresAt = time() + 900;

        $del = $db->prepare('DELETE FROM password_resets WHERE email = ?');
        $del->execute([$email]);

        $ins = $db->prepare('INSERT INTO password_resets(email, code, token, expires_at) VALUES (?, ?, ?, ?)');
        $ins->execute([$email, $code, $token, $expiresAt]);

        codeStepSendResetCode($email, $code);

        $_SESSION['auth_notice'] = 'Tasdiqlash kodi ' . $email . ' pochtasiga yuborildi!';
        header('Location: ../?page=reset-password&token=' . rawurlencode($token), true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('CodeStep forgot password: ' . $e->getMessage());
        $_SESSION['auth_error'] = 'Xatolik yuz berdi. Iltimos keyinroq qayta urinib ko‘ring.';
        header('Location: ../?page=forgot-password', true, 303);
        exit;
    }
}

if ($action === 'reset_password') {
    $token = is_string($_POST['token'] ?? null) ? trim($_POST['token']) : '';
    $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    if ($token === '' || $code === '') {
        $_SESSION['auth_error'] = 'Tasdiqlash kodi yoki havola yaroqsiz.';
        header('Location: ../?page=forgot-password', true, 303);
        exit;
    }

    try {
        $db = solutionDb();
        solutionEnsureFeatureSchema($db);
        $query = $db->prepare('SELECT id, email, code, expires_at FROM password_resets WHERE token = ?');
        $query->execute([$token]);
        $reset = $query->fetch();

        if (!$reset || (int) $reset['expires_at'] < time() || $reset['code'] !== $code) {
            $_SESSION['auth_error'] = 'Tasdiqlash kodi noto‘g‘ri yoki muddati o‘tgan. Qaytadan urinib ko‘ring.';
            header('Location: ../?page=reset-password&token=' . rawurlencode($token), true, 303);
            exit;
        }

        if (strlen($password) < 8 || strlen($password) > 72 || strpos($password, "\0") !== false) {
            $_SESSION['auth_error'] = 'Parol uzunligi 8–72 ta belgi oralig‘ida bo‘lishi lozim.';
            header('Location: ../?page=reset-password&token=' . rawurlencode($token), true, 303);
            exit;
        }

        if (!hash_equals($password, $passwordConfirm)) {
            $_SESSION['auth_error'] = 'Kiritilgan parollar bir-biriga mos kelmadi.';
            header('Location: ../?page=reset-password&token=' . rawurlencode($token), true, 303);
            exit;
        }

        $email = $reset['email'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        $upd = $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
        $upd->execute([$newHash, $email]);

        $del = $db->prepare('DELETE FROM password_resets WHERE email = ?');
        $del->execute([$email]);

        unset($_SESSION['reset_code_notice']);
        $_SESSION['auth_success'] = 'Parolingiz muvaffaqiyatli yangilandi! Yangi parol bilan tizimga kiring.';
        header('Location: ../?page=login', true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('CodeStep reset password: ' . $e->getMessage());
        $_SESSION['auth_error'] = 'Xatolik yuz berdi. Iltimos keyinroq qayta urinib ko‘ring.';
        header('Location: ../?page=reset-password&token=' . rawurlencode($token), true, 303);
        exit;
    }
}

if (!in_array($action, ['login', 'register'], true)) {
    http_response_code(400);
    exit('Noto‘g‘ri amal.');
}

$identifier = is_string($_POST['username'] ?? null) ? strtolower(trim($_POST['username'])) : '';
$email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$error = null;

try {
    $bucket = codeStepAuthBucket($action, $action === 'register' ? '' : $identifier);
    if (codeStepAuthLimited($bucket, 10)) {
        $error = 'Urinishlar soni ko‘payib ketdi. 15 daqiqadan keyin qayta urinib ko‘ring.';
    } elseif ($action === 'login') {
        if ($identifier === '' || strlen($password) < 8 || strlen($password) > 72 || strpos($password, "\0") !== false) {
            $error = 'Login yoki parol noto‘g‘ri.';
        } else {
            $db = solutionDb();
            $cols = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            $hasEmail = in_array('email', $cols, true);
            if ($hasEmail && str_contains($identifier, '@')) {
                $query = $db->prepare('SELECT id, username, password_hash FROM users WHERE email = ?');
            } else {
                $query = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
            }
            $query->execute([$identifier]);
            $record = $query->fetch();
            $hash = $record['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $passwordValid = password_verify($password, $hash);
            if (!$record || !$passwordValid) {
                $error = 'Login yoki parol noto‘g‘ri.';
            } else {
                $user = ['id' => (int) $record['id'], 'username' => $record['username']];
            }
        }
    } elseif ($action === 'register') {
        if (preg_match('/^[a-z0-9_]{3,32}$/D', $identifier) !== 1) {
            $error = 'Login 3–32 ta lotin harfi, raqam yoki pastki chiziqdan iborat bo‘lsin. Parol uzunligi 8–72 ta belgi bo‘lsin.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'To‘g‘ri email manzilini kiriting (masalan: foydalanuvchi@mail.uz).';
        } elseif (strlen($password) < 8 || strlen($password) > 72 || strpos($password, "\0") !== false) {
            $error = 'Parol uzunligi 8–72 ta belgi bo‘lsin.';
        } elseif (!is_string($_POST['password_confirm'] ?? null) || !hash_equals($password, $_POST['password_confirm'])) {
            $error = 'Parollar bir xil emas.';
        } else {
            $db = solutionDb();
            if ($email !== '') {
                solutionEnsureFeatureSchema($db);
                $checkE = $db->prepare('SELECT 1 FROM users WHERE email = ?');
                $checkE->execute([$email]);
                if ($checkE->fetch()) {
                    $error = 'Ushbu email bilan ro‘yxatdan o‘tilgan. Kirish sahifasidan foydalaning.';
                }
            }
            if ($error === null) {
                $cols = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
                $hasEmail = in_array('email', $cols, true);
                try {
                    if ($hasEmail) {
                        $insert = $db->prepare('INSERT INTO users(username, email, password_hash) VALUES (?, ?, ?)');
                        $insert->execute([$identifier, $email !== '' ? $email : null, password_hash($password, PASSWORD_DEFAULT)]);
                    } else {
                        $insert = $db->prepare('INSERT INTO users(username, password_hash) VALUES (?, ?)');
                        $insert->execute([$identifier, password_hash($password, PASSWORD_DEFAULT)]);
                    }
                    $user = ['id' => (int) $db->lastInsertId(), 'username' => $identifier];
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $error = 'Bu login band. Boshqa login tanlang.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
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
