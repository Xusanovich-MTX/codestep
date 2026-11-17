<?php
declare(strict_types=1);

final class SolutionConflict extends RuntimeException
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct('Bu kod boshqa oynada yangilangan. Joriy kodingizni saqlab, serverdagi nusxani qayta yuklang.');
    }
}

function solutionDb(): PDO
{
    $root = dirname(__DIR__, 2);
    $configured = getenv('CODESTEP_DB_PATH');
    $path = is_string($configured) && $configured !== '' ? $configured : $root . '/storage/codestep.sqlite';
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Ma’lumotlar bazasi papkasini yaratib bo‘lmadi.');
    }
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA journal_mode = WAL');
    $version = (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($version > 2) throw new RuntimeException('Ma’lumotlar bazasi versiyasi dasturga mos emas.');
    if ($version < 2) {
        $db->exec('BEGIN IMMEDIATE');
        try {
            // Another request may have initialized the schema while this one waited.
            $version = (int) $db->query('PRAGMA user_version')->fetchColumn();
            if ($version === 0 || $version === 1) {
                $schema = file_get_contents($root . '/database/schema.sql');
                if ($schema === false) throw new RuntimeException('Ma’lumotlar bazasi sxemasi topilmadi.');
                $db->exec($schema);
            } elseif ($version !== 2) {
                throw new RuntimeException('Ma’lumotlar bazasi versiyasi dasturga mos emas.');
            }
            $db->exec('COMMIT');
        } catch (Throwable $error) {
            try { $db->exec('ROLLBACK'); } catch (Throwable) { }
            throw $error;
        }
    }
    return $db;
}

