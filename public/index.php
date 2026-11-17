<?php
if (($_GET['api'] ?? null) === 'health') {
    require __DIR__ . '/healthz.php';
    exit;
}
if (($_GET['api'] ?? null) === 'chat') {
    require __DIR__ . '/api/chat.php';
    exit;
}

require_once __DIR__ . '/includes/auth.php';
codeStepSession();
$currentUser = codeStepUser();
header('Cache-Control: no-store');
$csrfToken = $_SESSION['csrf_token'];

$profileImageFile = '';
$currentProfile = null;
require_once __DIR__ . '/includes/profile-store.php';
if ($currentUser) {
    try {
        $currentProfile = profileGet($currentUser['id']);
        if ($currentProfile && $currentProfile['has_avatar']) {
            $profileImageFile = 'api/profile-image.php?v=' . rawurlencode($currentUser['id'] . '-' . $currentProfile['updated_at']);
        }
    } catch (Throwable $error) { error_log('CodeStep profile: ' . $error->getMessage()); }
}

// Asosiy sozlamalar
define('SITE_NAME', 'CodeStep');
define('SITE_VERSION', '2.0.0');
define('ACCENT_GREEN', '#10B981');
define('ACCENT_BLUE', '#3B82F6');

// Mavzuni aniqlash
if (!isset($_COOKIE['theme'])) {
    $theme = 'dark';
    setcookie('theme', $theme, [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'secure' => codeStepIsHttps(),
        'samesite' => 'Lax',
    ]);
} else {
    $theme = in_array($_COOKIE['theme'], ['dark', 'light'], true) ? $_COOKIE['theme'] : 'dark';
}
define('CURRENT_THEME', $theme);

// Mavzular ma'lumotlari
$topics = [
    1 => [
        'title' => 'C++ Kirish',
        'language' => 'cpp',
        'icon' => 'fas fa-code',
        'duration' => '15 daqiqa',
        'exercises' => 5,
        'description' => 'C++ dasturlash tiliga kirish, asosiy tushunchalar va birinchi dastur',
        'color' => '#004482',
        'content' => '
            <h2>C++ dasturlash tiliga kirish</h2>
            <p>C++ - bu umumiy maqsadli dasturlash tili bo\'lib, u 1979 yilda Bjarne Stroustrup tomonidan ishlab chiqilgan. C++ tili C tilining kengaytmasi sifatida yaratilgan va obektga yo\'naltirilgan dasturlash imkoniyatlarini qo\'shadi.</p>
            
            <h3>Birinchi dastur</h3>
            <pre><code>#include &lt;iostream&gt;
using namespace std;

int main() {
    cout &lt;&lt; "Hello, World!" &lt;&lt; endl;
    return 0;
}</code></pre>
            
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
        'icon' => 'fab fa-python',
        'duration' => '12 daqiqa',
        'exercises' => 4,
        'description' => 'Python dasturlash tiliga kirish, o\'zgaruvchilar va ma\'lumot turlari',
        'color' => '#3776AB',
        'content' => '
            <h2>Python dasturlash tiliga kirish</h2>
            <p>Python - bu yuqori darajadagi, interpretatsiya qilinadigan dasturlash tili. U 1991 yilda Guido van Rossum tomonidan yaratilgan. Python o\'qish oson sintaksisi va keng qo\'llanilishi bilan mashhur.</p>
            
            <h3>Birinchi dastur</h3>
            <pre><code>print("Hello, World!")</code></pre>
            
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
        'icon' => 'fas fa-random',
        'duration' => '20 daqiqa',
        'exercises' => 8,
        'description' => 'if-else, switch case shart operatorlari va ularning qo\'llanilishi',
        'color' => '#10B981',
        'content' => '<h2>Shart operatorlari</h2><p>if-else va switch case operatorlari haqida...</p>'
    ],
    4 => [
        'title' => 'Sikllar',
        'language' => 'cpp',
        'icon' => 'fas fa-redo',
        'duration' => '25 daqiqa',
        'exercises' => 10,
        'description' => 'for, while, do-while sikllari va ularni amalda qo\'llash',
        'color' => '#3B82F6',
        'content' => '<h2>Sikllar</h2><p>for, while va do-while sikllari haqida...</p>'
    ],
    5 => [
        'title' => 'Funksiyalar',
        'language' => 'cpp',
        'icon' => 'fas fa-box',
        'duration' => '30 daqiqa',
        'exercises' => 12,
        'description' => 'Funksiyalar, parametrlar va qaytarish qiymatlari',
        'color' => '#8B5CF6',
        'content' => '<h2>Funksiyalar</h2><p>Funksiyalar bilan ishlash...</p>'
    ],
    6 => [
        'title' => 'Massivlar',
        'language' => 'cpp',
        'icon' => 'fas fa-layer-group',
        'duration' => '25 daqiqa',
        'exercises' => 8,
        'description' => 'Bir va ko\'p o\'lchamli massivlar, ular bilan ishlash',
        'color' => '#EC4899',
        'content' => '<h2>Massivlar</h2><p>Massivlar bilan ishlash...</p>'
    ],
    7 => [
        'title' => 'HTML va CSS',
        'language' => 'html',
        'icon' => 'fab fa-html5',
        'duration' => '18 daqiqa',
        'exercises' => 6,
        'description' => 'Veb-sahifaning tuzilishi, uslublari va responsiv dizayn',
        'color' => '#E44D26',
        'content' => '<h2>HTML va CSS</h2><p>HTML sahifa tuzilishini, CSS esa dizayn va uslublarni belgilaydi.</p>'
    ],
    8 => [
        'title' => 'JavaScript Asoslari',
        'language' => 'javascript',
        'icon' => 'fab fa-js',
        'duration' => '22 daqiqa',
        'exercises' => 9,
        'description' => 'JavaScript o\'zgaruvchilari, funksiyalari va hodisalar',
        'color' => '#F7DF1E',
        'content' => '<h2>JavaScript Asoslari</h2><p>JavaScript sahifaga interaktivlik va dinamik xatti-harakatlar qo\'shadi.</p>'
    ],
    9 => [
        'title' => 'OOP (Obyektga yo\'naltirilgan dasturlash)',
        'language' => 'cpp',
        'icon' => 'fas fa-cubes',
        'duration' => '28 daqiqa',
        'exercises' => 7,
        'description' => 'Klasslar, obyektlar, meros va inkapsulyatsiya',
        'color' => '#8B5CF6',
        'content' => '<h2>OOP</h2><p>Obyektga yo\'naltirilgan dasturlash yaproqlar va modullarni tashkil etishda yordam beradi.</p>'
    ],
    10 => [
        'title' => 'SQL va ma\'lumotlar bazalari',
        'language' => 'sql',
        'icon' => 'fas fa-database',
        'duration' => '24 daqiqa',
        'exercises' => 8,
        'description' => 'SELECT, INSERT, UPDATE va tabellar bilan ishlash',
        'color' => '#10B981',
        'content' => '<h2>SQL va ma\'lumotlar bazalari</h2><p>SQL orqali ma\'lumotlarni saqlash, o\'qish va yangilash mumkin.</p>'
    ],
    11 => [
        'title' => 'Git va GitHub',
        'language' => 'bash',
        'icon' => 'fab fa-github',
        'duration' => '16 daqiqa',
        'exercises' => 5,
        'description' => 'Versiyalarni boshqarish va loyihalarni hamkorlikda rivojlantirish',
        'color' => '#181717',
        'content' => '<h2>Git va GitHub</h2><p>Git bilan ish olib borish va kodni GitHubda saqlash ko\'nikmasi kerak.</p>'
    ],
    12 => [
        'title' => 'Algoritmlar va ma\'lumotlar tuzilmalari',
        'language' => 'cpp',
        'icon' => 'fas fa-sitemap',
        'duration' => '35 daqiqa',
        'exercises' => 11,
        'description' => 'Qidiruv, saralash va asosiy ma\'lumotlar tuzilmalari',
        'color' => '#F59E0B',
        'content' => '<h2>Algoritmlar</h2><p>Algoritmlar muammolarni samarali va tez yechishga yordam beradi.</p>'
    ]
];

