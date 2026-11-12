<?php
// Navigatsiya menyusi uchun yordamchi funksiyalar
function isActivePage($pageName) {
    global $page;
    return $page == $pageName ? 'active' : '';
}
?>