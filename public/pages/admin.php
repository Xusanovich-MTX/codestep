<?php
declare(strict_types=1);

// Faqat admin ruxsati bor foydalanuvchilar (masalan: mamurovtohir)
$adminUsers = ['mamurovtohir'];
$isAdmin = $currentUser && in_array($currentUser['username'], $adminUsers, true);

if (!$isAdmin): ?>
<section class="account-page">
    <div class="account-card" style="text-align: center;">
        <p class="account-eyebrow" style="color: #ef4444;">RUXSAT CHEKLANGAN</p>
        <h1 style="font-size: 1.5rem;">Admin Panel</h1>
        <p>Ushbu boshqaruv bo‘limi faqat loyiha asoschisi va adminlari uchun mo‘ljallangan.</p>
        <p class="account-error">Sizning profilingizda admin huquqi mavjud emas.</p>
        <a class="account-button" href="?page=home" style="margin-top: 15px;">Bosh sahifaga qaytish</a>
    </div>
</section>
<?php return; endif;

$db = solutionDb();
solutionEnsureFeatureSchema($db);

if (!function_exists('adminFormatDate')) {
    function adminFormatDate(?string $iso): string {
        if (!$iso) return 'Faollik yo‘q';
        try {
            $dt = new DateTimeImmutable($iso);
            return $dt->setTimezone(new DateTimeZone('Asia/Tashkent'))->format('d.m.Y H:i');
        } catch (Throwable $e) {
            return $iso;
        }
    }
}

// 1. Jami statistika
$totalUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalAccepted = (int) $db->query("SELECT COUNT(*) FROM (SELECT topic_id, problem_number FROM solution_runs WHERE verdict = 'accepted' GROUP BY owner_id, topic_id, problem_number)")->fetchColumn();
$totalRuns = (int) $db->query('SELECT COUNT(*) FROM solution_runs')->fetchColumn();
$pyRuns = (int) $db->query("SELECT COUNT(*) FROM solution_runs WHERE language = 'python'")->fetchColumn();
$cppRuns = (int) $db->query("SELECT COUNT(*) FROM solution_runs WHERE language = 'cpp'")->fetchColumn();
$totalDrafts = (int) $db->query('SELECT COUNT(*) FROM solution_drafts')->fetchColumn();

