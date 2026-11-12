<?php
$topicId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Mavzu ma'lumotlari
$topics = [
    1 => [
        'title' => 'C++ Kirish',
        'language' => 'cpp',
        'content' => '
            <h2>C++ dasturlash tiliga kirish</h2>
            <p>C++ - bu umumiy maqsadli dasturlash tili bo\'lib, u 1979 yilda Bjarne Stroustrup tomonidan ishlab chiqilgan. C++ tili C tilining kengaytmasi sifatida yaratilgan va obektga yo\'naltirilgan dasturlash imkoniyatlarini qo\'shadi.</p>
            
            <h3>Birinchi dastur</h3>
            <pre><code class="language-cpp">
#include &lt;iostream&gt;
using namespace std;

int main() {
    cout &lt;&lt; "Hello, World!" &lt;&lt; endl;
    return 0;
}
            </code></pre>
            
            <h3>Asosiy tushunchalar</h3>
            <ul>
                <li><strong>O\'zgaruvchilar:</strong> Ma\'lumotlarni saqlash uchun</li>
                <li><strong>Ma\'lumot turlari:</strong> int, float, char, bool</li>
                <li><strong>Operatorlar:</strong> +, -, *, /, %</li>
                <li><strong>Shart operatorlari:</strong> if, else, switch</li>
                <li><strong>Sikllar:</strong> for, while, do-while</li>
            </ul>
        '
    ],
    2 => [
        'title' => 'Python Kirish',
        'language' => 'python',
        'content' => '
            <h2>Python dasturlash tiliga kirish</h2>
            <p>Python - bu yuqori darajadagi, interpretatsiya qilinadigan dasturlash tili. U 1991 yilda Guido van Rossum tomonidan yaratilgan. Python o\'qish oson sintaksisi va keng qo\'llanilishi bilan mashhur.</p>
            
            <h3>Birinchi dastur</h3>
            <pre><code class="language-python">
print("Hello, World!")
            </code></pre>
            
            <h3>Asosiy tushunchalar</h3>
            <ul>
                <li><strong>O\'zgaruvchilar:</strong> Dinamik tiplash</li>
                <li><strong>Ma\'lumot turlari:</strong> int, float, str, bool</li>
                <li><strong>Ro\'yxatlar:</strong> List, Tuple, Dictionary</li>
                <li><strong>Shart operatorlari:</strong> if, elif, else</li>
                <li><strong>Sikllar:</strong> for, while</li>
            </ul>
        '
    ],
    3 => [
        'title' => 'Shart operatorlari',
        'language' => 'cpp',
        'content' => '<h2>Shart operatorlari</h2><p>if-else va switch case operatorlari haqida...</p>'
    ],
    4 => [
        'title' => 'Sikllar',
        'language' => 'cpp',
        'content' => '<h2>Sikllar</h2><p>for, while va do-while sikllari haqida...</p>'
    ],
    5 => [
        'title' => 'Funksiyalar',
        'language' => 'cpp',
        'content' => '<h2>Funksiyalar</h2><p>Funksiyalar bilan ishlash...</p>'
    ],
    6 => [
        'title' => 'Massivlar',
        'language' => 'cpp',
        'content' => '<h2>Massivlar</h2><p>Massivlar bilan ishlash...</p>'
    ],
    7 => [
        'title' => 'HTML va CSS',
        'language' => 'html',
        'content' => '<h2>HTML va CSS</h2><p>HTML sahifa tuzilishini, CSS esa dizayn va uslublarni belgilaydi.</p>'
    ],
    8 => [
        'title' => 'JavaScript Asoslari',
        'language' => 'javascript',
        'content' => '<h2>JavaScript Asoslari</h2><p>JavaScript sahifaga interaktivlik va dinamik xatti-harakatlar qo\'shadi.</p>'
    ],
    9 => [
        'title' => 'OOP',
        'language' => 'cpp',
        'content' => '<h2>OOP</h2><p>Obyektga yo\'naltirilgan dasturlash yaproqlar va modullarni tashkil etishda yordam beradi.</p>'
    ],
    10 => [
        'title' => 'SQL va ma\'lumotlar bazalari',
        'language' => 'sql',
        'content' => '<h2>SQL va ma\'lumotlar bazalari</h2><p>SQL orqali ma\'lumotlarni saqlash, o\'qish va yangilash mumkin.</p>'
    ],
    11 => [
        'title' => 'Git va GitHub',
        'language' => 'bash',
        'content' => '<h2>Git va GitHub</h2><p>Git bilan ish olib borish va kodni GitHubda saqlash ko\'nikmasi kerak.</p>'
    ],
    12 => [
        'title' => 'Algoritmlar va ma\'lumotlar tuzilmalari',
        'language' => 'cpp',
        'content' => '<h2>Algoritmlar</h2><p>Algoritmlar muammolarni samarali va tez yechishga yordam beradi.</p>'
    ],
    13 => [
        'title' => '1-bob. Sonli ma\'lumotlar',
        'language' => 'math',
        'content' => '
            <h2>1-bob. Sonli ma\'lumotlar</h2>
            <h3>1.1.1 Ma\'lumotlarni mashina xotirasida ifodalash</h3>
            <ol>
                <li><p><strong>1.1.1-masala.</strong> N = 1254 butun musbat son berilgan. Mashina razryadi k = 16 teng. Ushbu manfiy emas butun sonni mashina xotirasida ifodalang (avval o\'tkazish algoritmini keltiring, keyin algoritm qadamlari bo\'yicha masalani yeching).</p></li>
                <li><p><strong>1.1.2-masala.</strong> N = 2541 butun musbat son berilgan. Mashina razryadi k = 16 teng. Ushbu manfiy emas butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.3-masala.</strong> N = 3675 butun musbat son berilgan. Mashina razryadi k = 15 teng. Ushbu manfiy emas butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.4-masala.</strong> N = 7458 butun musbat son berilgan. Mashina razryadi k = 17 teng. Ushbu manfiy emas butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.5-masala.</strong> N = 7654 butun musbat son berilgan. Mashina razryadi k = 18 teng. Ushbu manfiy emas butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.6-masala.</strong> N = -7654 butun manfiy son berilgan. Mashina razryadi k = 18 teng. Ushbu manfiy butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.7-masala.</strong> N = -1025 butun manfiy son berilgan. Mashina razryadi k = 16 teng. Ushbu manfiy butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.8-masala.</strong> N = -5232 butun manfiy son berilgan. Mashina razryadi k = 16 teng. Ushbu manfiy butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.9-masala.</strong> N = -5879 butun manfiy son berilgan. Mashina razryadi k = 14 teng. Ushbu manfiy butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.10-masala.</strong> N = -7895 butun manfiy son berilgan. Mashina razryadi k = 18 teng. Ushbu manfiy butun sonni mashina xotirasida ifodalang.</p></li>
                <li><p><strong>1.1.11-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 101111111001 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.12-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 10111111100101 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.13-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 101111110111001 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.14-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 10110011111101 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.15-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 100011111111011 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.16-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 101111111001 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.17-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 10111111100101 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.18-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 101111110111001 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.19-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 10110011111101 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
                <li><p><strong>1.1.20-masala.</strong> Mashina xotirasida ifodalangan manfiy butun son (ikkilik) N = 100011111111011 berilgan. Ushbu sonni 10 lik sanoq sistemasiga qanday son ekanligi aniqlang.</p></li>
            </ol>
        '
    ]
];

$topic = isset($topics[$topicId]) ? $topics[$topicId] : $topics[1];
$topicIds = array_keys($topics);
$firstTopicId = (int) min($topicIds);
$lastTopicId = (int) max($topicIds);
?>

<div class="topic-detail-container">
    <div class="topic-detail-header">
        <h1 class="topic-detail-title"><?php echo $topic['title']; ?></h1>
        <div class="topic-actions">
            <a href="?page=code-editor&lang=<?php echo $topic['language']; ?>" class="btn btn-primary">
                <i class="fas fa-code"></i> Amaliyot
            </a>
        </div>
    </div>
    
    <div class="topic-content card">
        <?php echo $topic['content']; ?>
    </div>
    
    <div class="topic-navigation">
        <?php if ($topicId > $firstTopicId): ?>
        <a href="?page=topic-detail&id=<?php echo $topicId - 1; ?>" class="nav-prev">
            <i class="fas fa-arrow-left"></i> Oldingi mavzu
        </a>
        <?php endif; ?>
        
        <a href="?page=topics" class="nav-home">
            <i class="fas fa-th"></i> Barcha mavzular
        </a>
        
        <?php if ($topicId < $lastTopicId): ?>
        <a href="?page=topic-detail&id=<?php echo $topicId + 1; ?>" class="nav-next">
            Keyingi mavzu <i class="fas fa-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>

<style>
.topic-detail-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.topic-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.topic-detail-title {
    font-size: 32px;
    color: #10B981;
    margin: 0;
}

.btn-primary {
    display: inline-block;
    padding: 12px 25px;
    background: linear-gradient(45deg, #10B981, #3B82F6);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    transition: transform 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
}

.topic-content {
    line-height: 1.8;
    margin-bottom: 40px;
}

.topic-content h2 {
    color: #10B981;
    margin: 30px 0 20px;
    font-size: 28px;
}

.topic-content h3 {
    color: #3B82F6;
    margin: 25px 0 15px;
    font-size: 22px;
}

.topic-content p {
    margin-bottom: 20px;
    opacity: 0.9;
}

.topic-content ul, .topic-content ol {
    margin: 15px 0 25px 25px;
}

.topic-content li {
    margin-bottom: 10px;
}

.topic-content pre {
    background-color: #1E293B;
    padding: 20px;
    border-radius: 10px;
    overflow-x: auto;
    margin: 20px 0;
    border: 1px solid #10B981;
}

.topic-content code {
    font-family: 'Consolas', monospace;
    font-size: 14px;
}

.topic-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.nav-prev, .nav-next, .nav-home {
    text-decoration: none;
    color: inherit;
    padding: 10px 15px;
    border-radius: 8px;
    transition: background-color 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-prev:hover, .nav-next:hover, .nav-home:hover {
    background-color: rgba(16, 185, 129, 0.1);
    color: #10B981;
}

.nav-home {
    color: #3B82F6;
}

@media (max-width: 768px) {
    .topic-navigation {
        flex-direction: column;
        gap: 10px;
    }
    
    .topic-detail-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var blocks = document.querySelectorAll('.topic-content pre code');
  blocks.forEach(function (code) {
    var pre = code.parentElement;
    if (!pre || pre.nextElementSibling && pre.nextElementSibling.classList && pre.nextElementSibling.classList.contains('run-sample-btn')) return;
    var btn = document.createElement('button');
    btn.className = 'run-sample-btn';
    btn.type = 'button';
    btn.textContent = 'Ishga tushirish';
    btn.addEventListener('click', function () {
      try {
        sessionStorage.setItem('codestep.pending-code', code.textContent);
        sessionStorage.setItem('codestep.pending-lang', (code.className || '').replace('language-', '') || 'cpp');
      } catch (e) {}
      window.location.href = '?page=code-editor';
    });
    pre.parentNode.insertBefore(btn, pre.nextSibling);
  });
});
</script>
<style>.run-sample-btn{display:inline-flex;align-items:center;gap:6px;margin:12px 0 20px;padding:10px 18px;background:linear-gradient(135deg,#10B981,#3B82F6);color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;box-shadow:0 4px 12px rgba(16,185,129,.3);transition:transform .2s,box-shadow .2s;}.run-sample-btn:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(16,185,129,.4);}</style>
