(function () {
    'use strict';
    function toggleTheme(theme) {
        fetch('api/save-theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'theme=' + encodeURIComponent(theme),
            credentials: 'same-origin'
        }).then(function () { location.reload(); }).catch(function () { location.reload(); });
    }
    document.addEventListener('DOMContentLoaded', function () {
        var cb = document.getElementById('themeToggle');
        if (!cb) return;
        cb.addEventListener('change', function () {
            toggleTheme(cb.checked ? 'light' : 'dark');
        });
    });
})();
