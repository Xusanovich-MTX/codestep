<?php
declare(strict_types=1);

// Prevent browser caching so live updates and contest changes reflect instantly
if (!headers_sent()) {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

$db = solutionDb();
solutionEnsureFeatureSchema($db);

// 1. Get active or upcoming contest
$contestStmt = $db->query("
    SELECT id, title, description, start_time, end_time, duration_minutes, status, created_at
    FROM contests
    ORDER BY id DESC
    LIMIT 1
");
$contest = $contestStmt->fetch();

if (!$contest) {
    echo '<div style="max-width:800px;margin:60px auto;text-align:center;padding:24px;"><h2>Hozircha faol musobaqalar mavjud emas.</h2></div>';
    return;
}

$contestId = (int)$contest['id'];

// Handle user registration action
$isRegistered = false;
$regMessage = '';

if ($currentUser) {
    // Check if registered
    $checkReg = $db->prepare("SELECT id FROM contest_registrations WHERE contest_id = ? AND user_id = ?");
    $checkReg->execute([$contestId, (int)$currentUser['id']]);
    $isRegistered = (bool)$checkReg->fetchColumn();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_contest') {
        if (!$isRegistered) {
            $regStmt = $db->prepare("INSERT OR IGNORE INTO contest_registrations (contest_id, user_id) VALUES (?, ?)");
            $regStmt->execute([$contestId, (int)$currentUser['id']]);
            $isRegistered = true;
            $regMessage = 'Siz muvaffaqiyatli ro‘yxatdan o‘tdingiz! Musobaqa boshlanganda sizga xabar beriladi.';
        }
    }
}

// Total registered count
$regCountStmt = $db->prepare("SELECT COUNT(*) FROM contest_registrations WHERE contest_id = ?");
$regCountStmt->execute([$contestId]);
$totalRegistered = (int)$regCountStmt->fetchColumn();

// 2. Get 5 problems (A, B, C, D, E)
$probStmt = $db->prepare("
    SELECT id, problem_order, title, difficulty, points, topic_id, problem_number, description, sample_input, sample_output
    FROM contest_problems
    WHERE contest_id = ?
    ORDER BY problem_order ASC
");
$probStmt->execute([$contestId]);
$problems = $probStmt->fetchAll();

// 3. Get leaderboard submissions
$leaderboardStmt = $db->prepare("
    SELECT 
        u.id as user_id,
        u.username,
        p.display_name,
        COALESCE(SUM(CASE WHEN s.verdict = 'accepted' THEN s.score ELSE 0 END), 0) as total_score,
        COALESCE(MAX(s.penalty_seconds), 0) as max_penalty,
        COUNT(CASE WHEN s.verdict = 'accepted' THEN 1 END) as accepted_count,
        MAX(CASE WHEN s.problem_order = 'A' AND s.verdict = 'accepted' THEN s.score ELSE NULL END) as score_a,
        MAX(CASE WHEN s.problem_order = 'B' AND s.verdict = 'accepted' THEN s.score ELSE NULL END) as score_b,
        MAX(CASE WHEN s.problem_order = 'C' AND s.verdict = 'accepted' THEN s.score ELSE NULL END) as score_c,
        MAX(CASE WHEN s.problem_order = 'D' AND s.verdict = 'accepted' THEN s.score ELSE NULL END) as score_d,
        MAX(CASE WHEN s.problem_order = 'E' AND s.verdict = 'accepted' THEN s.score ELSE NULL END) as score_e
    FROM contest_registrations r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    LEFT JOIN contest_submissions s ON s.contest_id = r.contest_id AND s.user_id = u.id
    WHERE r.contest_id = ?
    GROUP BY u.id
    ORDER BY total_score DESC, max_penalty ASC, u.id ASC
    LIMIT 50
");
$leaderboardStmt->execute([$contestId]);
$leaderboard = $leaderboardStmt->fetchAll();

// Dates in Tashkent timezone
$startIso = $contest['start_time'];
$endIso = $contest['end_time'];
$nowIso = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tashkent')))->format('c');

$isStarted = strtotime($nowIso) >= strtotime($startIso);
$isFinished = strtotime($nowIso) >= strtotime($endIso);
?>

<style id="contestsPageStyles">
.contest-wrap {
    max-width: 1200px;
    margin: 24px auto 60px;
    padding: 0 16px;
    font-family: inherit;
    --c-card-bg: #182331;
    --c-card-border: #344457;
    --c-text-main: #f8fafc;
    --c-text-muted: #94a3b8;
    --c-text-subtle: #64748b;
    --c-hero-gradient: linear-gradient(135deg, rgba(6, 214, 165, 0.12), rgba(2, 132, 199, 0.12));
    --c-hero-border: rgba(16, 185, 129, 0.3);
    --c-chip-bg: rgba(255, 255, 255, 0.05);
    --c-chip-border: rgba(255, 255, 255, 0.1);
    --c-timer-box-bg: rgba(15, 23, 42, 0.85);
    --c-timer-border: rgba(56, 189, 248, 0.3);
    --c-diff-easy-bg: rgba(16, 185, 129, 0.15);
    --c-diff-easy-color: #10b981;
    --c-diff-med-bg: rgba(245, 158, 11, 0.15);
    --c-diff-med-color: #f59e0b;
    --c-diff-hard-bg: rgba(239, 68, 68, 0.15);
    --c-diff-hard-color: #f87171;
    --c-diff-exp-bg: rgba(168, 85, 247, 0.15);
    --c-diff-exp-color: #c084fc;
}

body.light-mode .contest-wrap,
.light-mode .contest-wrap {
    --c-card-bg: #ffffff;
    --c-card-border: #e2e8f0;
    --c-text-main: #0f172a;
    --c-text-muted: #475569;
    --c-text-subtle: #64748b;
    --c-hero-gradient: linear-gradient(135deg, #ecfdf5, #f0fdf4 60%, #e0f2fe);
    --c-hero-border: #a7f3d0;
    --c-chip-bg: #ffffff;
    --c-chip-border: #cbd5e1;
    --c-timer-box-bg: #ffffff;
    --c-timer-border: #bae6fd;
    --c-diff-easy-bg: #d1fae5;
    --c-diff-easy-color: #047857;
    --c-diff-med-bg: #fef3c7;
    --c-diff-med-color: #b45309;
    --c-diff-hard-color: #b91c1c;
    --c-diff-hard-bg: #fee2e2;
    --c-diff-exp-bg: #f3e8ff;
    --c-diff-exp-color: #7e22ce;
}

.contest-card {
    background: var(--c-card-bg);
    border: 1px solid var(--c-card-border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}

.contest-hero {
    background: var(--c-hero-gradient);
    border: 1px solid var(--c-hero-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 28px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.contest-title {
    font-size: 2.2rem;
    font-weight: 800;
    margin: 8px 0 12px;
    color: var(--c-text-main);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.contest-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.contest-tag.upcoming {
    background: rgba(56, 189, 248, 0.2);
    color: #0284c7;
    border: 1px solid rgba(56, 189, 248, 0.4);
}

.contest-tag.live {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.5);
    animation: livePulse 1.5s infinite;
}

@keyframes livePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.03); }
}

/* Countdown Grid */
.contest-countdown-wrap {
    display: flex;
    gap: 12px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.timer-box {
    background: var(--c-timer-box-bg);
    border: 1px solid var(--c-timer-border);
    border-radius: 12px;
    padding: 12px 18px;
    min-width: 75px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.timer-num {
    font-size: 2rem;
    font-weight: 800;
    color: #0284c7;
    line-height: 1;
}

.timer-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--c-text-muted);
    text-transform: uppercase;
    margin-top: 4px;
    letter-spacing: 1px;
}

/* Problem Cards */
.problem-item-card {
    background: var(--c-card-bg);
    border: 1px solid var(--c-card-border);
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 14px;
    transition: all 0.2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.problem-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07);
}

.problem-order-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981, #0284c7);
    color: #ffffff;
    font-size: 1.25rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.problem-meta-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 4px;
}

.chip-diff {
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.74rem;
    font-weight: 700;
}

.chip-points {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    font-weight: 700;
    font-size: 0.76rem;
    padding: 3px 8px;
    border-radius: 6px;
}

.contest-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    background: linear-gradient(115deg, #10b981, #059669);
    color: #ffffff !important;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    transition: all 0.2s ease;
}

.contest-btn-primary:hover {
    filter: brightness(1.06);
    transform: translateY(-2px);
}

.contest-btn-registered {
    background: #0284c7;
    box-shadow: 0 4px 15px rgba(2, 132, 199, 0.3);
}

.contest-rules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.rule-box {
    background: var(--c-chip-bg);
    border: 1px solid var(--c-chip-border);
    border-radius: 12px;
    padding: 16px;
}

.rule-box h4 {
    margin: 0 0 6px;
    color: var(--c-text-main);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}

.rule-box p {
    margin: 0;
    font-size: 0.82rem;
    color: var(--c-text-muted);
    line-height: 1.5;
}
</style>

<div class="contest-wrap">
    <!-- Header Breadcrumbs / Back link -->
    <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
        <a href="?page=home" style="color: var(--c-text-muted); text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 6px;">
            ← Bosh sahifaga qaytish
        </a>
        <div style="font-size: 0.82rem; color: var(--c-text-muted);">
            Haftalik Kiber-Olimpiada Markazi
        </div>
    </div>

    <!-- Contests Hero Section -->
    <div class="contest-hero">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
            <div>
                <?php if ($isFinished): ?>
                    <span class="contest-tag" style="background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.4);">Yakunlangan</span>
                <?php elseif ($isStarted): ?>
                    <span class="contest-tag live">🔴 JONLI EFIR (KETMOQDA)</span>
                <?php else: ?>
                    <span class="contest-tag upcoming">⏳ YAQINDA BOSHLANADI</span>
                <?php endif; ?>

                <h1 class="contest-title">
                    🏆 <?php echo htmlspecialchars($contest['title']); ?>
                </h1>
                <p style="margin: 0; color: var(--c-text-muted); max-width: 650px; line-height: 1.6; font-size: 0.95rem;">
                    <?php echo htmlspecialchars($contest['description']); ?>
                </p>
            </div>

            <!-- Registration / Action Block -->
            <div style="text-align: right;">
                <?php if (!$currentUser): ?>
                    <a href="?page=login&next=?page=contests" class="contest-btn-primary">
                        🔑 Kirish va Qatnashish
                    </a>
                <?php elseif ($isRegistered): ?>
                    <div class="contest-btn-primary contest-btn-registered" style="cursor: default;">
                        ✔ Ishtirok uchun ro‘yxatdan o‘tildi
                    </div>
                    <div style="font-size: 0.78rem; color: var(--c-text-muted); margin-top: 6px;">
                        Musobaqa boshlanishiga tayyor turing!
                    </div>
                <?php else: ?>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="register_contest">
                        <button type="submit" class="contest-btn-primary">
                            📝 Ishtirok etish uchun ro‘yxatdan o‘tish
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($regMessage)): ?>
            <div style="margin-top: 14px; padding: 10px 14px; background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 10px; color: #10b981; font-weight: 600; font-size: 0.875rem;">
                ✔ <?php echo htmlspecialchars($regMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Countdown Timer -->
        <div style="margin-top: 24px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 18px;">
            <div style="font-size: 0.82rem; font-weight: 700; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 1px;">
                <?php echo $isStarted && !$isFinished ? 'Musobaqa tugashiga qolgan vaqt:' : 'Musobaqa boshlanishiga qoldi:'; ?>
            </div>

            <div class="contest-countdown-wrap" id="contestCountdown">
                <div class="timer-box">
                    <div class="timer-num" id="tDays">00</div>
                    <div class="timer-label">Kun</div>
                </div>
                <div class="timer-box">
                    <div class="timer-num" id="tHours">00</div>
                    <div class="timer-label">Soat</div>
                </div>
                <div class="timer-box">
                    <div class="timer-num" id="tMinutes">00</div>
                    <div class="timer-label">Daqiqa</div>
                </div>
                <div class="timer-box">
                    <div class="timer-num" id="tSeconds">00</div>
                    <div class="timer-label">Soniya</div>
                </div>
            </div>
        </div>

        <!-- Quick info metrics -->
        <div style="display: flex; gap: 18px; flex-wrap: wrap; margin-top: 10px; font-size: 0.85rem; color: var(--c-text-muted);">
            <div>⏱️ Davomiyligi: <strong style="color: var(--c-text-main);"><?php echo (int)$contest['duration_minutes']; ?> daqiqa</strong></div>
            <div>🧩 Masalalar soni: <strong style="color: var(--c-text-main);">5 ta (Osondan Murakkabgacha)</strong></div>
            <div>🎯 Jami ball: <strong style="color: #10b981;">750 ball</strong></div>
            <div>👥 Ro‘yxatdan o‘tganlar: <strong style="color: #0284c7;"><?php echo $totalRegistered; ?> nafar</strong></div>
        </div>
    </div>

    <!-- 5 TA MASALA RO'YXATI (A dan E gacha: OSONDAN QIYINGACHA) -->
    <div class="contest-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="margin: 0; font-size: 1.4rem; color: var(--c-text-main); display: flex; align-items: center; gap: 8px;">
                    🧩 5 ta Masala (Osondan Qiyingacha)
                </h2>
                <div style="font-size: 0.85rem; color: var(--c-text-muted); margin-top: 3px;">
                    Har bir masalani o‘z darajasiga mos ball evaziga yeching
                </div>
            </div>
            <div style="font-size: 0.85rem; font-weight: 700; color: #10b981;">
                Jami: 750 ball
            </div>
        </div>

        <div class="problems-list-wrap">
            <?php foreach ($problems as $p): 
                $diffClass = match($p['difficulty']) {
                    'Juda oson', 'Oson' => 'chip-diff-easy',
                    'O‘rta' => 'chip-diff-med',
                    'Murakkab' => 'chip-diff-hard',
                    default => 'chip-diff-exp'
                };
                $diffStyle = match($p['difficulty']) {
                    'Juda oson', 'Oson' => 'background: var(--c-diff-easy-bg); color: var(--c-diff-easy-color);',
                    'O‘rta' => 'background: var(--c-diff-med-bg); color: var(--c-diff-med-color);',
                    'Murakkab' => 'background: var(--c-diff-hard-bg); color: var(--c-diff-hard-color);',
                    default => 'background: var(--c-diff-exp-bg); color: var(--c-diff-exp-color);'
                };
            ?>
                <div class="problem-item-card">
                    <div style="display: flex; align-items: center; gap: 14px; min-width: 0; flex: 1;">
                        <div class="problem-order-badge">
                            <?php echo htmlspecialchars($p['problem_order']); ?>
                        </div>
                        <div style="min-width: 0;">
                            <h3 style="margin: 0; font-size: 1.05rem; color: var(--c-text-main);">
                                Masala <?php echo htmlspecialchars($p['problem_order']); ?>: <?php echo htmlspecialchars($p['title']); ?>
                            </h3>
                            <div class="problem-meta-chips">
                                <span class="chip-diff" style="<?php echo $diffStyle; ?>">
                                    <?php echo htmlspecialchars($p['difficulty']); ?>
                                </span>
                                <span class="chip-points">
                                    ⭐ <?php echo (int)$p['points']; ?> ball
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <a href="?page=code-editor&contest=<?php echo $contestId; ?>&problem=<?php echo htmlspecialchars($p['problem_order']); ?>" class="contest-btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
                            💻 Yechish
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- JONLI PESHTAXTA (LEADERBOARD) -->
    <div class="contest-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="margin: 0; font-size: 1.4rem; color: var(--c-text-main); display: flex; align-items: center; gap: 8px;">
                    🏆 Jonli Peshtaxta (Leaderboard)
                </h2>
                <div style="font-size: 0.85rem; color: var(--c-text-muted); margin-top: 3px;">
                    Ishtirokchilarning to‘plagan ballari va yechilgan masalalari
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--c-text-muted);">
                Yangilanish: <span style="color: #10b981; font-weight: 700;">● Jonli</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--c-card-border); color: var(--c-text-muted);">
                        <th style="padding: 10px 12px; width: 60px;">O‘rin</th>
                        <th style="padding: 10px 12px;">Ishtirokchi</th>
                        <th style="padding: 10px 12px; text-align: center;" title="50 ball">A (50)</th>
                        <th style="padding: 10px 12px; text-align: center;" title="100 ball">B (100)</th>
                        <th style="padding: 10px 12px; text-align: center;" title="150 ball">C (150)</th>
                        <th style="padding: 10px 12px; text-align: center;" title="200 ball">D (200)</th>
                        <th style="padding: 10px 12px; text-align: center;" title="250 ball">E (250)</th>
                        <th style="padding: 10px 12px; text-align: center;">Jami Ball</th>
                        <th style="padding: 10px 12px; text-align: center;">Yechilgan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaderboard)): ?>
                        <tr>
                            <td colspan="9" style="padding: 28px; text-align: center; color: var(--c-text-subtle);">
                                Hozircha ishtirokchilar ro‘yxati bo‘sh. Birinchi bo‘lib ro‘yxatdan o‘ting!
                            </td>
                        </tr>
                    <?php else: foreach ($leaderboard as $idx => $row): 
                        $rank = $idx + 1;
                        $medal = match($rank) {
                            1 => '🥇',
                            2 => '🥈',
                            3 => '🥉',
                            default => '#' . $rank
                        };
                    ?>
                        <tr style="border-bottom: 1px solid var(--c-card-border);">
                            <td style="padding: 12px 10px; font-weight: 800; font-size: 1rem; color: var(--c-text-main);">
                                <?php echo $medal; ?>
                            </td>
                            <td style="padding: 12px 10px;">
                                <strong style="color: var(--c-text-main);">@<?php echo htmlspecialchars($row['username']); ?></strong>
                                <?php if (!empty($row['display_name']) && $row['display_name'] !== $row['username']): ?>
                                    <div style="font-size: 0.75rem; color: var(--c-text-muted);"><?php echo htmlspecialchars($row['display_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: center;">
                                <?php echo $row['score_a'] ? '<span style="color:#10b981;font-weight:700;">✔ +' . $row['score_a'] . '</span>' : '<span style="color:var(--c-text-subtle);">—</span>'; ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: center;">
                                <?php echo $row['score_b'] ? '<span style="color:#10b981;font-weight:700;">✔ +' . $row['score_b'] . '</span>' : '<span style="color:var(--c-text-subtle);">—</span>'; ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: center;">
                                <?php echo $row['score_c'] ? '<span style="color:#10b981;font-weight:700;">✔ +' . $row['score_c'] . '</span>' : '<span style="color:var(--c-text-subtle);">—</span>'; ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: center;">
                                <?php echo $row['score_d'] ? '<span style="color:#10b981;font-weight:700;">✔ +' . $row['score_d'] . '</span>' : '<span style="color:var(--c-text-subtle);">—</span>'; ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: center;">
                                <?php echo $row['score_e'] ? '<span style="color:#10b981;font-weight:700;">✔ +' . $row['score_e'] . '</span>' : '<span style="color:var(--c-text-subtle);">—</span>'; ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: center; font-weight: 800; color: #10b981; font-size: 1rem;">
                                <?php echo (int)$row['total_score']; ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: center; color: var(--c-text-muted);">
                                <?php echo (int)$row['accepted_count']; ?> / 5
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- QOIDALAR VA BAHOLASH SHARTLARI -->
    <div class="contest-card">
        <h3 style="margin: 0 0 16px; font-size: 1.2rem; color: var(--c-text-main);">
            📜 Olimpiada Qoidalari va Shartlari
        </h3>

        <div class="contest-rules-grid">
            <div class="rule-box">
                <h4>🎯 5 ta Masala va Ballar</h4>
                <p>Oson masaladan murakkabgacha: A (50), B (100), C (150), D (200), E (250). Jami maksimal natija: 750 ball.</p>
            </div>
            <div class="rule-box">
                <h4>⚡ Dasturlash Tillari</h4>
                <p>Yechimlarni Python 3 va C++ dasturlash tillarida topshirish mumkin. Barcha testlardan o‘tgan yechim to‘liq qabul qilinadi.</p>
            </div>
            <div class="rule-box">
                <h4>⏱️ Vaqt va Reyting</h4>
                <p>Bir xil ball to‘plagan ishtirokchilar orasida masalalarni tezroq va kamroq xato bilan yechgan dasturchi yuqori o‘rinni egallaydi.</p>
            </div>
            <div class="rule-box">
                <h4>🥇 Mukofot va Sertifikat</h4>
                <p>Kuchli uchlik (1, 2 va 3-o‘rin) egalariga shaxsiy profilida oltin, kumush va bronza virtual kuboklari hamda CodeStep rasmiy diplomi beriladi.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Countdown Timer logic
(function() {
    const targetDate = new Date("<?php echo $startIso; ?>").getTime();

    function updateTimer() {
        const now = new Date().getTime();
        const diff = targetDate - now;

        if (diff <= 0) {
            document.getElementById('tDays').innerText = '00';
            document.getElementById('tHours').innerText = '00';
            document.getElementById('tMinutes').innerText = '00';
            document.getElementById('tSeconds').innerText = '00';
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        document.getElementById('tDays').innerText = String(days).padStart(2, '0');
        document.getElementById('tHours').innerText = String(hours).padStart(2, '0');
        document.getElementById('tMinutes').innerText = String(minutes).padStart(2, '0');
        document.getElementById('tSeconds').innerText = String(seconds).padStart(2, '0');
    }

    updateTimer();
    setInterval(updateTimer, 1000);
})();
</script>
