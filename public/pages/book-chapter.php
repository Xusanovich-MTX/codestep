<?php if (!is_array($bookSection ?? null) || !is_array($bookChapter ?? null)): ?>
<section class="solutions-page book-page"><div class="book-empty"><p class="book-eyebrow">CODESTEP</p><h1>Bob topilmadi</h1><p>Bosh sahifadan bo‘limni, so‘ng kerakli bobni tanlang.</p><a class="account-button" href="?page=home">Bosh sahifaga qaytish</a></div></section>
<?php return; endif;
$bookChapterTopics = $bookChapter['topics'] ?? [];
$bookParentUrl = '?page=section&section=' . rawurlencode($bookSection['number']);
?>
<section class="solutions-page book-page" data-section="<?php echo topicText($bookSection['number']); ?>" aria-labelledby="book-chapter-title">
    <nav class="book-breadcrumb" aria-label="Sahifa joylashuvi">
        <a href="?page=home">Bosh sahifa</a><span aria-hidden="true">/</span><a href="<?php echo topicText($bookParentUrl); ?>"><?php echo topicText($bookSection['number']); ?> bo‘lim</a><span aria-hidden="true">/</span><span aria-current="page"><?php echo topicText($bookChapter['number']); ?>-bob</span>
    </nav>
    <a class="book-back" href="<?php echo topicText($bookParentUrl); ?>"><span aria-hidden="true">←</span> Boblarga qaytish</a>
    <header class="book-page-heading">
        <p class="book-eyebrow"><?php echo topicText($bookChapter['number']); ?>-BOB</p>
        <h1 id="book-chapter-title"><?php echo topicText($bookChapter['title']); ?></h1>
        <p><?php echo $bookChapterTopics !== [] ? count($bookChapterTopics) . ' mavzu. Masalalarni ko‘rish uchun mavzuni tanlang.' : (isset($bookChapter['id']) ? 'Shu bobning umumiy masalalar sahifasini oching.' : 'Bu bobga mavzular hali qo‘shilmagan.'); ?></p>
    </header>
    <ol class="book-topic-list" aria-label="Bob mavzulari">
        <?php if (isset($bookChapter['id'])): ?>
        <li>
            <a class="book-topic-card book-general-card" href="?page=topic-detail&amp;id=<?php echo (int) $bookChapter['id']; ?>">
                <span class="book-topic-mark" aria-hidden="true">{ }</span>
                <div class="book-topic-copy"><h2>Umumiy masalalar</h2><p><?php echo !empty($bookChapter['problems']) ? count($bookChapter['problems']) . ' ta masala' : 'Masalalar hali qo‘shilmagan'; ?></p></div>
                <span class="book-card-chevron" aria-hidden="true">→</span>
            </a>
        </li>
        <?php endif; ?>
        <?php foreach ($bookChapterTopics as $chapterTopic): ?>
        <li>
            <a class="book-topic-card" href="?page=topic-detail&amp;id=<?php echo (int) $chapterTopic['id']; ?>">
                <span class="book-topic-number"><?php echo topicText($chapterTopic['number']); ?></span>
                <div class="book-topic-copy"><h2><?php echo topicText($chapterTopic['title']); ?></h2><p><?php echo !empty($chapterTopic['problems']) ? count($chapterTopic['problems']) . ' ta masala' : 'Masalalar hali qo‘shilmagan'; ?></p></div>
                <span class="book-card-chevron" aria-hidden="true">→</span>
            </a>
        </li>
        <?php endforeach; ?>
    </ol>
</section>
