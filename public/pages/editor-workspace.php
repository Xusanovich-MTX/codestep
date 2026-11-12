<?php
$language = isset($_GET['lang']) && $_GET['lang'] === 'python' ? 'python' : 'cpp';
$editorToken = isset($csrfToken) ? $csrfToken : ($_SESSION['csrf_token'] ?? '');
$editorProblem = $editorProblem ?? null;
require_once dirname(__DIR__) . '/includes/problem-rules.php';
require_once dirname(__DIR__) . '/includes/code-execution.php';
$editorExecutionProvider = codeExecutionProvider();
$editorRule = $editorProblem ? problemRule($editorTopicId, $editorProblem['number']) : null;
$editorExamples = $editorProblem ? programmingTopicExamples($editorTopicId) : ['examples' => []];
$editorExamplesUrl = $editorProblem ? '?page=sample-problems&topic=' . $editorTopicId . '&problem=' . rawurlencode($editorProblem['number']) . '&lang=' . $language : '';
$editorBackUrl = $editorProblem
    ? '?page=topic-detail&id=' . $editorTopicId . '#problem-' . str_replace('.', '-', $editorProblem['number'])
    : '?page=home';
$editorAccount = codeStepUser();
$editorStorage = ['owner' => $editorAccount['id'] ?? null, 'drafts' => new stdClass(), 'run' => null, 'error' => ''];
if ($editorAccount && $editorProblem) {
    try {
        $editorStorage['drafts'] = (object) solutionDrafts($editorAccount['id'], $editorTopicId, $editorProblem['number']);
        if (isset($_GET['run'])) {
            $runId = is_string($_GET['run']) && ctype_digit($_GET['run']) ? (int) $_GET['run'] : 0;
            $storedRun = $runId > 0 ? solutionRun($editorAccount['id'], $runId) : null;
            if ($storedRun && $storedRun['topic_id'] === $editorTopicId && $storedRun['problem_number'] === $editorProblem['number']) {
                $editorStorage['run'] = $storedRun;
                $language = $storedRun['language'];
            } else $editorStorage['error'] = 'Saqlangan urinish topilmadi. Yechimlarim sahifasidan qayta oching.';
        }
    } catch (Throwable $error) {
        error_log('CodeStep editor drafts: ' . $error->getMessage());
        $editorStorage['error'] = 'Bazadagi kodni yuklab bo‘lmadi. Sahifani yangilab qayta urinib ko‘ring.';
    }
}
$editorLoginUrl = '?page=login&next=' . rawurlencode('?page=code-editor&topic=' . $editorTopicId . '&problem=' . ($editorProblem['number'] ?? '') . '&lang=' . $language);
?>
<script type="application/json" id="editorStorage"><?php echo json_encode($editorStorage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>
<section class="editor-workspace show-indent" id="editorWorkspace"
    data-language="<?php echo $language; ?>"
    <?php if ($editorProblem): ?>data-problem-key="<?php echo topicText($editorTopicId . ':' . $editorProblem['number']); ?>"<?php endif; ?>
    data-problem-tests="<?php echo count($editorRule['tests'] ?? []); ?>"
    data-test-input="<?php echo topicText($editorRule['input_hint'] ?? ''); ?>"
    data-csrf-token="<?php echo htmlspecialchars($editorToken, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="back-button" href="<?php echo htmlspecialchars($editorBackUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> <?php echo $editorProblem ? 'Masalalarga qaytish' : 'Orqaga'; ?></a>
    <div class="editor-heading">
        <div>
            <p class="editor-eyebrow">YOZING · TEKSHIRING · O‘RGANING</p>
            <h1><?php echo $editorProblem ? topicText($editorProblem['number']) . '-masala' : 'Kod muharriri'; ?></h1>
            <p><?php echo $editorProblem ? topicText($editorTopic['title']) : 'C++ va Python qoidalari endi kod yozayotganingizda yoningizda.'; ?></p>
        </div>
        <span class="editor-badge">4 ta bo‘sh joy</span>
    </div>
    <?php if ($editorProblem): ?>
    <div class="editor-cloud-actions">
        <?php if ($editorAccount): ?>
        <a href="?page=solutions">Yechimlarim</a>
        <button type="button" id="loadSavedCode" class="editor-button">Bazadagi kodni ochish</button>
        <?php else: ?>
        <a id="signInToSave" href="<?php echo topicText($editorLoginUrl); ?>">Yechimlarni bazaga saqlash uchun kiring</a>
        <?php endif; ?>
        <span id="cloudStatus" class="editor-cloud-status" role="status" aria-live="polite"><?php echo $editorAccount ? '“Bazaga saqlash” qoralamani saqlaydi. Tekshirilgan urinishlar tarixga avtomatik yoziladi.' : 'Kod hozircha shu brauzerda saqlanadi.'; ?></span>
    </div>
    <?php endif; ?>
    <?php if ($editorProblem): ?>
    <section class="editor-card editor-problem" aria-labelledby="editorProblemTitle">
        <h2 id="editorProblemTitle">Masala sharti</h2>
        <p id="editorProblemStatement"><?php echo topicText($editorProblem['statement']); ?></p>
        <?php echo formatProblemVisuals($editorProblem); ?>
        <?php if (!empty($editorProblem['note'])): ?>
        <p class="problem-source-note"><strong>Izoh:</strong> <?php echo topicText($editorProblem['note']); ?></p>
        <?php endif; ?>
        <details class="editor-requirements" open>
            <summary>Qabul qilish talablari</summary>
            <?php if ($editorRule): ?>
            <p>Kod shu masalani yechishi kerak. Natija quyidagi talablarga mos kelmasa, yechim qabul qilinmaydi.</p>
            <ul><?php foreach ($editorRule['requirements'] as $requirement): ?><li><?php echo topicText($requirement); ?></li><?php endforeach; ?></ul>
            <?php else: ?>
            <p>Bu masala uchun avtomatik qabul qilish talablari hali belgilanmagan.</p>
            <?php endif; ?>
        </details>
        <?php if ($editorExamples['examples']): ?>
        <div class="editor-examples-help">
            <a id="sampleProblemsLink" class="editor-button" href="<?php echo topicText($editorExamplesUrl); ?>"><i class="fas fa-book-open" aria-hidden="true"></i> Namuna masalalar</a>
            <span>Yechimlarni bosqichma-bosqich o‘rganing.</span>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <div class="editor-toolbar">
        <div class="editor-tabs" role="group" aria-label="Dasturlash tili">
            <button type="button" class="editor-language" data-lang="cpp" aria-pressed="<?php echo $language === 'cpp' ? 'true' : 'false'; ?>">C++</button>
            <button type="button" class="editor-language" data-lang="python" aria-pressed="<?php echo $language === 'python' ? 'true' : 'false'; ?>">Python</button>
        </div>
        <div class="editor-actions">
            <button type="button" id="loadExample" class="editor-button"><i class="fas fa-file-code" aria-hidden="true"></i> Kod namunasi</button>
            <button type="button" id="saveCode" class="editor-button" title="Ctrl+S"><i class="fas fa-save" aria-hidden="true"></i> <?php echo $editorAccount && $editorProblem ? 'Bazaga saqlash' : 'Saqlash'; ?></button>
            <button type="button" id="downloadCode" class="editor-button"><i class="fas fa-download" aria-hidden="true"></i> Yuklab olish</button>
            <button type="button" id="clearEditor" class="editor-button">Tozalash</button>
        </div>
    </div>
    <div id="editorAssist" aria-label="Kod yozishda yordam"></div>
    <div id="editorExtras" aria-label="Muharrir asboblari"></div>
    <div class="editor-layout">
        <div class="editor-main">
            <div class="editor-filebar">
                <label for="codeInput" id="editorFilename">main.<?php echo $language === 'python' ? 'py' : 'cpp'; ?></label>
                <label id="cppStyleControl">Qavs uslubi <select id="cppStyle"><option value="kr">K&amp;R</option><option value="allman">Allman</option></select></label>
                <span id="editorLimits">4 bo‘sh joy</span>
            </div>
            <div class="code-editor-panel">
                <textarea id="codeInput" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off" aria-label="Dastur kodi" aria-describedby="editorShortcuts"></textarea>
            </div>
            <div class="editor-statusbar">
                <span id="cursorPosition">Qator 1, ustun 1</span>
                <span id="saveStatus" role="status">Shu brauzerda saqlanadi</span>
            </div>
            <div class="editor-tools">
                <button type="button" id="checkCode" class="editor-button editor-button-primary"><i class="fas fa-check" aria-hidden="true"></i> <?php echo $editorProblem ? 'Kod tahlili' : 'Tekshirish'; ?></button>
                <button type="button" id="formatCode" class="editor-button"><i class="fas fa-align-left" aria-hidden="true"></i> Formatlash</button>
                <button type="button" id="undoEdit" class="editor-button" title="Oxirgi o‘zgarishni bekor qilish">Bekor qilish</button>
                <label class="editor-live-check"><input type="checkbox" id="liveCheck" checked> Yozishda tekshirish</label>
                <label class="editor-live-check"><input type="checkbox" id="showIndent" checked> Chekinishlarni ko‘rsatish</label>
            </div>
            <div class="editor-color-key" aria-label="Kod ranglari">
                <span class="editor-color-keyword">Kalit so‘z</span>
                <span class="editor-color-string">Matn</span>
                <span class="editor-color-number">Son</span>
                <span class="editor-color-comment">Izoh</span>
                <span class="editor-color-function">Funksiya</span>
            </div>
            <p class="editor-help" id="pythonIndentHelp"<?php echo $language !== 'python' ? ' hidden' : ''; ?>>Python: yuqori darajadagi qatorni chapdan boshlang. <code>if</code>, <code>for</code>, <code>def</code> kabi bloklar ichida 4 bo‘sh joy ishlating. Operator atrofidagi bo‘sh joy va bo‘sh qatorlar odatda xato emas. Nuqtalar chekinishdagi bo‘sh joylarni ko‘rsatadi; kodga qo‘shilmaydi.</p>
            <p class="editor-help" id="cppNamespaceHelp"<?php echo $language !== 'cpp' ? ' hidden' : ''; ?>>C++: kutubxonalar ostidagi <code>using namespace std;</code> orqali <code>cout</code>, <code>cin</code>, <code>string</code> va <code>vector</code> nomlarini <code>std::</code>siz yozing. Kerakli kutubxonani ham ulang: <code>&lt;iostream&gt;</code>, <code>&lt;string&gt;</code> yoki <code>&lt;vector&gt;</code>. <code>std::cout</code> kabi yozuvlar ham ishlaydi.</p>
            <p class="editor-help" id="editorShortcuts">Tab: 4 bo‘sh joy · Shift+Tab: chekinishni kamaytirish · Ctrl+Space: kod takliflari · Ctrl+F: qidirish · Ctrl+H: almashtirish · Ctrl+G: qatorga o‘tish · Ctrl+Shift+F: formatlash · Ctrl+Enter: bajarish · Esc: yordam oynasini yopish yoki muharrirdan chiqish</p>
            <p class="editor-notice" id="editorNotice" role="status" aria-live="polite"></p>
            <section class="editor-card" aria-labelledby="diagnosticsTitle">
                <div class="editor-card-heading"><h2 id="diagnosticsTitle">Kod tekshiruvi</h2><span id="diagnosticsCount" class="editor-badge">0</span></div>
                <p class="editor-help" id="analysisStatus" role="status">Tekshiruv tayyorlanmoqda…</p>
                <ol class="editor-diagnostics" id="diagnosticsList" aria-label="Topilgan muammolar"></ol>
                <p class="editor-help">Uslub tavsiyalari dasturni bajarishga to‘sqinlik qilmaydi. Mazmun, arxitektura va testlarni qo‘llanma asosida o‘zingiz ham ko‘rib chiqing.</p>
            </section>
        </div>
        <aside class="editor-sidebar">
            <section class="editor-card" aria-labelledby="outputTitle">
                <h2 id="outputTitle"><i class="fas fa-terminal" aria-hidden="true"></i> Bajarish</h2>
                <div class="editor-stdin-card">
                    <label class="editor-field-label" for="stdinInput">
                        <i class="fas fa-keyboard" aria-hidden="true"></i> Kirish ma’lumotlari (input / stdin)
                    </label>
                    <textarea id="stdinInput" rows="3" spellcheck="false" aria-describedby="stdinHelp" placeholder="input() yoki cin orqali o‘qiladigan qiymat(lar)ni kiriting (masalan: 5)"></textarea>
                    <p class="editor-help" id="stdinHelp">Dasturingiz klaviaturadan (<code>input()</code> yoki <code>cin &gt;&gt;</code>) o‘qiydigan qiymat(lar)ni shu yerga kiriting.</p>
                </div>
                <?php if ($editorExecutionProvider === 'wandbox'): ?>
                <p class="editor-help" id="executionPrivacyNotice">Kod va kirish ma’lumotlari Wandbox xizmatiga yuboriladi. <a href="?page=privacy#privacy-access">Maxfiylik siyosati</a></p>
                <?php elseif ($editorExecutionProvider === 'private'): ?>
                <p class="editor-help" id="executionPrivacyNotice">Kod va kirish ma’lumotlari administrator ulagan bajarish xizmatiga yuboriladi. <a href="?page=privacy#privacy-access">Maxfiylik siyosati</a></p>
                <?php endif; ?>
                <button type="button" id="runCode" class="editor-button editor-button-primary editor-run"<?php echo in_array($editorExecutionProvider, ['wandbox', 'private'], true) ? ' aria-describedby="executionPrivacyNotice"' : ''; ?>><i class="fas fa-play" aria-hidden="true"></i> <?php echo $editorProblem ? 'Bajarish va tekshirish' : 'Kodni bajarish'; ?></button>
                <?php if ($editorProblem): ?>
                <div id="problemVerdict" class="editor-verdict" data-state="idle" role="status" aria-live="polite">
                    <h3 id="verdictTitle">Masala hali tekshirilmagan</h3>
                    <p id="verdictMessage">Kodni yozib, “Bajarish va tekshirish” tugmasini bosing.</p>
                    <p id="verdictChecks" hidden></p>
                    <div id="verdictInput" hidden>Tekshirilgan kirish: <code id="verdictInputValue"></code></div>
                </div>
                <?php endif; ?>
                <p class="editor-help" id="outputStatus" role="status" hidden></p>
                <?php if ($editorProblem): ?><p class="editor-help">Oxirgi test natijasi</p><?php endif; ?>
                <pre id="output" tabindex="0" aria-label="Dastur natijasi" aria-live="polite">Natija shu yerda ko‘rsatiladi.</pre>
                <pre id="executionDiagnostics" class="editor-help" aria-label="Kompilyator va dastur xabarlari" hidden></pre>
            </section>
            <section class="editor-card editor-guide" aria-labelledby="rulesTitle">
                <div class="editor-card-heading"><h2 id="rulesTitle">Kod yozish qoidalari</h2><span id="rulesCount" class="editor-badge"></span></div>
                <p class="editor-help">Avtomatik tekshiruvlar va qo‘lda ko‘rib chiqiladigan tavsiyalar.</p>
                <label class="editor-field-label" for="ruleSearch">Qoidani qidirish</label>
                <input type="search" id="ruleSearch" placeholder="Masalan: nomlash, import, RAII">
                <div id="rulesList"></div>
            </section>
        </aside>
    </div>
    <noscript><p>Kod muharriri va tekshiruvlardan foydalanish uchun JavaScript’ni yoqing.</p></noscript>
</section>
