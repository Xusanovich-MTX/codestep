<?php
if (!$currentUser): ?>
<section class="account-page">
    <div class="account-card" style="text-align: center; max-width: 480px; margin: 60px auto;">
        <i class="fas fa-history" style="font-size: 3rem; color: #10b981; margin-bottom: 16px;"></i>
        <h1>Yechimlar tarixi</h1>
        <p>Qoralamalaringiz, test natijalari va yechimlar tarixini ko‘rish uchun profilingizga kiring.</p>
        <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
            <a class="account-button" href="?page=login"><i class="fas fa-sign-in-alt"></i> Hisobga kirish</a>
            <a class="sol-btn" href="?page=register" style="padding: 10px 16px;">Ro‘yxatdan o‘tish</a>
        </div>
    </div>
</section>
<?php return; endif;

$historyPage = isset($_GET['history']) && is_string($_GET['history']) && ctype_digit($_GET['history']) ? min(100000, max(1, (int) $_GET['history'])) : 1;
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], ['all', 'accepted', 'failed', 'drafts'], true) ? $_GET['tab'] : 'all';

try {
    $stats = solutionHistoryStats($currentUser['id']);
    $savedDrafts = solutionSavedDrafts($currentUser['id']);
    
    // Server-side filter if tab is specified
    $dbFilter = match($activeTab) {
        'accepted' => 'accepted',
        'failed' => 'failed',
        default => null,
    };
    
    $perPage = 30;
    $savedRuns = solutionHistory($currentUser['id'], $perPage + 1, ($historyPage - 1) * $perPage, $dbFilter);
    $hasMore = count($savedRuns) > $perPage;
    $savedRuns = array_slice($savedRuns, 0, $perPage);
} catch (Throwable $error) {
    error_log('CodeStep history error: ' . $error->getMessage());
    echo '<div class="solutions-page"><div class="solutions-empty-state"><p class="empty-desc">Yechimlar tarixini yuklab bo‘lmadi. Keyinroq qayta urinib ko‘ring.</p></div></div>';
    return;
}

$totalRuns = $stats['total'];
$acceptedCount = $stats['accepted'];
$failedCount = $stats['wrong_answer'] + $stats['error'];
$structureCount = $stats['structure_passed'];
$draftsCount = count($savedDrafts);
$successRate = $totalRuns > 0 ? round(($acceptedCount / $totalRuns) * 100) : 0;

$verdictInfo = [
    'accepted' => ['label' => 'Qabul qilindi', 'icon' => 'fas fa-check-circle', 'class' => 'tag-accepted'],
    'wrong_answer' => ['label' => 'Qabul qilinmadi', 'icon' => 'fas fa-times-circle', 'class' => 'tag-wrong'],
    'error' => ['label' => 'Bajarish xatosi', 'icon' => 'fas fa-exclamation-triangle', 'class' => 'tag-error'],
    'structure_passed' => ['label' => 'Qisman tekshirildi', 'icon' => 'fas fa-info-circle', 'class' => 'tag-structure'],
];

$editorUrl = static fn(array $row): string => '?page=code-editor&topic=' . (int) $row['topic_id'] . '&problem=' . rawurlencode($row['problem_number']) . '&lang=' . $row['language'];
$displayTime = static fn(string $time): string => (new DateTimeImmutable($time))->setTimezone(new DateTimeZone('Asia/Tashkent'))->format('d.m.Y H:i');

