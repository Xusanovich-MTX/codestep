(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else {
        root.CodeStepNotifications = api;
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', api.attach);
        else api.attach();
    }
}(typeof window === 'undefined' ? globalThis : window, function () {
    'use strict';
    var STORAGE_KEY = 'codestep.notifications.v1';
    var MAX_ITEMS = 50;
    var MAX_TOPICS = 5000;

    function cleanText(value, limit) {
        return typeof value === 'string' ? value.slice(0, limit) : '';
    }

    function safeHref(value) {
        return typeof value === 'string' && /^(?:\?page=home|\?page=settings|\?page=topic-detail&id=[1-9]\d{0,8})$/.test(value) ? value : '?page=home';
    }

    function uniqueStrings(values, pattern, limit) {
        if (!Array.isArray(values)) return [];
        return Array.from(new Set(values.filter(function (value) {
            return typeof value === 'string' && pattern.test(value);
        }))).slice(-limit);
    }

    function normalizeState(value) {
        value = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
        var ids = new Set();
        var items = Array.isArray(value.items) ? value.items.filter(function (item) {
            if (!item || typeof item !== 'object' || !['topic', 'daily', 'test'].includes(item.kind)) return false;
            if (typeof item.id !== 'string' || !/^[a-z0-9:_-]{1,100}$/i.test(item.id) || ids.has(item.id)) return false;
            if (typeof item.title !== 'string' || !item.title.trim() || !Number.isFinite(item.createdAt) || item.createdAt < 0) return false;
            ids.add(item.id);
            return true;
        }).map(function (item) {
            return {
                id: item.id, kind: item.kind, title: cleanText(item.title, 220),
                body: cleanText(item.body, 500), href: safeHref(item.href),
                createdAt: Math.min(item.createdAt, 8640000000000000), read: item.read === true
            };
        }).sort(function (a, b) { return b.createdAt - a.createdAt; }).slice(0, MAX_ITEMS) : [];
        return {
            version: 1,
            initialized: value.initialized === true,
            topicIds: uniqueStrings(value.topicIds, /^[1-9]\d{0,8}$/, MAX_TOPICS),
            reminderDays: uniqueStrings(value.reminderDays, /^\d{4}-\d{2}-\d{2}$/, 90),
            items: items
        };
    }

    function normalizeCatalog(value) {
        if (!Array.isArray(value)) return [];
        var seen = new Set();
        return value.filter(function (topic) {
            if (!topic || typeof topic !== 'object' || !/^[1-9]\d{0,8}$/.test(String(topic.id)) || seen.has(String(topic.id))) return false;
            if (typeof topic.title !== 'string' || !topic.title.trim()) return false;
            seen.add(String(topic.id));
            return true;
        }).slice(0, MAX_TOPICS).map(function (topic) {
            var id = String(topic.id);
            return {id: id, title: cleanText(topic.title, 180), href: '?page=topic-detail&id=' + id};
        });
    }

    function pushItem(state, item) {
        state.items = [item].concat(state.items.filter(function (existing) { return existing.id !== item.id; })).slice(0, MAX_ITEMS);
    }

    function observeTopics(input, catalog, enabled, now) {
        var state = normalizeState(input);
        var topics = normalizeCatalog(catalog);
        var known = new Set(state.topicIds);
        topics.forEach(function (topic) {
            if (state.initialized && enabled === true && !known.has(topic.id)) {
                pushItem(state, {
                    id: 'topic:' + topic.id, kind: 'topic', title: 'Yangi mavzu: ' + topic.title,
                    body: 'Mavzuni ochib, unga tegishli masalalarni ko‘rishingiz mumkin.',
                    href: topic.href, createdAt: now, read: false
                });
            }
            known.add(topic.id);
        });
        state.topicIds = Array.from(known).slice(-MAX_TOPICS);
        state.initialized = true;
        return state;
    }

    function localDay(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function addDailyReminder(input, settings, date, visible) {
        var state = normalizeState(input);
        if (!settings || settings.dailyReminder !== true || visible !== true || !(date instanceof Date) || !Number.isFinite(date.getTime())) return state;
        var time = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(settings.reminderTime || '');
        if (!time || date.getHours() * 60 + date.getMinutes() < Number(time[1]) * 60 + Number(time[2])) return state;
        var day = localDay(date);
        if (state.reminderDays.includes(day)) return state;
        pushItem(state, {
            id: 'daily:' + day, kind: 'daily', title: 'Bugungi mashqni unutmang',
            body: 'Dasturlashni mashq qilish vaqti keldi. Bir mavzuni tanlab, masalalarni yechishni davom ettiring.',
            href: '?page=home', createdAt: date.getTime(), read: false
        });
        state.reminderDays = state.reminderDays.concat(day).slice(-90);
        return state;
    }

    function markRead(input, id) {
        var state = normalizeState(input);
        state.items.forEach(function (item) { if (!id || item.id === id) item.read = true; });
        return state;
    }

    function dismiss(input, id) {
        var state = normalizeState(input);
        state.items = id ? state.items.filter(function (item) { return item.id !== id; }) : [];
        return state;
    }

    function attach() {
        if (typeof document === 'undefined') return;
        var host = document.getElementById('siteNotifications');
        if (!host || host.dataset.ready === 'true') return;
        host.dataset.ready = 'true';
        var state = normalizeState(null);
        var catalog = [];
        var catalogAvailable = false;
        var storageAvailable = true;
        var open = false;
        var catalogElement = document.getElementById('notificationCatalog');
        try {
            var parsedCatalog = catalogElement ? JSON.parse(catalogElement.textContent) : null;
            catalogAvailable = Array.isArray(parsedCatalog);
            catalog = normalizeCatalog(parsedCatalog);
        } catch (_) { /* A missing catalog must not establish an empty first-visit baseline. */ }

        function settings() {
            var source = window.CodeStepSettings;
            return source && typeof source.get === 'function' ? source.get() : {topicUpdates: true, dailyReminder: false, reminderTime: '20:00'};
        }

        function readState() {
            if (!storageAvailable) return state;
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                return raw && raw.length <= 200000 ? normalizeState(JSON.parse(raw)) : normalizeState(null);
            } catch (_) {
                return state;
            }
        }

        function status(message) {
            var element = document.getElementById('notificationSettingsStatus');
            if (element) element.textContent = message;
        }

        function save(next) {
            state = normalizeState(next);
            try {
                var serialized = JSON.stringify(state);
                if (localStorage.getItem(STORAGE_KEY) !== serialized) localStorage.setItem(STORAGE_KEY, serialized);
            } catch (_) {
                storageAvailable = false;
                status('Brauzer bildirishnomalar tarixini saqlay olmadi. Hozirgi sahifada ishlashda davom etadi.');
            }
            render();
        }

        function element(tag, className, text) {
            var node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        }

        function button(className, label, action) {
            var node = element('button', className, label);
            node.type = 'button';
            node.addEventListener('click', action);
            return node;
        }

        function bellIcon() {
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('aria-hidden', 'true');
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4');
            svg.appendChild(path);
            return svg;
        }

        var trigger = button('notification-bell', '', function () { setOpen(!open); });
        trigger.id = 'notificationBell';
        trigger.setAttribute('aria-label', 'Bildirishnomalar');
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-controls', 'notificationPopover');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.appendChild(bellIcon());
        var badge = element('span', 'notification-badge');
        badge.setAttribute('aria-hidden', 'true');
        badge.hidden = true;
        trigger.appendChild(badge);
        var panel = element('section', 'notification-popover');
        panel.id = 'notificationPopover';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-labelledby', 'notificationHeading');
        panel.hidden = true;
        var heading = element('div', 'notification-heading');
        var title = element('h2', '', 'Bildirishnomalar');
        title.id = 'notificationHeading';
        title.tabIndex = -1;
        heading.appendChild(title);
        heading.appendChild(button('notification-close', '×', function () { setOpen(false, true); }));
        heading.lastChild.setAttribute('aria-label', 'Bildirishnomalarni yopish');
        panel.appendChild(heading);
        var actions = element('div', 'notification-actions');
        var readAll = button('notification-text-button', 'Barchasini o‘qilgan deb belgilash', function () { save(markRead(readState())); });
        var clearAll = button('notification-text-button', 'Tozalash', function () { save(dismiss(readState())); });
        actions.appendChild(readAll);
        actions.appendChild(clearAll);
        panel.appendChild(actions);
        var list = element('ul', 'notification-list');
        panel.appendChild(list);
        var empty = element('p', 'notification-empty', 'Hozircha bildirishnomalar yo‘q. Yangi mavzu qo‘shilsa yoki yoqilgan eslatma vaqti kelsa, shu yerda ko‘rinadi.');
        panel.appendChild(empty);
        var footer = element('div', 'notification-footer');
        var settingsLink = element('a', '', 'Bildirishnoma sozlamalari');
        settingsLink.href = '?page=settings#notificationSettings';
        footer.appendChild(settingsLink);
        footer.appendChild(element('p', '', 'Eslatmalar sayt ochiq turganda ko‘rsatiladi.'));
        panel.appendChild(footer);
        var live = element('span', 'notification-live');
        live.setAttribute('role', 'status');
        live.setAttribute('aria-live', 'polite');
        host.appendChild(trigger);
        host.appendChild(panel);
        host.appendChild(live);
        var previousUnread = -1;

        function formatDate(time) {
            var date = new Date(time);
            try { return date.toLocaleString('uz-UZ', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}); }
            catch (_) { return localDay(date) + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0'); }
        }

        function render() {
            var unread = state.items.filter(function (item) { return !item.read; }).length;
            badge.textContent = unread > 9 ? '9+' : String(unread);
            badge.hidden = unread === 0;
            trigger.setAttribute('aria-label', 'Bildirishnomalar' + (unread ? ', ' + unread + ' ta o‘qilmagan' : ''));
            if (previousUnread >= 0 && unread > previousUnread) live.textContent = unread + ' ta o‘qilmagan bildirishnoma bor.';
            previousUnread = unread;
            readAll.disabled = unread === 0;
            clearAll.disabled = state.items.length === 0;
            empty.hidden = state.items.length !== 0;
            list.replaceChildren();
            state.items.forEach(function (item) {
                var row = element('li', 'notification-item' + (item.read ? '' : ' is-unread'));
                row.dataset.notificationId = item.id;
                var link = element('a', 'notification-item-link');
                link.href = item.href;
                link.appendChild(element('strong', '', item.title));
                link.appendChild(element('span', 'notification-description', item.body));
                var time = element('time', 'notification-time', formatDate(item.createdAt));
                time.dateTime = new Date(item.createdAt).toISOString();
                link.appendChild(time);
                link.addEventListener('click', function () { save(markRead(readState(), item.id)); });
                row.appendChild(link);
                var itemActions = element('div', 'notification-item-actions');
                if (!item.read) {
                    itemActions.appendChild(button('notification-text-button', 'O‘qilgan', function () {
                        save(markRead(readState(), item.id));
                        focusItem(item.id);
                    }));
                }
                itemActions.appendChild(button('notification-text-button', 'O‘chirish', function () {
                    var previous = state.items.findIndex(function (entry) { return entry.id === item.id; });
                    save(dismiss(readState(), item.id));
                    var next = list.children[Math.min(previous, list.children.length - 1)];
                    if (next) next.querySelector('a').focus();
                    else title.focus();
                }));
                row.appendChild(itemActions);
                list.appendChild(row);
            });
        }

        function focusItem(id) {
            Array.from(list.children).some(function (row) {
                if (row.dataset.notificationId !== id) return false;
                row.querySelector('a').focus();
                return true;
            });
        }

        function setOpen(value, restoreFocus) {
            open = value;
            panel.hidden = !value;
            trigger.setAttribute('aria-expanded', String(value));
            if (value) {
                save(readState());
                title.focus();
            } else if (restoreFocus) trigger.focus();
        }

        function check() {
            var current = readState();
            var prefs = settings();
            if (catalogAvailable) current = observeTopics(current, catalog, prefs.topicUpdates, Date.now());
            current = addDailyReminder(current, prefs, new Date(), document.visibilityState === 'visible');
            if (JSON.stringify(current) !== JSON.stringify(state)) save(current);
        }

        document.addEventListener('pointerdown', function (event) { if (open && !host.contains(event.target)) setOpen(false); });
        document.addEventListener('keydown', function (event) {
            if (open && event.key === 'Escape') {
                event.preventDefault();
                setOpen(false, true);
            }
        });
        document.addEventListener('visibilitychange', check);
        document.addEventListener('codestep:settingschange', check);
        window.addEventListener('storage', function (event) {
            if (event.key === STORAGE_KEY || event.key === null) {
                state = readState();
                render();
            }
        });
        window.addEventListener('pageshow', check);
        var testButton = document.getElementById('testNotification');
        if (testButton) testButton.addEventListener('click', function () {
            var next = readState();
            pushItem(next, {
                id: 'test:' + Date.now().toString(36) + ':' + Math.random().toString(36).slice(2, 8),
                kind: 'test', title: 'Sinov bildirishnomasi',
                body: 'Bildirishnomalar ishlayapti. Yangi xabarlar yuqoridagi qo‘ng‘iroq belgisi orqali ko‘rinadi.',
                href: '?page=settings', createdAt: Date.now(), read: false
            });
            save(next);
            status('Sinov bildirishnomasi qo‘shildi. Uni yuqoridagi qo‘ng‘iroq belgisidan ko‘rishingiz mumkin.' + (storageAvailable ? '' : ' Brauzer uni keyingi tashrif uchun saqlay olmadi.'));
        });
        state = readState();
        render();
        check();
        window.setInterval(function () { if (document.visibilityState === 'visible') check(); }, 15000);
    }

    return {
        attach: attach, normalizeState: normalizeState, normalizeCatalog: normalizeCatalog,
        observeTopics: observeTopics, addDailyReminder: addDailyReminder,
        markRead: markRead, dismiss: dismiss, localDay: localDay, storageKey: STORAGE_KEY
    };
}));