// 2. Foydalanuvchilar ro'yxati va ularning fanlar/bo'limlar bo'yicha to'liq statistikasi
$usersQuery = $db->query("
    SELECT 
        u.id, 
        u.username, 
        u.email, 
        u.created_at,
        p.display_name,
        p.bio,
        -- Jami to'g'ri yechilganlar (noyob masala bo'yicha)
        (SELECT COUNT(DISTINCT r.topic_id || '-' || r.problem_number) 
         FROM solution_runs r 
         WHERE r.owner_id = u.id AND r.verdict = 'accepted') AS solved_count,
        
        -- I bo'limda yechilgan (topics 1..39)
        (SELECT COUNT(DISTINCT r.topic_id || '-' || r.problem_number) 
         FROM solution_runs r 
         WHERE r.owner_id = u.id AND r.verdict = 'accepted' AND r.topic_id < 40) AS s1_solved,
         
        -- I bo'limda xato qilingan / yecholmagan urinishlar
        (SELECT COUNT(*) 
         FROM solution_runs r 
         WHERE r.owner_id = u.id AND r.verdict != 'accepted' AND r.topic_id < 40) AS s1_failed,
         
        -- II bo'limda yechilgan (topics 40..65)
        (SELECT COUNT(DISTINCT r.topic_id || '-' || r.problem_number) 
         FROM solution_runs r 
         WHERE r.owner_id = u.id AND r.verdict = 'accepted' AND r.topic_id >= 40 AND r.topic_id < 66) AS s2_solved,
         
        -- II bo'limda xato qilingan
        (SELECT COUNT(*) 
         FROM solution_runs r 
         WHERE r.owner_id = u.id AND r.verdict != 'accepted' AND r.topic_id >= 40 AND r.topic_id < 66) AS s2_failed,
         
        -- III bo'limda yechilgan (topics 66..79)
        (SELECT COUNT(DISTINCT r.topic_id || '-' || r.problem_number) 
         FROM solution_runs r 
         WHERE r.owner_id = u.id AND r.verdict = 'accepted' AND r.topic_id >= 66) AS s3_solved,
         
        -- III bo'limda xato qilingan
        (SELECT COUNT(*) 
         FROM solution_runs r 
         WHERE r.owner_id = u.id AND r.verdict != 'accepted' AND r.topic_id >= 66) AS s3_failed,

        -- Python urinishlari
        (SELECT COUNT(*) FROM solution_runs r WHERE r.owner_id = u.id AND r.language = 'python') AS py_count,
        
        -- C++ urinishlari
        (SELECT COUNT(*) FROM solution_runs r WHERE r.owner_id = u.id AND r.language = 'cpp') AS cpp_count,

        -- Jami urinishlar
        (SELECT COUNT(*) FROM solution_runs r WHERE r.owner_id = u.id) AS attempts_count,
        
        -- Jami xato urinishlar
        (SELECT COUNT(*) FROM solution_runs r WHERE r.owner_id = u.id AND r.verdict != 'accepted') AS total_failed,

        (SELECT MAX(r.created_at) FROM solution_runs r WHERE r.owner_id = u.id) AS last_active
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    ORDER BY solved_count DESC, attempts_count DESC, u.id DESC
");
$allUsers = $usersQuery->fetchAll();

// 3. Foydalanuvchilarning batafsil urinishlari tarixi (modal oynada ko'rish uchun)
$runsHistoryQuery = $db->query("
    SELECT 
        r.id, 
        r.owner_id, 
        r.topic_id, 
        r.problem_number, 
        r.language, 
        r.verdict, 
        r.checks_passed, 
        r.total_checks, 
        r.created_at,
        substr(r.code, 1, 3000) AS code_snippet
    FROM solution_runs r
    ORDER BY r.id DESC
    LIMIT 300
");
$allRunsByOwner = [];
foreach ($runsHistoryQuery->fetchAll() as $run) {
    $allRunsByOwner[(int)$run['owner_id']][] = [
        'id' => (int)$run['id'],
        'topic_id' => (int)$run['topic_id'],
        'problem_number' => $run['problem_number'],
        'language' => $run['language'],
        'verdict' => $run['verdict'],
        'checks_passed' => (int)$run['checks_passed'],
        'total_checks' => (int)$run['total_checks'],
        'created_at' => adminFormatDate($run['created_at']),
        'code' => $run['code_snippet'] ?? ''
    ];
}

// 4. So'nggi 25 ta jonli yechim topshirish faoliyati
$recentRunsQuery = $db->query("
    SELECT 
        r.id, 
        r.owner_id, 
        u.username, 
        r.topic_id, 
        r.problem_number, 
        r.language, 
        r.verdict, 
        r.checks_passed, 
        r.total_checks, 
        r.created_at
    FROM solution_runs r
    JOIN users u ON u.id = r.owner_id
    ORDER BY r.id DESC
    LIMIT 25
");
$recentRuns = $recentRunsQuery->fetchAll();

// Baza fayli haqida
$root = is_dir(dirname(__DIR__, 2) . '/storage') ? dirname(__DIR__, 2) : dirname(__DIR__);
$dbFile = $root . '/storage/codestep.sqlite';
$dbFileSize = file_exists($dbFile) ? round(filesize($dbFile) / 1024, 1) . ' KB' : 'Topilmadi';
?>

<style id="adminDashboardStyles">
.admin-dashboard-wrap {
    --admin-card-bg: #182331;
    --admin-card-border: #344457;
    --admin-card-border-subtle: rgba(255, 255, 255, 0.06);
    --admin-text-main: #f8fafc;
    --admin-text-muted: #94a3b8;
    --admin-text-subtle: #64748b;
    --admin-banner-bg: rgba(16, 185, 129, 0.08);
    --admin-banner-border: rgba(16, 185, 129, 0.28);
    --admin-banner-title: #10b981;
    --admin-banner-path-bg: rgba(15, 23, 42, 0.7);
    --admin-banner-path-color: #cbd5e1;
    --admin-banner-path-border: rgba(255, 255, 255, 0.08);
    --admin-banner-val: #f8fafc;
    --admin-banner-accent: #10b981;
    --admin-banner-status: #10b981;
    --admin-btn-back-bg: #334155;
    --admin-btn-back-border: #475569;
    --admin-btn-back-text: #f8fafc;
    --admin-header-border: #334155;
    --admin-table-head-border: #334155;
    --admin-table-hover: rgba(255, 255, 255, 0.02);
    --admin-badge-empty-bg: rgba(100, 116, 139, 0.2);
    --admin-badge-empty-color: #94a3b8;
    --admin-badge-active-bg: rgba(16, 185, 129, 0.2);
    --admin-badge-active-color: #10b981;
    --admin-badge-failed-bg: rgba(239, 68, 68, 0.15);
    --admin-badge-failed-color: #f87171;
    --admin-num-users: #38bdf8;
    --admin-num-accepted: #10b981;
    --admin-num-runs: #f59e0b;
    --admin-num-drafts: #a855f7;
    --admin-email-color: #38bdf8;
    --admin-tag-py-bg: rgba(56, 189, 248, 0.18);
    --admin-tag-py-color: #38bdf8;
    --admin-tag-cpp-bg: rgba(168, 85, 247, 0.18);
    --admin-tag-cpp-color: #c084fc;
    --admin-input-bg: #0f172a;
    --admin-input-border: #334155;
    --admin-input-color: #f8fafc;
    --admin-btn-secondary-bg: #1e293b;
    --admin-btn-secondary-border: #334155;
    --admin-btn-secondary-color: #e2e8f0;
    --admin-modal-bg: #182331;
    --admin-modal-border: #344457;
    --admin-code-bg: #0b1118;
    --admin-progress-track: rgba(255, 255, 255, 0.08);
}

body.light-mode .admin-dashboard-wrap,
.light-mode .admin-dashboard-wrap {
    --admin-card-bg: #ffffff;
    --admin-card-border: #e2e8f0;
    --admin-card-border-subtle: #f1f5f9;
    --admin-text-main: #0f172a;
    --admin-text-muted: #475569;
    --admin-text-subtle: #64748b;
    --admin-banner-bg: #ecfdf5;
    --admin-banner-border: #a7f3d0;
    --admin-banner-title: #047857;
    --admin-banner-path-bg: #ffffff;
    --admin-banner-path-color: #065f46;
    --admin-banner-path-border: #cbd5e1;
    --admin-banner-val: #0f172a;
    --admin-banner-accent: #047857;
    --admin-banner-status: #059669;
    --admin-btn-back-bg: #f1f5f9;
    --admin-btn-back-border: #cbd5e1;
    --admin-btn-back-text: #1e293b;
    --admin-header-border: #e2e8f0;
    --admin-table-head-border: #cbd5e1;
    --admin-table-hover: #f8fafc;
    --admin-badge-empty-bg: #f1f5f9;
    --admin-badge-empty-color: #64748b;
    --admin-badge-active-bg: #d1fae5;
    --admin-badge-active-color: #047857;
    --admin-badge-failed-bg: #fee2e2;
    --admin-badge-failed-color: #b91c1c;
    --admin-num-users: #0284c7;
    --admin-num-accepted: #059669;
    --admin-num-runs: #d97706;
    --admin-num-drafts: #7c3aed;
    --admin-email-color: #0284c7;
    --admin-tag-py-bg: #e0f2fe;
    --admin-tag-py-color: #0369a1;
    --admin-tag-cpp-bg: #f3e8ff;
    --admin-tag-cpp-color: #7e22ce;
    --admin-input-bg: #ffffff;
    --admin-input-border: #cbd5e1;
    --admin-input-color: #0f172a;
    --admin-btn-secondary-bg: #f8fafc;
    --admin-btn-secondary-border: #cbd5e1;
    --admin-btn-secondary-color: #1e293b;
    --admin-modal-bg: #ffffff;
    --admin-modal-border: #e2e8f0;
    --admin-code-bg: #f8fafc;
    --admin-progress-track: #e2e8f0;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--admin-header-border);
    padding-bottom: 16px;
    transition: border-color 0.2s ease;
}

.admin-title {
    margin: 4px 0 0;
    font-size: 1.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--admin-text-main);
}

.admin-btn-back {
    margin: 0 !important;
    background: var(--admin-btn-back-bg) !important;
    border: 1px solid var(--admin-btn-back-border) !important;
    color: var(--admin-btn-back-text) !important;
    transition: background 0.2s ease, border-color 0.2s ease;
}

.admin-btn-back:hover {
    filter: brightness(0.95);
}

.admin-banner {
    background: var(--admin-banner-bg);
    border: 1px solid var(--admin-banner-border);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.admin-banner-title {
    color: var(--admin-banner-title);
    font-size: 0.95rem;
}

.admin-banner-path {
    background: var(--admin-banner-path-bg);
    color: var(--admin-banner-path-color);
    border: 1px solid var(--admin-banner-path-border);
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-family: monospace;
    word-break: break-all;
}

.admin-banner-meta {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
}

.admin-meta-val {
    color: var(--admin-banner-val);
}

.admin-accent-val {
    color: var(--admin-banner-accent);
}

.admin-status-live {
    color: var(--admin-banner-status);
    font-weight: 700;
}

.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.admin-card {
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-card-border);
    border-radius: 14px;
    padding: 20px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.admin-card-head {
    margin: 0 0 16px;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    color: var(--admin-text-main);
}

.admin-card-meta {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
    font-weight: normal;
}

.admin-stat-label {
    color: var(--admin-text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}

.admin-stat-num {
    font-size: 2rem;
    font-weight: 800;
    margin-top: 6px;
}

.admin-stat-sub {
    color: var(--admin-text-subtle);
    font-size: 0.78rem;
    margin-top: 4px;
}

.admin-table-wrap {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 0.875rem;
}

.admin-table thead tr {
    border-bottom: 2px solid var(--admin-table-head-border);
    color: var(--admin-text-muted);
}

.admin-table tbody tr {
    border-bottom: 1px solid var(--admin-card-border-subtle);
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: var(--admin-table-hover);
}

.admin-user-title {
    color: var(--admin-text-main);
}

.admin-user-sub {
    color: var(--admin-text-muted);
    font-size: 0.78rem;
}

.admin-user-email {
    color: var(--admin-email-color);
    font-family: monospace;
    font-size: 0.82rem;
}

.admin-td-val {
    color: var(--admin-text-main);
}

.admin-td-sub {
    color: var(--admin-text-muted);
    font-size: 0.82rem;
}

.admin-badge-solved {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 16px;
    font-weight: 700;
    font-size: 0.8rem;
}

.admin-tag-lang {
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
}

.admin-tag-py {
    background: var(--admin-tag-py-bg);
    color: var(--admin-tag-py-color);
}

.admin-tag-cpp {
    background: var(--admin-tag-cpp-bg);
    color: var(--admin-tag-cpp-color);
}

/* User Tracking Matrix Controls */
.admin-toolbar-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}

.admin-search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--admin-input-bg);
    border: 1px solid var(--admin-input-border);
    border-radius: 10px;
    padding: 8px 14px;
    flex: 1;
    min-width: 260px;
    max-width: 450px;
}

.admin-search-input {
    border: none;
    background: transparent;
    color: var(--admin-input-color);
    font: inherit;
    width: 100%;
    outline: none;
}

.admin-filter-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.admin-filter-pill {
    background: var(--admin-btn-secondary-bg);
    border: 1px solid var(--admin-btn-secondary-border);
    color: var(--admin-btn-secondary-color);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.15s ease;
}

.admin-filter-pill:hover,
.admin-filter-pill.active {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}

.admin-btn-action {
    background: var(--admin-btn-secondary-bg);
    border: 1px solid var(--admin-btn-secondary-border);
    color: var(--admin-btn-secondary-color);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}

.admin-btn-action:hover {
    filter: brightness(0.92);
}

/* Section cell chips */
.admin-section-stat {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 85px;
}

.chip-ok {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-weight: 700;
    font-size: 0.78rem;
    color: #10b981;
    background: rgba(16, 185, 129, 0.12);
    padding: 2px 6px;
    border-radius: 6px;
}

.chip-fail {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-weight: 600;
    font-size: 0.74rem;
    color: #ef4444;
    background: rgba(239, 68, 68, 0.12);
    padding: 1px 5px;
    border-radius: 6px;
}

.chip-none {
    color: var(--admin-text-subtle);
    font-size: 0.78rem;
}

/* Progress bar inside table */
.admin-rate-wrap {
    width: 90px;
}

.admin-progress-bar {
    width: 100%;
    height: 6px;
    background: var(--admin-progress-track);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 4px;
}

.admin-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #06b6d4);
    border-radius: 3px;
}

/* Drilldown Modal */
.admin-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.admin-modal-box {
    background: var(--admin-modal-bg);
    border: 1px solid var(--admin-modal-border);
    border-radius: 16px;
    max-width: 850px;
    width: 100%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    animation: adminModalFade 0.2s ease-out;
}

@keyframes adminModalFade {
    from { opacity: 0; transform: scale(0.96); }
    to { opacity: 1; transform: scale(1); }
}

.admin-modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--admin-modal-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.admin-modal-body {
    padding: 20px;
    overflow-y: auto;
}

.admin-modal-close {
    background: transparent;
    border: none;
    color: var(--admin-text-muted);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}

.admin-code-block {
    background: var(--admin-code-bg);
    border: 1px solid var(--admin-card-border-subtle);
    border-radius: 8px;
    padding: 12px;
    font-family: monospace;
    font-size: 0.8rem;
    white-space: pre-wrap;
    max-height: 180px;
    overflow-y: auto;
    color: var(--admin-text-main);
    margin-top: 8px;
}
</style>

<div class="admin-dashboard-wrap" style="max-width: 1240px; margin: 24px auto 60px; padding: 0 16px;">
    <!-- Dashboard Header -->
    <div class="admin-header">
        <div>
            <span style="font-size: 0.75rem; font-weight: 700; color: #10b981; letter-spacing: 1.5px; text-transform: uppercase;">Loyiha Nazorati</span>
            <h1 class="admin-title">
                👑 CodeStep Boshqaruv (Admin) Paneli
            </h1>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="?page=profile" class="account-button admin-btn-back">Profilga qaytish</a>
            <a href="?page=home" class="account-button" style="margin: 0;">Saytga o‘tish</a>
        </div>
    </div>

    <!-- Baza ma'lumoti bildirishnomasi -->
    <div class="admin-banner">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <strong class="admin-banner-title">📁 Ma'lumotlar bazasi:</strong>
            <code class="admin-banner-path"><?php echo htmlspecialchars($dbFile); ?></code>
        </div>
        <div class="admin-banner-meta">
            Hajmi: <strong class="admin-meta-val"><?php echo $dbFileSize; ?></strong> &nbsp;|&nbsp;
            Versiyasi: <strong class="admin-accent-val">SQLite v3</strong> &nbsp;|&nbsp;
            Status: <span class="admin-status-live">● Jonli (Aktiv)</span>
        </div>
    </div>

    <!-- Statistika kartochkalari -->
    <div class="admin-stats-grid">
        <div class="admin-card">
            <div class="admin-stat-label">👥 Foydalanuvchilar</div>
            <div class="admin-stat-num" style="color: var(--admin-num-users);"><?php echo number_format($totalUsers); ?> ta</div>
            <div class="admin-stat-sub">Ro‘yxatdan o‘tgan barcha hisoblar</div>
        </div>
        <div class="admin-card">
            <div class="admin-stat-label">✅ Yechilgan masalalar</div>
            <div class="admin-stat-num" style="color: var(--admin-num-accepted);"><?php echo number_format($totalAccepted); ?> ta</div>
            <div class="admin-stat-sub">Qabul qilingan (Accepted) yechimlar</div>
        </div>
        <div class="admin-card">
            <div class="admin-stat-label">⚡ Jami urinishlar</div>
            <div class="admin-stat-num" style="color: var(--admin-num-runs);"><?php echo number_format($totalRuns); ?> ta</div>
            <div class="admin-stat-sub">Python: <?php echo $pyRuns; ?> | C++: <?php echo $cppRuns; ?></div>
        </div>
        <div class="admin-card">
            <div class="admin-stat-label">📝 Qoralamalar (Drafts)</div>
            <div class="admin-stat-num" style="color: var(--admin-num-drafts);"><?php echo number_format($totalDrafts); ?> ta</div>
            <div class="admin-stat-sub">Tahrirlangan va saqlangan kodlar</div>
        </div>
    </div>

    <!-- ASOSIY JADVAL: Foydalanuvchilar monitoringi va bo'limlar bo'yicha nazorat jadvali -->
    <div class="admin-card" style="margin-bottom: 30px;">
        <div class="admin-card-head">
            <div>
                <h2 style="margin: 0; font-size: 1.3rem;">📊 Foydalanuvchilar va Fanlar bo‘yicha O‘zlashtirish Nazorati</h2>
                <div class="admin-card-meta" style="margin-top: 4px;">Barcha ro‘yxatdan o‘tgan o‘quvchilarning bo‘limlar kesimidagi natijalari</div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="admin-btn-action" onclick="exportUsersToCSV()">
                    📥 Excel / CSV yuklab olish
                </button>
            </div>
        </div>

        <!-- Filter and Search Row -->
        <div class="admin-toolbar-row">
            <div class="admin-search-box">
                <span style="color: var(--admin-text-subtle);">🔍</span>
                <input type="text" id="userSearchInput" class="admin-search-input" placeholder="Login, ism yoki email orqali qidirish..." oninput="filterUserTable()">
            </div>
            <div class="admin-filter-tabs">
                <button type="button" class="admin-filter-pill active" data-filter="all" onclick="setUserFilter('all', this)">Barchasi (<?php echo count($allUsers); ?>)</button>
                <button type="button" class="admin-filter-pill" data-filter="active" onclick="setUserFilter('active', this)">Yechganlar</button>
                <button type="button" class="admin-filter-pill" data-filter="s1" onclick="setUserFilter('s1', this)">I bo‘lim (Asoslar)</button>
                <button type="button" class="admin-filter-pill" data-filter="s2" onclick="setUserFilter('s2', this)">II bo‘lim (OOP)</button>
                <button type="button" class="admin-filter-pill" data-filter="s3" onclick="setUserFilter('s3', this)">III bo‘lim (Vizual)</button>
            </div>
        </div>
        
        <div class="admin-table-wrap">
            <table class="admin-table" id="usersMonitoringTable">
                <thead>
                    <tr>
                        <th style="padding: 12px 10px;">ID</th>
                        <th style="padding: 12px 10px;">Foydalanuvchi</th>
                        <th style="padding: 12px 10px;">Email pochta</th>
                        <th style="padding: 12px 10px;" title="1-39 mavzular">I bo‘lim (Asoslar)</th>
                        <th style="padding: 12px 10px;" title="40-65 mavzular">II bo‘lim (OOP)</th>
                        <th style="padding: 12px 10px;" title="66-79 mavzular">III bo‘lim (Vizual)</th>
                        <th style="padding: 12px 10px;">Tillar</th>
                        <th style="padding: 12px 10px; text-align: center;">Samaradorlik</th>
                        <th style="padding: 12px 10px; text-align: center;">Jami yechilgan</th>
                        <th style="padding: 12px 10px;">Oxirgi faollik</th>
                        <th style="padding: 12px 10px; text-align: center;">Amal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allUsers)): ?>
                        <tr><td colspan="11" style="padding: 24px; text-align: center; color: var(--admin-text-subtle);">Hozircha foydalanuvchilar mavjud emas.</td></tr>
                    <?php else: foreach ($allUsers as $u): 
                        $rate = $u['attempts_count'] > 0 ? round(($u['solved_count'] / max(1, $u['attempts_count'])) * 100) : 0;
                        $hasActivity = $u['solved_count'] > 0 || $u['attempts_count'] > 0;
                    ?>
                        <tr class="user-row" 
                            data-username="<?php echo htmlspecialchars(strtolower($u['username'])); ?>"
                            data-name="<?php echo htmlspecialchars(strtolower($u['display_name'] ?? '')); ?>"
                            data-email="<?php echo htmlspecialchars(strtolower($u['email'] ?? '')); ?>"
                            data-has-active="<?php echo $hasActivity ? '1' : '0'; ?>"
                            data-s1="<?php echo $u['s1_solved'] > 0 ? '1' : '0'; ?>"
                            data-s2="<?php echo $u['s2_solved'] > 0 ? '1' : '0'; ?>"
                            data-s3="<?php echo $u['s3_solved'] > 0 ? '1' : '0'; ?>">
                            <td style="padding: 12px 10px; color: var(--admin-text-subtle); font-family: monospace;">#<?php echo $u['id']; ?></td>
                            <td style="padding: 12px 10px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #0284c7); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong class="admin-user-title">@<?php echo htmlspecialchars($u['username']); ?></strong>
                                        <?php if (!empty($u['display_name']) && $u['display_name'] !== $u['username']): ?>
                                            <div class="admin-user-sub"><?php echo htmlspecialchars($u['display_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($u['username'] === 'mamurovtohir'): ?>
                                            <span style="background: #2563eb; color: #fff; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 2px;">ADMIN</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 12px 10px;">
                                <?php if (!empty($u['email'])): ?>
                                    <span class="admin-user-email"><?php echo htmlspecialchars($u['email']); ?></span>
                                <?php else: ?>
                                    <span style="color: var(--admin-text-subtle); font-style: italic; font-size: 0.8rem;">Yo‘q</span>
                                <?php endif; ?>
                            </td>
                            <!-- 1-bo'lim statistikasi -->
                            <td style="padding: 12px 10px;">
                                <div class="admin-section-stat">
                                    <?php if ($u['s1_solved'] > 0): ?>
                                        <span class="chip-ok" title="To'g'ri yechilgan">✔ <?php echo (int)$u['s1_solved']; ?> ta</span>
                                    <?php endif; ?>
                                    <?php if ($u['s1_failed'] > 0): ?>
                                        <span class="chip-fail" title="Xato bo'lgan urinishlar">✖ <?php echo (int)$u['s1_failed']; ?> ta</span>
                                    <?php endif; ?>
                                    <?php if ($u['s1_solved'] == 0 && $u['s1_failed'] == 0): ?>
                                        <span class="chip-none">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <!-- 2-bo'lim statistikasi -->
                            <td style="padding: 12px 10px;">
                                <div class="admin-section-stat">
                                    <?php if ($u['s2_solved'] > 0): ?>
                                        <span class="chip-ok" title="To'g'ri yechilgan">✔ <?php echo (int)$u['s2_solved']; ?> ta</span>
                                    <?php endif; ?>
                                    <?php if ($u['s2_failed'] > 0): ?>
                                        <span class="chip-fail" title="Xato bo'lgan urinishlar">✖ <?php echo (int)$u['s2_failed']; ?> ta</span>
                                    <?php endif; ?>
                                    <?php if ($u['s2_solved'] == 0 && $u['s2_failed'] == 0): ?>
                                        <span class="chip-none">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <!-- 3-bo'lim statistikasi -->
                            <td style="padding: 12px 10px;">
                                <div class="admin-section-stat">
                                    <?php if ($u['s3_solved'] > 0): ?>
                                        <span class="chip-ok" title="To'g'ri yechilgan">✔ <?php echo (int)$u['s3_solved']; ?> ta</span>
                                    <?php endif; ?>
                                    <?php if ($u['s3_failed'] > 0): ?>
                                        <span class="chip-fail" title="Xato bo'lgan urinishlar">✖ <?php echo (int)$u['s3_failed']; ?> ta</span>
                                    <?php endif; ?>
                                    <?php if ($u['s3_solved'] == 0 && $u['s3_failed'] == 0): ?>
                                        <span class="chip-none">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <!-- Tillar -->
                            <td style="padding: 12px 10px;">
                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                    <?php if ($u['py_count'] > 0): ?>
                                        <span class="admin-tag-lang admin-tag-py" title="Python urinishlari">Py: <?php echo (int)$u['py_count']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($u['cpp_count'] > 0): ?>
                                        <span class="admin-tag-lang admin-tag-cpp" title="C++ urinishlari">C++: <?php echo (int)$u['cpp_count']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($u['py_count'] == 0 && $u['cpp_count'] == 0): ?>
                                        <span class="chip-none">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <!-- Samaradorlik va progress -->
                            <td style="padding: 12px 10px; text-align: center;">
                                <?php if ($u['attempts_count'] > 0): ?>
                                    <div class="admin-rate-wrap" style="margin: 0 auto;">
                                        <span style="font-weight: 700; font-size: 0.8rem; color: <?php echo $rate >= 50 ? '#10b981' : '#f59e0b'; ?>;"><?php echo $rate; ?>%</span>
                                        <div class="admin-progress-bar">
                                            <div class="admin-progress-fill" style="width: <?php echo min(100, max(5, $rate)); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="chip-none">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- Jami yechilgan -->
                            <td style="padding: 12px 10px; text-align: center;">
                                <span class="admin-badge-solved" style="background: <?php echo $u['solved_count'] > 0 ? 'var(--admin-badge-active-bg)' : 'var(--admin-badge-empty-bg)'; ?>; color: <?php echo $u['solved_count'] > 0 ? 'var(--admin-badge-active-color)' : 'var(--admin-badge-empty-color)'; ?>;">
                                    🎯 <?php echo (int) $u['solved_count']; ?> ta
                                </span>
                                <div style="font-size: 0.72rem; color: var(--admin-text-subtle); margin-top: 2px;">
                                    (<?php echo (int) $u['attempts_count']; ?> urinish)
                                </div>
                            </td>
                            <!-- Oxirgi faollik -->
                            <td style="padding: 12px 10px; color: var(--admin-td-sub); font-size: 0.8rem;">
                                <?php echo adminFormatDate($u['last_active']); ?>
                            </td>
                            <!-- Amal (Modal ochish) -->
                            <td style="padding: 12px 10px; text-align: center;">
                                <button type="button" class="admin-btn-action" style="padding: 4px 8px; font-size: 0.76rem;" onclick='openUserHistoryModal(<?php echo (int)$u['id']; ?>, <?php echo json_encode($u['username']); ?>, <?php echo (int)$u['solved_count']; ?>, <?php echo (int)$u['attempts_count']; ?>, <?php echo (int)$u['s1_solved']; ?>, <?php echo (int)$u['s2_solved']; ?>, <?php echo (int)$u['s3_solved']; ?>)'>
                                    👁️ Ko‘rish
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2-JADVAL: So'nggi jonli yechimlar faoliyati (Real vaqt) -->
    <div class="admin-card">
        <h2 class="admin-card-head" style="margin-bottom: 16px;">
            <span>⚡ So‘nggi 25 ta yechim topshirish faoliyati (Real vaqt)</span>
        </h2>
        
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="padding: 10px 12px;">ID</th>
                        <th style="padding: 10px 12px;">Foydalanuvchi</th>
                        <th style="padding: 10px 12px;">Mavzu &amp; Masala</th>
                        <th style="padding: 10px 12px;">Til</th>
                        <th style="padding: 10px 12px;">Natija (Verdict)</th>
                        <th style="padding: 10px 12px;">Testlar</th>
                        <th style="padding: 10px 12px;">Vaqt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRuns)): ?>
                        <tr><td colspan="7" style="padding: 24px; text-align: center; color: var(--admin-text-subtle);">Hozircha yechim urinishlari amalga oshirilmagan.</td></tr>
                    <?php else: foreach ($recentRuns as $r): ?>
                        <tr>
                            <td style="padding: 10px 12px; color: var(--admin-text-subtle); font-family: monospace;">#<?php echo $r['id']; ?></td>
                            <td style="padding: 10px 12px; font-weight: 600; color: var(--admin-td-val);">
                                @<?php echo htmlspecialchars($r['username']); ?>
                            </td>
                            <td style="padding: 10px 12px; color: var(--admin-td-val);">
                                <?php echo (int) $r['topic_id']; ?>-mavzu, Masala: <strong><?php echo htmlspecialchars($r['problem_number']); ?></strong>
                            </td>
                            <td style="padding: 10px 12px;">
                                <span class="admin-tag-lang <?php echo $r['language'] === 'python' ? 'admin-tag-py' : 'admin-tag-cpp'; ?>">
                                    <?php echo strtoupper($r['language']); ?>
                                </span>
                            </td>
                            <td style="padding: 10px 12px;">
                                <?php if ($r['verdict'] === 'accepted'): ?>
                                    <span style="color: #059669; font-weight: 700; background: rgba(16, 185, 129, 0.15); padding: 3px 8px; border-radius: 6px;">✔ Accepted</span>
                                <?php elseif ($r['verdict'] === 'wrong_answer'): ?>
                                    <span style="color: #d97706; font-weight: 600; background: rgba(245, 158, 11, 0.15); padding: 3px 8px; border-radius: 6px;">✖ Wrong Answer</span>
                                <?php else: ?>
                                    <span style="color: #dc2626; font-weight: 600; background: rgba(239, 68, 68, 0.15); padding: 3px 8px; border-radius: 6px;"><?php echo htmlspecialchars($r['verdict']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 12px; font-family: monospace; color: var(--admin-td-val);">
                                <?php echo (int) $r['checks_passed']; ?> / <?php echo (int) $r['total_checks']; ?>
                            </td>
                            <td style="padding: 10px 12px; color: var(--admin-td-sub);">
                                <?php echo adminFormatDate($r['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Foydalanuvchi Tarixini Ko'rish Modali (User Drilldown Modal) -->
<div id="userHistoryModal" class="admin-modal-overlay">
    <div class="admin-modal-box">
        <div class="admin-modal-header">
            <div>
                <h3 id="modalUserTitle" style="margin: 0; font-size: 1.15rem; color: var(--admin-text-main);">Foydalanuvchi Faoliyati</h3>
                <div id="modalUserMeta" style="font-size: 0.8rem; color: var(--admin-text-muted); margin-top: 2px;"></div>
            </div>
            <button type="button" class="admin-modal-close" onclick="closeUserHistoryModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <!-- Bo'limlar kesimidagi qisqa statistik kartochkalar -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px;">
                <div class="admin-card" style="padding: 12px; text-align: center;">
                    <div style="font-size: 0.78rem; color: var(--admin-text-muted);">I bo‘lim (Asoslar)</div>
                    <div id="modalS1Stat" style="font-size: 1.4rem; font-weight: 800; color: #10b981; margin-top: 4px;">0 ta</div>
                </div>
                <div class="admin-card" style="padding: 12px; text-align: center;">
                    <div style="font-size: 0.78rem; color: var(--admin-text-muted);">II bo‘lim (OOP)</div>
                    <div id="modalS2Stat" style="font-size: 1.4rem; font-weight: 800; color: #0284c7; margin-top: 4px;">0 ta</div>
                </div>
                <div class="admin-card" style="padding: 12px; text-align: center;">
                    <div style="font-size: 0.78rem; color: var(--admin-text-muted);">III bo‘lim (Vizual)</div>
                    <div id="modalS3Stat" style="font-size: 1.4rem; font-weight: 800; color: #a855f7; margin-top: 4px;">0 ta</div>
                </div>
            </div>

            <!-- Yechimlar jurnali -->
            <h4 style="margin: 0 0 10px; color: var(--admin-text-main); font-size: 0.95rem;">Topshirgan barcha yechimlar ro‘yxati:</h4>
            <div id="modalRunsList"></div>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid var(--admin-modal-border); display: flex; justify-content: flex-end;">
            <button type="button" class="admin-btn-action" onclick="closeUserHistoryModal()">Yopish</button>
        </div>
    </div>
</div>

<script>
// Tarix ma'lumotlari
const USER_RUNS_DATA = <?php echo json_encode($allRunsByOwner, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || {};

let currentFilter = 'all';

function setUserFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.admin-filter-pill').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    filterUserTable();
}

function filterUserTable() {
    const query = (document.getElementById('userSearchInput').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#usersMonitoringTable tbody tr.user-row');

    rows.forEach(row => {
        const u = row.dataset.username || '';
        const n = row.dataset.name || '';
        const e = row.dataset.email || '';
        const hasActive = row.dataset.hasActive === '1';
        const s1 = row.dataset.s1 === '1';
        const s2 = row.dataset.s2 === '1';
        const s3 = row.dataset.s3 === '1';

        const matchSearch = !query || u.includes(query) || n.includes(query) || e.includes(query);
        let matchFilter = true;
        if (currentFilter === 'active') matchFilter = hasActive;
        else if (currentFilter === 's1') matchFilter = s1;
        else if (currentFilter === 's2') matchFilter = s2;
        else if (currentFilter === 's3') matchFilter = s3;

        row.style.display = (matchSearch && matchFilter) ? '' : 'none';
    });
}

function openUserHistoryModal(userId, username, solved, attempts, s1, s2, s3) {
    document.getElementById('modalUserTitle').innerText = '👤 @' + username + ' faoliyati';
    document.getElementById('modalUserMeta').innerText = 'Jami yechilgan: ' + solved + ' ta masala | Jami urinishlar: ' + attempts + ' marta';
    document.getElementById('modalS1Stat').innerText = s1 + ' ta';
    document.getElementById('modalS2Stat').innerText = s2 + ' ta';
    document.getElementById('modalS3Stat').innerText = s3 + ' ta';

    const runs = USER_RUNS_DATA[userId] || [];
    const container = document.getElementById('modalRunsList');

    if (runs.length === 0) {
        container.innerHTML = '<div style="padding: 24px; text-align: center; color: var(--admin-text-subtle);">Ushbu foydalanuvchi hali kod yechimini topshirmagan.</div>';
    } else {
        let html = '<div style="overflow-x:auto;"><table class="admin-table" style="font-size:0.8rem;">' +
                   '<thead><tr><th>ID</th><th>Mavzu & Masala</th><th>Til</th><th>Natija</th><th>Testlar</th><th>Vaqt</th><th>Kod</th></tr></thead><tbody>';
        runs.forEach((r, idx) => {
            let verdictBadge = '';
            if (r.verdict === 'accepted') {
                verdictBadge = '<span style="color:#059669;font-weight:700;background:rgba(16,185,129,0.15);padding:2px 6px;border-radius:4px;">✔ Accepted</span>';
            } else if (r.verdict === 'wrong_answer') {
                verdictBadge = '<span style="color:#d97706;font-weight:600;background:rgba(245,158,11,0.15);padding:2px 6px;border-radius:4px;">✖ Wrong</span>';
            } else {
                verdictBadge = '<span style="color:#dc2626;font-weight:600;background:rgba(239,68,68,0.15);padding:2px 6px;border-radius:4px;">' + (r.verdict || 'Error') + '</span>';
            }

            const codeId = 'userCode_' + r.id + '_' + idx;
            const codeBlock = r.code ? 
                '<button type="button" class="admin-btn-action" style="padding:2px 6px;font-size:0.7rem;" onclick="toggleCodeSnippet(\'' + codeId + '\')">Kod</button>' +
                '<div id="' + codeId + '" class="admin-code-block" style="display:none;">' + escapeHtml(r.code) + '</div>' : '—';

            html += '<tr>' +
                    '<td style="padding:8px;color:var(--admin-text-subtle);font-family:monospace;">#' + r.id + '</td>' +
                    '<td style="padding:8px;color:var(--admin-text-main);">' + r.topic_id + '-mavzu, Masala: <strong>' + escapeHtml(r.problem_number) + '</strong></td>' +
                    '<td style="padding:8px;"><span class="admin-tag-lang ' + (r.language === 'python' ? 'admin-tag-py' : 'admin-tag-cpp') + '">' + (r.language || '').toUpperCase() + '</span></td>' +
                    '<td style="padding:8px;">' + verdictBadge + '</td>' +
                    '<td style="padding:8px;font-family:monospace;color:var(--admin-text-main);">' + r.checks_passed + ' / ' + r.total_checks + '</td>' +
                    '<td style="padding:8px;color:var(--admin-text-muted);">' + r.created_at + '</td>' +
                    '<td style="padding:8px;">' + codeBlock + '</td>' +
                    '</tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    const modal = document.getElementById('userHistoryModal');
    modal.style.display = 'flex';
}

function toggleCodeSnippet(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function closeUserHistoryModal() {
    const modal = document.getElementById('userHistoryModal');
    if (modal) modal.style.display = 'none';
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeUserHistoryModal();
});

// CSV Export
function exportUsersToCSV() {
    const rows = document.querySelectorAll('#usersMonitoringTable tbody tr.user-row');
    if (!rows.length) {
        alert('Eksport qilish uchun foydalanuvchilar mavjud emas.');
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
    csvContent += "ID,Username,Email,I_bolim_yechilgan,II_bolim_yechilgan,III_bolim_yechilgan,Jami_yechilgan,Jami_urinishlar\n";

    rows.forEach(row => {
        const id = row.cells[0]?.innerText.trim().replace('#', '') || '';
        const user = row.dataset.username || '';
        const email = row.dataset.email || '';
        const s1 = row.cells[3]?.innerText.replace(/[^0-9]/g, ' ') || '0';
        const s2 = row.cells[4]?.innerText.replace(/[^0-9]/g, ' ') || '0';
        const s3 = row.cells[5]?.innerText.replace(/[^0-9]/g, ' ') || '0';
        const solved = row.cells[8]?.innerText.split('ta')[0].replace(/[^0-9]/g, '') || '0';
        const attempts = row.cells[8]?.innerText.split('ta')[1]?.replace(/[^0-9]/g, '') || '0';

        csvContent += `"${id}","${user}","${email}","${s1.trim()}","${s2.trim()}","${s3.trim()}","${solved}","${attempts}"\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "CodeStep_Oquvchilar_Monitoring_" + new Date().toISOString().slice(0, 10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
