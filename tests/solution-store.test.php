<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public/includes/solution-store.php';

$testDirectory = sys_get_temp_dir() . '/codestep-store-test-' . bin2hex(random_bytes(8));
$testDatabase = $testDirectory . '/isolated.sqlite';
$previousPath = getenv('CODESTEP_DB_PATH');
putenv('CODESTEP_DB_PATH=' . $testDatabase);
$checks = 0;
$failures = [];
$db = null;

function storeCheck(string $name, callable $test): void
{
    global $checks, $failures;
    $checks++;
    try { $test(); } catch (Throwable $error) { $failures[] = $name . ': ' . $error->getMessage(); }
}
function storeAssert(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) throw new RuntimeException($message);
}
function storeThrows(callable $action, string $class): Throwable
{
    try { $action(); } catch (Throwable $error) {
        if (!$error instanceof $class) throw new RuntimeException('Unexpected exception class: ' . get_class($error));
        return $error;
    }
    throw new RuntimeException('Expected ' . $class);
}
function storeJudgment(string $status = 'accepted'): array
{
    return [
        'status' => 200, 'error' => false, 'output' => "0000010011100110\n", 'test_stdin' => "1254 16\n",
        'verdict' => ['status' => $status, 'checks_passed' => $status === 'wrong_answer' ? 0 : 1,
            'total_checks' => 1, 'scope' => $status === 'structure_passed' ? 'structure_only' : 'output'],
    ];
}