function solutionEnsureFeatureSchema(PDO $db): void
{
    $cols = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('email', $cols, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN email TEXT COLLATE NOCASE');
    }
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique ON users(email) WHERE email IS NOT NULL');
    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL COLLATE NOCASE,
        code TEXT NOT NULL,
        token TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS password_resets_email_idx ON password_resets(email)');
    $db->exec('CREATE INDEX IF NOT EXISTS password_resets_token_idx ON password_resets(token)');

    $db->exec("CREATE TABLE IF NOT EXISTS contests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        duration_minutes INTEGER NOT NULL DEFAULT 90,
        status TEXT NOT NULL DEFAULT 'upcoming' CHECK (status IN ('upcoming', 'running', 'finished')),
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS contest_problems (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contest_id INTEGER NOT NULL REFERENCES contests(id) ON DELETE CASCADE,
        problem_order TEXT NOT NULL CHECK (problem_order IN ('A', 'B', 'C', 'D', 'E')),
        title TEXT NOT NULL,
        difficulty TEXT NOT NULL,
        points INTEGER NOT NULL,
        topic_id INTEGER NOT NULL,
        problem_number TEXT NOT NULL,
        description TEXT NOT NULL,
        sample_input TEXT DEFAULT '',
        sample_output TEXT DEFAULT '',
        UNIQUE(contest_id, problem_order)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS contest_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contest_id INTEGER NOT NULL REFERENCES contests(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        problem_order TEXT NOT NULL CHECK (problem_order IN ('A', 'B', 'C', 'D', 'E')),
        language TEXT NOT NULL CHECK (language IN ('cpp', 'python')),
        code TEXT NOT NULL,
        verdict TEXT NOT NULL CHECK (verdict IN ('accepted', 'wrong_answer', 'error')),
        score INTEGER NOT NULL DEFAULT 0,
        penalty_seconds INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS contest_registrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contest_id INTEGER NOT NULL REFERENCES contests(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        registered_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
        UNIQUE(contest_id, user_id)
    )");

    $count = (int) $db->query('SELECT COUNT(*) FROM contests')->fetchColumn();
    if ($count === 0) {
        $tz = new DateTimeZone('Asia/Tashkent');
        $startDt = (new DateTimeImmutable('next sunday 20:00', $tz));
        $endDt = $startDt->modify('+90 minutes');
        $insContest = $db->prepare('INSERT INTO contests (title, description, start_time, end_time, duration_minutes, status) VALUES (?, ?, ?, ?, ?, ?)');
        $insContest->execute([
            'CodeStep Weekly Cup #1',
            'Haftalik dasturlash olimpiadasi. 5 ta masala (osondan murakkabgacha). Python va C++ dasturlash tillarida qatnashish mumkin.',
            $startDt->format('c'),
            $endDt->format('c'),
            90,
            'upcoming',
        ]);
        $contestId = (int) $db->lastInsertId();
        $seedProblems = [
            ['A', 'Ikki sonning yigʻindisi va koʻpaytmasi', 'Juda oson', 50, 1, '1.1', "Ikkita butun a va b sonlari berilgan. Ularning yig'indisi va ko'paytmasini probel bilan bitta qatorda chop eting.", '4 7', '11 28'],
            ['B', 'Uchburchak mavjudligi va perimetri', 'Oson', 100, 4, '1.4', "Uchta musbat butun a, b, c sonlari berilgan. Agar ular uchburchak tomonlari bo'la olsa, uning perimetrini, aks holda \"NO\" so'zini chiqaring.", '3 4 5', '12'],
            ['C', 'Massivdagi juft sonlar yigʻindisi', 'Oʻrta', 150, 15, '1.15', "N ta butun sondan iborat massiv berilgan. Massivdagi barcha juft sonlar yig'indisi va ularning sonini chop etuvchi dastur tuzing.", "5\n1 4 7 8 2", '14 3'],
            ['D', 'Palindrom satr va soʻzlar tahlili', 'Murakkab', 200, 20, '1.20', "Berilgan satr bo'sh joylar va tinish belgilarisiz chapdan ham, o'ngdan ham bir xil o'qiladimi (palindrommi) tekshiring hamda undagi unli harflar sonini toping.", 'aziza', 'YES 3'],
            ['E', 'Olimpiada: Eng uzun oʻsuvchi qism-ketma-ketlik', 'Ekspert', 250, 35, '1.35', "N ta sondan iborat ketma-ketlik berilgan. Dinamik dasturlash usulida undagi eng uzun qat'iy o'suvchi qism-ketma-ketlik (LIS) uzunligini hisoblang.", "6\n10 22 9 33 21 50", '4'],
        ];
        $insProb = $db->prepare('INSERT INTO contest_problems (contest_id, problem_order, title, difficulty, points, topic_id, problem_number, description, sample_input, sample_output) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($seedProblems as $p) {
            $insProb->execute(array_merge([$contestId], $p));
        }
    }
}

function solutionContext(int $owner, ?int $topic = null, ?string $problem = null, ?string $language = null): void
{
    if ($owner < 1 || ($topic !== null && $topic < 1)) throw new InvalidArgumentException('Foydalanuvchi yoki mavzu noto‘g‘ri.');
    if ($problem !== null && preg_match('/^(?:[1-9][0-9]*(?:\.[0-9]+){1,3}|[A-E])$/D', $problem) !== 1) {
        throw new InvalidArgumentException('Masala raqami noto‘g‘ri.');
    }
    if ($language !== null && !in_array($language, ['cpp', 'python'], true)) throw new InvalidArgumentException('Dasturlash tili noto‘g‘ri.');
}

function solutionText(string $text, int $maxBytes, string $label): void
{
    if (strlen($text) > $maxBytes || strpos($text, "\0") !== false || preg_match('//u', $text) !== 1) {
        throw new InvalidArgumentException($label . ' yaroqli UTF-8 matn bo‘lishi va hajm chegarasidan oshmasligi kerak.');
    }
}

function solutionTimestamp(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
}

/** Initial drafts use expectedRevision=0. Existing drafts use the last read revision. */
function solutionSaveDraft(int $owner, int $topic, string $problem, string $lang, string $code, int $expectedRevision): array
{
    solutionContext($owner, $topic, $problem, $lang);
    solutionText($code, 65536, 'Kod');
    if ($expectedRevision < 0 || $expectedRevision === PHP_INT_MAX) throw new InvalidArgumentException('Kod versiyasi noto‘g‘ri.');
    $db = solutionDb();
    $key = ['owner' => $owner, 'topic' => $topic, 'problem' => $problem, 'language' => $lang];
    $db->exec('BEGIN IMMEDIATE');
    try {
        $select = $db->prepare('SELECT revision FROM solution_drafts WHERE owner_id = :owner AND topic_id = :topic AND problem_number = :problem AND language = :language');
        $select->execute($key);
        $found = $select->fetchColumn();
        $currentRevision = $found === false ? 0 : (int) $found;
        if ($expectedRevision !== $currentRevision) throw new SolutionConflict($currentRevision);
        $revision = $currentRevision + 1;
        $updatedAt = solutionTimestamp();
        if ($currentRevision === 0) {
            $statement = $db->prepare('INSERT INTO solution_drafts (owner_id, topic_id, problem_number, language, code, revision, updated_at) VALUES (:owner, :topic, :problem, :language, :code, :revision, :updated_at)');
        } else {
            $statement = $db->prepare('UPDATE solution_drafts SET code = :code, revision = :revision, updated_at = :updated_at WHERE owner_id = :owner AND topic_id = :topic AND problem_number = :problem AND language = :language');
        }
        $statement->execute($key + ['code' => $code, 'revision' => $revision, 'updated_at' => $updatedAt]);
        $db->exec('COMMIT');
        return ['revision' => $revision, 'updated_at' => $updatedAt];
    } catch (Throwable $error) {
        try { $db->exec('ROLLBACK'); } catch (Throwable) { }
        throw $error;
    }
}

function solutionDrafts(int $owner, int $topic, string $problem): array
{
    solutionContext($owner, $topic, $problem);
    $query = solutionDb()->prepare('SELECT * FROM solution_drafts WHERE owner_id = :owner AND topic_id = :topic AND problem_number = :problem ORDER BY language');
    $query->execute(['owner' => $owner, 'topic' => $topic, 'problem' => $problem]);
    $drafts = [];
    foreach ($query->fetchAll() as $row) $drafts[$row['language']] = $row;
    return $drafts;
}

/**
 * Call only with the result produced by the server execution/judge path.
 * HTTP code must never forward a submitted verdict or result object here.
 * A run stores its submitted code snapshot and never overwrites a draft.
 */
function solutionSaveRun(int $owner, int $topic, string $problem, string $lang, string $code, array $result): int
{
    solutionContext($owner, $topic, $problem, $lang);
    solutionText($code, 65536, 'Kod');
    if (!is_int($result['status'] ?? null) || !is_bool($result['error'] ?? null) || !is_string($result['output'] ?? null)) {
        throw new InvalidArgumentException('Server bajarish natijasi noto‘g‘ri.');
    }
    solutionText($result['output'], 65536, 'Natija');
    $testInput = $result['test_stdin'] ?? '';
    if (!is_string($testInput)) throw new InvalidArgumentException('Test kiritish ma’lumoti noto‘g‘ri.');
    solutionText($testInput, 16384, 'Test kiritish ma’lumoti');
    $verdict = 'error';
    $passed = 0;
    $total = 0;
    if ($result['status'] === 200 && !$result['error']) {
        $judgment = $result['verdict'] ?? null;
        if (!is_array($judgment)
            || !in_array($judgment['status'] ?? null, ['accepted', 'wrong_answer', 'structure_passed'], true)
            || !is_int($judgment['checks_passed'] ?? null) || !is_int($judgment['total_checks'] ?? null)
            || $judgment['total_checks'] < 1 || $judgment['total_checks'] > 3
            || $judgment['checks_passed'] < 0 || $judgment['checks_passed'] > $judgment['total_checks']) {
            throw new InvalidArgumentException('Server tekshiruv xulosasi noto‘g‘ri.');
        }
        $verdict = $judgment['status'];
        $passed = $judgment['checks_passed'];
        $total = $judgment['total_checks'];
        if (($verdict === 'wrong_answer' && $passed >= $total)
            || ($verdict !== 'wrong_answer' && $passed !== $total)
            || ($verdict === 'accepted' && !in_array($judgment['scope'] ?? '', ['output', 'contest'], true))
            || ($verdict === 'structure_passed' && ($judgment['scope'] ?? '') !== 'structure_only')) {
            throw new InvalidArgumentException('Tekshiruv xulosasi va testlar soni mos emas.');
        }
    }
    $db = solutionDb();
    $insert = $db->prepare('INSERT INTO solution_runs (owner_id, topic_id, problem_number, language, code, code_hash, output, verdict, checks_passed, total_checks, test_stdin, created_at) VALUES (:owner, :topic, :problem, :language, :code, :code_hash, :output, :verdict, :passed, :total, :test_stdin, :created_at)');
    $insert->execute([
        'owner' => $owner, 'topic' => $topic, 'problem' => $problem, 'language' => $lang,
        'code' => $code, 'code_hash' => hash('sha256', $code), 'output' => $result['output'],
        'verdict' => $verdict, 'passed' => $passed, 'total' => $total,
        'test_stdin' => $testInput, 'created_at' => solutionTimestamp(),
    ]);
    return (int) $db->lastInsertId();
}

function solutionHistory(int $owner, int $limit = 50, int $offset = 0, ?string $filter = null): array
{
    solutionContext($owner);
    if ($limit < 1 || $limit > 100 || $offset < 0) throw new InvalidArgumentException('Tarix sahifasi chegarasi noto‘g‘ri.');
    $sql = 'SELECT * FROM solution_runs WHERE owner_id = :owner';
    if ($filter === 'accepted') {
        $sql .= ' AND verdict = "accepted"';
    } elseif ($filter === 'failed') {
        $sql .= ' AND verdict IN ("wrong_answer", "error")';
    } elseif ($filter === 'structure') {
        $sql .= ' AND verdict = "structure_passed"';
    }
    $sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
    $query = solutionDb()->prepare($sql);
    $query->bindValue(':owner', $owner, PDO::PARAM_INT);
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    return $query->fetchAll();
}

function solutionHistoryStats(int $owner): array
{
    solutionContext($owner);
    $db = solutionDb();
    $stmt = $db->prepare('
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN verdict = "accepted" THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN verdict = "wrong_answer" THEN 1 ELSE 0 END) AS wrong_answer,
            SUM(CASE WHEN verdict = "error" THEN 1 ELSE 0 END) AS error,
            SUM(CASE WHEN verdict = "structure_passed" THEN 1 ELSE 0 END) AS structure_passed,
            COUNT(DISTINCT CASE WHEN verdict = "accepted" THEN topic_id || ":" || problem_number END) AS unique_solved
        FROM solution_runs
        WHERE owner_id = :owner
    ');
    $stmt->execute(['owner' => $owner]);
    $stats = $stmt->fetch() ?: [];
    return [
        'total' => (int) ($stats['total'] ?? 0),
        'accepted' => (int) ($stats['accepted'] ?? 0),
        'wrong_answer' => (int) ($stats['wrong_answer'] ?? 0),
        'error' => (int) ($stats['error'] ?? 0),
        'structure_passed' => (int) ($stats['structure_passed'] ?? 0),
        'unique_solved' => (int) ($stats['unique_solved'] ?? 0),
    ];
}

function solutionSavedDrafts(int $owner): array
{
    solutionContext($owner);
    $query = solutionDb()->prepare('SELECT * FROM solution_drafts WHERE owner_id = :owner ORDER BY updated_at DESC, topic_id, problem_number, language');
    $query->execute(['owner' => $owner]);
    return $query->fetchAll();
}

function solutionRun(int $owner, int $runId): ?array
{
    solutionContext($owner);
    if ($runId < 1) throw new InvalidArgumentException('Yechim raqami noto‘g‘ri.');
    $query = solutionDb()->prepare('SELECT * FROM solution_runs WHERE owner_id = :owner AND id = :id');
    $query->execute(['owner' => $owner, 'id' => $runId]);
    $row = $query->fetch();
    return $row === false ? null : $row;
}
