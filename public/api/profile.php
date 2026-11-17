<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/profile-store.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function profileResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
codeStepSession();
$user = codeStepUser();
$validCsrf = codeStepCsrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    session_write_close();
    header('Allow: POST');
    profileResponse(405, ['error' => true, 'message' => 'Faqat POST so‘rovi qabul qilinadi.']);
}
if (!$user) {
    session_write_close();
    profileResponse(401, ['error' => true, 'message' => 'Profil uchun hisobingizga kiring.']);
}
if (!$validCsrf) {
    session_write_close();
    profileResponse(403, ['error' => true, 'message' => 'Seans yangilangan. Sahifani yangilab qayta urinib ko‘ring.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    session_write_close();
    profileResponse(413, ['error' => true, 'message' => 'Profil matni juda uzun.']);
}
try {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        session_write_close();
        $name = $_POST['display_name'] ?? null;
        $bio = $_POST['bio'] ?? null;
        if (!is_string($name) || !is_string($bio)) throw new InvalidArgumentException('Ism va izoh matn ko‘rinishida bo‘lsin.');
        profileUpdate($user['id'], $name, $bio);
    } elseif ($action === 'remove_avatar') {
        session_write_close();
        profileAvatar($user['id'], null, null);
    } elseif ($action === 'delete_account') {
        $password = $_POST['current_password'] ?? null;
        if (!is_string($password) || $password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
            session_write_close();
            throw new InvalidArgumentException('Hisobni o‘chirishni tasdiqlash uchun joriy parolingizni kiriting.');
        }
        $bucket = codeStepAuthBucket('delete_account', (string) $user['id']);
        if (codeStepAuthLimited($bucket, 10)) {
            session_write_close();
            profileResponse(429, ['error' => true, 'message' => 'Urinishlar soni ko‘payib ketdi. 15 daqiqadan keyin qayta urinib ko‘ring.']);
        }
        $db = solutionDb();
        $cols = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $hasEmail = in_array('email', $cols, true);
        $query = $db->prepare($hasEmail ? 'SELECT password_hash, email FROM users WHERE id = ?' : 'SELECT password_hash FROM users WHERE id = ?');
        $query->execute([$user['id']]);
        $row = $query->fetch();
        $oldHash = $row['password_hash'] ?? null;
        if (!is_string($oldHash) || !password_verify($password, $oldHash)) {
            codeStepAuthFailure($bucket);
            session_write_close();
            throw new InvalidArgumentException('Joriy parol noto‘g‘ri.');
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            try {
                $db->prepare('DELETE FROM contest_submissions WHERE user_id = ?')->execute([$user['id']]);
                $db->prepare('DELETE FROM contest_registrations WHERE user_id = ?')->execute([$user['id']]);
            } catch (Throwable) {}
            if ($hasEmail && !empty($row['email'])) {
                try {
                    $db->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$row['email']]);
                } catch (Throwable) {}
            }
            $db->prepare('DELETE FROM solution_runs WHERE owner_id = ?')->execute([$user['id']]);
            $db->prepare('DELETE FROM solution_drafts WHERE owner_id = ?')->execute([$user['id']]);
            $db->prepare('DELETE FROM user_profiles WHERE user_id = ?')->execute([$user['id']]);
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
            $db->exec('COMMIT');
        } catch (Throwable $txErr) {
            try { $db->exec('ROLLBACK'); } catch (Throwable) {}
            throw $txErr;
        }
        unset($_SESSION['account']);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        session_write_close();
        profileResponse(200, ['error' => false, 'deleted' => true, 'message' => 'Hisobingiz va barcha ma’lumotlaringiz butunlay o‘chirildi.']);
    } else {
        session_write_close();
        throw new InvalidArgumentException('Noto‘g‘ri amal.');
    }
    profileResponse(200, ['error' => false, 'profile' => profileGet($user['id']), 'message' => 'Profil saqlandi.']);
} catch (InvalidArgumentException $error) {
    profileResponse(400, ['error' => true, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('CodeStep profile update: ' . $error->getMessage());
    profileResponse(503, ['error' => true, 'message' => 'Profilni saqlab bo‘lmadi. Keyinroq qayta urinib ko‘ring.']);
}
