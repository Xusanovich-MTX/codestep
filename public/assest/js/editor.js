(function () {
    'use strict';

    const workspace = document.getElementById('editorWorkspace');
    if (!workspace || !window.CodeStepRules) return;
    const rules = window.CodeStepRules;
    const byId = id => document.getElementById(id);
    const textarea = byId('codeInput');
    const problemKey = workspace.dataset.problemKey;
    const problemContext = problemKey ? { topic: problemKey.split(':')[0], problem: problemKey.split(':')[1] } : null;
    const problemTests = Number(workspace.dataset.problemTests) || 1;
    const runLabel = problemContext ? 'Bajarish va tekshirish' : 'Kodni bajarish';
    const cloud = JSON.parse(byId('editorStorage')?.textContent || '{}');
    const accountId = Number.isInteger(cloud.owner) && cloud.owner > 0 ? cloud.owner : null;
    const storageKey = 'codestep.editor.v2' + (accountId ? '.user.' + accountId : '')
        + (problemKey ? '.problem.' + problemKey : '') + (cloud.run ? '.run.' + cloud.run.id : '');
    const serverRevisions = { python: 0, cpp: 0 };
    const serverCodes = { python: null, cpp: null };
    let saveBusy = false;
    let cloudReady = !cloud.error;
    let initialCloudMessage = cloud.error || '';
    let hasLocalHistoryDraft = false;
    const maxBytes = 65536;
    const maxInputBytes = 16384;
    let language = workspace.dataset.language === 'python' ? 'python' : 'cpp';
    let revision = 0;
    let timer;
    let syntaxTimer;
    let syntaxEpoch = 0;
    let toolBusy = false;
    let runBusy = false;
    let editor = null;
    let serverDiagnostics = [];
    let serverSummary = '';
    let highlighted = [];
    let storageAvailable = true;
    const docs = {};
    const fallbackUndo = { python: [], cpp: [] };
    const drafts = problemKey
        ? { python: '', cpp: '#include <iostream>\nusing namespace std;\n\nint main() {\n    \n    return 0;\n}\n' }
        : { python: rules.examples.python, cpp: rules.examples.cpp };
    const inputs = { python: '', cpp: '' };
    const runs = { python: null, cpp: null };
    let cppStyle = 'kr';

    for (const lang of ['python', 'cpp']) {
        const saved = cloud.drafts?.[lang];
        if (saved && typeof saved.code === 'string') {
            drafts[lang] = saved.code;
            serverCodes[lang] = saved.code;
            serverRevisions[lang] = saved.revision;
        }
    }

    try {
        const raw = localStorage.getItem(storageKey);
        if (raw) {
            const saved = JSON.parse(raw);
            hasLocalHistoryDraft = Boolean(cloud.run && typeof saved?.drafts?.[cloud.run.language] === 'string');
            for (const lang of ['python', 'cpp']) {
                if (saved && saved.drafts && typeof saved.drafts[lang] === 'string') {
                    const dirty = saved.drafts[lang] !== saved.serverCodes?.[lang];
                    if (!accountId || dirty || !cloudReady) {
                        drafts[lang] = saved.drafts[lang];
                        if (accountId && Number.isInteger(saved.serverRevisions?.[lang])) {
                            serverRevisions[lang] = saved.serverRevisions[lang];
                            serverCodes[lang] = saved.serverCodes?.[lang] ?? null;
                        }
                        if (accountId && dirty && !cloud.error) initialCloudMessage = 'Brauzerdagi qoralama tiklandi. Bazaga saqlash uchun tugmani bosing.';
                    }
                }
                if (saved && saved.inputs && typeof saved.inputs[lang] === 'string') inputs[lang] = saved.inputs[lang];
            }
            cppStyle = saved && saved.cppStyle === 'allman' ? 'allman' : 'kr';
        } else if (!problemKey && !accountId) {
            const savedLanguage = localStorage.getItem('savedLanguage');
            const savedCode = localStorage.getItem('savedCode');
            if (['python', 'cpp'].includes(savedLanguage) && savedCode !== null) drafts[savedLanguage] = savedCode;
        }
    } catch (_) {
        storageAvailable = false;
    }

    if (accountId && problemContext) {
        try {
            const pending = JSON.parse(sessionStorage.getItem('codestep.pending-draft') || 'null');
            if (pending && pending.problemKey === problemKey && Date.now() - pending.createdAt < 600000) {
                for (const lang of ['cpp', 'python']) {
                    if (typeof pending.drafts?.[lang] === 'string') drafts[lang] = pending.drafts[lang];
                }
                sessionStorage.removeItem('codestep.pending-draft');
                initialCloudMessage = 'Kirishdan oldingi kod tiklandi. “Bazaga saqlash”ni bosing.';
            }
        } catch (_) { /* Browser draft still remains available in the guest workspace. */ }
    }
    if (cloud.run && !hasLocalHistoryDraft && typeof cloud.run.code === 'string' && ['cpp', 'python'].includes(cloud.run.language)) {
        // A history link intentionally opens an immutable snapshot; its local draft has a separate key.
        drafts[cloud.run.language] = cloud.run.code;
        initialCloudMessage = 'Tarixdagi kod ochildi. O‘zgartirish yoki qayta tekshirish mumkin.';
    }

    if (problemContext) {
        if (!inputs.python && workspace.dataset.testInput) inputs.python = workspace.dataset.testInput;
        if (!inputs.cpp && workspace.dataset.testInput) inputs.cpp = workspace.dataset.testInput;
    }
    textarea.value = drafts[language];
    byId('stdinInput').value = inputs[language];
    byId('cppStyle').value = cppStyle;

    function getCode() { return editor ? editor.getValue() : textarea.value; }
    function notice(message, error = false) {
        byId('editorNotice').textContent = message;
        byId('editorNotice').dataset.error = String(error);
    }
    function cloudStatus(message, error = false) {
        if (!byId('cloudStatus')) return;
        byId('cloudStatus').textContent = message;
        byId('cloudStatus').dataset.error = String(error);
    }
    function renderOutput() {
        const run = runs[language];
        const output = byId('output');
        const status = byId('outputStatus');
        output.classList.toggle('error', Boolean(run && run.error));
        const rawOutput = run ? run.output || '' : '';
        const hasStdin = Boolean(run && run.stdin && run.stdin.trim());
        const displayOutput = hasStdin
            ? `> ${run.stdin.trim()}\n${rawOutput}`
            : rawOutput;
        const text = !run ? 'Natija shu yerda ko‘rsatiladi.'
            : run.pending ? 'Dastur natijasi kutilmoqda…'
            : displayOutput || 'Dastur chiqish ma’lumotisiz yakunlandi.';
        if (output.textContent !== text) output.textContent = text;
        const diagnostics = byId('executionDiagnostics');
        if (diagnostics) {
            diagnostics.textContent = run && !run.pending ? run.diagnostics || '' : '';
            diagnostics.hidden = diagnostics.textContent === '';
        }
        const stale = run && (run.code !== getCode() || run.stdin !== byId('stdinInput').value);
        status.hidden = !stale;
        status.textContent = stale ? 'Kod yoki kirish ma’lumotlari o‘zgardi. Qayta bajaring.' : '';
        if (problemContext) renderVerdict(run, stale);
    }
    function renderVerdict(run, stale) {
        const state = !run ? 'idle' : run.pending ? 'pending' : stale ? 'stale'
            : run.error ? 'error' : run.verdict ? run.verdict.status : 'error';
        const titles = { idle: 'Masala hali tekshirilmagan', pending: 'Masala tekshirilmoqda…', stale: 'Qayta tekshirish kerak',
            error: 'Yechim tekshirilmadi', accepted: 'Qabul qilindi', wrong_answer: 'Qabul qilinmadi', structure_passed: 'Qisman tekshirildi' };
        const messages = { idle: 'Kodni yozib, “Bajarish va tekshirish” tugmasini bosing.',
            pending: `${problemTests} ta test bajarilmoqda.`, stale: 'Kod o‘zgardi. Oldingi natija hozirgi kod uchun amal qilmaydi.',
            error: 'Kod yoki bajarish xatosini tuzatib, qayta tekshiring.' };
        byId('problemVerdict').dataset.state = state;
        byId('verdictTitle').textContent = titles[state];
        byId('verdictMessage').textContent = messages[state] || run.verdict.message;
        const current = run && !run.pending && !stale && !run.error && run.verdict;
        byId('verdictChecks').hidden = !current;
        byId('verdictChecks').textContent = current ? `O‘tgan testlar: ${run.verdict.checks_passed} / ${run.verdict.total_checks}` : '';
        byId('verdictInput').hidden = !current || !run.testStdin;
        byId('verdictInputValue').textContent = current ? run.testStdin || '' : '';
    }
    function validVerdict(verdict) {
        if (!verdict || typeof verdict !== 'object' || Array.isArray(verdict)
            || String(verdict.topic) !== problemContext.topic || verdict.problem !== problemContext.problem
            || !['accepted', 'wrong_answer', 'structure_passed'].includes(verdict.status)
            || typeof verdict.message !== 'string' || !Number.isInteger(verdict.checks_passed)
            || verdict.total_checks !== problemTests || verdict.checks_passed < 0 || verdict.checks_passed > problemTests) return false;
        if (verdict.status === 'wrong_answer') return verdict.checks_passed < problemTests;
        return verdict.checks_passed === problemTests
            && verdict.scope === (verdict.status === 'accepted' ? 'output' : 'structure_only');
    }
    function persist(explicit = false) {
        drafts[language] = getCode();
        inputs[language] = byId('stdinInput').value;
        try {
            localStorage.setItem(storageKey, JSON.stringify({ drafts, inputs, cppStyle, serverRevisions, serverCodes }));
            storageAvailable = true;
            byId('saveStatus').textContent = 'Brauzerda saqlandi';
            if (explicit) notice('Ikkala til kodi shu brauzerda saqlandi. Fayl sifatida ham yuklab olishingiz mumkin.');
        } catch (_) {
            storageAvailable = false;
            byId('saveStatus').textContent = 'Saqlanmadi — faylni yuklab oling';
            if (explicit) notice('Brauzer xotirasiga saqlab bo‘lmadi. “Yuklab olish” tugmasidan foydalaning.', true);
        }
    }
    function cursorStatus() {
        const before = textarea.value.slice(0, textarea.selectionStart);
        const cursor = editor ? editor.getCursor() : { line: before.split('\n').length - 1, ch: before.length - before.lastIndexOf('\n') - 1 };
        byId('cursorPosition').textContent = `Qator ${cursor.line + 1}, ustun ${cursor.ch + 1}`;
    }
    function mode(lang) { return lang === 'python' ? { name: 'python', version: 3 } : 'text/x-c++src'; }
    function jumpTo(diagnostic) {
        const lines = getCode().split('\n');
        const line = Math.max(0, Math.min(lines.length - 1, Number(diagnostic.line) - 1 || 0));
        const ch = Math.max(0, Math.min(lines[line].length, Number(diagnostic.column) - 1 || 0));
        if (editor) {
            editor.focus();
            editor.setCursor({ line, ch });
            editor.scrollIntoView({ line, ch }, 70);
        } else {
            textarea.focus();
            const offset = lines.slice(0, line).reduce((sum, text) => sum + text.length + 1, 0) + ch;
            textarea.setSelectionRange(offset, offset);
        }
        cursorStatus();
    }
    function clearMarkers() {
        if (!editor) return;
        editor.clearGutter('editor-lint-gutter');
        highlighted.forEach(handle => {
            editor.removeLineClass(handle, 'background', 'editor-lint-line');
            editor.removeLineClass(handle, 'background', 'editor-lint-error');
        });
        highlighted = [];
    }
    function renderDiagnostics(diagnostics) {
        const list = byId('diagnosticsList');
        list.replaceChildren();
        clearMarkers();
        byId('diagnosticsCount').textContent = diagnostics.length;
        if (!diagnostics.length) {
            const empty = document.createElement('li');
            empty.className = 'editor-empty';
            empty.textContent = getCode().trim() ? 'Hozirgi tekshiruvlarda muammo topilmadi.' : 'Tekshirish uchun kod yozing.';
            list.append(empty);
        }
        const severities = { error: 'Xato', warning: 'Ogohlantirish', info: 'Tavsiya' };
        const markedLines = new Set();
        diagnostics.slice(0, 200).forEach(diagnostic => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'editor-diagnostic';
            button.dataset.severity = diagnostic.severity;
            const metadata = document.createElement('small');
            metadata.textContent = `${severities[diagnostic.severity] || 'Tavsiya'} · ${diagnostic.line}:${diagnostic.column} · ${diagnostic.rule}`;
            button.append(metadata, document.createTextNode(diagnostic.message));
            button.addEventListener('click', () => jumpTo(diagnostic));
            item.append(button);
            list.append(item);
            const line = Math.max(0, diagnostic.line - 1);
            if (editor && line < editor.lineCount() && !markedLines.has(line)) {
                markedLines.add(line);
                const marker = document.createElement('span');
                marker.className = `editor-lint-marker ${diagnostic.severity}`;
                marker.title = diagnostic.message;
                editor.setGutterMarker(line, 'editor-lint-gutter', marker);
                highlighted.push(editor.addLineClass(line, 'background', diagnostic.severity === 'error' ? 'editor-lint-error' : 'editor-lint-line'));
            }
        });
        if (diagnostics.length > 200) {
            const remaining = document.createElement('li');
            remaining.className = 'editor-empty';
            remaining.textContent = 'Birinchi 200 ta natija ko‘rsatilmoqda. Ularni tuzatib, qayta tekshiring.';
            list.append(remaining);
        }
    }
    function analyze(force = false) {
        clearTimeout(timer);
        if (!force && !byId('liveCheck').checked) {
            renderDiagnostics([]);
            byId('diagnosticsList').replaceChildren();
            byId('analysisStatus').textContent = 'Yozishda tekshirish o‘chirilgan. “Tekshirish” tugmasini bosing.';
            return;
        }
        if (new Blob([getCode()]).size > maxBytes) {
            renderDiagnostics([]);
            byId('analysisStatus').textContent = 'Tekshirish va formatlash uchun kod hajmi 64 KB dan oshmasin.';
            return;
        }
        const local = rules.analyze(getCode(), language, { cppStyle });
        const seen = new Set();
        const merged = [...serverDiagnostics, ...local].filter(item => {
            const key = `${item.line}:${item.column}:${item.rule}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        }).sort((a, b) => a.line - b.line || ({ error: 0, warning: 1, info: 2 }[a.severity] ?? 2) - ({ error: 0, warning: 1, info: 2 }[b.severity] ?? 2) || a.column - b.column);
        renderDiagnostics(merged);
        byId('analysisStatus').textContent = serverSummary || 'Tezkor uslub tekshiruvi. To‘liqroq natija uchun “Tekshirish”ni bosing.';
    }
    function cancelSyntax() {
        syntaxEpoch += 1;
        clearTimeout(syntaxTimer);
    }
    function scheduleSyntax() {
        cancelSyntax();
        if (language !== 'python' || !byId('liveCheck').checked) return;
        const snapshot = { epoch: syntaxEpoch, revision, code: getCode() };
        if (!snapshot.code.trim() || new Blob([snapshot.code]).size > maxBytes) return;
        syntaxTimer = setTimeout(async () => {
            if (toolBusy) return;
            try {
                const result = await request('api/code-tools.php', { action: 'syntax', language: 'python', code: snapshot.code }, 12000);
                if (snapshot.epoch !== syntaxEpoch || snapshot.revision !== revision || language !== 'python' || !byId('liveCheck').checked) return;
                serverDiagnostics = Array.isArray(result.diagnostics) ? result.diagnostics : [];
                serverSummary = result.message + ' To‘liq uslub tekshiruvi uchun “Tekshirish”ni bosing.';
                analyze();
            } catch (_) {
                if (snapshot.epoch === syntaxEpoch && snapshot.revision === revision && language === 'python') {
                    byId('analysisStatus').textContent = 'Tezkor uslub tekshiruvi ishlayapti. Python sintaksisini tekshirishga ulanib bo‘lmadi; “Tekshirish”ni qayta bosing.';
                }
            }
        }, 700);
    }
    function changed() {
        revision += 1;
        renderOutput();
        serverDiagnostics = [];
        serverSummary = '';
        byId('saveStatus').textContent = 'Saqlanmoqda…';
        if (accountId && problemContext && !saveBusy) cloudStatus('Kod o‘zgardi. Bazaga saqlash uchun tugmani bosing.');
        clearTimeout(timer);
        clearMarkers();
        byId('analysisStatus').textContent = byId('liveCheck').checked ? 'Tekshirilmoqda…' : 'Yozishda tekshirish o‘chirilgan.';
        byId('diagnosticsList').replaceChildren();
        byId('diagnosticsCount').textContent = '—';
        timer = setTimeout(() => { persist(); analyze(); }, 400);
        scheduleSyntax();
        cursorStatus();
    }
    function replaceCode(value) {
        if (getCode() === value) return;
        if (editor) {
            const cursor = editor.getCursor();
            editor.operation(() => {
                editor.replaceRange(value, { line: 0, ch: 0 }, { line: editor.lastLine(), ch: editor.getLine(editor.lastLine()).length }, '+format');
                editor.setCursor(cursor);
            });
        } else {
            fallbackUndo[language].push(textarea.value);
            textarea.value = value;
            changed();
        }
        persist();
    }
    function undo() {
        if (editor) editor.undo();
        else if (fallbackUndo[language].length) {
            textarea.value = fallbackUndo[language].pop();
            changed();
        }
        persist();
        analyze();
    }
    function renderGuide() {
        const query = byId('ruleSearch').value.toLocaleLowerCase().trim();
        const guide = rules.guide(language);
        const matching = guide.filter(rule => `${rule.title} ${rule.body}`.toLocaleLowerCase().includes(query));
        const container = byId('rulesList');
        container.replaceChildren();
        byId('rulesCount').textContent = query ? `${matching.length}/${guide.length}` : guide.length;
        matching.forEach(rule => {
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            summary.textContent = rule.title;
            const kind = document.createElement('span');
            kind.className = 'editor-rule-kind';
            kind.textContent = rule.kind === 'automatic' ? 'Avtomatik yordam mavjud' : 'Qo‘lda ko‘rib chiqish';
            const description = document.createElement('p');
            description.textContent = rule.body;
            details.append(summary, kind, description);
            if (rule.example) {
                const example = document.createElement('pre');
                example.className = 'editor-rule-example';
                const code = document.createElement('code');
                code.textContent = rule.example;
                example.append(code);
                details.append(example);
            }
            if (query) details.open = true;
            container.append(details);
        });
        if (!matching.length) {
            const empty = document.createElement('p');
            empty.className = 'editor-help';
            empty.textContent = 'Bu so‘z bo‘yicha qoida topilmadi.';
            container.append(empty);
        }
    }
    function languageUI() {
        workspace.dataset.language = language;
        const sampleProblemsLink = byId('sampleProblemsLink');
        if (sampleProblemsLink) {
            const samplesUrl = new URL(sampleProblemsLink.href);
            samplesUrl.searchParams.set('lang', language);
            sampleProblemsLink.href = samplesUrl.href;
        }
        workspace.querySelectorAll('.editor-language').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.lang === language)));
        byId('editorFilename').textContent = language === 'python' ? 'main.py' : 'main.cpp';
        byId('cppStyleControl').hidden = language === 'python';
        byId('pythonIndentHelp').hidden = language !== 'python';
        byId('cppNamespaceHelp').hidden = language !== 'cpp';
        byId('editorLimits').textContent = language === 'python' ? '4 bo‘sh joy · kod 79 / izoh 72' : '4 bo‘sh joy · kod 79 · ' + (cppStyle === 'kr' ? 'K&R' : 'Allman');
        if (editor) {
            editor.setOption('rulers', language === 'python' ? [{ column: 72, className: 'editor-comment-ruler' }, { column: 79 }] : [{ column: 79 }]);
            editor.getInputField().setAttribute('aria-label', `${language === 'python' ? 'Python' : 'C++'} dastur kodi`);
        }
        renderGuide();
        analyze();
        cursorStatus();
        scheduleSyntax();
        workspace.dispatchEvent(new CustomEvent('codestep:languagechange', { detail: { language } }));
    }
    function switchLanguage(next) {
        if (!['python', 'cpp'].includes(next) || next === language) return;
        clearTimeout(timer);
        persist();
        clearMarkers();
        language = next;
        revision += 1;
        serverDiagnostics = [];
        serverSummary = '';
        if (editor) editor.swapDoc(docs[language]);
        else textarea.value = drafts[language];
        byId('stdinInput').value = inputs[language];
        renderOutput();
        notice('');
        const url = new URL(window.location.href);
        url.searchParams.set('lang', language);
        history.replaceState(null, '', url);
        languageUI();
    }
    async function request(endpoint, data, timeout = 25000) {
        const controller = new AbortController();
        let timeoutId;
        // Reject the request itself as well as aborting transport. UI recovery
        // must not depend on a fetch/body promise acknowledging the abort.
        const deadline = new Promise((_, reject) => {
            timeoutId = setTimeout(() => {
                reject(new Error('Server belgilangan vaqtda javob bermadi. Qayta bajarib ko‘ring.'));
                controller.abort();
            }, timeout);
        });
        const receive = async () => {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-CSRF-Token': workspace.dataset.csrfToken },
                body: new URLSearchParams(data),
                signal: controller.signal
            });
            let result;
            try { result = await response.json(); }
            catch (_) { throw new Error('Server tushunarli javob qaytarmadi. Sahifani yangilab qayta urinib ko‘ring.'); }
            if (!result || typeof result !== 'object' || Array.isArray(result)) {
                throw new Error('Server tushunarli javob qaytarmadi. Qayta urinib ko‘ring.');
            }
            if (!response.ok || result.error) {
                const error = new Error(result.message || result.output || 'So‘rovni bajarib bo‘lmadi.');
                error.result = result;
                error.status = response.status;
                throw error;
            }
            return result;
        };
        try {
            return await Promise.race([receive(), deadline]);
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('So‘rov vaqti tugadi. Qayta urinib ko‘ring.');
            if (error instanceof TypeError) throw new Error('Serverga ulanib bo‘lmadi. Aloqani tekshiring.');
            throw error;
        } finally { clearTimeout(timeoutId); }
    }
    function validCode() {
        if (!getCode().trim()) { notice('Avval kod yozing.', true); return false; }
        if (new Blob([getCode()]).size > maxBytes) { notice('Kod hajmi 64 KB dan oshmasin.', true); return false; }
        return true;
    }
    async function codeTool(action) {
        if (toolBusy || !validCode()) return;
        cancelSyntax();
        persist();
        analyze(true);
        const snapshot = { code: getCode(), language, revision, cppStyle };
        toolBusy = true;
        byId('checkCode').disabled = true;
        byId('formatCode').disabled = true;
        notice(action === 'format' ? 'Kod formatlanmoqda…' : 'Kod tekshirilmoqda…');
        try {
            const result = await request('api/code-tools.php', { action, code: snapshot.code, language, cpp_style: cppStyle });
            if (snapshot.revision !== revision || snapshot.language !== language || snapshot.cppStyle !== cppStyle) {
                notice('So‘rov davomida kod yoki til o‘zgardi. Joriy kod uchun qayta urinib ko‘ring.');
                return;
            }
            if (action === 'format') {
                if (typeof result.code !== 'string') throw new Error('Formatlash natijasi olinmadi.');
                const modified = result.code !== snapshot.code;
                replaceCode(result.code);
                notice(modified ? 'Kod formatlandi. “Bekor qilish” yoki Ctrl+Z bilan oldingi holatga qaytishingiz mumkin.' : 'Kod allaqachon tanlangan formatda.');
                analyze(true);
            } else {
                serverDiagnostics = Array.isArray(result.diagnostics) ? result.diagnostics : [];
                serverSummary = result.message || `Tekshirildi: ${(result.tools || []).join(', ')}`;
                analyze(true);
                notice(serverSummary);
            }
        } catch (error) {
            if (snapshot.language === language) notice(error.message, true);
        } finally {
            toolBusy = false;
            byId('checkCode').disabled = false;
            byId('formatCode').disabled = false;
            if (snapshot.revision !== revision || snapshot.language !== language) scheduleSyntax();
        }
    }
    function codeExpectsInput(src, lang) {
        if (!src || typeof src !== 'string') return false;
        if (lang === 'python') {
            return /\binput\s*\(/.test(src) || /\bsys\.stdin\b/.test(src);
        }
        return /\bcin\s*>>/.test(src) || /\bscanf\s*\(/.test(src) || /\bgetline\s*\(/.test(src);
    }
    async function runCode() {
        if (runBusy || !validCode()) return;
        const code = getCode();
        const stdin = byId('stdinInput').value;
        if (codeExpectsInput(code, language) && !stdin.trim()) {
            const outElem = byId('output');
            if (outElem) {
                outElem.textContent = '> Dastur kiritishni (input) kutmoqda...\nIltimos, yuqoridagi "Kirish ma’lumotlari (input / stdin)" maydoniga qiymat kiriting va "Bajarish va tekshirish" tugmasini bosing.';
                outElem.classList.remove('error');
            }
            const stdinElem = byId('stdinInput');
            if (stdinElem) {
                stdinElem.focus();
                stdinElem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            if (problemContext) {
                byId('problemVerdict').dataset.state = 'pending';
                byId('verdictTitle').textContent = 'Qiymat kiritilishi kutilmoqda…';
                byId('verdictMessage').textContent = 'Dastur hisoblashi va qabul qilinishi uchun yuqoridagi "Kirish ma’lumotlari" maydoniga qiymat kiriting.';
            }
            return;
        }
        if (new Blob([stdin]).size > maxInputBytes) {
            notice('Kirish ma’lumotlari hajmi 16 KB dan oshmasin.', true);
            return;
        }
        notice('');
        persist();
        const snapshot = { language, code, stdin, pending: true, output: '', error: false };
        runs[language] = snapshot;
        runBusy = true;
        try {
            byId('runCode').disabled = true;
            byId('runCode').textContent = problemContext ? 'Tekshirilmoqda…' : 'Bajarilmoqda…';
            renderOutput();
            const payload = { code: snapshot.code, language: snapshot.language, stdin: snapshot.stdin, ...(problemContext || {}) };
            const result = await request('api/execute-code.php', payload, problemContext && problemTests > 1 ? 100000 : 35000);
            if (typeof result.output !== 'string' || typeof result.error !== 'boolean') {
                throw new Error('Server dastur natijasini qaytarmadi. Qayta bajarib ko‘ring.');
            }
            snapshot.output = result.output;
            snapshot.diagnostics = typeof result.diagnostics === 'string' ? result.diagnostics : '';
            if (result.save_message) cloudStatus(result.save_message, !result.saved_run_id);
            if (problemContext) {
                if (!validVerdict(result.verdict) || typeof result.test_stdin !== 'string') {
                    throw new Error('Masala bo‘yicha tekshiruv natijasi olinmadi. Qayta tekshiring.');
                }
                snapshot.verdict = result.verdict;
                snapshot.testStdin = result.test_stdin;
            }
        } catch (error) {
            snapshot.output = error.message;
            snapshot.error = true;
            if (error.result?.save_message) cloudStatus(error.result.save_message, !error.result.saved_run_id);
        } finally {
            snapshot.pending = false;
            runBusy = false;
            byId('runCode').disabled = false;
            byId('runCode').textContent = runLabel;
            if (snapshot.language === language && runs[language] === snapshot) renderOutput();
        }
    }
    async function saveShortcut() {
        if (!accountId || !problemContext) { persist(true); return; }
        persist();
        if (saveBusy) return;
        if (!cloudReady) { cloudStatus('Bazaga ulanib bo‘lmadi. Kod brauzerda saqlandi; sahifani yangilang.', true); return; }
        if (new Blob([getCode()]).size > maxBytes) { cloudStatus('Kod hajmi 64 KB dan oshmasin.', true); return; }
        const snapshot = { language, code: getCode(), revision: serverRevisions[language] };
        saveBusy = true;
        byId('saveCode').disabled = true;
        cloudStatus('Bazaga saqlanmoqda…');
        try {
            const result = await request('api/solutions.php', { ...problemContext, ...snapshot }, 15000);
            if (!Number.isInteger(result.revision) || result.revision !== snapshot.revision + 1) throw new Error('Saqlash tasdig‘i olinmadi. Sahifani yangilang.');
            serverRevisions[snapshot.language] = result.revision;
            serverCodes[snapshot.language] = snapshot.code;
            cloud.drafts[snapshot.language] = { code: snapshot.code, revision: result.revision };
            persist();
            cloudStatus(snapshot.language === language && getCode() === snapshot.code
                ? 'Qoralama bazaga saqlandi. Yechimlarim sahifasidan ochishingiz mumkin.'
                : 'Yuborilgan nusxa bazaga saqlandi. Keyingi o‘zgarishlar uchun yana saqlang.');
        } catch (error) { cloudStatus(error.message, true); }
        finally { saveBusy = false; byId('saveCode').disabled = false; }
    }
    if (window.CodeMirror) {
        editor = window.CodeMirror.fromTextArea(textarea, {
            mode: mode(language),
            theme: document.body.classList.contains('light-mode') ? 'codestep-light' : 'codestep-dark',
            lineNumbers: window.CodeStepSettings?.get().lineNumbers !== false, indentUnit: 4, tabSize: 4, indentWithTabs: false,
            smartIndent: true, lineWrapping: false, matchBrackets: true,
            autoCloseBrackets: true, styleActiveLine: true, viewportMargin: 20,
            foldGutter: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter', 'editor-lint-gutter'],
            extraKeys: {
                Tab: cm => cm.somethingSelected() ? cm.indentSelection('add') : cm.replaceSelection(' '.repeat(4 - cm.getCursor().ch % 4), 'end', '+input'),
                'Shift-Tab': cm => cm.indentSelection('subtract'),
                'Ctrl-S': saveShortcut, 'Cmd-S': saveShortcut,
                'Ctrl-Enter': runCode, 'Cmd-Enter': runCode,
                'Ctrl-Shift-F': () => codeTool('format'), 'Cmd-Shift-F': () => codeTool('format'),
                Esc: () => byId('checkCode').focus()
            }
        });
        docs[language] = editor.getDoc();
        const other = language === 'python' ? 'cpp' : 'python';
        docs[other] = new window.CodeMirror.Doc(drafts[other], mode(other));
        editor.on('change', changed);
        editor.on('cursorActivity', cursorStatus);
        editor.addOverlay({
            token(stream) {
                if (stream.sol() && stream.eatWhile(/[ \t]/)) return 'leading-space';
                stream.skipToEnd();
                return null;
            }
        });
        window.codeEditor = editor;
    } else {
        textarea.addEventListener('input', changed);
        textarea.addEventListener('click', cursorStatus);
        textarea.addEventListener('keyup', cursorStatus);
        textarea.addEventListener('keydown', event => {
            if (event.key === 'Escape') byId('checkCode').focus();
            if (event.key !== 'Tab') return;
            event.preventDefault();
            const value = textarea.value;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const lineStart = value.lastIndexOf('\n', start - 1) + 1;
            fallbackUndo[language].push(value);
            if (start === end && !event.shiftKey) {
                textarea.setRangeText(' '.repeat(4 - (start - lineStart) % 4), start, end, 'end');
            } else {
                const chunk = value.slice(lineStart, end);
                const replacement = chunk.split('\n').map(line => event.shiftKey ? line.replace(/^( {1,4}|\t)/, '') : '    ' + line).join('\n');
                textarea.setRangeText(replacement, lineStart, end, 'select');
            }
            changed();
        });
        notice('Oddiy matn muharriri ishga tushdi. Tekshirish va formatlash mavjud.');
    }
    workspace.querySelectorAll('.editor-language').forEach(button => button.addEventListener('click', () => switchLanguage(button.dataset.lang)));
    byId('saveCode').addEventListener('click', saveShortcut);
    if (byId('loadSavedCode')) byId('loadSavedCode').addEventListener('click', async () => {
        if (saveBusy) return;
        const snapshot = { language, revision };
        saveBusy = true;
        byId('saveCode').disabled = true;
        byId('loadSavedCode').disabled = true;
        try {
            const result = await request('api/solutions.php', { ...problemContext, language, code: '', revision: 0, action: 'load' }, 15000);
            if (snapshot.language !== language || snapshot.revision !== revision) {
                cloudStatus('Yuklash vaqtida kod yoki til o‘zgardi. Bazadagi kodni ochish uchun qayta bosing.');
                return;
            }
            if (!result.draft) { cloudStatus('Bu tilda bazaga saqlangan qoralama hali yo‘q.'); return; }
            serverRevisions[language] = result.draft.revision;
            serverCodes[language] = result.draft.code;
            cloud.drafts[language] = result.draft;
            cloudReady = true;
            replaceCode(result.draft.code);
            persist();
            cloudStatus('Bazadagi kod ochildi. Oldingi matnni “Bekor qilish” bilan qaytarish mumkin.');
        } catch (error) { cloudStatus(error.message, true); }
        finally { saveBusy = false; byId('saveCode').disabled = false; byId('loadSavedCode').disabled = false; }
    });
    if (byId('signInToSave')) byId('signInToSave').addEventListener('click', event => {
        persist();
        try { sessionStorage.setItem('codestep.pending-draft', JSON.stringify({ problemKey, drafts, createdAt: Date.now() })); }
        catch (_) { /* The original browser draft remains under its guest key. */ }
        const next = `?page=code-editor&topic=${problemContext.topic}&problem=${problemContext.problem}&lang=${language}`;
        event.currentTarget.href = '?page=login&next=' + encodeURIComponent(next);
    });
    if (byId('sampleProblemsLink')) byId('sampleProblemsLink').addEventListener('click', () => persist());
    byId('checkCode').addEventListener('click', () => codeTool('check'));
    byId('formatCode').addEventListener('click', () => codeTool('format'));
    byId('runCode').addEventListener('click', runCode);
    byId('undoEdit').addEventListener('click', undo);
    byId('loadExample').addEventListener('click', () => {
        replaceCode(rules.example(language, { cppStyle }));
        analyze();
        notice('Namuna yuklandi. Oldingi kodni “Bekor qilish” bilan qaytarish mumkin.');
    });
    byId('clearEditor').addEventListener('click', () => {
        replaceCode('');
        runs[language] = null;
        renderOutput();
        analyze();
        notice('Kod tozalandi. “Bekor qilish” bilan qaytarish mumkin.');
    });
    byId('downloadCode').addEventListener('click', () => {
        const blob = new Blob([getCode()], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = language === 'python' ? 'main.py' : 'main.cpp';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    byId('cppStyle').addEventListener('change', () => {
        cppStyle = byId('cppStyle').value;
        revision += 1;
        serverDiagnostics = [];
        serverSummary = '';
        persist();
        languageUI();
    });
    byId('liveCheck').addEventListener('change', () => { analyze(); scheduleSyntax(); });
    byId('showIndent').addEventListener('change', () => {
        workspace.classList.toggle('show-indent', byId('showIndent').checked);
    });
    byId('stdinInput').addEventListener('input', () => { persist(); renderOutput(); });
    byId('stdinInput').addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            runCode();
        }
    });
    workspace.addEventListener('keydown', event => {
        if (event.defaultPrevented || !(event.ctrlKey || event.metaKey)) return;
        if (event.key.toLowerCase() === 's') { event.preventDefault(); saveShortcut(); }
        else if (event.key === 'Enter') { event.preventDefault(); runCode(); }
        else if (event.shiftKey && event.key.toLowerCase() === 'f') { event.preventDefault(); codeTool('format'); }
    });
    window.addEventListener('pagehide', () => persist());
    window.addEventListener('beforeunload', event => {
        persist();
        if (!storageAvailable && getCode().trim()) { event.preventDefault(); event.returnValue = ''; }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) persist(); });
    languageUI();
    if (window.CodeStepCompletion) {
        window.CodeStepCompletion.attach({ editor, workspace, getLanguage: () => language, notice, replaceCode });
    }
    if (window.CodeStepProductivity) {
        window.CodeStepProductivity.attach({
            editor, workspace, textarea, getLanguage: () => language, getCode, replaceCode,
            switchLanguage, notice, maxBytes, persist,
            getOutput: () => runs[language] && !runs[language].pending ? runs[language].output : null,
            clearOutput: () => { runs[language] = null; renderOutput(); notice('Natija oynasi tozalandi.'); }
        });
    }
    if (initialCloudMessage) cloudStatus(initialCloudMessage, Boolean(cloud.error));
    if (!storageAvailable) byId('saveStatus').textContent = 'Brauzer xotirasi mavjud emas — faylni yuklab oling';
}());
