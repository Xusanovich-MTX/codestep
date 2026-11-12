(function () {
    'use strict';
    const key = 'codestep.settings.v1';
    const editorKey = 'codestep.editor.view.v1';
    const defaults = { textSize: 16, topicUpdates: true, dailyReminder: false, reminderTime: '20:00', lineNumbers: true };
    const sizes = [14, 16, 18, 20];
    let saved = true;
    function read(storageKey) {
        try { return JSON.parse(localStorage.getItem(storageKey) || '{}') || {}; }
        catch (_) { return {}; }
    }
    function normalize(value) {
        return {
            textSize: sizes.includes(value.textSize) ? value.textSize : defaults.textSize,
            topicUpdates: typeof value.topicUpdates === 'boolean' ? value.topicUpdates : defaults.topicUpdates,
            dailyReminder: typeof value.dailyReminder === 'boolean' ? value.dailyReminder : defaults.dailyReminder,
            reminderTime: typeof value.reminderTime === 'string' && /^([01]\d|2[0-3]):[0-5]\d$/.test(value.reminderTime) ? value.reminderTime : defaults.reminderTime,
            lineNumbers: typeof value.lineNumbers === 'boolean' ? value.lineNumbers : defaults.lineNumbers
        };
    }
    let current = normalize(read(key));
    function write(storageKey, value) {
        try { localStorage.setItem(storageKey, JSON.stringify(value)); return true; }
        catch (_) { return false; }
    }
    function status(message, error = false) {
        const element = document.getElementById('settingsStatus');
        if (element) { element.textContent = message; element.dataset.error = String(error); }
    }
    function editorView() {
        const value = read(editorKey);
        return { fontSize: Number.isInteger(value.fontSize) && value.fontSize >= 12 && value.fontSize <= 24 ? value.fontSize : 14, lineWrapping: value.lineWrapping === true };
    }
    function render() {
        document.documentElement.style.fontSize = current.textSize + 'px';
        const byId = id => document.getElementById(id);
        if (byId('currentFontSize')) {
            byId('currentFontSize').textContent = current.textSize + ' px';
            byId('decreaseFont').disabled = current.textSize === sizes[0];
            byId('increaseFont').disabled = current.textSize === sizes[sizes.length - 1];
            byId('notifTopics').checked = current.topicUpdates;
            byId('notifDaily').checked = current.dailyReminder;
            byId('reminderTime').value = current.reminderTime;
            byId('reminderTime').disabled = !current.dailyReminder;
            byId('lineNumbers').checked = current.lineNumbers;
            byId('editorFontSize').value = String(editorView().fontSize);
        }
        if (window.codeEditor) {
            window.codeEditor.setOption('lineNumbers', current.lineNumbers);
            window.codeEditor.refresh();
        }
    }
    function update(patch) {
        current = normalize(Object.assign({}, current, patch));
        saved = write(key, current);
        render();
        document.dispatchEvent(new CustomEvent('codestep:settingschange', { detail: { ...current } }));
        status(saved ? 'Sozlamalar shu brauzerda saqlandi.' : 'Tanlov qo‘llandi, lekin brauzer uni saqlay olmadi. Sahifa yopilganda yo‘qolishi mumkin.', !saved);
    }
    function changeEditorView(value) {
        const success = write(editorKey, value);
        document.dispatchEvent(new CustomEvent('codestep:editorviewchange', { detail: value }));
        render();
        status(success ? 'Muharrir ko‘rinishi saqlandi.' : 'Brauzer muharrir sozlamasini saqlay olmadi.', !success);
        return success;
    }
    function setTheme(theme) {
        document.cookie = 'theme=' + theme + '; Max-Age=2592000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        document.body.classList.toggle('light-mode', theme === 'light');
        document.body.classList.toggle('dark-mode', theme === 'dark');
        document.querySelectorAll('#themeToggle, #themeToggle2').forEach(input => { input.checked = theme === 'light'; });
        const mobile = document.getElementById('mobileThemeToggle');
        if (mobile) {
            mobile.setAttribute('aria-label', theme === 'light' ? 'Tungi rejimga o‘tish' : 'Kunduzgi rejimga o‘tish');
            mobile.innerHTML = theme === 'light' ? '<span aria-hidden="true">☀</span>' : '<span aria-hidden="true">☾</span>';
        }
        if (window.codeEditor) window.codeEditor.setOption('theme', theme === 'light' ? 'codestep-light' : 'codestep-dark');
    }
    window.CodeStepSettings = { get: () => ({ ...current }), update, editorView };
    document.documentElement.style.fontSize = current.textSize + 'px';
    function attach() {
        const on = (id, event, handler) => document.getElementById(id)?.addEventListener(event, handler);
        on('increaseFont', 'click', () => update({textSize: sizes[Math.min(sizes.length - 1, sizes.indexOf(current.textSize) + 1)]}));
        on('decreaseFont', 'click', () => update({textSize: sizes[Math.max(0, sizes.indexOf(current.textSize) - 1)]}));
        on('notifTopics', 'change', event => update({topicUpdates: event.target.checked}));
        on('notifDaily', 'change', event => update({dailyReminder: event.target.checked}));
        on('lineNumbers', 'change', event => update({lineNumbers: event.target.checked}));
        on('reminderTime', 'change', event => {
            if (!event.target.validity.valid || !/^([01]\d|2[0-3]):[0-5]\d$/.test(event.target.value)) {
                status('Eslatma vaqtini soat va daqiqa ko‘rinishida kiriting.', true);
                event.target.value = current.reminderTime;
                return;
            }
            update({reminderTime: event.target.value});
        });
        on('editorFontSize', 'change', event => changeEditorView({ ...editorView(), fontSize: Number(event.target.value) }));
        on('resetSettings', 'click', () => {
            if (!window.confirm('Matn, muharrir va bildirishnoma sozlamalari tiklansinmi? Yozgan kodlaringiz saqlanib qoladi.')) return;
            update(defaults);
            const siteSaved = saved;
            const editorSaved = changeEditorView({fontSize: 14, lineWrapping: false});
            status(siteSaved && editorSaved ? 'Boshlang‘ich sozlamalar tiklandi. Kodlaringiz saqlanib qoldi.' : 'Sozlamalarni to‘liq saqlab bo‘lmadi. Brauzer xotirasi cheklangan.', !(siteSaved && editorSaved));
        });
        document.querySelectorAll('#themeToggle, #themeToggle2').forEach(input => input.addEventListener('change', () => setTheme(input.checked ? 'light' : 'dark')));
        const nav = window.CodeStepNav;
        if (nav) {
            const list = document.getElementById('menuOrderList');
            if (!list) return;
            const s = nav.getSettings();
            // init toggles
            list.querySelectorAll('[data-menu-toggle]').forEach(cb => {
                const key = cb.getAttribute('data-menu-toggle');
                cb.checked = s.items[key] !== false;
                cb.addEventListener('change', () => nav.updateSettings({ items: { [key]: cb.checked } }));
            });
            // init order from settings
            const order = s.order || ['topics','editor','contact','settings','theme'];
            const itemsByKey = {};
            list.querySelectorAll('li[data-menu-item]').forEach(li => { itemsByKey[li.getAttribute('data-menu-item')] = li; });
            list.innerHTML = '';
            order.forEach(k => { if (itemsByKey[k]) list.appendChild(itemsByKey[k]); });
            // append missing
            Object.keys(itemsByKey).forEach(k => { if (!order.includes(k)) list.appendChild(itemsByKey[k]); });

            // drag and drop
            let dragEl = null;
            list.querySelectorAll('li').forEach(li => {
                li.addEventListener('dragstart', e => { dragEl = li; li.classList.add('dragging'); e.dataTransfer.effectAllowed='move'; });
                li.addEventListener('dragend', () => { li.classList.remove('dragging'); dragEl = null; saveOrder(); });
                li.addEventListener('dragover', e => { e.preventDefault(); const after = getDragAfterElement(list, e.clientY); if (dragEl && after == null) list.appendChild(dragEl); else if (dragEl && after) list.insertBefore(dragEl, after); });
            });
            function getDragAfterElement(container, y) {
                const els = [...container.querySelectorAll('li:not(.dragging)')];
                return els.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height/2;
                    if (offset < 0 && offset > closest.offset) return { offset, element: child };
                    return closest;
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }
            function saveOrder() {
                const newOrder = [...list.querySelectorAll('li')].map(li => li.getAttribute('data-menu-item'));
                nav.updateSettings({ order: newOrder });
            }
        }
        on('mobileThemeToggle', 'click', () => setTheme(document.body.classList.contains('light-mode') ? 'dark' : 'light'));
        setTheme(document.body.classList.contains('light-mode') ? 'light' : 'dark');
        render();
    }
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) {
            current = normalize(read(key));
            render();
            document.dispatchEvent(new CustomEvent('codestep:settingschange', { detail: { ...current } }));
        } else if (event.key === editorKey) render();
    });
    document.addEventListener('codestep:editorviewchange', render);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach);
    else attach();
}());
