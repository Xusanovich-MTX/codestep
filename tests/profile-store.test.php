<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public/includes/profile-store.php';

$temporaryRoot = realpath(sys_get_temp_dir());
if ($temporaryRoot === false) throw new RuntimeException('Temporary directory is unavailable.');
$testDirectory = $temporaryRoot . DIRECTORY_SEPARATOR . 'codestep-profile-test-' . bin2hex(random_bytes(8));
$testDatabase = $testDirectory . DIRECTORY_SEPARATOR . 'isolated.sqlite';
$previousPath = getenv('CODESTEP_DB_PATH');
putenv('CODESTEP_DB_PATH=' . $testDatabase);
$checks = 0;
$failures = [];
$db = null;

function profileCheck(string $name, callable $test): void
{
    global $checks, $failures;
    $checks++;
    try { $test(); } catch (Throwable $error) { $failures[] = $name . ': ' . $error->getMessage(); }
}

function profileAssert(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) throw new RuntimeException($message);
}

function profileThrows(callable $action, string $class = InvalidArgumentException::class): void
{
    try { $action(); } catch (Throwable $error) {
        if (!$error instanceof $class) throw new RuntimeException('Unexpected exception: ' . get_class($error));
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}

function profileJudgment(string $verdict): array
{
    return [
        'status' => 200, 'error' => false, 'output' => "42\n", 'test_stdin' => "21\n",
        'verdict' => [
            'status' => $verdict, 'checks_passed' => $verdict === 'wrong_answer' ? 0 : 1,
            'total_checks' => 1, 'scope' => $verdict === 'structure_passed' ? 'structure_only' : 'output',
        ],
    ];
}

try {
    if (!mkdir($testDirectory, 0700)) throw new RuntimeException('Could not create isolated test directory.');
    $db = new PDO('sqlite:' . $testDatabase, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    // Independent v1 fixture: existing accounts and solutions have no profile table.
    $db->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL COLLATE NOCASE UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
CREATE TABLE auth_throttle (bucket TEXT PRIMARY KEY, failures INTEGER NOT NULL, expires_at INTEGER NOT NULL);
CREATE TABLE solution_drafts (
    owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    topic_id INTEGER NOT NULL, problem_number TEXT NOT NULL, language TEXT NOT NULL,
    code TEXT NOT NULL, revision INTEGER NOT NULL, updated_at TEXT NOT NULL,
    PRIMARY KEY (owner_id, topic_id, problem_number, language)
);
CREATE TABLE solution_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    topic_id INTEGER NOT NULL, problem_number TEXT NOT NULL, language TEXT NOT NULL,
    code TEXT NOT NULL, code_hash TEXT NOT NULL, output TEXT NOT NULL, verdict TEXT NOT NULL,
    checks_passed INTEGER NOT NULL, total_checks INTEGER NOT NULL, test_stdin TEXT NOT NULL,
    created_at TEXT NOT NULL
);
INSERT INTO users (id, username, password_hash, created_at) VALUES
    (41, 'existing_a', 'fixture-only-password-hash-a', '2026-01-02T03:04:05.000Z'),
    (73, 'existing_b', 'fixture-only-password-hash-b', '2026-02-03T04:05:06.000Z');
INSERT INTO auth_throttle VALUES ('fixture-bucket', 2, 2000000000);
INSERT INTO solution_drafts VALUES
    (41, 14, '1.1.1', 'python', 'print("old A draft")', 7, '2026-03-04T05:06:07.000Z'),
    (73, 14, '1.1.9', 'cpp', 'int main() { return 0; }', 3, '2026-03-04T05:06:08.000Z');
PRAGMA user_version = 1;
SQL);
    $seedRun = $db->prepare('INSERT INTO solution_runs VALUES (?, ?, 14, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)');
    foreach ([[91, 41, '1.1.1', 'python'], [92, 73, '1.1.9', 'cpp']] as [$id, $owner, $problem, $language]) {
        $code = 'legacy submitted code for ' . $owner;
        $seedRun->execute([$id, $owner, $problem, $language, $code, hash('sha256', $code), "42\n", 'accepted', "21\n", '2026-03-04T05:06:09.000Z']);
    }
    $seedRun = null;
    $legacyRows = [];
    foreach (['users', 'auth_throttle', 'solution_drafts', 'solution_runs'] as $table) {
        $legacyRows[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY rowid')->fetchAll();
    }
    $db = null;
    $db = solutionDb();
    $ownerA = 41;
    $ownerB = 73;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aV1kAAAAASUVORK5CYII=', true);
    if ($png === false) throw new RuntimeException('Invalid test image fixture.');

    profileCheck('v1 migration preserves account credentials, drafts, revisions, runs and throttles', static function () use ($db, $legacyRows): void {
        profileAssert((int) $db->query('PRAGMA user_version')->fetchColumn() === 2);
        foreach ($legacyRows as $table => $rows) {
            profileAssert($db->query('SELECT * FROM ' . $table . ' ORDER BY rowid')->fetchAll() === $rows, $table . ' changed during migration');
        }
        profileAssert((int) $db->query('SELECT COUNT(*) FROM user_profiles')->fetchColumn() === 2);
    });

    profileCheck('Existing accounts receive their own default profiles and new accounts use the trigger', static function () use ($db, $ownerA, $ownerB): void {
        foreach ([$ownerA => 'existing_a', $ownerB => 'existing_b'] as $owner => $username) {
            $profile = profileGet($owner);
            profileAssert($profile['id'] === $owner && $profile['username'] === $username && $profile['display_name'] === $username);
            profileAssert($profile['bio'] === '' && $profile['has_avatar'] === false);
            profileAssert($profile['updated_at'] === $profile['created_at']);
        }
        $db->exec("INSERT INTO users (username, password_hash) VALUES ('new_profile', 'fixture-only-password-hash-c')");
        $profile = profileGet((int) $db->lastInsertId());
        profileAssert($profile['display_name'] === 'new_profile' && $profile['bio'] === '' && !$profile['has_avatar']);
        profileAssert(profileStats($profile['id']) === ['attempts' => 0, 'python_attempts' => 0, 'cpp_attempts' => 0, 'solved' => 0, 'drafts' => 0]);
    });

    profileCheck('UTF-8 names and biographies round-trip, trim and normalize line endings for one owner', static function () use ($ownerA, $ownerB): void {
        $beforeB = profileGet($ownerB);
        profileUpdate($ownerA, '  Tohir O‘zbek 👋  ', "  Dasturlashni o‘rganaman.\r\nPython\rC++\tmasalalari  ");
        $profile = profileGet($ownerA);
        profileAssert($profile['display_name'] === 'Tohir O‘zbek 👋');
        profileAssert($profile['bio'] === "Dasturlashni o‘rganaman.\nPython\nC++\tmasalalari");
        profileAssert(profileGet($ownerB) === $beforeB, 'Another owner was changed');
    });

    profileCheck('Character limits permit 80-name and 280-biography Unicode characters', static function () use ($ownerA): void {
        profileUpdate($ownerA, str_repeat('🙂', 80), str_repeat('🙂', 280));
        $profile = profileGet($ownerA);
        profileAssert($profile['display_name'] === str_repeat('🙂', 80) && $profile['bio'] === str_repeat('🙂', 280));
        profileUpdate($ownerA, 'Qisqa ism', '');
        profileAssert(profileGet($ownerA)['bio'] === '');
    });

    profileCheck('Invalid text and over-limit character counts leave saved data intact', static function () use ($ownerA): void {
        $before = profileGet($ownerA);
        foreach (['', '   ', "bad\0name", "\xFF", "two\nlines", "tab\tname", "bad\x7Fname", str_repeat('é', 81), str_repeat('🙂', 81), str_repeat('x', 321)] as $name) {
            profileThrows(static fn() => profileUpdate($ownerA, $name, 'Valid biography'));
        }
        foreach (["bad\0bio", "\xFF", "bad\x01bio", "bad\x0Bbio", "bad\x7Fbio", str_repeat('é', 281), str_repeat('🙂', 281), str_repeat('x', 1121)] as $bio) {
            profileThrows(static fn() => profileUpdate($ownerA, 'Valid name', $bio));
        }
        profileAssert(profileGet($ownerA) === $before, 'Invalid text changed the profile');
    });

    profileCheck('Avatar and text updates are independent and cannot cross owner boundaries', static function () use ($ownerA, $ownerB, $png): void {
        profileAvatar($ownerA, $png, 'image/png');
        profileAvatar($ownerB, $png . 'owner-b', 'image/png');
        $beforeB = profileGet($ownerB);
        $avatarB = profileAvatarData($ownerB);
        profileUpdate($ownerA, 'Yangi ism', 'Yangi tarjimai hol');
        profileAssert(profileAvatarData($ownerA) === ['avatar' => $png, 'avatar_mime' => 'image/png']);
        profileAvatar($ownerA, $png . 'updated', 'image/png');
        $profile = profileGet($ownerA);
        profileAssert($profile['display_name'] === 'Yangi ism' && $profile['bio'] === 'Yangi tarjimai hol' && $profile['has_avatar']);
        profileAssert(profileGet($ownerB) === $beforeB && profileAvatarData($ownerB) === $avatarB);
        profileAvatar($ownerA, null, null);
        profileAssert(profileAvatarData($ownerA) === null && profileGet($ownerA)['has_avatar'] === false);
        profileAssert(profileGet($ownerA)['display_name'] === 'Yangi ism' && profileGet($ownerA)['bio'] === 'Yangi tarjimai hol');
        profileAssert(profileGet($ownerB) === $beforeB && profileAvatarData($ownerB) === $avatarB);
    });

    profileCheck('Avatar validation rejects mismatched metadata, unsupported types and oversized data', static function () use ($ownerA, $png): void {
        profileAvatar($ownerA, $png, 'image/png');
        $before = profileGet($ownerA);
        foreach ([[null, 'image/png'], [$png, null], ['', 'image/png'], [$png, 'image/svg+xml'], [$png, 'image/gif'], [str_repeat('x', 2097153), 'image/png']] as [$bytes, $mime]) {
            profileThrows(static fn() => profileAvatar($ownerA, $bytes, $mime));
        }
        profileAssert(profileGet($ownerA) === $before && profileAvatarData($ownerA)['avatar'] === $png);
        $maximumAvatar = str_repeat("\0\xFF", 1048576);
        profileAvatar($ownerA, $maximumAvatar, 'image/webp');
        profileAssert(profileAvatarData($ownerA) === ['avatar' => $maximumAvatar, 'avatar_mime' => 'image/webp']);
        profileAvatar($ownerA, $png, 'image/png');
    });

    profileCheck('Profile reads never expose password hashes or avatar bytes', static function () use ($ownerA): void {
        $profile = profileGet($ownerA);
        profileAssert($profile['has_avatar'] === true);
        foreach (['password_hash', 'avatar', 'avatar_mime'] as $privateField) profileAssert(!array_key_exists($privateField, $profile));
        profileAssert(!str_contains(json_encode($profile, JSON_THROW_ON_ERROR), 'fixture-only-password-hash'));
    });

    profileCheck('Invalid and nonexistent owners cannot update profiles or receive another profile', static function (): void {
        foreach ([0, -1] as $owner) {
            profileThrows(static fn() => profileGet($owner));
            profileThrows(static fn() => profileUpdate($owner, 'Name', 'Bio'));
            profileThrows(static fn() => profileAvatar($owner, null, null));
            profileThrows(static fn() => profileAvatarData($owner));
            profileThrows(static fn() => profileStats($owner));
        }
        profileAssert(profileGet(999999) === null && profileAvatarData(999999) === null);
        profileThrows(static fn() => profileUpdate(999999, 'Name', 'Bio'), RuntimeException::class);
        profileThrows(static fn() => profileAvatar(999999, null, null), RuntimeException::class);
    });

    profileCheck('Stats count distinct accepted problems across languages and isolate all owner totals', static function () use ($ownerA, $ownerB): void {
        $beforeB = profileStats($ownerB);
        profileAssert($beforeB === ['attempts' => 1, 'python_attempts' => 0, 'cpp_attempts' => 1, 'solved' => 1, 'drafts' => 1]);
        solutionSaveRun($ownerA, 14, '1.1.1', 'cpp', 'accepted in cpp', profileJudgment('accepted'));
        solutionSaveRun($ownerA, 14, '1.1.1', 'python', 'accepted again', profileJudgment('accepted'));
        solutionSaveRun($ownerA, 14, '1.1.2', 'python', 'structure only', profileJudgment('structure_passed'));
        solutionSaveRun($ownerA, 14, '1.1.3', 'cpp', 'wrong answer', profileJudgment('wrong_answer'));
        solutionSaveRun($ownerA, 14, '1.1.5', 'python', 'runtime error', ['status' => 422, 'error' => true, 'output' => 'Runtime error']);
        profileAssert(profileStats($ownerA)['solved'] === 1, 'Duplicate, structural, wrong or error runs counted as solved');
        solutionSaveRun($ownerA, 15, '1.1.1', 'cpp', 'different topic', profileJudgment('accepted'));
        solutionSaveRun($ownerA, 14, '1.1.4', 'python', 'different problem', profileJudgment('accepted'));
        solutionSaveDraft($ownerA, 14, '1.1.1', 'cpp', 'cpp draft', 0);
        solutionSaveDraft($ownerA, 14, '1.1.2', 'python', 'another draft', 0);
        profileAssert(profileStats($ownerA) === ['attempts' => 8, 'python_attempts' => 5, 'cpp_attempts' => 3, 'solved' => 3, 'drafts' => 3]);
        profileAssert(profileStats($ownerB) === $beforeB, 'Another owner contributed to stats');
    });

    profileCheck('Repeated migration and schema application preserve edited profiles and existing solutions', static function () use ($db): void {
        $tables = ['users', 'user_profiles', 'auth_throttle', 'solution_drafts', 'solution_runs'];
        $before = [];
        foreach ($tables as $table) $before[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY rowid')->fetchAll();
        $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        if ($schema === false) throw new RuntimeException('Schema fixture unavailable.');
        $db->exec($schema);
        $db->exec($schema);
        for ($retry = 0; $retry < 2; $retry++) {
            $db->exec('PRAGMA user_version = 1');
            $reopened = solutionDb();
            profileAssert((int) $reopened->query('PRAGMA user_version')->fetchColumn() === 2);
            $reopened = null;
        }
        foreach ($tables as $table) profileAssert($db->query('SELECT * FROM ' . $table . ' ORDER BY rowid')->fetchAll() === $before[$table], $table . ' was overwritten');
    });

    profileCheck('Deleting one account cascades its profile while preserving another owner', static function () use ($db, $ownerA, $ownerB): void {
        $beforeA = profileGet($ownerA);
        $avatarA = profileAvatarData($ownerA);
        $statsA = profileStats($ownerA);
        $delete = $db->prepare('DELETE FROM users WHERE id = ?');
        $delete->execute([$ownerB]);
        profileAssert(profileGet($ownerB) === null && profileAvatarData($ownerB) === null);
        profileAssert((int) $db->query('SELECT COUNT(*) FROM user_profiles WHERE user_id = ' . $ownerB)->fetchColumn() === 0);
        profileAssert(profileGet($ownerA) === $beforeA && profileAvatarData($ownerA) === $avatarA && profileStats($ownerA) === $statsA);
    });
} catch (Throwable $error) {
    $failures[] = 'Test setup/execution: ' . $error->getMessage();
} finally {
    $db = null;
    if ($previousPath === false) putenv('CODESTEP_DB_PATH'); else putenv('CODESTEP_DB_PATH=' . $previousPath);
    // Cleanup only this randomly named test directory and its three exact SQLite files.
    $resolvedDirectory = realpath($testDirectory);
    if ($resolvedDirectory !== false) {
        if (dirname($resolvedDirectory) !== $temporaryRoot || preg_match('/^codestep-profile-test-[a-f0-9]{16}$/D', basename($resolvedDirectory)) !== 1) {
            $failures[] = 'Refusing cleanup outside the isolated test directory.';
        } else {
            foreach (['isolated.sqlite-wal', 'isolated.sqlite-shm', 'isolated.sqlite'] as $name) {
                $file = $resolvedDirectory . DIRECTORY_SEPARATOR . $name;
                if (is_file($file) && !unlink($file)) $failures[] = 'Could not remove temporary ' . $name;
            }
            if (!rmdir($resolvedDirectory)) $failures[] = 'Could not remove isolated test directory.';
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    fwrite(STDERR, count($failures) . ' failure(s) across ' . $checks . ' checks.' . PHP_EOL);
    exit(1);
}
echo 'PASS: ' . $checks . ' profile migration, validation, avatar, isolation and statistics checks.' . PHP_EOL;