require_once __DIR__ . '/includes/programming-topics.php';
$programmingCatalog = programmingTopicsCatalog();
$programmingTopics = programmingTopicsIndex($programmingCatalog);

// Joriy sahifani olish
$page = is_string($_GET['page'] ?? null) && $_GET['page'] !== '' ? $_GET['page'] : 'home';
$topicId = is_string($_GET['id'] ?? null) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 1;
if ($page === 'topic-detail' && !isset($programmingTopics[$topicId]) && !isset($topics[$topicId])) {
    http_response_code(404);
}
// Old bookmarks lead into the book's new starting page.
if ($page === 'topics') {
    header('Location: ?page=home', true, 302);
    exit;
}
$knownPages = [
    'home', 'section', 'chapter', 'topic-detail', 'sample-problems',
    'login', 'register', 'forgot-password', 'reset-password',
    'admin', 'contests', 'solutions', 'profile',
    'terms', 'privacy', 'code-editor', 'contact', 'settings',
];
if (!in_array($page, $knownPages, true)) {
    $page = 'home';
}
$bookSection = null;
$bookChapter = null;
if ($page === 'section' || $page === 'chapter') {
    $requestedSection = $_GET['section'] ?? null;
    $requestedChapter = $_GET['chapter'] ?? null;
    foreach ($programmingCatalog['sections'] as $section) {
        if ($page === 'section' && is_string($requestedSection) && $section['number'] === $requestedSection) {
            $bookSection = $section;
            break;
        }
        if ($page === 'chapter' && is_string($requestedChapter)) {
            foreach ($section['chapters'] as $chapter) {
                if ($chapter['number'] === $requestedChapter) {
                    $bookSection = $section;
                    $bookChapter = $chapter;
                    break 2;
                }
            }
        }
    }
    if ($bookSection === null || ($page === 'chapter' && $bookChapter === null)) {
        // Keep recovery inside the site even when the web server replaces error pages.
        header('Location: ?page=home&notice=' . ($page === 'section' ? 'section-not-found' : 'chapter-not-found'), true, 302);
        exit;
    }
}
$pageTitle = match ($page) {
    'home' => 'Kitob bo‘limlari',
    'section' => $bookSection ? $bookSection['number'] . ' bo‘lim · ' . $bookSection['title'] : 'Bo‘lim topilmadi',
    'chapter' => $bookChapter ? $bookChapter['number'] . '-bob · ' . $bookChapter['title'] : 'Bob topilmadi',
    'topic-detail' => $programmingTopics[$topicId]['title'] ?? $topics[$topicId]['title'] ?? 'Mavzu',
    'admin' => 'Admin Panel',
    'contests' => 'Haftalik Kiber-Olimpiada',
    'forgot-password' => 'Parolni qayta tiklash',
    'reset-password' => 'Yangi parol o‘rnatish',
    'terms' => 'Foydalanish shartlari',
    'privacy' => 'Maxfiylik siyosati',
    'settings' => 'Sozlamalar',
    default => 'Dasturlashni o‘rganing',
};
$requestScheme = codeStepIsHttps() ? 'https' : 'http';
$requestHost = is_string($_SERVER['HTTP_HOST'] ?? null) && preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'codestep.tokhirdev.uz';
$publicBaseUrl = ($requestHost === 'codestep' || str_starts_with($requestHost, '127.') || str_starts_with($requestHost, 'localhost'))
    ? ($requestScheme . '://' . $requestHost . '/')
    : 'https://codestep.tokhirdev.uz/';
