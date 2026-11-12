<?php
function getThemeClass() {
    return CURRENT_THEME == 'dark' ? 'dark-mode' : 'light-mode';
}

function getThemeIcon() {
    return CURRENT_THEME == 'dark' ? 'fa-moon' : 'fa-sun';
}

function getAccentColor() {
    return CURRENT_THEME == 'dark' ? ACCENT_GREEN : ACCENT_BLUE;
}
?>