$findTopicTitle = function(int $topicId) use ($programmingTopics): string {
    return $programmingTopics[$topicId]['title'] ?? ('Mavzu #' . $topicId);
};
?>
<section class="solutions-page">
    <!-- Header -->
    <div class="solutions-header-section">
        <div>
            <p class="solutions-subtitle"><i class="fas fa-user-circle"></i> @<?php echo topicText($currentUser['username']); ?> hisobidagi natijalar</p>
            <h1>Yechimlarim va Tekshirish Tarixi</h1>
        </div>
        <div style="display: flex; gap: 10px;">
            <a class="sol-btn" href="?page=home"><i class="fas fa-book"></i> Masalalar to‘plami</a>
            <a class="sol-btn sol-btn-primary" href="?page=code-editor"><i class="fas fa-code"></i> Muharrirni ochish</a>
        </div>
    </div>

    <!-- Statistics Overview Grid -->
    <div class="solutions-stats-grid">
        <div class="solutions-stat-card">
            <div class="stat-icon-wrapper stat-icon-accepted">
                <i class="fas fa-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Qabul qilingan</div>
                <div class="stat-value" style="color: #10b981;"><?php echo $acceptedCount; ?></div>
                <div class="stat-subtext"><?php echo $stats['unique_solved']; ?> ta noyob masala yechilgan</div>
            </div>
        </div>

        <div class="solutions-stat-card">
            <div class="stat-icon-wrapper stat-icon-failed">
                <i class="fas fa-times"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Qabul qilinmagan</div>
                <div class="stat-value" style="color: #ef4444;"><?php echo $failedCount; ?></div>
                <div class="stat-subtext">Xatolik bilan yakunlangan urinishlar</div>
            </div>
        </div>

        <div class="solutions-stat-card">
            <div class="stat-icon-wrapper stat-icon-drafts">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Qoralamalar</div>
                <div class="stat-value" style="color: #3b82f6;"><?php echo $draftsCount; ?></div>
                <div class="stat-subtext">Muharrirda saqlangan kodlar</div>
            </div>
        </div>

        <div class="solutions-stat-card">
            <div class="stat-icon-wrapper stat-icon-rate">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Samaradorlik</div>
                <div class="stat-value" style="color: #f59e0b;"><?php echo $successRate; ?>%</div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: <?php echo $successRate; ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation & Search Bar -->
    <div class="solutions-nav-bar">
        <div class="solutions-tabs" role="tablist">
            <button type="button" class="solutions-tab-btn <?php echo $activeTab === 'all' ? 'active' : ''; ?>" data-tab="all">
                <i class="fas fa-list-ul"></i> Barchasi <span class="tab-badge"><?php echo $totalRuns; ?></span>
            </button>
            <button type="button" class="solutions-tab-btn tab-accepted <?php echo $activeTab === 'accepted' ? 'active' : ''; ?>" data-tab="accepted">
                <i class="fas fa-check-circle"></i> Qabul qilinganlar <span class="tab-badge"><?php echo $acceptedCount; ?></span>
            </button>
            <button type="button" class="solutions-tab-btn tab-failed <?php echo $activeTab === 'failed' ? 'active' : ''; ?>" data-tab="failed">
                <i class="fas fa-times-circle"></i> Qabul qilinmaganlar <span class="tab-badge"><?php echo $failedCount; ?></span>
            </button>
            <button type="button" class="solutions-tab-btn <?php echo $activeTab === 'drafts' ? 'active' : ''; ?>" data-tab="drafts">
                <i class="fas fa-save"></i> Qoralamalar <span class="tab-badge"><?php echo $draftsCount; ?></span>
            </button>
        </div>

        <div class="solutions-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="solutionSearchInput" placeholder="Masala raqami bo‘yicha izlash (masalan: 1.1.21)…" />
        </div>
    </div>

    <!-- Tab Content: Drafts Section -->
    <div id="tabContentDrafts" class="tab-panel" style="<?php echo $activeTab === 'drafts' ? '' : 'display: none;'; ?>">
        <?php if (!$savedDrafts): ?>
            <div class="solutions-empty-state">
                <i class="fas fa-file-code empty-icon"></i>
                <div class="empty-title">Saqlangan qoralamalar hali yo‘q</div>
                <p class="empty-desc">Masalani ochib, kod yozing va muharrirdagi «Bazaga saqlash» tugmasini bosing.</p>
                <a class="sol-btn sol-btn-primary" href="?page=home"><i class="fas fa-arrow-right"></i> Masalaga o‘tish</a>
            </div>
        <?php else: ?>
            <div class="drafts-grid">
                <?php foreach ($savedDrafts as $draft): ?>
                    <article class="draft-card" data-problem="<?php echo topicText($draft['problem_number']); ?>">
                        <div>
                            <div class="draft-card-header">
                                <span class="lang-badge <?php echo $draft['language'] === 'python' ? 'lang-python' : 'lang-cpp'; ?>">
                                    <i class="<?php echo $draft['language'] === 'python' ? 'fab fa-python' : 'fas fa-code'; ?>"></i>
                                    <?php echo $draft['language'] === 'python' ? 'Python' : 'C++'; ?>
                                </span>
                                <span class="tab-badge">v<?php echo (int) $draft['revision']; ?></span>
                            </div>
                            <h3 class="draft-problem-title" style="margin-top: 10px;"><?php echo topicText($draft['problem_number']); ?>-masala</h3>
                            <p class="draft-topic-title"><?php echo topicText($findTopicTitle($draft['topic_id'])); ?></p>
                        </div>

                        <pre class="draft-preview-code"><?php echo topicText(mb_substr(trim($draft['code']), 0, 150)); ?></pre>

                        <div class="draft-card-footer">
                            <span class="draft-time"><i class="far fa-clock"></i> <?php echo $displayTime($draft['updated_at']); ?></span>
                            <a class="sol-btn sol-btn-primary" href="<?php echo topicText($editorUrl($draft) . '&saved=1'); ?>">
                                <i class="fas fa-edit"></i> Davom ettirish
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Content: History Runs Section (All, Accepted, Failed) -->
    <div id="tabContentHistory" class="tab-panel" style="<?php echo $activeTab === 'drafts' ? 'display: none;' : ''; ?>">
        <?php if (!$savedRuns): ?>
            <div class="solutions-empty-state">
                <i class="fas fa-clipboard-check empty-icon"></i>
                <div class="empty-title">Bu bo‘limda natijalar topilmadi</div>
                <p class="empty-desc">
                    <?php if ($activeTab === 'accepted'): ?>
                        Hali qabul qilingan yechimlar yo‘q. Masalalardan birini tanlab, to‘g‘ri kod yozib ko‘ring!
                    <?php elseif ($activeTab === 'failed'): ?>
                        Tabriklaymiz, qabul qilinmagan yechimlar mavjud emas!
                    <?php else: ?>
                        Hozircha tekshirilgan yechimlar tarixi yo‘q. Masalani ochib, «Bajarish va tekshirish»ni bosing.
                    <?php endif; ?>
                </p>
                <a class="sol-btn sol-btn-primary" href="?page=home"><i class="fas fa-arrow-right"></i> Masalalarga o‘tish</a>
            </div>
        <?php else: ?>
            <div class="solutions-list" id="solutionsListContainer">
                <?php foreach ($savedRuns as $run): 
                    $vKey = $run['verdict'] ?? 'error';
                    $vInfo = $verdictInfo[$vKey] ?? $verdictInfo['error'];
                    $isAccepted = $vKey === 'accepted';
                    $isFailed = in_array($vKey, ['wrong_answer', 'error'], true);
                ?>
                <article class="solution-item-card verdict-<?php echo topicText($vKey); ?>" 
                         data-verdict="<?php echo topicText($vKey); ?>"
                         data-category="<?php echo $isAccepted ? 'accepted' : ($isFailed ? 'failed' : 'other'); ?>"
                         data-problem="<?php echo topicText($run['problem_number']); ?>"
                         data-id="<?php echo (int) $run['id']; ?>">
                    
                    <div class="solution-item-header">
                        <div class="solution-problem-info">
                            <h3 class="solution-problem-title"><?php echo topicText($run['problem_number']); ?>-masala</h3>
                            <span class="solution-topic-badge">
                                <i class="fas fa-folder-open"></i> <?php echo topicText($findTopicTitle($run['topic_id'])); ?>
                            </span>
                        </div>

                        <div class="solution-header-badges">
                            <span class="lang-badge <?php echo $run['language'] === 'python' ? 'lang-python' : 'lang-cpp'; ?>">
                                <i class="<?php echo $run['language'] === 'python' ? 'fab fa-python' : 'fas fa-code'; ?>"></i>
                                <?php echo $run['language'] === 'python' ? 'Python' : 'C++'; ?>
                            </span>

                            <span class="verdict-tag <?php echo $vInfo['class']; ?>">
                                <i class="<?php echo $vInfo['icon']; ?>"></i> <?php echo $vInfo['label']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="solution-item-meta">
                        <div class="solution-meta-items">
                            <span class="solution-meta-item">
                                <i class="far fa-clock"></i> <?php echo $displayTime($run['created_at']); ?>
                            </span>
                            <?php if ($run['total_checks'] > 0): ?>
                            <span class="solution-meta-item">
                                <i class="fas fa-tasks"></i> Testlar: <strong><?php echo $run['checks_passed'] . ' / ' . $run['total_checks']; ?></strong>
                            </span>
                            <?php endif; ?>
                            <span class="solution-meta-item">
                                <i class="fas fa-file-code"></i> <?php echo mb_strlen($run['code']); ?> belgi
                            </span>
                        </div>

                        <div class="solution-actions">
                            <button type="button" class="sol-btn toggle-code-btn" data-target="drawer-<?php echo $run['id']; ?>">
                                <i class="fas fa-code"></i> Kodni ko‘rish
                            </button>
                            <button type="button" class="sol-btn copy-code-btn" data-code="<?php echo htmlspecialchars($run['code'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-copy"></i> Nusxa
                            </button>
                            <a class="sol-btn sol-btn-primary" href="<?php echo topicText($editorUrl($run) . '&run=' . $run['id']); ?>">
                                <i class="fas fa-external-link-alt"></i> Muharrirda ochish
                            </a>
                        </div>
                    </div>

                    <!-- Expandable Code Inspector Drawer -->
                    <div id="drawer-<?php echo $run['id']; ?>" class="solution-code-drawer">
                        <div class="drawer-section-title">
                            <span><i class="fas fa-code"></i> Yuborilgan dastur kodi</span>
                            <span class="copy-status-tip" id="copy-tip-<?php echo $run['id']; ?>">Nusxalandi! ✓</span>
                        </div>
                        <pre class="drawer-code-pre"><code><?php echo topicText($run['code']); ?></code></pre>

                        <?php if ($run['test_stdin'] !== ''): ?>
                        <div class="drawer-section-title">
                            <span><i class="fas fa-keyboard"></i> Test kiritish ma’lumoti (stdin)</span>
                        </div>
                        <pre class="drawer-output-pre"><code><?php echo topicText($run['test_stdin']); ?></code></pre>
                        <?php endif; ?>

                        <div class="drawer-section-title">
                            <span><i class="fas fa-terminal"></i> Dastur natijasi / Chiqish</span>
                        </div>
                        <pre class="drawer-output-pre"><code><?php echo topicText($run['output'] === '' ? 'Chiqish ma’lumoti yo‘q.' : $run['output']); ?></code></pre>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <nav class="solutions-pagination" aria-label="Tarix sahifalari">
                <?php if ($historyPage > 1): ?>
                    <a class="sol-btn" href="?page=solutions&tab=<?php echo $activeTab; ?>&history=<?php echo $historyPage - 1; ?>"><i class="fas fa-arrow-left"></i> Oldingi</a>
                <?php endif; ?>
                <span class="tab-badge" style="padding: 8px 16px; font-size: 0.875rem;"><?php echo $historyPage; ?>-sahifa</span>
                <?php if ($hasMore): ?>
                    <a class="sol-btn" href="?page=solutions&tab=<?php echo $activeTab; ?>&history=<?php echo $historyPage + 1; ?>">Keyingi <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<script>
