<?php
$sectionCount = count($programmingCatalog['sections']);
$chapterCount = array_sum(array_map(static function (array $section): int {
    return count($section['chapters']);
}, $programmingCatalog['sections']));
$problemCount = array_sum(array_map(static function (array $topic): int {
    return count($topic['problems'] ?? []);
}, $programmingTopics));
?>
<section class="programming-catalog" aria-label="Mavzular mundarijasi">
    <p class="curriculum-summary"><?php echo $sectionCount; ?> bo‘lim · <?php echo $chapterCount; ?> bob · <?php echo $problemCount; ?> ta masala</p>
    <nav class="curriculum-sections-nav" aria-label="Bo‘limga o‘tish">
        <?php foreach ($programmingCatalog['sections'] as $section): ?>
        <a href="#section-<?php echo topicText($section['number']); ?>">
            <span><?php echo topicText($section['number']); ?> bo‘lim</span>
            <?php echo topicText($section['title']); ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php if (isset($programmingCatalog['introduction'])): ?>
    <a class="curriculum-introduction" href="?page=topic-detail&amp;id=<?php echo (int) $programmingCatalog['introduction']['id']; ?>">
        <span><?php echo topicText($programmingCatalog['introduction']['title']); ?></span>
    </a>
    <?php endif; ?>
    <?php foreach ($programmingCatalog['sections'] as $section): ?>
    <?php
    $sectionTopicCount = array_sum(array_map(static function (array $chapter): int {
        return count($chapter['topics']) + (isset($chapter['id']) ? 1 : 0);
    }, $section['chapters']));
    ?>
    <section class="curriculum-section" id="section-<?php echo topicText($section['number']); ?>" aria-labelledby="curriculum-title-<?php echo topicText($section['number']); ?>">
    <header class="curriculum-heading">
        <p class="curriculum-eyebrow"><?php echo topicText($section['number']); ?> BO‘LIM</p>
        <h2 id="curriculum-title-<?php echo topicText($section['number']); ?>"><?php echo topicText($section['title']); ?></h2>
        <p class="curriculum-summary"><?php echo count($section['chapters']); ?> bob · <?php echo $sectionTopicCount; ?> mavzu</p>
    </header>
    <div class="curriculum-chapters">
        <?php foreach ($section['chapters'] as $chapter): ?>
        <section class="curriculum-chapter" id="chapter-<?php echo topicText(str_replace('.', '-', $chapter['number'])); ?>">
            <header>
                <span class="chapter-number"><?php echo topicText($chapter['number']); ?>-bob</span>
                <h3><?php echo topicText($chapter['title']); ?></h3>
            </header>
            <?php if (isset($chapter['id'])): ?>
            <a class="curriculum-topic curriculum-chapter-link" href="?page=topic-detail&amp;id=<?php echo (int) $chapter['id']; ?>">
                <span class="curriculum-topic-name">Masalalar sahifasi<?php if (!empty($chapter['problems'])): ?><span class="curriculum-problem-count"><?php echo count($chapter['problems']); ?> ta masala</span><?php endif; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($chapter['topics']): ?>
            <ol class="curriculum-topic-list">
                <?php foreach ($chapter['topics'] as $curriculumTopic): ?>
                <li>
                    <a class="curriculum-topic" href="?page=topic-detail&amp;id=<?php echo (int) $curriculumTopic['id']; ?>">
                        <span class="curriculum-topic-number"><?php echo topicText($curriculumTopic['number']); ?></span>
                        <span class="curriculum-topic-name"><?php echo topicText($curriculumTopic['title']); ?><?php if (!empty($curriculumTopic['problems'])): ?><span class="curriculum-problem-count"><?php echo count($curriculumTopic['problems']); ?> ta masala</span><?php endif; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
    </section>
    <?php endforeach; ?>
    <?php foreach ($programmingCatalog['references'] ?? [] as $reference): ?>
    <a class="curriculum-introduction curriculum-reference" href="?page=topic-detail&amp;id=<?php echo (int) $reference['id']; ?>">
        <span><?php echo topicText($reference['title']); ?></span>
    </a>
    <?php endforeach; ?>
</section>
