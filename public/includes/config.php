<?php
session_start();

// Asosiy sozlamalar
define('SITE_NAME', 'CodeStep');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $scheme . '://' . $host);
define('ASSETS_URL', SITE_URL . '/assets');
define('API_URL', SITE_URL . '/api');

// Mavzu sozlamalari
define('DARK_MODE_COLOR', '#0F172A');
define('ACCENT_GREEN', '#10B981');
define('ACCENT_BLUE', '#3B82F6');

// Mavzuni aniqlash
if (!isset($_COOKIE['theme'])) {
    $theme = 'dark';
    setcookie('theme', $theme, time() + (86400 * 30), "/");
} else {
    $theme = $_COOKIE['theme'];
}

define('CURRENT_THEME', $theme);

// Xato xabarlarini sozlash
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fayl yo'llari
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('DATA_PATH', ROOT_PATH . '/data');

// Mavzular ma'lumotlarini yuklash
function getTopics() {
    $topicsFile = DATA_PATH . '/topics.json';
    if (file_exists($topicsFile)) {
        $data = json_decode(file_get_contents($topicsFile), true);

        if (is_array($data) && isset($data['topics']) && is_array($data['topics'])) {
            return $data['topics'];
        }

        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

// Sahifa yuklash funksiyasi
function loadPage($page) {
    $allowedPages = ['welcome', 'topics', 'topic-detail', 'code-editor', 'contact', 'settings'];
    $page = in_array($page, $allowedPages) ? $page : 'welcome';
    
    $pageFile = PAGES_PATH . '/' . $page . '.php';
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        include PAGES_PATH . '/welcome.php';
    }
}

// Joriy sahifani olish
$page = isset($_GET['page']) ? $_GET['page'] : 'welcome';
?>