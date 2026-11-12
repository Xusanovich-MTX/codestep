<?php
$curriculumTopic = $programmingTopics[$topicId];
$isIntroduction = $curriculumTopic['kind'] === 'introduction';
$isReference = $curriculumTopic['kind'] === 'reference';
$isChapter = $curriculumTopic['kind'] === 'chapter';
$previousTopicId = null;
$nextTopicId = null;
if ($curriculumTopic['kind'] === 'topic') {
    $curriculumIds = array_keys(array_filter($programmingTopics, static function (array $candidate) use ($curriculumTopic): bool {
        return $candidate['kind'] === 'topic'
            && ($candidate['section_number'] ?? null) === $curriculumTopic['section_number']
            && ($candidate['chapter_number'] ?? null) === $curriculumTopic['chapter_number'];
    }));
    $curriculumPosition = array_search($topicId, $curriculumIds, true);
    if ($curriculumPosition !== false) {
        $previousTopicId = $curriculumIds[$curriculumPosition - 1] ?? null;
        $nextTopicId = $curriculumIds[$curriculumPosition + 1] ?? null;
    }
}
$topicBackUrl = isset($curriculumTopic['chapter_number'])
    ? '?page=chapter&chapter=' . rawurlencode($curriculumTopic['chapter_number'])
    : '?page=home';
$topicBackLabel = isset($curriculumTopic['chapter_number']) ? 'Mavzularga qaytish' : 'Bo‘limlarga qaytish';
$contentTitle = $isIntroduction ? 'Kirish' : ($isReference ? 'Foydalanilgan adabiyotlar' : 'Masalalar');
$problems = $curriculumTopic['problems'] ?? [];
$topicSummary = $problems !== []
    ? count($problems) . ' ta masala. Masala shartini ko‘rib, yechishni boshlang.'
    : ($isChapter ? 'Bobga tegishli materiallar va umumiy masalalar.' : 'Mavzu materiallari va tushuntirishlari.');
