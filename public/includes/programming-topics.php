<?php
/** Curriculum transcribed from the supplied table of contents. */
function programmingTopicsCatalog(): array
{
    $path = dirname(__DIR__) . '/data/programming-topics.json';
    $catalog = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($catalog)) {
        return ['sections' => [], 'references' => []];
    }
    // Accept the initial single-section catalog while its data is expanded.
    if (!isset($catalog['sections'])) {
        $catalog['sections'] = [array_merge($catalog['section'], ['chapters' => $catalog['chapters']])];
    }
    return $catalog;
}

function programmingTopicsIndex(array $catalog): array
{
    $topics = [];
    if (isset($catalog['introduction']['id'])) {
        $introduction = $catalog['introduction'];
        $introduction['kind'] = 'introduction';
        $topics[(int) $introduction['id']] = $introduction;
    }
    foreach ($catalog['sections'] as $section) {
        foreach ($section['chapters'] as $chapter) {
            $location = [
                'section_number' => $section['number'],
                'section_title' => $section['title'],
                'chapter_number' => $chapter['number'],
                'chapter_title' => $chapter['title'],
            ];
            // General-problem chapters have their own page, without an invented subtopic number.
            if (isset($chapter['id'])) {
                $topics[(int) $chapter['id']] = array_merge($chapter, $location, ['kind' => 'chapter']);
            }
            foreach ($chapter['topics'] as $topic) {
                $topics[(int) $topic['id']] = array_merge($topic, $location, ['kind' => 'topic']);
            }
        }
    }
    foreach ($catalog['references'] ?? [] as $reference) {
        $reference['kind'] = 'reference';
        $topics[(int) $reference['id']] = $reference;
    }
    return $topics;
}

function programmingTopicExamples(int $topicId): array
{
    $file = $topicId === 15 ? 'programming-examples-output.json' : 'programming-examples.json';
    $path = dirname(__DIR__) . '/data/' . $file;
    $catalog = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($catalog[$topicId] ?? null) ? $catalog[$topicId] : ['intro' => '', 'examples' => []];
}

function topicText(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function formatProblemVisuals(array $problem): string
{
    $html = '';
    if (!empty($problem['piecewise'])) {
        $formulas = isset($problem['piecewise']['cases']) ? [$problem['piecewise']] : $problem['piecewise'];
        foreach ($formulas as $formula) {
            if (empty($formula['cases'])) continue;
            $html .= '<div class="problem-formula"><span class="problem-formula-variable">' . topicText($formula['variable']) . ' =</span>';
            $html .= '<span class="problem-formula-brace" aria-hidden="true">{</span><div class="problem-formula-cases">';
            foreach ($formula['cases'] as $case) {
                $html .= '<div class="problem-formula-case"><span class="problem-formula-expression">' . topicText($case['expression']) . '</span> <span>' . topicText($case['condition']) . '</span></div>';
            }
            $html .= '</div></div>';
        }
    }
    if (!empty($problem['figures'])) {
        $html .= '<div class="problem-figures">';
        foreach ($problem['figures'] as $figure) {
            $html .= '<figure class="problem-figure"><img src="' . topicText($figure['src']) . '" alt="' . topicText($figure['alt']) . '" loading="lazy" decoding="async"></figure>';
        }
        $html .= '</div>';
    }
    return $html;
}

function formatProblemStatement(string $statement): string
{
    $lines = explode("\n", trim($statement));
    if (count($lines) <= 1) {
        return '<p class="problem-statement-text">' . topicText($statement) . '</p>';
    }

    $header = array_shift($lines);
    $html = '<p class="problem-statement-text">' . topicText($header) . '</p>';
    $items = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // Check if line starts with numbered item like "1) " or letter like "a) "
        if (preg_match('/^(([0-9]+|[a-zA-Z])\))\s*(.*)$/u', $line, $matches)) {
            $num = $matches[1];
            $content = $matches[3];
            $items[] = '<div class="problem-subitem"><span class="subitem-num">' . topicText($num) . '</span><span class="subitem-content">' . topicText($content) . '</span></div>';
        } else {
            $items[] = '<div class="problem-subitem problem-subitem-plain">' . topicText($line) . '</div>';
        }
    }

    if (!empty($items)) {
        $html .= '<div class="problem-subitems-grid">' . implode('', $items) . '</div>';
    }

    return $html;
}