$showSplash = !isset($_COOKIE['visited']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo topicText($pageTitle); ?> · <?php echo SITE_NAME; ?></title>
    <meta name="description" content="CodeStep — C++ va Python dasturlash tillarini interaktiv tarzda o‘rganish, dasturlash kitobi bo‘limlari va masalalarni brauzerda kod yozib yechish platformasi.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo topicText($publicBaseUrl); ?>">
    <meta property="og:site_name" content="CodeStep">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo topicText($publicBaseUrl); ?>">
    <meta property="og:title" content="CodeStep — Dasturlashni o‘rganamiz va onlayn amaliyot">
    <meta property="og:description" content="C++ va Python tillarini interaktiv tarzda o‘rganing. Bo‘limdan bobga, bobdan mavzuga o‘ting va masalalarni kod yozib yeching.">
    <meta property="og:image" content="<?php echo topicText($publicBaseUrl . 'assest/images/codestep-preview.jpg'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="CodeStep — Dasturlashni o‘rganamiz va onlayn amaliyot">
    <meta name="twitter:description" content="C++ va Python tillarini interaktiv tarzda o‘rganing.">
    <meta name="twitter:image" content="<?php echo topicText($publicBaseUrl . 'assest/images/codestep-preview.jpg'); ?>">
    <script>
        try {
            if (localStorage.getItem('codestep:desktop-menu-collapsed') === '1') {
                document.documentElement.classList.add('desktop-menu-collapsed');
            }
        } catch (_) { }
    </script>
    
    <script src="assest/js/settings.js?v=<?php echo filemtime(__DIR__ . '/assest/js/settings.js'); ?>"></script>
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assest/images/codestep-icon.svg?v=<?php echo filemtime(__DIR__ . '/assest/images/codestep-icon.svg'); ?>">
    <?php if ($page === 'code-editor'): ?>
    <link rel="stylesheet" href="assest/vendor/codemirror/lib/codemirror.css">
    <link rel="stylesheet" href="assest/vendor/codemirror/addon/hint/show-hint.css">
    <link rel="stylesheet" href="assest/vendor/codemirror/addon/fold/foldgutter.css">
    <?php endif; ?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .dark-mode {
            background-color: #0F172A;
            color: #E2E8F0;
        }

        .light-mode {
            --day-bg: #f3ead7;
            --day-surface: #fff8e9;
            --day-panel: #eee2ca;
            --day-border: #ded2bc;
            --day-text: #342f27;
            --day-muted: #6b6254;
            --day-accent: #27634e;
            background-color: var(--day-bg);
            color: var(--day-text);
        }

        .main-content {
            flex: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background-color: #0F172A;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .light-mode .toolbar {
            background-color: var(--day-surface);
            border-bottom-color: var(--day-border);
        }

        .menu-button {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-button:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .toolbar-mark {
            width: 40px;
            color: #10B981;
            font-family: monospace;
            font-size: 1.125rem;
            font-weight: 700;
            text-align: center;
        }

        .toolbar-title {
            font-size: 1.25rem;
            font-weight: 600;
            background: linear-gradient(45deg, #10B981, #3B82F6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .theme-toggle-mobile {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
        }

        .drawer-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .navigation-drawer {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background-color: #1E293B;
            z-index: 1000;
            transition: left 0.3s;
            box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            overflow-y: auto;
        }

        .light-mode .navigation-drawer {
            background-color: var(--day-surface);
        }

        .navigation-drawer.open {
            left: 0;
        }

        .drawer-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .profile-image {
            width: 80px;
            height: 80px;
            border-radius: 40px;
            margin-bottom: 15px;
            object-fit: cover;
        }

        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            background: rgba(16, 185, 129, 0.12);
            border: 2px solid #10B981;
            color: #10B981;
            font-size: 1.75rem;
        }

        .settings-profile-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 2px solid #10B981;
            object-fit: cover;
            flex: 0 0 auto;
        }

        .profile-upload-control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            border: 1px solid #10B981;
            border-radius: 7px;
            color: inherit;
            cursor: pointer;
        }

        .profile-upload-control:hover {
            background: rgba(16, 185, 129, 0.12);
        }

        .profile-upload-input {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        .drawer-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: inherit;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .menu-item:hover {
            background-color: rgba(16,185,129,0.1);
        }

        .menu-item i {
            width: 24px;
            color: #10B981;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            margin-left: auto;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #10B981;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 24px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .theme-switch-container {
            display: flex;
            align-items: center;
        }

        .splash-screen {
            --splash-ink: #e8f4e8;
            --splash-muted: #9bad9e;
            --splash-accent: #c5f46a;
            position: fixed;
            inset: 0;
            display: grid;
            place-items: center;
            overflow: hidden;
            padding: 28px;
            background: #071712;
            color: var(--splash-ink);
            z-index: 10010;
            isolation: isolate;
            transition: opacity .55s ease, transform .55s ease;
        }

        .splash-screen::before {
            position: absolute;
            inset: 0;
            z-index: -1;
            background-image: linear-gradient(rgba(185, 225, 190, .055) 1px, transparent 1px), linear-gradient(90deg, rgba(185, 225, 190, .055) 1px, transparent 1px);
            background-size: 42px 42px;
            content: "";
            mask-image: linear-gradient(to bottom, #000 0%, rgba(0,0,0,.7) 70%, transparent 100%);
        }

        .splash-screen::after {
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(112deg, transparent 0 54%, rgba(197, 244, 106, .045) 54.1% 54.2%, transparent 54.3% 100%);
            content: "";
        }

        .splash-screen.hide {
            opacity: 0;
            transform: translateY(-8px);
            pointer-events: none;
        }

        .splash-content {
            position: relative;
            display: grid;
            justify-items: center;
            width: min(420px, 100%);
            animation: splashEnter .7s cubic-bezier(.2,.8,.2,1) both;
        }

        .splash-mark {
            display: grid;
            grid-template-columns: repeat(3, auto);
            align-items: center;
            justify-content: center;
            gap: 2px;
            width: 76px;
            height: 76px;
            border: 1px solid rgba(197,244,106,.4);
            border-radius: 20px;
            background: rgba(197,244,106,.08);
            color: var(--splash-accent);
            font-family: Consolas, 'Courier New', monospace;
            font-size: 25px;
            line-height: 1;
            box-shadow: 0 0 42px rgba(197,244,106,.1), inset 0 0 22px rgba(197,244,106,.04);
            transform: rotate(-4deg);
            animation: splashMarkIn .65s .08s cubic-bezier(.2,.8,.2,1) both;
        }

        .splash-mark b { color: #f2f7ed; font-weight: 400; }
        .splash-brand-name { margin-top: 21px; color: #f3faed; font-size: 20px; font-weight: 700; }
        .splash-brand-name span { color: var(--splash-accent); }

        .splash-title {
            margin: 10px 0 34px;
            color: #f0f5eb;
            font-size: 36px;
            font-weight: 500;
            letter-spacing: 0;
            line-height: 1.25;
        }

        .splash-title span { color: var(--splash-accent); }

        .splash-progress { width: 104px; height: 2px; overflow: hidden; background: rgba(191,216,190,.16); }
        .splash-progress span { display: block; width: 100%; height: 100%; background: var(--splash-accent); transform: scaleX(0); transform-origin: left; animation: splashProgress 2.5s linear forwards; }

        @keyframes splashEnter { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes splashMarkIn { from { opacity: 0; transform: translateY(8px) rotate(-8deg) scale(.9); } to { opacity: 1; transform: translateY(0) rotate(-4deg) scale(1); } }
        @keyframes splashProgress { to { transform: scaleX(1); } }

        @media (max-width: 420px) {
            .splash-screen { padding: 18px; }
            .splash-title { font-size: 30px; margin-bottom: 28px; }
            .splash-mark { width: 68px; height: 68px; font-size: 22px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .splash-content, .splash-mark, .splash-progress span { animation: none; }
            .splash-progress span { transform: scaleX(1); }
            .splash-screen { transition-duration: .01ms; }
        }

        .footer {
            background-color: inherit;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 40px 0 20px;
            margin-top: 40px;
        }

        .light-mode .footer {
            border-top-color: var(--day-border);
        }

        .footer-content {
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }

        .footer-section h3 {
            color: #10B981;
            margin-bottom: 20px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: inherit;
            text-decoration: none;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .footer-links a:hover {
            opacity: 1;
            color: #10B981;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 20px;
            background-color: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: inherit;
            text-decoration: none;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background-color: #10B981;
            color: white;
            transform: translateY(-3px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            opacity: 0.6;
            font-size: 0.875rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: rgba(255,255,255,0.1);
            border: none;
            border-radius: 8px;
            color: inherit;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .back-button:hover {
            background-color: rgba(16,185,129,0.1);
        }

        .light-mode .back-button,
        .light-mode .social-links a {
            background-color: var(--day-panel);
        }

        .light-mode .back-button:hover,
        .light-mode .menu-button:hover,
        .light-mode .menu-item:hover {
            background-color: #e6eedf;
        }

        .home-container {
            min-height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 60px;
        }

        .welcome-title {
            font-size: 2.5rem;
            margin-bottom: 12px;
            background: linear-gradient(45deg, #10B981, #3B82F6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 30px;
            color: #10B981;
            text-align: center;
        }

        .home-topics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .home-topic-card {
            background-color: rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .light-mode .home-topic-card {
            background-color: var(--day-surface);
            border-color: var(--day-border);
            box-shadow: 0 5px 20px rgba(91,68,32,0.06);
        }

        .home-topic-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 30px rgba(0,0,0,0.2);
        }

        .topic-big-icon {
            width: 100px;
            height: 100px;
            border-radius: 50px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.125rem;
            color: white;
        }

        .cpp .topic-big-icon {
            background: linear-gradient(135deg, #004482, #0099FF);
        }

        .cpp-mark {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .python .topic-big-icon {
            border: 4px solid transparent;
            background:
                linear-gradient(145deg, #17232E, #101820) padding-box,
                linear-gradient(145deg, #12A9E8 0%, #078CD5 42%, #08BD70 70%, #18D65B 100%) border-box;
            box-shadow: 0 0 14px rgba(0, 190, 130, 0.18);
        }

        .python .topic-big-icon i {
            background: linear-gradient(160deg, #18A9E8 5%, #078BD2 48%, #08B96C 52%, #22D653 95%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .home-topic-title {
            font-size: 1.75rem;
            margin-bottom: 15px;
        }

        .topic-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            color: #10B981;
            margin-top: 15px;
        }

        .topic-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-title {
            font-size: 2rem;
            color: #10B981;
            text-align: center;
            margin: 30px 0;
        }

        .topics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .topic-card {
            background-color: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 25px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .light-mode .topic-card {
            background-color: var(--day-surface);
            border-color: var(--day-border);
            box-shadow: 0 2px 10px rgba(91,68,32,0.06);
        }

        .topic-card:hover {
            transform: translateY(-5px);
        }

        .topic-icon {
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: linear-gradient(45deg, #10B981, #3B82F6);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .topic-icon i {
            font-size: 1.875rem;
            color: white;
        }

        .topic-meta {
            display: flex;
            justify-content: space-between;
            color: #10B981;
            margin-top: 15px;
            font-size: 0.875rem;
        }

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
            font-size: 2rem;
            color: #10B981;
        }

        .btn-primary {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(45deg, #10B981, #3B82F6);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: transform 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .topic-content {
            line-height: 1.8;
            padding: 30px;
            background-color: rgba(255,255,255,0.05);
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .light-mode .topic-content {
            background-color: var(--day-surface);
            border-color: var(--day-border);
            box-shadow: 0 2px 10px rgba(91,68,32,0.06);
        }

        .topic-content h2 {
            color: #10B981;
            margin: 30px 0 20px;
        }

        .topic-content h3 {
            color: #3B82F6;
            margin: 25px 0 15px;
        }

        .topic-content pre {
            background-color: #1E293B;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            margin: 20px 0;
            border: 1px solid #10B981;
        }

        .light-mode .topic-content pre {
            background-color: var(--day-panel);
        }

        .topic-content code {
            font-family: monospace;
            color: #10B981;
        }

        .code-editor-panel {
            flex: 1;
            min-width: 300px;
        }

        .code-editor-panel .CodeMirror {
            height: 400px;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 10px 0;
            font-family: Consolas, 'Cascadia Code', monospace;
            font-size: 0.9375rem;
            line-height: 1.65;
        }

        .cm-s-codestep-dark.CodeMirror {
            background: #111827;
            color: #E2E8F0;
        }

        .cm-s-codestep-dark .CodeMirror-gutters {
            background: #0F172A;
            border-right: 1px solid #334155;
        }

        .cm-s-codestep-dark .CodeMirror-linenumber { color: #64748B; }
        .cm-s-codestep-dark .CodeMirror-cursor { border-left: 2px solid #F8FAFC; }
        .cm-s-codestep-dark .CodeMirror-selected { background: rgba(56, 189, 248, 0.22); }
        .cm-s-codestep-dark .CodeMirror-activeline-background { background: rgba(148, 163, 184, 0.08); }
        .cm-s-codestep-light.CodeMirror { background: var(--day-surface, #fff8e9); color: #342f27; border-color: #ded2bc; }
        .cm-s-codestep-light .CodeMirror-gutters { background: #eee2ca; border-right: 1px solid #ded2bc; }
        .cm-s-codestep-light .CodeMirror-linenumber { color: #6b6254; }
        .cm-s-codestep-light .CodeMirror-cursor { border-left: 2px solid #342f27; }
        .cm-s-codestep-light .CodeMirror-selected { background: rgba(14, 116, 144, 0.18); }
        .cm-s-codestep-light .CodeMirror-activeline-background { background: rgba(15, 23, 42, 0.045); }

        .cm-s-codestep-dark .cm-keyword,
        .cm-s-codestep-light .cm-keyword { color: #C792EA; font-weight: 600; }
        .cm-s-codestep-dark .cm-operator,
        .cm-s-codestep-light .cm-operator { color: #89DDFF; }
        .cm-s-codestep-dark .cm-number,
        .cm-s-codestep-light .cm-number { color: #F78C6C; }
        .cm-s-codestep-dark .cm-string,
        .cm-s-codestep-dark .cm-string-2 { color: #C3E88D; }
        .cm-s-codestep-light .cm-string,
        .cm-s-codestep-light .cm-string-2 { color: #3F7D20; }
        .cm-s-codestep-dark .cm-comment,
        .cm-s-codestep-light .cm-comment { color: #7F9D85; font-style: italic; }
        .cm-s-codestep-dark .cm-def,
        .cm-s-codestep-dark .cm-builtin { color: #82AAFF; }
        .cm-s-codestep-light .cm-def,
        .cm-s-codestep-light .cm-builtin { color: #245DB2; }
        .cm-s-codestep-dark .cm-variable { color: #E2E8F0; }
        .cm-s-codestep-light .cm-variable { color: #1E293B; }
        .cm-s-codestep-dark .cm-variable-2,
        .cm-s-codestep-dark .cm-property { color: #F2C97D; }
        .cm-s-codestep-light .cm-variable-2,
        .cm-s-codestep-light .cm-property { color: #9A6700; }
        .cm-s-codestep-dark .cm-atom,
        .cm-s-codestep-dark .cm-meta { color: #F07178; }
        .cm-s-codestep-light .cm-atom,
        .cm-s-codestep-light .cm-meta { color: #B4233D; }
        .cm-s-codestep-light .cm-keyword { color: #7C3AED; }
        .cm-s-codestep-light .cm-operator { color: #0369A1; }
        .cm-s-codestep-light .cm-number { color: #C2410C; }
        .cm-s-codestep-light .cm-comment { color: #4B6B50; }

        .light-mode .page-title,
        .light-mode .topic-stats,
        .light-mode .footer-section h3,
        .light-mode .topic-content h2,
        .light-mode .topic-content code {
            color: var(--day-accent);
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            background-color: #10B981;
            color: white;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from{transform:translateX(100%);opacity:0}
            to{transform:translateX(0);opacity:1}
        }

        @media (max-width: 768px) {
            .welcome-title{font-size: 2rem}
            .home-topics-grid{grid-template-columns:1fr}
            .footer-content{grid-template-columns:1fr;text-align:center}
            .social-links{justify-content:center}
        }
    </style>
    <link rel="stylesheet" href="assest/css/background.css?v=<?php echo filemtime(__DIR__ . '/assest/css/background.css'); ?>">
    <link rel="stylesheet" href="assest/css/account.css?v=<?php echo filemtime(__DIR__ . '/assest/css/account.css'); ?>">
    <link rel="stylesheet" href="assest/css/profile.css?v=<?php echo filemtime(__DIR__ . '/assest/css/profile.css'); ?>">
    <link rel="stylesheet" href="assest/css/navigation.css?v=<?php echo filemtime(__DIR__ . '/assest/css/navigation.css'); ?>">
    <link rel="stylesheet" href="assest/css/notifications.css?v=<?php echo filemtime(__DIR__ . '/assest/css/notifications.css'); ?>">
    <?php if ($page === 'solutions'): ?>
    <link rel="stylesheet" href="assest/css/solutions.css?v=<?php echo filemtime(__DIR__ . '/assest/css/solutions.css'); ?>">
    <?php endif; ?>
    <?php if ($page === 'settings'): ?>
    <link rel="stylesheet" href="assest/css/settings.css?v=<?php echo filemtime(__DIR__ . '/assest/css/settings.css'); ?>">
    <?php endif; ?>
    <?php if (in_array($page, ['terms', 'privacy'], true)): ?>
    <link rel="stylesheet" href="assest/css/terms.css?v=<?php echo filemtime(__DIR__ . '/assest/css/terms.css'); ?>">
    <?php endif; ?>
    <?php if ($page === 'code-editor'): ?>
    <link rel="stylesheet" href="assest/css/editor.css?v=<?php echo filemtime(__DIR__ . '/assest/css/editor.css'); ?>">
    <link rel="stylesheet" href="assest/css/editor-productivity.css?v=<?php echo filemtime(__DIR__ . '/assest/css/editor-productivity.css'); ?>">
    <?php endif; ?>
    <?php if (in_array($page, ['home', 'section', 'chapter', 'topic-detail', 'sample-problems'], true)): ?>
    <link rel="stylesheet" href="assest/css/topics.css?v=<?php echo filemtime(__DIR__ . '/assest/css/topics.css'); ?>">
    <?php endif; ?>
    <?php if (in_array($page, ['home', 'section', 'chapter'], true)): ?>
    <link rel="stylesheet" href="assest/css/book.css?v=<?php echo filemtime(__DIR__ . '/assest/css/book.css'); ?>">
    <?php endif; ?>
    <?php if ($page === 'contact'): ?>
    <link rel="stylesheet" href="assest/css/contact.css?v=<?php echo filemtime(__DIR__ . '/assest/css/contact.css'); ?>">
    <?php endif; ?>
</head>
<body class="<?php echo $theme; ?>-mode<?php echo $page === 'home' ? ' home-dashboard' : ($page === 'contact' ? ' contact-dashboard' : ''); ?>">
    <div class="site-background" aria-hidden="true">
        <span class="background-code background-code-left">&lt;/&gt;</span>
        <span class="background-code background-code-right">{ }</span>
    </div>
    
    <?php if ($showSplash): ?>
    <div class="splash-screen" id="splash" role="status" aria-label="CodeStep">
        <div class="splash-content">
            <span class="splash-mark" aria-hidden="true"><span>&lt;</span><b>/</b><span>&gt;</span></span>
            <p class="splash-brand-name">Code<span>Step</span></p>
            <h1 class="splash-title">Xush kelibsiz<span>.</span></h1>
            <div class="splash-progress" role="progressbar" aria-label="Yuklanmoqda" aria-valuemin="0" aria-valuemax="100"><span></span></div>
        </div>
    </div>
    <?php endif; ?>
    
    <nav class="navigation-drawer" id="navigationDrawer" aria-label="Asosiy menyu">
        <?php if ($page === 'home'): ?>
        <a class="home-sidebar-brand" href="?page=home" aria-label="CodeStep asosiy sahifa">
            <span class="home-sidebar-mark" aria-hidden="true">&lt;/&gt;</span>
            <span>Code<span>Step</span></span>
        </a>
        <?php endif; ?>
        <div class="drawer-header">
            <a class="drawer-profile-link" href="?page=profile" aria-label="Shaxsiy profilni ochish">
            <?php if ($profileImageFile !== ''): ?>
                <img id="profileAvatarDrawer" data-profile-avatar src="<?php echo topicText($profileImageFile); ?>" alt="Profil rasmi" class="profile-image">
            <?php elseif ($currentUser): ?>
                <span id="profileAvatarDrawer" data-profile-avatar class="profile-image profile-initial" aria-label="Profil rasmi"><?php echo topicText(strtoupper(substr($currentUser['username'], 0, 1))); ?></span>
            <?php else: ?>
                <div id="profileAvatarDrawer" class="profile-image avatar-placeholder" aria-label="Profil rasmi"><i class="fas fa-user"></i></div>
            <?php endif; ?>
            <h3 data-profile-name><?php echo topicText($currentProfile['display_name'] ?? $currentUser['username'] ?? 'CodeStep'); ?></h3>
            <p><?php echo $currentUser ? '@' . topicText($currentUser['username']) : 'Dasturlashni o‘rganing'; ?></p>
            </a>
        </div>
        
        <div class="drawer-menu">
            <a href="?page=profile" class="menu-item"<?php echo $page === 'profile' ? ' aria-current="page"' : ''; ?>><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg> <span>Profil</span></a>
            <a href="?page=solutions" class="menu-item"<?php echo $page === 'solutions' ? ' aria-current="page"' : ''; ?>><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v2H5l-2 10V7Zm0 13h16l3-10"/></svg> <span>Yechimlarim</span></a>
            <?php if (!$currentUser): ?>
            <a href="?page=login" class="menu-item" aria-label="Kirish / Ro‘yxatdan o‘tish"<?php echo in_array($page, ['login', 'register'], true) ? ' aria-current="page"' : ''; ?>><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5M3 12h12m-4-4 4 4-4 4"/></svg> <span class="menu-label-full">Kirish / Ro‘yxatdan o‘tish</span><span class="menu-label-short" aria-hidden="true">Kirish</span></a>
            <?php endif; ?>
            <a href="?page=home" class="menu-item"<?php echo in_array($page, ['home', 'section', 'chapter', 'topic-detail', 'sample-problems', 'code-editor'], true) ? ' aria-current="page"' : ''; ?>>
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10H3V10Z"/><path d="M9 20v-7h6v7"/></svg> <span>Asosiy</span>
            </a>
            <a href="?page=contact" class="menu-item"<?php echo $page === 'contact' ? ' aria-current="page"' : ''; ?>>
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg> <span>Aloqa</span>
            </a>
            <a href="?page=settings" class="menu-item"<?php echo in_array($page, ['settings', 'terms', 'privacy'], true) ? ' aria-current="page"' : ''; ?>>
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3v18M12 3v18M20 3v18"/><path d="M1 8h6M9 16h6M17 8h6"/></svg> <span>Sozlamalar</span>
            </a>
            
        </div>
        <?php if ($page === 'home'): ?>
        <a class="home-sidebar-cta" href="?page=code-editor">
            <i class="fas fa-crown" aria-hidden="true"></i>
            <span>O‘z mahoratingizni keyingi bosqichga olib chiqing</span>
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <?php endif; ?>
    </nav>
    
    <main class="main-content">
        <header class="toolbar">
            <button type="button" class="menu-button" id="menuToggle" aria-label="Menyuni yig‘ish" aria-controls="navigationDrawer" aria-expanded="true" title="Menyuni yig‘ish"><svg class="nav-toggle-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m7-12-4 4 4 4"/></svg></button>
            <div class="logo-container" onclick="window.location.href='?page=home'">
                <span class="toolbar-mark" aria-hidden="true">&lt;/&gt;</span>
                <span class="toolbar-title">CodeStep</span>
            </div>
            <?php if ($page === 'home'): ?>
            <div class="home-search-wrap">
                <form class="home-search" id="homeSearchForm" role="search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input id="homeSearchInput" type="search" autocomplete="off" aria-autocomplete="list" aria-controls="homeSearchResults" aria-expanded="false" placeholder="Kurslar, mavzular va masalalarni qidiring..." aria-label="Kurslar, mavzular va masalalarni qidirish">
                    <kbd aria-hidden="true">Ctrl K</kbd>
                </form>
                <div class="home-search-results" id="homeSearchResults" role="listbox" aria-label="Qidiruv natijalari" hidden></div>
                <p class="home-search-empty" id="homeSearchEmpty" role="status" hidden></p>
            </div>
            <nav class="home-top-nav" aria-label="Tezkor havolalar">
                <a class="active" href="?page=home" aria-current="page"><i class="fas fa-home" aria-hidden="true"></i>Asosiy</a>
                <a href="?page=contests"><i class="fas fa-trophy" aria-hidden="true"></i>Musobaqalar</a>
                <a href="#homeCourses"><i class="fas fa-book-open" aria-hidden="true"></i>Kurslar</a>
                <a href="?page=contact"><i class="fas fa-users" aria-hidden="true"></i>Aloqa</a>
            </nav>
            <?php endif; ?>
            <div class="toolbar-actions">
                <div id="siteNotifications"></div>
                <?php if ($page === 'home' && $currentUser): ?>
                <a class="home-profile-shortcut" href="?page=profile" aria-label="Profilni ochish">
                    <?php if ($profileImageFile !== ''): ?>
                    <img src="<?php echo topicText($profileImageFile); ?>" alt="">
                    <?php else: ?>
                    <span><?php echo topicText(strtoupper(substr($currentUser['username'], 0, 1))); ?></span>
                    <?php endif; ?>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
                <?php if (!$currentUser): ?>
                <a class="account-toolbar-link" href="?page=login">Kirish</a>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="container">
            <?php if ($page === 'home'): ?>
                <?php include __DIR__ . '/pages/book-home.php'; ?>

            <?php elseif ($page === 'section'): ?>
                <?php include __DIR__ . '/pages/book-section.php'; ?>

            <?php elseif ($page === 'chapter'): ?>
                <?php include __DIR__ . '/pages/book-chapter.php'; ?>

            <?php elseif ($page == 'topic-detail'): ?>
                <?php if (isset($programmingTopics[$topicId])): ?>
                <?php include __DIR__ . '/pages/programming-topic-detail.php'; ?>
                <?php elseif (isset($topics[$topicId])): ?>
                <?php $topic = $topics[$topicId]; ?>
                <div class="topic-detail-container">
                    <a class="back-button" href="?page=home"><i class="fas fa-arrow-left"></i> Bo‘limlarga qaytish</a>
                    <div class="topic-detail-header">
                        <h1 class="topic-detail-title"><?php echo $topic['title']; ?></h1>
                        <a href="?page=code-editor&lang=<?php echo $topic['language']; ?>" class="btn-primary"><i class="fas fa-code"></i> Amaliyot</a>
                    </div>
                    <div class="topic-content"><?php echo $topic['content']; ?></div>
                </div>
                <?php else: ?>
                <section class="solutions-page book-page"><div class="book-empty"><h1>Mavzu topilmadi</h1><p>Bosh sahifadan bo‘lim, bob va mavzuni qayta tanlang.</p><a class="account-button" href="?page=home">Bosh sahifaga qaytish</a></div></section>
                <?php endif; ?>

            <?php elseif ($page == 'sample-problems'): ?>
                <?php include __DIR__ . '/pages/sample-problems.php'; ?>

            <?php elseif ($page === 'login' || $page === 'register'): ?>
                <?php include __DIR__ . '/pages/account.php'; ?>

            <?php elseif ($page === 'forgot-password'): ?>
                <?php include __DIR__ . '/pages/forgot-password.php'; ?>

            <?php elseif ($page === 'reset-password'): ?>
                <?php include __DIR__ . '/pages/reset-password.php'; ?>

            <?php elseif ($page === 'admin'): ?>
                <?php include __DIR__ . '/pages/admin.php'; ?>

            <?php elseif ($page === 'contests'): ?>
                <?php include __DIR__ . '/pages/contests.php'; ?>

            <?php elseif ($page === 'solutions'): ?>
                <?php include __DIR__ . '/pages/solutions.php'; ?>

            <?php elseif ($page === 'profile'): ?>
                <?php include __DIR__ . '/pages/profile.php'; ?>

            <?php elseif ($page === 'terms'): ?>
                <?php include __DIR__ . '/pages/terms.php'; ?>

            <?php elseif ($page === 'privacy'): ?>
                <?php include __DIR__ . '/pages/privacy.php'; ?>

            <?php elseif ($page == 'code-editor'): ?>
                <?php include __DIR__ . '/pages/code-editor.php'; ?>

            <?php elseif ($page == 'contact'): ?>
                <?php include __DIR__ . '/pages/contact.php'; ?>

            <?php elseif ($page == 'settings'): ?>
                <?php include __DIR__ . '/pages/settings.php'; ?>

            <?php else: ?>
                <?php include __DIR__ . '/pages/book-home.php'; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>CodeStep</h3>
                <p>C++ va Python dasturlash tillarini o'rganing!</p>
                <div class="social-links">
                    <a href="https://t.me/MTX_2477" target="_blank"><i class="fab fa-telegram"></i></a>
                    <a href="https://instagram.com/codestep_programming" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="mailto:mamurovtohir571@gmail.com"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Kitob bo‘limlari</h3>
                <ul class="footer-links">
                    <?php foreach ($programmingCatalog['sections'] as $section): ?>
                    <li><a href="?page=section&amp;section=<?php echo topicText(rawurlencode($section['number'])); ?>"><?php echo topicText($section['number'] . ' bo‘lim. ' . $section['title']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Bog'lanish</h3>
                <ul class="footer-links">
                    <li><a href="?page=contact">Aloqa</a></li>
                    <li><a href="?page=terms">Foydalanish shartlari</a></li>
                    <li><a href="?page=privacy">Maxfiylik siyosati</a></li>
                    <li><a href="tel:+998773502477">+998 77 350 24 77</a></li>
                    <li><a href="mailto:mamurovtohir571@gmail.com">Email</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 CodeStep. Barcha huquqlar himoyalangan.</p>
        </div>
    </footer>

    <?php if ($page === 'code-editor' || $page === 'sample-problems'): ?>
    <script src="assest/vendor/codemirror/lib/codemirror.js"></script>
    <script src="assest/vendor/codemirror/mode/clike/clike.js"></script>
    <script src="assest/vendor/codemirror/mode/python/python.js"></script>
    <?php endif; ?>
    <?php if ($page === 'sample-problems'): ?>
    <script src="assest/vendor/codemirror/addon/runmode/runmode.js"></script>
    <script src="assest/js/sample-code.js?v=<?php echo filemtime(__DIR__ . '/assest/js/sample-code.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($page === 'code-editor'): ?>
    <script src="assest/vendor/codemirror/addon/edit/closebrackets.js"></script>
    <script src="assest/vendor/codemirror/addon/edit/matchbrackets.js"></script>
    <script src="assest/vendor/codemirror/addon/selection/active-line.js"></script>
    <script src="assest/vendor/codemirror/addon/display/rulers.js"></script>
    <script src="assest/vendor/codemirror/addon/hint/show-hint.js"></script>
    <script src="assest/vendor/codemirror/addon/search/searchcursor.js"></script>
    <script src="assest/vendor/codemirror/addon/comment/comment.js"></script>
    <script src="assest/vendor/codemirror/addon/fold/foldcode.js"></script>
    <script src="assest/vendor/codemirror/addon/fold/foldgutter.js"></script>
    <script src="assest/vendor/codemirror/addon/fold/brace-fold.js"></script>
    <script src="assest/vendor/codemirror/addon/fold/indent-fold.js"></script>
    <script src="assest/js/code-rules.js?v=<?php echo filemtime(__DIR__ . '/assest/js/code-rules.js'); ?>"></script>
    <script src="assest/js/editor-completion.js?v=<?php echo filemtime(__DIR__ . '/assest/js/editor-completion.js'); ?>"></script>
    <script src="assest/js/editor-productivity.js?v=<?php echo filemtime(__DIR__ . '/assest/js/editor-productivity.js'); ?>"></script>
    <script src="assest/js/editor.js?v=<?php echo filemtime(__DIR__ . '/assest/js/editor.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($page === 'profile'): ?>
    <script src="assest/js/profile.js?v=<?php echo filemtime(__DIR__ . '/assest/js/profile.js'); ?>"></script>
    <?php endif; ?>
    <script src="assest/js/navigation.js?v=<?php echo filemtime(__DIR__ . '/assest/js/navigation.js'); ?>"></script>
    <?php if ($showSplash): ?>
    <script>
        setTimeout(()=>{const s=document.getElementById('splash');if(s){s.classList.add('hide');setTimeout(()=>{s.style.display='none';},600);}document.cookie="visited=true; max-age=3600; path=/; SameSite=Lax"+(location.protocol==='https:'?'; Secure':'')},2500);
    </script>
    <?php endif; ?>
    <?php if ($page === 'home'): ?>
    <script src="assest/js/home-search.js?v=<?php echo filemtime(__DIR__ . '/assest/js/home-search.js'); ?>"></script>
    <?php endif; ?>
    <?php
    $notificationTopics = [];
    foreach ($programmingTopics as $id => $topic) {
        if (($topic['kind'] ?? '') === 'topic') {
            $notificationTopics[] = ['id' => (string) $id, 'title' => $topic['title'], 'href' => '?page=topic-detail&id=' . $id];
        }
    }
    ?>
    <script type="application/json" id="notificationCatalog"><?php echo json_encode($notificationTopics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
    <script src="assest/js/notifications.js?v=<?php echo filemtime(__DIR__ . '/assest/js/notifications.js'); ?>"></script>
    <link rel="stylesheet" href="assest/css/chat.css">
    <script src="assest/js/chat.js?v=<?php echo filemtime(__DIR__ . '/assest/js/chat.js'); ?>"></script>
</body>
</html>
