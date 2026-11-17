<?php
$editorProblem = null;
$editorTopic = null;
$editorTopicId = null;

if (isset($_GET['contest']) && isset($_GET['problem'])) {
    $contestId = (int)$_GET['contest'];
    $probOrder = strtoupper(trim((string)$_GET['problem']));
    $db = solutionDb();
    solutionEnsureFeatureSchema($db);
    
    $cStmt = $db->prepare("SELECT * FROM contests WHERE id = ?");
    $cStmt->execute([$contestId]);
    $contestRow = $cStmt->fetch();
    
    $cpStmt = $db->prepare("SELECT * FROM contest_problems WHERE contest_id = ? AND problem_order = ?");
    $cpStmt->execute([$contestId, $probOrder]);
    $cp = $cpStmt->fetch();
    
    if ($cp && $contestRow) {
        $editorProblem = [
            'number' => $cp['problem_order'],
            'title' => 'Masala ' . $cp['problem_order'] . ': ' . $cp['title'],
            'statement' => $cp['description'] . "\n\n**Kiruvchi ma'lumot:** " . ($cp['sample_input'] !== '' ? $cp['sample_input'] : 'Mavjud emas') . "\n**Kutilgan natija:** " . ($cp['sample_output'] !== '' ? $cp['sample_output'] : 'Mavjud emas'),
            'difficulty' => $cp['difficulty'],
            'points' => $cp['points'],
            'sample_input' => $cp['sample_input'],
            'sample_output' => $cp['sample_output']
        ];
        $editorTopic = [
            'id' => 1000 + $contestId,
            'title' => $contestRow['title'] . ' · Masala ' . $cp['problem_order'] . ' (' . $cp['points'] . ' ball)',
            'chapter_title' => 'Haftalik Olimpiada',
            'section_title' => 'Musobaqa'
        ];
        $editorTopicId = 1000 + $contestId;
        $contestRule = [
            'kind' => 'exact',
            'requirements' => [
                'Kiruvchi ma’lumotlarni standart oqimdan (cin yoki input) to‘g‘ri qabul qiling.',
                'Natijani probellar va qator tashlashlarga qat’iy rioya qilgan holda chiqaring.',
                'Namuna kirish: ' . ($cp['sample_input'] !== '' ? $cp['sample_input'] : 'Bo‘sh'),
                'Namuna kutilgan natija: ' . ($cp['sample_output'] !== '' ? $cp['sample_output'] : 'Bo‘sh'),
            ],
            'tests' => [
                ['stdin' => $cp['sample_input'], 'expected' => $cp['sample_output']]
            ],
            'input_hint' => $cp['sample_input']
        ];
    }
} elseif (isset($_GET['topic']) || isset($_GET['problem'])) {
    $requestedTopic = $_GET['topic'] ?? '';
    $requestedProblem = $_GET['problem'] ?? '';
    if (is_string($requestedTopic) && ctype_digit($requestedTopic) && is_string($requestedProblem)) {
        $candidateTopic = $programmingTopics[(int) $requestedTopic] ?? null;
        foreach ($candidateTopic['problems'] ?? [] as $candidateProblem) {
            if ($candidateProblem['number'] === $requestedProblem) {
                $editorProblem = $candidateProblem;
                $editorTopic = $candidateTopic;
                $editorTopicId = (int) $requestedTopic;
                break;
            }
        }
    }
}

if ((isset($_GET['contest']) || isset($_GET['topic']) || isset($_GET['problem'])) && $editorProblem === null) {
    ?>
    <section class="editor-workspace">
        <a class="back-button" href="?page=contests"><i class="fas fa-arrow-left" aria-hidden="true"></i> Musobaqalarga qaytish</a>
        <div class="editor-card">
            <h1>Masala topilmadi</h1>
            <p>Musobaqa yoki darslik sahifasidan masalani qayta tanlang.</p>
        </div>
    </section>
    <?php
    return;
}

require __DIR__ . '/editor-workspace.php';
?>
<script>
(function(){
  try {
    var code = sessionStorage.getItem('codestep.pending-code');
    var lang = sessionStorage.getItem('codestep.pending-lang');
    if (code) {
      var ta = document.getElementById('codeInput');
      if (ta) ta.value = code;
      if (lang) {
        var url = new URL(window.location.href);
        url.searchParams.set('lang', lang);
        history.replaceState(null,'',url);
      }
      sessionStorage.removeItem('codestep.pending-code');
      sessionStorage.removeItem('codestep.pending-lang');
    }
  } catch(e){}
})();
</script>