(function() {
    'use strict';

    // 1. Tab switching
    const tabBtns = document.querySelectorAll('.solutions-tab-btn');
    const historyPanel = document.getElementById('tabContentHistory');
    const draftsPanel = document.getElementById('tabContentDrafts');
    const items = document.querySelectorAll('.solution-item-card');

    function applyFilter(tab) {
        tabBtns.forEach(b => b.classList.toggle('active', b.dataset.tab === tab));

        if (tab === 'drafts') {
            if (historyPanel) historyPanel.style.display = 'none';
            if (draftsPanel) draftsPanel.style.display = 'block';
            return;
        }

        if (draftsPanel) draftsPanel.style.display = 'none';
        if (historyPanel) historyPanel.style.display = 'block';

        items.forEach(card => {
            if (tab === 'all') {
                card.style.display = 'block';
            } else if (tab === 'accepted') {
                card.style.display = card.dataset.category === 'accepted' ? 'block' : 'none';
            } else if (tab === 'failed') {
                card.style.display = card.dataset.category === 'failed' ? 'block' : 'none';
            }
        });
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            applyFilter(tab);
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            url.searchParams.delete('history');
            history.replaceState(null, '', url);
        });
    });

    // 2. Search input filtering by problem number
    const searchInput = document.getElementById('solutionSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            const draftCards = document.querySelectorAll('.draft-card');

            items.forEach(card => {
                const prob = (card.dataset.problem || '').toLowerCase();
                const matches = !query || prob.includes(query);
                card.style.display = matches ? 'block' : 'none';
            });

            draftCards.forEach(card => {
                const prob = (card.dataset.problem || '').toLowerCase();
                const matches = !query || prob.includes(query);
                card.style.display = matches ? 'block' : 'none';
            });
        });
    }

    // 3. Toggle Code Inspector Drawer
    document.querySelectorAll('.toggle-code-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const drawer = document.getElementById(targetId);
            if (!drawer) return;
            const isOpen = drawer.classList.contains('open');
            drawer.classList.toggle('open', !isOpen);
            btn.innerHTML = isOpen 
                ? '<i class="fas fa-code"></i> Kodni ko‘rish' 
                : '<i class="fas fa-chevron-up"></i> Yopish';
        });
    });

    // 4. Copy Code to clipboard
    document.querySelectorAll('.copy-code-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const code = btn.dataset.code;
            if (!code) return;
            try {
                await navigator.clipboard.writeText(code);
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check" style="color:#10b981;"></i> Nusxalandi!';
                setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
            } catch (_) {
                alert('Kodni nusxalab bo‘lmadi.');
            }
        });
    });
})();
</script>
