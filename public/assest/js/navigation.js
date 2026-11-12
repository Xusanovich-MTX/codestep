(function () {
    'use strict';
    const menuToggle = document.getElementById('menuToggle');
    const drawer = document.getElementById('navigationDrawer');
    const compactToggle = document.getElementById('toggleCompactDrawer');
    if (!drawer) return;

    const root = document.documentElement;
    const mobileQuery = window.matchMedia('(max-width: 1023px)');
    const SETTINGS_KEY = 'codestep:nav-settings';
    const LEGACY_KEY = 'codestep:desktop-menu-collapsed';

    const DEFAULTS = {
        mode: 'standard', // 'standard' | 'compact' | 'collapsed'
        position: 'left', // 'left' | 'right'
        hidden: false,
        items: {
            topics: true,
            editor: true,
            contact: true,
            settings: true,
            theme: true
        },
        order: ['topics', 'editor', 'contact', 'settings', 'theme']
    };

    function loadSettings() {
        try {
            const raw = localStorage.getItem(SETTINGS_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                return {
                    mode: ['standard', 'compact', 'collapsed'].includes(parsed.mode) ? parsed.mode : DEFAULTS.mode,
                    position: ['left', 'right'].includes(parsed.position) ? parsed.position : DEFAULTS.position,
                    hidden: false, // removed
                    items: Object.assign({}, DEFAULTS.items, parsed.items || {}),
                    order: Array.isArray(parsed.order) ? parsed.order.filter(k => DEFAULTS.order.includes(k)) : [...DEFAULTS.order]
                };
            }
        } catch (_) {}

        // Fallback to legacy collapsed key if set
        try {
            if (localStorage.getItem(LEGACY_KEY) === '1') {
                return { mode: 'collapsed', position: 'left', hidden: false, items: { ...DEFAULTS.items }, order: [...DEFAULTS.order] };
            }
        } catch (_) {}

        return { ...DEFAULTS, items: { ...DEFAULTS.items }, order: [...DEFAULTS.order], hidden: false };
    }

    let settings = loadSettings();

    function saveSettings(value) {
        try {
            localStorage.setItem(SETTINGS_KEY, JSON.stringify(value));
            localStorage.setItem(LEGACY_KEY, value.mode === 'collapsed' ? '1' : '0');
        } catch (_) {}
    }

    function render() {
        const isMobile = mobileQuery.matches;
        const isCollapsed = !isMobile && settings.mode === 'collapsed';
        const isCompact = !isMobile && settings.mode === 'compact';
        const isRight = !isMobile && settings.position === 'right';

        root.classList.toggle('desktop-menu-collapsed', isCollapsed);
        root.classList.toggle('desktop-menu-compact', isCompact);
        root.classList.toggle('menu-position-right', isRight);
        root.classList.toggle('menu-hidden', settings.hidden === true);

        const open = !settings.hidden && (isMobile || !isCollapsed);
        drawer.classList.toggle('open', open);
        drawer.inert = !open;

        // Apply item visibility
        drawer.querySelectorAll('[data-nav-item]').forEach(el => {
            const itemKey = el.getAttribute('data-nav-item');
            if (itemKey && settings.items && settings.items[itemKey] === false) {
                el.classList.add('nav-item-hidden');
            } else {
                el.classList.remove('nav-item-hidden');
            }
        });

        // Apply order
        const container = drawer.querySelector('.drawer-menu');
        if (container && Array.isArray(settings.order)) {
            const items = Array.from(container.querySelectorAll('[data-nav-item]'));
            const byKey = {};
            items.forEach(el => { byKey[el.getAttribute('data-nav-item')] = el; });
            settings.order.forEach(key => {
                if (byKey[key]) container.appendChild(byKey[key]);
            });
            // append any missing
            items.forEach(el => {
                const k = el.getAttribute('data-nav-item');
                if (!settings.order.includes(k)) container.appendChild(el);
            });
        }

        // Header toggle button
        if (menuToggle) {
            const label = open ? 'Menyuni yig‘ish' : 'Menyuni ochish';
            menuToggle.setAttribute('aria-label', label);
            menuToggle.title = label;
            menuToggle.setAttribute('aria-expanded', String(open));
        }

        // Compact toggle button in drawer footer
        if (compactToggle) {
            const textSpan = compactToggle.querySelector('.drawer-action-text');
            if (isCompact) {
                compactToggle.setAttribute('title', 'Kengaytirilgan rejimga o‘tish (280px)');
                compactToggle.setAttribute('aria-label', 'Kengaytirilgan rejimga o‘tish');
                if (textSpan) textSpan.textContent = 'Kengaytirish';
            } else {
                compactToggle.setAttribute('title', 'Ixcham rejimga o‘tish (76px)');
                compactToggle.setAttribute('aria-label', 'Ixcham rejimga o‘tish');
                if (textSpan) textSpan.textContent = 'Ixcham rejim';
            }
        }

        // CodeMirror resize trigger
        requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
    }

    function update(patch) {
        if (patch.mode && ['standard', 'compact', 'collapsed'].includes(patch.mode)) {
            settings.mode = patch.mode;
        }
        if (patch.position && ['left', 'right'].includes(patch.position)) {
            settings.position = patch.position;
        }
        if (typeof patch.hidden === 'boolean') {
            settings.hidden = patch.hidden;
        }
        if (patch.items) {
            settings.items = Object.assign({}, settings.items, patch.items);
        }
        saveSettings(settings);
        render();
        document.dispatchEvent(new CustomEvent('codestep:navchange', { detail: { ...settings, items: { ...settings.items } } }));
    }

    function reset() {
        settings = JSON.parse(JSON.stringify(DEFAULTS));
        saveSettings(settings);
        render();
        document.dispatchEvent(new CustomEvent('codestep:navchange', { detail: { ...settings, items: { ...settings.items } } }));
    }

    // Public API for settings page and other modules
    window.CodeStepNav = {
        getSettings: () => ({ ...settings, items: { ...settings.items } }),
        updateSettings: update,
        resetSettings: reset
    };

    // Header menu toggle: toggles collapsed state
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            if (mobileQuery.matches) return;
            if (settings.mode === 'collapsed') {
                update({ mode: 'standard' });
            } else {
                update({ mode: 'collapsed' });
            }
        });
    }

    // Quick compact toggle button in drawer
    if (compactToggle) {
        compactToggle.addEventListener('click', () => {
            if (mobileQuery.matches) return;
            if (settings.mode === 'compact') {
                update({ mode: 'standard' });
            } else {
                update({ mode: 'compact' });
            }
        });
    }

    // Escape key collapses drawer on desktop if focused inside
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !mobileQuery.matches && settings.mode !== 'collapsed' && drawer.contains(document.activeElement)) {
            update({ mode: 'collapsed' });
            if (menuToggle) menuToggle.focus();
        }
    });

    mobileQuery.addEventListener('change', () => {
        const focused = document.activeElement;
        render();
        if (mobileQuery.matches && (focused === menuToggle || (focused && focused.closest('.drawer-header, .theme-switch-container')))) {
            drawer.querySelector('a.menu-item[aria-current="page"], a.menu-item')?.focus();
        } else if (!mobileQuery.matches && settings.mode === 'collapsed' && drawer.contains(focused)) {
            if (menuToggle) menuToggle.focus();
        }
    });

    window.addEventListener('storage', event => {
        if (event.key === SETTINGS_KEY || event.key === LEGACY_KEY || event.key === null) {
            settings = loadSettings();
            render();
            document.dispatchEvent(new CustomEvent('codestep:navchange', { detail: { ...settings, items: { ...settings.items } } }));
        }
    });

    // Drag & drop reordering of menu items
    const menuContainer = drawer.querySelector('.drawer-menu');
    let dragEl = null;
    if (menuContainer) {
        menuContainer.addEventListener('dragstart', e => {
            const item = e.target.closest('[data-nav-item]');
            if (!item) return;
            dragEl = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        menuContainer.addEventListener('dragend', e => {
            const item = e.target.closest('[data-nav-item]');
            if (item) item.classList.remove('dragging');
            dragEl = null;
            const newOrder = [...menuContainer.querySelectorAll('[data-nav-item]')].map(el => el.getAttribute('data-nav-item'));
            settings.order = newOrder;
            saveSettings(settings);
            render();
        });
        menuContainer.addEventListener('dragover', e => {
            e.preventDefault();
            if (!dragEl) return;
            const after = [...menuContainer.querySelectorAll('[data-nav-item]:not(.dragging)')].reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = e.clientY - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) return { offset, element: child };
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY }).element;
            if (after == null) menuContainer.appendChild(dragEl);
            else menuContainer.insertBefore(dragEl, after);
        });
    }

    render();
}());
