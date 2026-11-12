<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

require_once dirname(__DIR__) . '/includes/programming-topics.php';

$programmingCatalog = programmingTopicsCatalog();
$bookHomeSections = $programmingCatalog['sections'] ?? [];
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
            foreach ($searchChapter['problems'] ?? [] as $searchProblem) {
                $homeSearchItems[] = [
                    'kind' => 'Masala',
                    'title' => 'Masala',
                    'number' => $searchProblem['number'] ?? '',
                    'context' => $searchSection['number'] . ' bo‘lim · ' . ($searchChapter['number'] ?? '') . '-bob · ' . $searchChapter['title'],
                    'search' => implode(' ', [$searchProblem['number'] ?? '', $searchProblem['statement'] ?? '', $searchChapter['title']]),
                    'href' => '?page=code-editor&topic=' . (int) $searchChapter['id'] . '&problem=' . rawurlencode((string) ($searchProblem['number'] ?? '')),
                ];
            }
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
            foreach ($searchTopic['problems'] ?? [] as $searchProblem) {
                $homeSearchItems[] = [
                    'kind' => 'Masala',
                    'title' => 'Masala',
                    'number' => $searchProblem['number'] ?? '',
                    'context' => $searchSection['number'] . ' bo‘lim · ' . ($searchChapter['number'] ?? '') . '-bob · ' . $searchTopic['title'],
                    'search' => implode(' ', [$searchProblem['number'] ?? '', $searchProblem['statement'] ?? '', $searchTopic['title'], $searchChapter['title']]),
                    'href' => '?page=code-editor&topic=' . (int) $searchTopic['id'] . '&problem=' . rawurlencode((string) ($searchProblem['number'] ?? '')),
                ];
            }
        }
    }
}

echo json_encode($homeSearchItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
