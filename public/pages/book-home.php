<?php
$bookHomeSections = $programmingCatalog['sections'] ?? [];
$bookNotice = is_string($_GET['notice'] ?? null) ? match ($_GET['notice']) {
    'section-not-found' => 'Bo‘lim topilmadi. Quyidagi kitob bo‘limlaridan birini tanlang.',
    'chapter-not-found' => 'Bob topilmadi. Bo‘limni ochib, kerakli bobni qayta tanlang.',
    default => '',
} : '';
$bookSectionDescriptions = [
    'I' => 'Sonlar, shartlar, takrorlanishlar va funksiyalar bilan dasturlash poydevorini yarating.',
    'II' => 'Sinflar va obyektlar, vorislik, virtual metodlar hamda shablonlar bilan ishlang.',
    'III' => 'Oynalar, interfeys komponentalari, grafiklar va ma’lumotlar bazasi bilan tanishing.',
];
$homeSearchItems = [];
foreach ($bookHomeSections as $searchSection) {
    foreach ($searchSection['chapters'] ?? [] as $searchChapter) {
        if (isset($searchChapter['id'])) {
            $homeSearchItems[] = [
                'kind' => 'Bob',
                'title' => $searchChapter['title'],
                'number' => $searchChapter['number'] ?? '',
                'context' => $searchSection['number'] . ' bo‘lim',
                'search' => implode(' ', [$searchChapter['number'] ?? '', $searchChapter['title']]),
                'href' => '?page=topic-detail&id=' . (int) $searchChapter['id'],
            ];
        }
        foreach ($searchChapter['topics'] ?? [] as $searchTopic) {
            $homeSearchItems[] = [
                'kind' => 'Mavzu',
                'title' => $searchTopic['title'],
                'number' => $searchTopic['number'] ?? '',
                'context' => $searchSection['number'] . ' bo‘lim · ' . ($searchChapter['number'] ?? '') . '-bob',
                'search' => implode(' ', [$searchTopic['number'] ?? '', $searchTopic['title'], $searchChapter['title']]),
                'href' => '?page=topic-detail&id=' . (int) $searchTopic['id'],
            ];
        }
    }
}
?>
<section class="solutions-page book-page book-home" aria-labelledby="book-home-title">
    <?php if ($bookNotice !== ''): ?><p class="book-notice" role="status"><?php echo topicText($bookNotice); ?></p><?php endif; ?>
    <header class="home-hero">
        <div class="home-hero-copy">
            <p class="book-eyebrow">CODESTEP · DASTURLASHNI O‘RGANAMIZ</p>
            <h1 id="book-home-title">Bo‘limni tanlang<br>va <span>boshlang</span></h1>
            <p class="home-hero-description">Bo‘limdan bobga, bobdan mavzuga o‘ting. So‘ng masalalarni kod yozib yeching.</p>
            <div class="home-hero-actions">
                <a class="home-secondary-action" href="?page=terms"><i class="far fa-compass" aria-hidden="true"></i> Foydalanish shartlari</a>
            </div>
        </div>
        <div class="home-hero-art" role="img" aria-label="Dasturlash muharriri va o‘rganish yo‘li tasviri">
            <span class="home-orbit home-orbit-one" aria-hidden="true"></span>
            <span class="home-orbit home-orbit-two" aria-hidden="true"></span>
            <span class="home-float home-float-code" aria-hidden="true"><i class="fas fa-code"></i></span>
            <span class="home-float home-float-cap" aria-hidden="true"><i class="fas fa-graduation-cap"></i></span>
            <div class="home-code-window" aria-hidden="true">
                <div class="home-code-topbar"><span><i></i><i></i><i></i></span><b>birinchi_qadam.cpp</b><span class="home-code-live">LIVE</span></div>
                <div class="home-code-body">
                    <div class="home-code-rail"><span></span><span></span><span></span><span></span><span></span></div>
                    <div class="home-code-lines">
                        <span><b>#include</b> &lt;iostream&gt;</span>
                        <span></span>
                        <span><em>int</em> main() {</span>
                        <span>&nbsp;&nbsp;cout &lt;&lt; <strong>"Salom, CodeStep!"</strong>;</span>
                        <span>&nbsp;&nbsp;<em>return</em> 0;</span>
                        <span>}</span>
                    </div>
                    <div class="home-code-guide"><i class="fas fa-code" aria-hidden="true"></i><span>Bo‘lim</span><b>›</b><span>Bob</span><b>›</b><span>Mavzu</span><b>›</b><span>Masala</span></div>
                </div>
                <div class="home-code-result"><i class="fas fa-check-circle" aria-hidden="true"></i><span>Natija:</span><strong>Salom, CodeStep!</strong></div>
            </div>
            <div class="home-journey" aria-hidden="true"><span><i class="fas fa-book-open"></i> Bo‘lim</span><b>→</b><span><i class="fas fa-list-ol"></i> Bob</span><b>→</b><span><i class="fas fa-play"></i> Mavzu</span><b>→</b><span><i class="fas fa-code"></i> Masala</span></div>
        </div>
    </header>

    <!-- Haftalik Kiber-Olimpiada E'lon Banneri -->
    <div class="home-contest-highlight" style="margin: 0 0 32px; padding: 18px 24px; border-radius: 16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(2, 132, 199, 0.12)); border: 1px solid rgba(16, 185, 129, 0.35); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
        <div style="display: flex; align-items: center; gap: 14px;">
            <span style="font-size: 2.2rem; filter: drop-shadow(0 2px 8px rgba(16,185,129,0.4));">🏆</span>
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: inherit;">CodeStep Weekly Cup #1</h3>
                    <span style="background: #10b981; color: #fff; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 6px;">5 TA MASALA</span>
                    <span style="background: rgba(2, 132, 199, 0.2); color: #0284c7; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 6px;">750 BALL</span>
                </div>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: var(--day-muted, #94a3b8); line-height: 1.4;">
                    Yakshanba 20:00 · 5 ta saralangan masala (osondan qiyingacha) · Jonli peshtaxta
                </p>
            </div>
        </div>
        <div>
            <a href="?page=contests" class="account-button" style="margin: 0; padding: 10px 22px; font-weight: 700; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                Ishtirok etish →
            </a>
        </div>
    </div>

    <div class="book-section-grid" id="homeCourses" aria-label="Dasturlash bo‘limlari">
        <?php foreach ($bookHomeSections as $homeSection): ?>
        <?php
        $homeChapters = $homeSection['chapters'] ?? [];
        $homeTopicCount = array_sum(array_map(static fn(array $chapter): int => count($chapter['topics'] ?? []), $homeChapters));
        $homeSearchTerms = [$homeSection['number'], $homeSection['title']];
        foreach ($homeChapters as $homeChapter) {
            $homeSearchTerms[] = $homeChapter['number'] ?? '';
            $homeSearchTerms[] = $homeChapter['title'] ?? '';
            foreach ($homeChapter['topics'] ?? [] as $homeTopic) {
                $homeSearchTerms[] = $homeTopic['number'] ?? '';
                $homeSearchTerms[] = $homeTopic['title'] ?? '';
            }
        }
        ?>
        <a class="book-section-card" data-section="<?php echo topicText($homeSection['number']); ?>" data-search="<?php echo topicText(implode(' ', $homeSearchTerms)); ?>" href="?page=section&amp;section=<?php echo rawurlencode($homeSection['number']); ?>">
            <div class="book-section-art" aria-hidden="true">
                <span class="book-section-icon">
                    <?php if ($homeSection['number'] === 'I'): ?>
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="6" y="8" width="36" height="32" rx="5"/><path d="M6 17h36M12 12.5h1M17 12.5h1M17 24l-5 5 5 5M31 24l5 5-5 5M27 23l-6 12"/></svg>
                    <?php elseif ($homeSection['number'] === 'II'): ?>
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="17" y="5" width="14" height="12" rx="3"/><rect x="4" y="31" width="14" height="12" rx="3"/><rect x="30" y="31" width="14" height="12" rx="3"/><path d="M24 17v7M11 31v-7h26v7M21 11h6M8 37h6M34 37h6"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="5" y="7" width="38" height="34" rx="5"/><path d="M5 16h38M12 11.5h1M17 11.5h1"/><rect x="11" y="22" width="10" height="13" rx="2"/><path d="M27 23h10M27 29h10M27 35h6"/></svg>
                    <?php endif; ?>
                </span>
                <span class="book-section-roman"><?php echo topicText($homeSection['number']); ?></span>
            </div>
            <p class="book-section-label"><?php echo topicText($homeSection['number']); ?> BO‘LIM</p>
            <h2><?php echo topicText($homeSection['title']); ?></h2>
            <p class="book-section-description"><?php echo topicText($bookSectionDescriptions[$homeSection['number']] ?? 'Boblar va mavzularni ketma-ket o‘rganing.'); ?></p>
            <div class="book-section-footer">
                <span class="book-section-count"><?php echo count($homeChapters); ?> bob <span aria-hidden="true">·</span> <?php echo $homeTopicCount; ?> mavzu</span>
                <span class="book-open-arrow" aria-label="Bo‘limni ochish">↗</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <script type="application/json" id="homeSearchCatalog"><?php echo json_encode($homeSearchItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>

    <nav class="book-support-links" aria-label="Qo‘shimcha materiallar">
        <?php if (isset($programmingCatalog['introduction']['id'])): ?>
        <a href="?page=topic-detail&amp;id=<?php echo (int) $programmingCatalog['introduction']['id']; ?>"><?php echo topicText($programmingCatalog['introduction']['title']); ?> <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
        <?php foreach ($programmingCatalog['references'] ?? [] as $bookReference): ?>
        <a href="?page=topic-detail&amp;id=<?php echo (int) $bookReference['id']; ?>"><?php echo topicText($bookReference['title']); ?> <span aria-hidden="true">↗</span></a>
        <?php endforeach; ?>
    </nav>
</section>
