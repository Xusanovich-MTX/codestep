<?php
declare(strict_types=1);
require_once __DIR__ . '/solution-store.php';

function profileGet(int $owner): ?array
{
    solutionContext($owner);
    $query = solutionDb()->prepare('SELECT u.id, u.username, u.created_at, p.display_name, p.bio, p.updated_at,
        p.avatar IS NOT NULL AS has_avatar FROM users u JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ?');
    $query->execute([$owner]);
    $profile = $query->fetch();
    if (!$profile) return null;
    $profile['has_avatar'] = (bool) $profile['has_avatar'];
    return $profile;
}

function profileUpdate(int $owner, string $name, string $bio): void
{
    solutionContext($owner);
    solutionText($name, 320, 'Ism');
    solutionText($bio, 1120, 'Tarjimai hol');
    $name = trim($name);
    $bio = trim(str_replace(["\r\n", "\r"], "\n", $bio));
    if ($name === '' || preg_match_all('/./us', $name) > 80 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
        throw new InvalidArgumentException('Ism 1–80 ta belgidan iborat bo‘lsin va bir qatorda yozilsin.');
    }
    if (preg_match_all('/./us', $bio) > 280 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $bio)) {
        throw new InvalidArgumentException('O‘zingiz haqingizdagi matn 280 ta belgidan oshmasin.');
    }
    $query = solutionDb()->prepare('UPDATE user_profiles SET display_name = ?, bio = ?, updated_at = ? WHERE user_id = ?');
    $query->execute([$name, $bio, solutionTimestamp(), $owner]);
    if ($query->rowCount() !== 1) throw new RuntimeException('Profil topilmadi.');
}

function profileAvatar(int $owner, ?string $bytes, ?string $mime): void
{
    solutionContext($owner);
    if (($bytes === null) !== ($mime === null) || ($bytes !== null && (strlen($bytes) === 0 || strlen($bytes) > 2097152
        || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)))) {
        throw new InvalidArgumentException('Profil rasmi yaroqsiz.');
    }
    $query = solutionDb()->prepare('UPDATE user_profiles SET avatar = :avatar, avatar_mime = :mime, updated_at = :updated WHERE user_id = :owner');
    $query->bindValue(':avatar', $bytes, $bytes === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
    $query->bindValue(':mime', $mime, $mime === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $query->bindValue(':updated', solutionTimestamp());
    $query->bindValue(':owner', $owner, PDO::PARAM_INT);
    $query->execute();
    if ($query->rowCount() !== 1) throw new RuntimeException('Profil topilmadi.');
}

function profileAvatarData(int $owner): ?array
{
    solutionContext($owner);
    $query = solutionDb()->prepare('SELECT avatar, avatar_mime FROM user_profiles WHERE user_id = ? AND avatar IS NOT NULL');
    $query->execute([$owner]);
    return $query->fetch() ?: null;
}

function profileStats(int $owner): array
{
    solutionContext($owner);
    $db = solutionDb();
    $query = $db->prepare('SELECT COUNT(*) AS attempts,
        COALESCE(SUM(language = \'python\'), 0) AS python_attempts,
        COALESCE(SUM(language = \'cpp\'), 0) AS cpp_attempts FROM solution_runs WHERE owner_id = ?');
    $query->execute([$owner]);
    $stats = array_map('intval', $query->fetch());
    $query = $db->prepare('SELECT COUNT(*) FROM (SELECT topic_id, problem_number FROM solution_runs WHERE owner_id = ? AND verdict = \'accepted\' GROUP BY topic_id, problem_number)');
    $query->execute([$owner]);
    $stats['solved'] = (int) $query->fetchColumn();
    $query = $db->prepare('SELECT COUNT(*) FROM solution_drafts WHERE owner_id = ?');
    $query->execute([$owner]);
    $stats['drafts'] = (int) $query->fetchColumn();
    return $stats;
}
