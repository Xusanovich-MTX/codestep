<?php if (!is_array($bookSection ?? null)): ?>
<section class="solutions-page book-page"><div class="book-empty"><p class="book-eyebrow">CODESTEP</p><h1>Bo‘lim topilmadi</h1><p>Bosh sahifadan kerakli bo‘limni qayta tanlang.</p><a class="account-button" href="?page=home">Bosh sahifaga qaytish</a></div></section>
<?php return; endif;
$bookSectionChapters = $bookSection['chapters'] ?? [];
?>
<section class="solutions-page book-page" data-section="<?php echo topicText($bookSection['number']); ?>" aria-labelledby="book-section-title">
    <nav class="book-breadcrumb" aria-label="Sahifa joylashuvi">
        <a href="?page=home">Bosh sahifa</a><span aria-hidden="true">/</span><span aria-current="page"><?php echo topicText($bookSection['number']); ?> bo‘lim</span>
    </nav>
    <a class="book-back" href="?page=home"><span aria-hidden="true">←</span> Bo‘limlarga qaytish</a>
    <header class="book-page-heading">
        <p class="book-eyebrow"><?php echo topicText($bookSection['number']); ?> BO‘LIM</p>
        <h1 id="book-section-title"><?php echo topicText($bookSection['title']); ?></h1>
        <p><?php echo count($bookSectionChapters); ?> bob. Mavzularni ko‘rish uchun bobni tanlang.</p>
    </header>
    <ol class="book-chapter-grid" aria-label="Bo‘lim boblari">
        <?php foreach ($bookSectionChapters as $sectionChapter): ?>
        <li>
            <a class="book-chapter-card" href="?page=chapter&amp;chapter=<?php echo rawurlencode($sectionChapter['number']); ?>">
                <span class="book-chapter-number"><?php echo topicText($sectionChapter['number']); ?><span>BOB</span></span>
                <div class="book-chapter-copy">
                    <h2><?php echo topicText($sectionChapter['title']); ?></h2>
                    <p><?php echo count($sectionChapter['topics'] ?? []) > 0 ? count($sectionChapter['topics']) . ' mavzu' : (isset($sectionChapter['id']) ? 'Umumiy masalalar to‘plami' : 'Mavzular hali qo‘shilmagan'); ?></p>
                </div>
                <span class="book-card-chevron" aria-hidden="true">→</span>
            </a>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php if ($bookSectionChapters === []): ?><p class="book-empty">Bu bo‘limga boblar hali qo‘shilmagan.</p><?php endif; ?>
</section>
