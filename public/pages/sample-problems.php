<?php
$requestedTopic = $_GET['topic'] ?? '';
$examplesTopicId = is_string($requestedTopic) && ctype_digit($requestedTopic) ? (int) $requestedTopic : 0;
$examplesTopic = $programmingTopics[$examplesTopicId] ?? null;
if ($examplesTopic === null) {
    ?>
    <section class="programming-topic-detail">
        <a class="back-button" href="?page=home">Bo‘limlarga qaytish</a>
        <h1>Mavzu topilmadi</h1>
        <p>Namuna masalalarni ko‘rish uchun mavzuni qayta tanlang.</p>
    </section>
    <?php
    return;
}
$topicExamples = programmingTopicExamples($examplesTopicId);
$examples = $topicExamples['examples'];
$topicUrl = '?page=topic-detail&id=' . $examplesTopicId;
$returnProblem = null;
$requestedProblem = $_GET['problem'] ?? null;
foreach ($examplesTopic['problems'] ?? [] as $problem) {
    if (is_string($requestedProblem) && $problem['number'] === $requestedProblem) {
        $returnProblem = $problem;
        break;
    }
}
$returnLanguage = ($_GET['lang'] ?? '') === 'python' ? 'python' : 'cpp';
$returnUrl = $returnProblem
    ? '?page=code-editor&topic=' . $examplesTopicId . '&problem=' . rawurlencode($returnProblem['number']) . '&lang=' . $returnLanguage
    : $topicUrl;
$returnLabel = $returnProblem ? $returnProblem['number'] . '-masalani yechishga qaytish' : 'Mavzu masalalariga qaytish';
?>
<section class="programming-topic-detail sample-problems">
    <nav class="curriculum-breadcrumb" aria-label="Sahifa joylashuvi">
        <a href="?page=home">Bo‘limlar</a>
        <?php if (isset($examplesTopic['section_number'])): ?>
        <span aria-hidden="true">/</span>
        <a href="?page=section&amp;section=<?php echo topicText(rawurlencode($examplesTopic['section_number'])); ?>"><?php echo topicText($examplesTopic['section_number']); ?> bo‘lim. <?php echo topicText($examplesTopic['section_title']); ?></a>
        <?php endif; ?>
        <?php if (isset($examplesTopic['chapter_number'])): ?>
        <span aria-hidden="true">/</span>
        <a href="?page=chapter&amp;chapter=<?php echo topicText(rawurlencode($examplesTopic['chapter_number'])); ?>"><?php echo topicText($examplesTopic['chapter_number']); ?>-bob. <?php echo topicText($examplesTopic['chapter_title']); ?></a>
        <?php endif; ?>
        <?php if ($examplesTopic['kind'] !== 'chapter'): ?>
        <span aria-hidden="true">/</span>
        <a href="<?php echo topicText($topicUrl); ?>"><?php echo isset($examplesTopic['number']) ? topicText($examplesTopic['number']) . '. ' : ''; ?><?php echo topicText($examplesTopic['title']); ?></a>
        <?php endif; ?>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Namuna masalalar</span>
    </nav>
    <a class="back-button sample-return" href="<?php echo topicText($returnUrl); ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> <?php echo topicText($returnLabel); ?></a>
    <header class="curriculum-detail-heading">
        <?php if (isset($examplesTopic['number'])): ?>
        <p class="curriculum-eyebrow"><?php echo topicText($examplesTopic['number']); ?> · <?php echo $examplesTopic['kind'] === 'chapter' ? 'BOB' : 'MAVZU'; ?></p>
        <?php endif; ?>
        <h1>Namuna masalalar</h1>
        <p class="curriculum-summary"><?php echo topicText($examplesTopic['title']); ?></p>
    </header>
    <?php if ($examples): ?>
    <p class="sample-intro"><?php echo topicText($topicExamples['intro']); ?></p>
    <nav class="sample-index" aria-label="Namuna tanlash">
        <?php foreach ($examples as $example): ?>
        <a href="#sample-<?php echo (int) $example['number']; ?>"><?php echo (int) $example['number']; ?>. <?php echo topicText($example['title']); ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sample-list">
        <?php foreach ($examples as $example): ?>
        <article class="sample-card" id="sample-<?php echo (int) $example['number']; ?>" aria-labelledby="sample-title-<?php echo (int) $example['number']; ?>">
            <header class="sample-question">
                <p class="curriculum-eyebrow"><?php echo (int) $example['number']; ?>-NAMUNAVIY MASALA</p>
                <h2 id="sample-title-<?php echo (int) $example['number']; ?>"><?php echo topicText($example['title']); ?></h2>
                <h3>Masala sharti</h3>
                <p class="sample-statement"><?php echo topicText($example['statement']); ?></p>
            </header>
            <section class="sample-solution" aria-labelledby="sample-solution-<?php echo (int) $example['number']; ?>">
                <h3 id="sample-solution-<?php echo (int) $example['number']; ?>">Bosqichma-bosqich yechim</h3>
                <ol class="sample-steps">
                    <?php foreach ($example['steps'] as $step): ?>
                    <li>
                        <p><?php echo topicText($step['text']); ?></p>
                        <?php if (!empty($step['formula'])): ?>
                        <pre class="sample-formula" tabindex="0" aria-label="Hisoblash"><code><?php echo topicText($step['formula']); ?></code></pre>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php if (!empty($example['implementations'])): ?>
            <div class="sample-programs">
                <?php foreach ($example['implementations'] as $implementation): ?>
                <section class="sample-program" aria-labelledby="sample-code-<?php echo (int) $example['number']; ?>-<?php echo topicText($implementation['language']); ?>">
                    <h3 id="sample-code-<?php echo (int) $example['number']; ?>-<?php echo topicText($implementation['language']); ?>"><?php echo topicText($implementation['title']); ?></h3>
                    <p><?php echo topicText($implementation['explanation']); ?></p>
                    <pre class="sample-formula sample-source" tabindex="0" aria-label="<?php echo topicText($implementation['title']); ?> kodi"><code data-language="<?php echo topicText($implementation['language']); ?>"><?php echo topicText($implementation['code']); ?></code></pre>
                    <?php if (($implementation['stdin'] ?? '') !== ''): ?>
                    <h4>Namuna kirishi</h4>
                    <pre class="sample-formula" tabindex="0" aria-label="Namuna kirishi"><code><?php echo topicText($implementation['stdin']); ?></code></pre>
                    <?php endif; ?>
                    <h4><?php echo topicText($implementation['output_label'] ?? 'Dastur natijasi'); ?></h4>
                    <pre class="sample-formula sample-console" tabindex="0" aria-label="Dastur natijasi"><code><?php echo topicText($implementation['output']); ?></code></pre>
                </section>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <section class="sample-answer" aria-labelledby="sample-answer-<?php echo (int) $example['number']; ?>"><h3 id="sample-answer-<?php echo (int) $example['number']; ?>">Yakuniy javob</h3><code><?php echo topicText($example['answer']); ?></code></section>
            <?php if (!empty($example['note'])): ?>
            <p class="sample-note"><?php echo topicText($example['note']); ?></p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="sample-intro">Bu mavzuga hali namuna masalalar qo‘shilmagan.</p>
    <?php endif; ?>
    <nav class="curriculum-navigation" aria-label="Masalalarga qaytish">
        <a class="sample-return" href="<?php echo topicText($returnUrl); ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> <?php echo topicText($returnLabel); ?></a>
    </nav>
</section>