$topicExamples = programmingTopicExamples($topicId);
?>
<section class="programming-topic-detail">
    <nav class="curriculum-breadcrumb" aria-label="Mavzu joylashuvi">
        <a href="?page=home">Bo‘limlar</a>
        <?php if (isset($curriculumTopic['section_number'])): ?>
        <span aria-hidden="true">/</span>
        <a href="?page=section&amp;section=<?php echo topicText(rawurlencode($curriculumTopic['section_number'])); ?>"><?php echo topicText($curriculumTopic['section_number']); ?> bo‘lim. <?php echo topicText($curriculumTopic['section_title']); ?></a>
        <?php endif; ?>
        <?php if (isset($curriculumTopic['chapter_number']) && !$isChapter): ?>
        <span aria-hidden="true">/</span>
        <a href="?page=chapter&amp;chapter=<?php echo topicText(rawurlencode($curriculumTopic['chapter_number'])); ?>"><?php echo topicText($curriculumTopic['chapter_number']); ?>-bob. <?php echo topicText($curriculumTopic['chapter_title']); ?></a>
        <?php endif; ?>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?php echo isset($curriculumTopic['number']) ? topicText($curriculumTopic['number']) . ($isChapter ? '-bob. ' : '. ') : ''; ?><?php echo topicText($curriculumTopic['title']); ?></span>
    </nav>
    <a class="back-button" href="<?php echo topicText($topicBackUrl); ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> <?php echo $topicBackLabel; ?></a>
    <header class="curriculum-detail-heading">
        <p class="curriculum-eyebrow"><?php echo $isIntroduction ? 'KIRISH' : ($isReference ? 'MANBALAR' : topicText($curriculumTopic['number']) . ($isChapter ? ' · BOB' : ' · MAVZU')); ?></p>
        <h1><?php echo topicText($curriculumTopic['title']); ?></h1>
        <p class="curriculum-summary"><?php echo topicText($topicSummary); ?></p>
    </header>
    <?php if ($topicExamples['examples']): ?>
    <a class="curriculum-examples-link" href="?page=sample-problems&amp;topic=<?php echo (int) $topicId; ?>"><i class="fas fa-book-open" aria-hidden="true"></i> Namuna masalalar <span><?php echo count($topicExamples['examples']); ?> ta yechim</span><i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    <?php endif; ?>
    <section class="curriculum-problems" aria-labelledby="problemsTitle">
        <h2 id="problemsTitle"><?php echo $contentTitle; ?><?php if ($problems): ?> <span class="curriculum-problems-count"><?php echo count($problems); ?> ta</span><?php endif; ?></h2>
        <?php if ($problems): ?>
        <div class="curriculum-problem-list">
            <?php foreach ($problems as $problem): ?>
            <?php $problemAnchor = 'problem-' . str_replace('.', '-', $problem['number']); ?>
            <article class="curriculum-problem" id="<?php echo topicText($problemAnchor); ?>" aria-labelledby="<?php echo topicText($problemAnchor); ?>-title">
                <a class="curriculum-problem-link" href="?page=code-editor&amp;topic=<?php echo (int) $topicId; ?>&amp;problem=<?php echo topicText(rawurlencode($problem['number'])); ?>" aria-labelledby="<?php echo topicText($problemAnchor); ?>-title <?php echo topicText($problemAnchor); ?>-action">
                    <h3 id="<?php echo topicText($problemAnchor); ?>-title"><?php echo topicText($problem['number']); ?>-masala</h3>
                    <div class="problem-statement-body"><?php echo formatProblemStatement($problem['statement']); ?></div>
                    <?php echo formatProblemVisuals($problem); ?>
                    <?php if (!empty($problem['note'])): ?>
                    <p class="problem-source-note"><strong>Izoh:</strong> <?php echo topicText($problem['note']); ?></p>
                    <?php endif; ?>
                    <span class="curriculum-problem-action" id="<?php echo topicText($problemAnchor); ?>-action"><i class="fas fa-code" aria-hidden="true"></i> Kod yozish <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php elseif ($isIntroduction && (!empty($curriculumTopic['content']) || !empty($curriculumTopic['publication']))): ?>
        <?php if (!empty($curriculumTopic['publication'])): ?>
        <?php $pub = $curriculumTopic['publication']; ?>
        <div class="book-front-matter-card">
            <div class="book-front-citation">
                <p><strong><?php echo topicText($pub['citation']); ?></strong></p>
            </div>
            <?php if (!empty($pub['annotation'])): ?>
            <div class="book-front-annotation">
                <p><?php echo topicText($pub['annotation']); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($pub['reviewers'])): ?>
            <div class="book-front-reviewers">
                <span class="reviewers-label">Taqrizchilar:</span>
                <div class="reviewers-list">
                    <?php foreach ($pub['reviewers'] as $rev): ?>
                    <p><?php echo topicText($rev); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($pub['recommendation'])): ?>
            <div class="book-front-recommendation">
                <p><em><?php echo topicText($pub['recommendation']); ?></em></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($pub['isbn'])): ?>
            <div class="book-front-isbn">
                <strong><?php echo topicText($pub['isbn']); ?></strong>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($curriculumTopic['content'])): ?>
        <div class="curriculum-introduction-content">
            <h3 class="introduction-text-title">Kirish so‘zi</h3>
            <?php foreach ($curriculumTopic['content'] as $paragraph): ?>
            <p><?php echo topicText($paragraph); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php elseif ($isReference && (!empty($curriculumTopic['books']) || !empty($curriculumTopic['electronic_resources']))): ?>
        <div class="curriculum-references-container">
            <?php if (!empty($curriculumTopic['books'])): ?>
            <div class="curriculum-reference-block">
                <h3 class="reference-subtitle"><i class="fas fa-book" aria-hidden="true"></i> Adabiyotlar:</h3>
                <ol class="reference-list">
                    <?php foreach ($curriculumTopic['books'] as $book): ?>
                    <li><?php echo topicText($book); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>

            <?php if (!empty($curriculumTopic['electronic_resources'])): ?>
            <div class="curriculum-reference-block">
                <h3 class="reference-subtitle"><i class="fas fa-globe" aria-hidden="true"></i> Elektron adabiyotlar (Web manbalar):</h3>
                <ol class="reference-list electronic-links">
                    <?php foreach ($curriculumTopic['electronic_resources'] as $url): ?>
                    <li>
                        <a href="<?php echo topicText($url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo topicText($url); ?> <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="curriculum-empty">
            <?php if ($isIntroduction): ?>
            <p><strong>Kirish matni keyin qo‘shiladi.</strong></p>
            <?php elseif ($isReference): ?>
            <p><strong>Adabiyotlar ro‘yxati keyin qo‘shiladi.</strong></p>
            <?php else: ?>
            <p><strong>Bu mavzuga hali masalalar qo‘shilmagan.</strong></p>
            <p>Masalalar keyin shu yerda joylashtiriladi.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php if ($topicExamples['examples']): ?>
    <section class="sample-list topic-inline-samples" aria-labelledby="topic-samples-title">
        <h2 id="topic-samples-title">Namuna masalalar</h2>
        <p class="sample-intro"><?php echo topicText($topicExamples['intro']); ?></p>
        <?php foreach ($topicExamples['examples'] as $example): ?>
        <article class="sample-card" aria-labelledby="topic-sample-<?php echo (int) $example['number']; ?>">
            <header class="sample-question">
                <p class="curriculum-eyebrow"><?php echo (int) $example['number']; ?>-NAMUNAVIY MASALA</p>
                <h3 id="topic-sample-<?php echo (int) $example['number']; ?>"><?php echo topicText($example['title']); ?></h3>
                <p class="sample-statement"><?php echo topicText($example['statement']); ?></p>
            </header>
            <section class="sample-solution">
                <h4>Bosqichma-bosqich yechim</h4>
                <ol class="sample-steps">
                    <?php foreach ($example['steps'] as $step): ?>
                    <li><p><?php echo topicText($step['text']); ?></p><?php if (!empty($step['formula'])): ?><pre class="sample-formula"><code><?php echo topicText($step['formula']); ?></code></pre><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php if (!empty($example['implementations'])): ?>
            <div class="sample-programs">
                <?php foreach ($example['implementations'] as $implementation): ?>
                <section class="sample-program">
                    <h4><?php echo topicText($implementation['title']); ?></h4>
                    <p><?php echo topicText($implementation['explanation']); ?></p>
                    <pre class="sample-formula sample-source"><code data-language="<?php echo topicText($implementation['language']); ?>"><?php echo topicText($implementation['code']); ?></code></pre>
                    <?php if (!empty($implementation['stdin'])): ?><h5>Namuna kirishi</h5><pre class="sample-formula"><code><?php echo topicText($implementation['stdin']); ?></code></pre><?php endif; ?>
                    <h5><?php echo topicText($implementation['output_label'] ?? 'Dastur natijasi'); ?></h5>
                    <pre class="sample-formula sample-console"><code><?php echo topicText($implementation['output']); ?></code></pre>
                </section>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <section class="sample-answer"><h4>Yakuniy javob</h4><code><?php echo topicText($example['answer']); ?></code></section>
        </article>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if ($previousTopicId !== null || $nextTopicId !== null): ?>
    <nav class="curriculum-navigation" aria-label="Bob mavzulari orasida o‘tish">
        <?php if ($previousTopicId !== null): ?>
        <a href="?page=topic-detail&amp;id=<?php echo $previousTopicId; ?>" rel="prev"><i class="fas fa-arrow-left" aria-hidden="true"></i> Oldingi mavzu</a>
        <?php endif; ?>
        <?php if ($nextTopicId !== null): ?>
        <a href="?page=topic-detail&amp;id=<?php echo $nextTopicId; ?>" rel="next">Keyingi mavzu <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>