try {
    $db = solutionDb();
    $insertUser = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $insertUser->execute(['account_a', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $ownerA = (int) $db->lastInsertId();
    $insertUser->execute(['account_b', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $ownerB = (int) $db->lastInsertId();
    $insertUser = null;

    storeCheck('Lazy schema, version, foreign keys, WAL and persistent throttle table', static function () use ($db): void {
        storeAssert((int) $db->query('PRAGMA user_version')->fetchColumn() === 2);
        storeAssert((int) $db->query('PRAGMA foreign_keys')->fetchColumn() === 1);
        storeAssert((int) $db->query('PRAGMA busy_timeout')->fetchColumn() >= 1000);
        storeAssert(strtolower((string) $db->query('PRAGMA journal_mode')->fetchColumn()) === 'wal');
        $db->exec("INSERT INTO auth_throttle (bucket, failures, expires_at) VALUES ('unit-bucket', 2, 2000000000)");
        storeAssert((int) solutionDb()->query("SELECT failures FROM auth_throttle WHERE bucket = 'unit-bucket'")->fetchColumn() === 2);
    });

    storeCheck('Schema is idempotent and reconnection preserves users', static function () use ($db): void {
        $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        $db->exec($schema);
        $db->exec($schema);
        storeAssert((int) solutionDb()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 2);
    });

    storeCheck('Username normalization and uniqueness are database constraints', static function () use ($db): void {
        $insert = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        storeThrows(static fn() => $insert->execute(['account_a', 'not-a-real-credential']), PDOException::class);
        storeThrows(static fn() => $insert->execute(['ACCOUNT_A', 'not-a-real-credential']), PDOException::class);
        storeThrows(static fn() => $insert->execute(['MixedCase', 'not-a-real-credential']), PDOException::class);
    });

    storeCheck('Draft creation and separate owner/language keys', static function () use ($ownerA, $ownerB): void {
        storeAssert(solutionDrafts($ownerA, 14, '1.1.1') === []);
        $savedA = solutionSaveDraft($ownerA, 14, '1.1.1', 'python', 'print("owner A")', 0);
        $savedB = solutionSaveDraft($ownerB, 14, '1.1.1', 'python', 'print("owner B")', 0);
        solutionSaveDraft($ownerA, 14, '1.1.1', 'cpp', 'int main() { return 0; }', 0);
        storeAssert($savedA['revision'] === 1 && $savedB['revision'] === 1);
        storeAssert(preg_match('/^\d{4}-\d{2}-\d{2}T.*Z$/D', $savedA['updated_at']) === 1);
        $a = solutionDrafts($ownerA, 14, '1.1.1');
        $b = solutionDrafts($ownerB, 14, '1.1.1');
        storeAssert(array_keys($a) === ['cpp', 'python'] && array_keys($b) === ['python']);
        storeAssert($a['python']['code'] === 'print("owner A")' && $b['python']['code'] === 'print("owner B")');
    });

    storeCheck('Compare-and-swap rejects missing and stale revisions without losing current draft', static function () use ($ownerA): void {
        $missing = storeThrows(static fn() => solutionSaveDraft($ownerA, 14, '1.1.2', 'python', 'new', 3), SolutionConflict::class);
        storeAssert($missing->currentRevision === 0);
        storeAssert(solutionDrafts($ownerA, 14, '1.1.2') === []);
        $updated = solutionSaveDraft($ownerA, 14, '1.1.1', 'python', 'newer code', 1);
        storeAssert($updated['revision'] === 2);
        $stale = storeThrows(static fn() => solutionSaveDraft($ownerA, 14, '1.1.1', 'python', 'stale tab', 1), SolutionConflict::class);
        storeAssert($stale->currentRevision === 2);
        $current = solutionDrafts($ownerA, 14, '1.1.1')['python'];
        storeAssert($current['revision'] === 2 && $current['code'] === 'newer code');
        storeThrows(static fn() => solutionSaveDraft($ownerA, 14, '1.1.1', 'python', 'overwrite via create', 0), SolutionConflict::class);
    });

    storeCheck('Conflicted transactions release their lock for subsequent writes', static function () use ($ownerA): void {
        $saved = solutionSaveDraft($ownerA, 14, '1.1.2', 'python', 'after conflict', 0);
        storeAssert($saved['revision'] === 1);
    });

    storeCheck('Empty draft persists deliberate clearing and parameterized code round-trips', static function () use ($ownerA): void {
        $payload = "'); DROP TABLE users; --\nprint('O‘zbekcha')";
        solutionSaveDraft($ownerA, 14, '1.1.3', 'python', $payload, 0);
        storeAssert(solutionDrafts($ownerA, 14, '1.1.3')['python']['code'] === $payload);
        solutionSaveDraft($ownerA, 14, '1.1.3', 'python', '', 1);
        storeAssert(solutionDrafts($ownerA, 14, '1.1.3')['python']['code'] === '');
    });

    storeCheck('Draft lists and exact problem keys remain owner isolated', static function () use ($ownerA, $ownerB): void {
        foreach (solutionSavedDrafts($ownerA) as $row) storeAssert($row['owner_id'] === $ownerA);
        $b = solutionSavedDrafts($ownerB);
        storeAssert(count($b) === 1 && $b[0]['owner_id'] === $ownerB);
        storeAssert(solutionDrafts($ownerB, 14, '1.1.2') === []);
        storeAssert(solutionDrafts($ownerA, 15, '1.1.1') === []);
    });

    storeCheck('Run snapshots remain immutable when an older execution finishes after a new draft', static function () use ($ownerA): void {
        $oldCode = 'old submitted code';
        $before = solutionDrafts($ownerA, 14, '1.1.1')['python'];
        $runId = solutionSaveRun($ownerA, 14, '1.1.1', 'python', $oldCode, storeJudgment());
        $run = solutionRun($ownerA, $runId);
        storeAssert($run['code'] === $oldCode && $run['code_hash'] === hash('sha256', $oldCode));
        storeAssert($run['verdict'] === 'accepted' && $run['checks_passed'] === 1);
        storeAssert($run['test_stdin'] === "1254 16\n" && $run['output'] === "0000010011100110\n");
        storeAssert(solutionDrafts($ownerA, 14, '1.1.1')['python'] === $before, 'Run completion overwrote draft');
    });

    storeCheck('Run lookup cannot cross account ownership', static function () use ($ownerA, $ownerB): void {
        $id = solutionSaveRun($ownerB, 14, '1.1.1', 'python', 'B submission', storeJudgment('wrong_answer'));
        storeAssert(solutionRun($ownerA, $id) === null);
        storeAssert(solutionRun($ownerB, $id)['owner_id'] === $ownerB);
        storeAssert(solutionRun($ownerA, PHP_INT_MAX) === null);
    });

    storeCheck('Structured verdict is not promoted to accepted', static function () use ($ownerA): void {
        $id = solutionSaveRun($ownerA, 15, '1.1.26', 'python', 'structured code', storeJudgment('structure_passed'));
        storeAssert(solutionRun($ownerA, $id)['verdict'] === 'structure_passed');
    });

    storeCheck('Runtime and infrastructure failures are stored as error even with an accepted-shaped payload', static function () use ($ownerA): void {
        foreach ([[200, true], [422, true], [503, true], [500, false]] as [$status, $error]) {
            $result = storeJudgment();
            $result['status'] = $status;
            $result['error'] = $error;
            $result['output'] = 'execution failed';
            $id = solutionSaveRun($ownerA, 14, '1.1.1', 'cpp', 'failed code', $result);
            $run = solutionRun($ownerA, $id);
            storeAssert($run['verdict'] === 'error' && $run['checks_passed'] === 0 && $run['total_checks'] === 0);
        }
    });

    storeCheck('History filters owners and paginates in stable newest-first order', static function () use ($ownerA, $ownerB): void {
        $history = solutionHistory($ownerA);
        storeAssert(count($history) >= 6);
        foreach ($history as $row) storeAssert($row['owner_id'] === $ownerA);
        for ($i = 1; $i < count($history); $i++) storeAssert($history[$i - 1]['id'] > $history[$i]['id']);
        storeAssert(array_column(solutionHistory($ownerA, 2, 1), 'id') === array_column(array_slice($history, 1, 2), 'id'));
        storeAssert(count(solutionHistory($ownerB)) === 1);
    });

    storeCheck('Successful execution needs a consistent actual judge result', static function () use ($ownerA): void {
        $badResults = [];
        $missing = storeJudgment(); unset($missing['verdict']); $badResults[] = $missing;
        $inconsistent = storeJudgment(); $inconsistent['verdict']['checks_passed'] = 0; $badResults[] = $inconsistent;
        $wrongScope = storeJudgment(); $wrongScope['verdict']['scope'] = 'structure_only'; $badResults[] = $wrongScope;
        $clientStatus = storeJudgment(); $clientStatus['verdict']['status'] = 'solved'; $badResults[] = $clientStatus;
        $wrongCounts = storeJudgment('wrong_answer'); $wrongCounts['verdict']['checks_passed'] = 1; $badResults[] = $wrongCounts;
        foreach ($badResults as $result) storeThrows(static fn() => solutionSaveRun($ownerA, 14, '1.1.1', 'python', 'code', $result), InvalidArgumentException::class);
    });

    storeCheck('Input validation enforces language, context, UTF-8 and byte limits', static function () use ($ownerA): void {
        foreach (['ruby', 'CPP', 'python; DROP TABLE users'] as $lang) storeThrows(static fn() => solutionSaveDraft($ownerA, 14, '1.1.1', $lang, 'x', 0), InvalidArgumentException::class);
        foreach ([str_repeat('x', 65537), str_repeat('é', 32769), "x\0y", "\xFF"] as $code) storeThrows(static fn() => solutionSaveDraft($ownerA, 14, '1.1.4', 'python', $code, 0), InvalidArgumentException::class);
        solutionSaveDraft($ownerA, 14, '1.1.4', 'python', str_repeat('x', 65536), 0);
        storeAssert(strlen(solutionDrafts($ownerA, 14, '1.1.4')['python']['code']) === 65536);
        storeThrows(static fn() => solutionSaveDraft(0, 14, '1.1.1', 'python', 'x', 0), InvalidArgumentException::class);
        storeThrows(static fn() => solutionSaveDraft($ownerA, 0, '1.1.1', 'python', 'x', 0), InvalidArgumentException::class);
        storeThrows(static fn() => solutionSaveDraft($ownerA, 14, "1.1.1' OR 1=1", 'python', 'x', 0), InvalidArgumentException::class);
        storeThrows(static fn() => solutionSaveDraft($ownerA, 14, '1.1.1', 'python', 'x', -1), InvalidArgumentException::class);
        storeThrows(static fn() => solutionHistory($ownerA, 101), InvalidArgumentException::class);
        storeThrows(static fn() => solutionHistory($ownerA, 50, -1), InvalidArgumentException::class);
        $oversized = storeJudgment(); $oversized['output'] = str_repeat('x', 65537);
        storeThrows(static fn() => solutionSaveRun($ownerA, 14, '1.1.1', 'python', 'code', $oversized), InvalidArgumentException::class);
        $badStdin = storeJudgment(); $badStdin['test_stdin'] = str_repeat('x', 16385);
        storeThrows(static fn() => solutionSaveRun($ownerA, 14, '1.1.1', 'python', 'code', $badStdin), InvalidArgumentException::class);
    });

    storeCheck('Foreign keys reject unauthenticated or nonexistent account records', static function (): void {
        storeThrows(static fn() => solutionSaveDraft(999999, 14, '1.1.1', 'python', 'x', 0), PDOException::class);
        storeThrows(static fn() => solutionSaveRun(999999, 14, '1.1.1', 'python', 'x', storeJudgment()), PDOException::class);
    });

    storeCheck('Unexpected future schema version is never silently downgraded', static function () use ($db): void {
        $db->exec('PRAGMA user_version = 3');
        try { storeThrows(static fn() => solutionDb(), RuntimeException::class); }
        finally { $db->exec('PRAGMA user_version = 2'); }
    });

    storeCheck('Owner cascade removes only that account data', static function () use ($db, $ownerA, $ownerB): void {
        $statement = $db->prepare('DELETE FROM users WHERE id = ?');
        $statement->execute([$ownerB]);
        storeAssert(solutionSavedDrafts($ownerB) === [] && solutionHistory($ownerB) === []);
        storeAssert(count(solutionSavedDrafts($ownerA)) > 0 && count(solutionHistory($ownerA)) > 0);
    });
} catch (Throwable $error) {
    $failures[] = 'Test setup/execution: ' . $error->getMessage();
} finally {
    $db = null;
    if ($previousPath === false) putenv('CODESTEP_DB_PATH'); else putenv('CODESTEP_DB_PATH=' . $previousPath);
    foreach ([$testDatabase . '-wal', $testDatabase . '-shm', $testDatabase] as $file) {
        if (is_file($file)) unlink($file);
    }
    if (is_dir($testDirectory)) rmdir($testDirectory);
}

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    fwrite(STDERR, count($failures) . ' failure(s) across ' . $checks . ' checks.' . PHP_EOL);
    exit(1);
}
echo 'PASS: ' . $checks . ' SQLite schema, isolation, revision, run-history and validation checks.' . PHP_EOL;
