(function () {
    'use strict';

    const preferenceKey = 'codestep.editor.view.v1';

    function attach(api) {
        const { editor, workspace, textarea } = api;
        const host = workspace && workspace.querySelector('#editorExtras');
        if (!host || host.dataset.attached === 'true') return;
        host.dataset.attached = 'true';
        workspace.classList.add('editor-productivity-enabled');
        host.classList.add('editor-extras');
        const maxBytes = Math.min(Number(api.maxBytes) || 65536, 65536);
        const feedback = (message, error = false) => api.notice(message, error);
        const focusEditor = () => editor ? editor.focus() : textarea.focus();
        const getCode = () => api.getCode();
        const byteLength = value => new Blob([value]).size;
        let searchTimer;
        let markers = [];
        let matches = [];
        let searchSnapshot = '';
        let focusState = null;
        let importEpoch = 0;
        let preferences = { fontSize: 14, lineWrapping: false };
        try {
            const stored = JSON.parse(localStorage.getItem(preferenceKey) || 'null');
            if (stored && Number.isInteger(stored.fontSize)) preferences.fontSize = Math.max(12, Math.min(24, stored.fontSize));
            if (stored && typeof stored.lineWrapping === 'boolean') preferences.lineWrapping = stored.lineWrapping;
        } catch (_) { /* The controls also work without browser storage. */ }

        function element(tag, className, text) {
            const item = document.createElement(tag);
            if (className) item.className = className;
            if (text) item.textContent = text;
            return item;
        }
        function button(label, action, handler, title) {
            const item = element('button', 'editor-extra-button', label);
            item.type = 'button';
            item.dataset.editorAction = action;
            item.setAttribute('aria-label', title || label);
            item.title = title || label;
            item.addEventListener('click', handler);
            return item;
        }
        function field(labelText, id, tag = 'textarea') {
            const label = element('label', 'editor-extra-field');
            label.htmlFor = id;
            label.append(element('span', '', labelText));
            const input = element(tag);
            input.id = id;
            input.autocomplete = 'off';
            input.spellcheck = false;
            if (tag === 'textarea') {
                input.rows = 1;
                input.maxLength = maxBytes;
            }
            label.append(input);
            return { label, input };
        }

        const toolbar = element('div', 'editor-extra-toolbar');
        toolbar.setAttribute('role', 'group');
        toolbar.setAttribute('aria-label', 'Kod tahrirlash vositalari');
        const searchButton = button('Qidirish', 'find', () => openSearch(false), 'Koddan qidirish (Ctrl+F)');
        const replaceButton = button('Almashtirish', 'replace', () => openSearch(true), 'Matnni almashtirish (Ctrl+H)');
        const gotoButton = button('Qatorga o‘tish', 'goto', openGoto, 'Qatorga o‘tish (Ctrl+G)');
        const redoButton = button('Qaytarish', 'redo', () => {
            editor.redo();
            api.persist();
            focusEditor();
        }, 'Bekor qilingan tahrirni qaytarish');
        const commentButton = button('Izoh', 'comment', toggleComment, 'Tanlangan qatorlarni izohga olish yoki izohdan chiqarish (Ctrl+/)');
        const copyButton = button('Kodni nusxalash', 'copy-code', () => copyText(getCode(), 'Kod'));
        const focusButton = button('Kengaytirish', 'focus', toggleFocus, 'Muharrirni kengaytirish');
        focusButton.setAttribute('aria-pressed', 'false');
        redoButton.disabled = !editor;
        commentButton.disabled = !editor || typeof editor.toggleComment !== 'function';
        if (!editor) {
            redoButton.title = 'Bu amal kengaytirilgan muharrirda mavjud.';
            commentButton.title = redoButton.title;
        }
        toolbar.append(searchButton, replaceButton, gotoButton, redoButton, commentButton, copyButton, focusButton);

        const options = element('details', 'editor-extra-options');
        options.append(element('summary', '', 'Ko‘rinish va fayllar'));
        const optionControls = element('div', 'editor-extra-option-controls');
        const fontControls = element('div', 'editor-extra-font');
        fontControls.setAttribute('role', 'group');
        fontControls.setAttribute('aria-label', 'Kod shriftining o‘lchami');
        const fontDown = button('A−', 'font-smaller', () => setFontSize(preferences.fontSize - 1), 'Shriftni kichraytirish');
        const fontUp = button('A+', 'font-larger', () => setFontSize(preferences.fontSize + 1), 'Shriftni kattalashtirish');
        const fontStatus = element('output', 'editor-extra-font-value');
        fontStatus.setAttribute('aria-live', 'polite');
        fontControls.append(fontDown, fontStatus, fontUp);
        const wrapLabel = element('label', 'editor-extra-check');
        const wrapInput = element('input');
        wrapInput.type = 'checkbox';
        wrapInput.id = 'editorLineWrapping';
        wrapInput.checked = preferences.lineWrapping;
        wrapLabel.append(wrapInput, document.createTextNode('Uzun qatorlarni o‘rash'));
        wrapInput.addEventListener('change', () => {
            preferences.lineWrapping = wrapInput.checked;
            applyView();
            savePreferences();
        });
        const fileInput = element('input');
        fileInput.type = 'file';
        fileInput.accept = '.cpp,.py,.txt';
        fileInput.hidden = true;
        fileInput.id = 'editorImportFile';
        const importButton = button('Fayl ochish', 'import', () => {
            fileInput.value = '';
            fileInput.click();
        }, 'UTF-8 formatidagi .cpp, .py yoki .txt faylni ochish (64 KB gacha)');
        const copyOutput = button('Natijani nusxalash', 'copy-output', () => {
            const output = typeof api.getOutput === 'function' ? api.getOutput() : workspace.querySelector('#output')?.textContent;
            if (output === null || output === undefined || output === ''
                || output === 'Natija shu yerda ko‘rsatiladi.' || output === 'Dastur natijasi kutilmoqda…') {
                feedback('Nusxalash uchun tayyor natija yo‘q.');
                return;
            }
            copyText(output, 'Natija');
        });
        const clearOutput = button('Natijani tozalash', 'clear-output', () => api.clearOutput());
        clearOutput.disabled = typeof api.clearOutput !== 'function';
        const foldButton = button('Bloklarni yig‘ish', 'fold-all', () => { editor.execCommand('foldAll'); focusEditor(); });
        const unfoldButton = button('Bloklarni yoyish', 'unfold-all', () => { editor.execCommand('unfoldAll'); focusEditor(); });
        foldButton.disabled = !editor || typeof window.CodeMirror?.commands.foldAll !== 'function';
        unfoldButton.disabled = !editor || typeof window.CodeMirror?.commands.unfoldAll !== 'function';
        optionControls.append(fontControls, wrapLabel, importButton, copyOutput, clearOutput, foldButton, unfoldButton, fileInput);
        options.append(optionControls);

        const searchPanel = element('section', 'editor-extra-panel');
        searchPanel.id = 'editorSearchPanel';
        searchPanel.hidden = true;
        searchPanel.setAttribute('aria-label', 'Koddan matn qidirish va almashtirish');
        searchButton.setAttribute('aria-controls', searchPanel.id);
        replaceButton.setAttribute('aria-controls', searchPanel.id);
        searchButton.setAttribute('aria-expanded', 'false');
        replaceButton.setAttribute('aria-expanded', 'false');
        const searchField = field('Qidiriladigan matn', 'editorFindText');
        const replaceField = field('Yangi matn', 'editorReplaceText');
        const searchFields = element('div', 'editor-extra-search-fields');
        searchFields.append(searchField.label, replaceField.label);
        const searchControls = element('div', 'editor-extra-search-controls');
        const caseLabel = element('label', 'editor-extra-check');
        const matchCase = element('input');
        matchCase.type = 'checkbox';
        matchCase.id = 'editorMatchCase';
        caseLabel.append(matchCase, document.createTextNode('Katta-kichik harfni farqlash'));
        const previousButton = button('Oldingi', 'find-previous', () => navigate(-1));
        const nextButton = button('Keyingi', 'find-next', () => navigate(1));
        const replaceOneButton = button('Almashtirish', 'replace-one', replaceOne);
        const replaceAllButton = button('Barchasini almashtirish', 'replace-all', replaceAll);
        const searchStatus = element('span', 'editor-extra-status');
        searchStatus.id = 'editorSearchStatus';
        searchStatus.setAttribute('role', 'status');
        searchStatus.setAttribute('aria-live', 'polite');
        const closeSearchButton = button('Yopish', 'close-search', () => closeSearch(true), 'Qidiruvni yopish (Esc)');
        searchControls.append(caseLabel, previousButton, nextButton, replaceOneButton, replaceAllButton, closeSearchButton);
        const searchHelp = element('p', 'editor-extra-help', 'Oddiy matn qidiriladi. Enter: keyingi moslik. Shift+Enter: yangi qator.');
        searchPanel.append(searchFields, searchControls, searchStatus, searchHelp);

        const gotoPanel = element('form', 'editor-extra-panel editor-extra-goto');
        gotoPanel.hidden = true;
        gotoPanel.noValidate = true;
        gotoPanel.id = 'editorGotoPanel';
        gotoPanel.setAttribute('aria-label', 'Qator raqamiga o‘tish');
        gotoButton.setAttribute('aria-controls', gotoPanel.id);
        gotoButton.setAttribute('aria-expanded', 'false');
        const gotoField = field('Qator raqami', 'editorGotoLine', 'input');
        gotoField.input.type = 'number';
        gotoField.input.min = '1';
        gotoField.input.step = '1';
        const gotoStatus = element('span', 'editor-extra-status');
        const goButton = button('O‘tish', 'goto-line', goToLine);
        const closeGotoButton = button('Yopish', 'close-goto', () => closeGoto(true));
        gotoPanel.append(gotoField.label, gotoStatus, goButton, closeGotoButton);
        gotoPanel.addEventListener('submit', event => { event.preventDefault(); goToLine(); });
        host.append(toolbar, options, searchPanel, gotoPanel);

        function clearMarkers() {
            markers.forEach(marker => marker.clear());
            markers = [];
        }
        function selectionOffsets() {
            if (!editor) return { from: textarea.selectionStart, to: textarea.selectionEnd };
            return { from: editor.indexFromPos(editor.getCursor('from')), to: editor.indexFromPos(editor.getCursor('to')) };
        }
        function currentMatch() {
            const selection = selectionOffsets();
            return matches.findIndex(match => match.from === selection.from && match.to === selection.to);
        }
        function updateSearchStatus() {
            const index = currentMatch();
            searchStatus.textContent = !searchField.input.value ? 'Qidirish uchun matn kiriting.'
                : matches.length ? `${index < 0 ? '' : (index + 1) + ' / '}${matches.length} ta moslik`
                    : 'Mos matn topilmadi.';
            previousButton.disabled = nextButton.disabled = replaceOneButton.disabled = replaceAllButton.disabled = matches.length === 0;
        }
        function refreshSearch(force = false) {
            clearTimeout(searchTimer);
            if (searchPanel.hidden) return;
            const code = getCode();
            const query = searchField.input.value.replace(/\r\n?/g, '\n');
            const snapshot = JSON.stringify([code, query, matchCase.checked]);
            if (!force && snapshot === searchSnapshot) { updateSearchStatus(); return; }
            searchSnapshot = snapshot;
            clearMarkers();
            matches = [];
            if (query) {
                const expression = new RegExp(query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), matchCase.checked ? 'gu' : 'giu');
                let found;
                while ((found = expression.exec(code)) !== null) matches.push({ from: found.index, to: found.index + found[0].length });
            }
            if (editor) editor.operation(() => {
                matches.slice(0, 250).forEach(match => {
                    markers.push(editor.markText(editor.posFromIndex(match.from), editor.posFromIndex(match.to), { className: 'editor-search-match' }));
                });
            });
            updateSearchStatus();
        }
        function selectMatch(index, focus = false) {
            const match = matches[index];
            if (!match) return;
            if (editor) {
                editor.setSelection(editor.posFromIndex(match.from), editor.posFromIndex(match.to));
                editor.scrollIntoView({ from: editor.posFromIndex(match.from), to: editor.posFromIndex(match.to) }, 60);
            } else textarea.setSelectionRange(match.from, match.to);
            if (focus) focusEditor();
            updateSearchStatus();
        }
        function nextIndex(direction) {
            const current = currentMatch();
            if (current >= 0) return (current + direction + matches.length) % matches.length;
            const selection = selectionOffsets();
            if (direction > 0) {
                const index = matches.findIndex(match => match.from >= selection.to);
                return index < 0 ? 0 : index;
            }
            for (let index = matches.length - 1; index >= 0; index -= 1) {
                if (matches[index].to <= selection.from) return index;
            }
            return matches.length - 1;
        }
        function navigate(direction) {
            refreshSearch();
            if (matches.length) selectMatch(nextIndex(direction));
        }
        function openSearch(replacing) {
            closeGoto(false);
            searchPanel.hidden = false;
            replaceField.label.hidden = replaceOneButton.hidden = replaceAllButton.hidden = !replacing;
            searchButton.setAttribute('aria-expanded', 'true');
            replaceButton.setAttribute('aria-expanded', String(replacing));
            const selected = editor ? editor.getSelection() : getCode().slice(textarea.selectionStart, textarea.selectionEnd);
            if (selected && selected.length <= maxBytes) searchField.input.value = selected;
            refreshSearch(true);
            searchField.input.focus();
            searchField.input.select();
        }
        function closeSearch(restoreFocus) {
            searchPanel.hidden = true;
            searchButton.setAttribute('aria-expanded', 'false');
            replaceButton.setAttribute('aria-expanded', 'false');
            clearTimeout(searchTimer);
            clearMarkers();
            matches = [];
            searchSnapshot = '';
            if (restoreFocus) focusEditor();
        }
        function replaceText(value) {
            if (editor) editor.getDoc().changeGeneration(true);
            api.replaceCode(value);
            if (editor) editor.getDoc().changeGeneration(true);
        }
        function replaceOne() {
            refreshSearch();
            if (!matches.length) return;
            const index = currentMatch() >= 0 ? currentMatch() : nextIndex(1);
            const match = matches[index];
            const code = getCode();
            const replacement = replaceField.input.value.replace(/\r\n?/g, '\n');
            const value = code.slice(0, match.from) + replacement + code.slice(match.to);
            if (byteLength(value) > maxBytes) { feedback('Almashtirishdan so‘ng kod 64 KB dan oshadi.', true); return; }
            replaceText(value);
            const end = match.from + replacement.length;
            if (editor) editor.setCursor(editor.posFromIndex(end));
            else textarea.setSelectionRange(end, end);
            refreshSearch(true);
            feedback('Bitta moslik almashtirildi. Ctrl+Z bilan qaytarish mumkin.');
        }
        function replaceAll() {
            refreshSearch();
            if (!matches.length) return;
            const code = getCode();
            const replacement = replaceField.input.value.replace(/\r\n?/g, '\n');
            let size = byteLength(code);
            const replacementBytes = byteLength(replacement);
            for (const match of matches) size += replacementBytes - byteLength(code.slice(match.from, match.to));
            if (size > maxBytes) { feedback('Almashtirishdan so‘ng kod 64 KB dan oshadi.', true); return; }
            const count = matches.length;
            const pieces = [];
            let offset = 0;
            matches.forEach(match => {
                pieces.push(code.slice(offset, match.from), replacement);
                offset = match.to;
            });
            pieces.push(code.slice(offset));
            replaceText(pieces.join(''));
            refreshSearch(true);
            feedback(`${count} ta moslik almashtirildi. Ctrl+Z bilan barchasini qaytarish mumkin.`);
        }
        searchField.input.addEventListener('input', () => refreshSearch(true));
        matchCase.addEventListener('change', () => refreshSearch(true));
        searchField.input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                navigate(1);
            }
        });

        function updateGotoLimit() {
            const count = editor ? editor.lineCount() : getCode().split('\n').length;
            gotoField.input.max = String(count);
            gotoStatus.textContent = `1–${count} qator`;
            return count;
        }
        function openGoto() {
            closeSearch(false);
            gotoPanel.hidden = false;
            gotoButton.setAttribute('aria-expanded', 'true');
            updateGotoLimit();
            gotoField.input.value = String(editor ? editor.getCursor().line + 1 : getCode().slice(0, textarea.selectionStart).split('\n').length);
            gotoField.input.focus();
            gotoField.input.select();
        }
        function closeGoto(restoreFocus) {
            gotoPanel.hidden = true;
            gotoButton.setAttribute('aria-expanded', 'false');
            if (restoreFocus) focusEditor();
        }
        function goToLine() {
            const requested = Number(gotoField.input.value);
            if (!Number.isInteger(requested) || !gotoField.input.value.trim()) {
                feedback('Butun qator raqamini kiriting.', true);
                gotoField.input.focus();
                return;
            }
            const line = Math.max(1, Math.min(updateGotoLimit(), requested)) - 1;
            if (editor) {
                editor.setCursor({ line, ch: 0 });
                editor.scrollIntoView({ line, ch: 0 }, 60);
            } else {
                const offset = getCode().split('\n').slice(0, line).reduce((total, text) => total + text.length + 1, 0);
                textarea.setSelectionRange(offset, offset);
            }
            closeGoto(true);
            feedback(`${line + 1}-qatorga o‘tildi.`);
        }
        function toggleComment() {
            if (!editor || typeof editor.toggleComment !== 'function') return;
            editor.getDoc().changeGeneration(true);
            editor.toggleComment({ indent: true });
            editor.getDoc().changeGeneration(true);
            api.persist();
            focusEditor();
        }
        function savePreferences() {
            try { localStorage.setItem(preferenceKey, JSON.stringify(preferences)); }
            catch (_) { feedback('Ko‘rinish o‘zgardi, ammo brauzer sozlamani saqlay olmadi.'); }
            document.dispatchEvent(new CustomEvent('codestep:editorviewchange', { detail: { ...preferences } }));
        }
        function syncPreferences(value) {
            if (!value || typeof value !== 'object') return;
            preferences.fontSize = Number.isInteger(value.fontSize) ? Math.max(12, Math.min(24, value.fontSize)) : 14;
            preferences.lineWrapping = value.lineWrapping === true;
            wrapInput.checked = preferences.lineWrapping;
            applyView();
        }
        document.addEventListener('codestep:editorviewchange', event => syncPreferences(event.detail));
        window.addEventListener('storage', event => {
            if (event.key === preferenceKey || event.key === null) {
                try { syncPreferences(JSON.parse(localStorage.getItem(preferenceKey) || '{}')); } catch (_) { /* Keep current view if storage is unreadable. */ }
            }
        });
        function applyView() {
            workspace.style.setProperty('--ed-font-size', preferences.fontSize + 'px');
            fontStatus.textContent = preferences.fontSize + ' px';
            fontDown.disabled = preferences.fontSize <= 12;
            fontUp.disabled = preferences.fontSize >= 24;
            workspace.classList.toggle('editor-wrap-lines', preferences.lineWrapping);
            if (editor) {
                editor.setOption('lineWrapping', preferences.lineWrapping);
                editor.refresh();
            } else textarea.wrap = preferences.lineWrapping ? 'soft' : 'off';
        }
        function setFontSize(size) {
            preferences.fontSize = Math.max(12, Math.min(24, size));
            applyView();
            savePreferences();
        }
        function toggleFocus() {
            if (focusState) {
                const previous = focusState;
                focusState = null;
                workspace.classList.remove('editor-focus-mode');
                document.body.style.overflow = previous.overflow;
                focusButton.textContent = 'Kengaytirish';
                focusButton.setAttribute('aria-label', 'Muharrirni kengaytirish');
                focusButton.setAttribute('aria-pressed', 'false');
                requestAnimationFrame(() => {
                    workspace.scrollTop = previous.workspaceScroll;
                    if (editor) editor.refresh();
                    if (previous.active && previous.active.isConnected) previous.active.focus({ preventScroll: true });
                    else focusEditor();
                    window.scrollTo(previous.x, previous.y);
                });
            } else {
                focusState = { overflow: document.body.style.overflow, x: window.scrollX, y: window.scrollY,
                    workspaceScroll: workspace.scrollTop, active: document.activeElement };
                document.body.style.overflow = 'hidden';
                workspace.classList.add('editor-focus-mode');
                focusButton.textContent = 'Kichraytirish';
                focusButton.setAttribute('aria-label', 'Oddiy ko‘rinishga qaytish (Esc)');
                focusButton.setAttribute('aria-pressed', 'true');
                if (editor) editor.refresh();
                focusEditor();
            }
        }
        function hintOpen() {
            return Boolean(editor && editor.state.completionActive) || Boolean(document.querySelector('.CodeMirror-hints'));
        }
        function escapePanel() {
            if (hintOpen()) return false;
            if (!searchPanel.hidden) { closeSearch(true); return true; }
            if (!gotoPanel.hidden) { closeGoto(true); return true; }
            if (focusState) { toggleFocus(); return true; }
            return false;
        }

        async function copyText(text, label) {
            if (!text) { feedback('Nusxalash uchun matn yo‘q.'); return; }
            try {
                if (!navigator.clipboard || !window.isSecureContext) throw new Error('Clipboard API is unavailable');
                await navigator.clipboard.writeText(text);
                feedback(label + ' nusxalandi.');
                return;
            } catch (_) { /* The native copy command also works on a local HTTP site. */ }
            const active = document.activeElement;
            const temporary = element('textarea', 'editor-clipboard-helper');
            temporary.value = text;
            temporary.readOnly = true;
            temporary.setAttribute('aria-label', 'Nusxalanadigan matn');
            workspace.append(temporary);
            let copied = false;
            try {
                temporary.focus({ preventScroll: true });
                temporary.select();
                copied = document.execCommand('copy');
            } catch (_) { /* Report failure without pretending the copy succeeded. */ }
            finally {
                temporary.remove();
                if (active && active.isConnected) active.focus({ preventScroll: true });
            }
            feedback(copied ? label + ' nusxalandi.' : 'Nusxa olishga ruxsat berilmadi. Matnni belgilab Ctrl+C bosing yoki kodni “Yuklab olish” bilan saqlang.', !copied);
        }
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;
            const epoch = ++importEpoch;
            const extension = /\.(cpp|py|txt)$/i.exec(file.name)?.[1].toLowerCase();
            if (!extension) { feedback('Faqat .cpp, .py yoki .txt fayllarni ochish mumkin.', true); return; }
            if (file.size > maxBytes) { feedback('Fayl hajmi 64 KB dan oshmasin.', true); return; }
            const importSnapshot = { code: getCode(), language: api.getLanguage() };
            const targetLanguage = extension === 'py' ? 'python' : extension === 'cpp' ? 'cpp' : api.getLanguage();
            try {
                const bytes = await file.arrayBuffer();
                if (epoch !== importEpoch) return;
                if (importSnapshot.code !== getCode() || importSnapshot.language !== api.getLanguage()) {
                    feedback('Fayl o‘qilayotganda kod yoki til o‘zgardi. Faylni qayta tanlang.', true);
                    return;
                }
                let text;
                try { text = new TextDecoder('utf-8', { fatal: true }).decode(bytes).replace(/\r\n?/g, '\n'); }
                catch (_) { throw new Error('Fayl UTF-8 formatida bo‘lishi kerak.'); }
                if (text.includes('\u0000')) throw new Error('Faylda matn bo‘lmagan ma’lumot bor. UTF-8 kod faylini tanlang.');
                if (byteLength(text) > maxBytes) throw new Error('Fayl hajmi 64 KB dan oshmasin.');
                const name = targetLanguage === 'python' ? 'Python' : 'C++';
                if (!window.confirm(`“${file.name}” fayli ${name} qoralamasidagi kodni almashtiradi. Davom etilsinmi?\n\nOldingi kodni Ctrl+Z bilan qaytarish mumkin.`)) return;
                api.switchLanguage(targetLanguage);
                replaceText(text);
                api.persist();
                focusEditor();
                feedback(`“${file.name}” ${name} muharririda ochildi. Oldingi kodni Ctrl+Z bilan qaytarish mumkin.`);
            } catch (error) { feedback(error.message || 'Faylni o‘qib bo‘lmadi.', true); }
            finally { if (epoch === importEpoch) fileInput.value = ''; }
        });

        if (editor) {
            editor.addKeyMap({
                'Ctrl-F': () => openSearch(false), 'Cmd-F': () => openSearch(false),
                'Ctrl-H': () => openSearch(true), 'Cmd-H': () => openSearch(true),
                'Ctrl-G': openGoto, 'Cmd-G': openGoto,
                'Ctrl-/': toggleComment, 'Cmd-/': toggleComment,
                Esc: () => escapePanel() ? undefined : window.CodeMirror.Pass
            });
            editor.on('change', () => {
                updateRedo();
                if (!gotoPanel.hidden) updateGotoLimit();
                if (!searchPanel.hidden) {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => refreshSearch(true), 100);
                }
            });
            editor.on('cursorActivity', () => { if (!searchPanel.hidden) updateSearchStatus(); });
            editor.on('swapDoc', () => {
                clearTimeout(searchTimer);
                clearMarkers();
                matches = [];
                searchSnapshot = '';
                updateRedo();
                updateGotoLimit();
                if (!searchPanel.hidden) refreshSearch(true);
            });
        } else {
            textarea.addEventListener('input', () => {
                if (!searchPanel.hidden) refreshSearch(true);
                if (!gotoPanel.hidden) updateGotoLimit();
            });
        }
        function updateRedo() {
            if (editor) redoButton.disabled = editor.historySize().redo === 0;
        }
        workspace.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !event.defaultPrevented && escapePanel()) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
        workspace.addEventListener('codestep:languagechange', () => {
            updateRedo();
            updateGotoLimit();
            if (!searchPanel.hidden) refreshSearch(true);
        });
        applyView();
        updateRedo();
        return { openSearch, openGoto, closeSearch, focusEditor };
    }

    window.CodeStepProductivity = { attach };
}());
