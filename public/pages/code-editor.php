<?php
$editorProblem = null;
$editorTopic = null;
$editorTopicId = null;
if (isset($_GET['topic']) || isset($_GET['problem'])) {
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
    if ($editorProblem === null) {
        ?>
        <section class="editor-workspace">
            <a class="back-button" href="?page=home"><i class="fas fa-arrow-left" aria-hidden="true"></i> Bo‘limlarga qaytish</a>
            <div class="editor-card">
                <h1>Masala topilmadi</h1>
                <p>Asosiy sahifadan bo‘lim, bob va mavzuni ochib, masalani qayta tanlang.</p>
            </div>
        </section>
        <?php
        return;
    }
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
