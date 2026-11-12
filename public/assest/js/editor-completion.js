(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.CodeStepCompletion = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const MAX_SOURCE = 131072;
    const MAX_IDENTIFIERS = 300;
    const MAX_HINTS = 60;
    const cppKeywords = 'alignas alignof auto bool break case catch char class const constexpr continue default delete do double else enum explicit extern false float for friend if inline int long namespace new nullptr operator private protected public return short signed sizeof static struct switch template this throw true try typedef typename union unsigned using virtual void volatile while'.split(' ');
    const pythonKeywords = 'False None True and as assert async await break class continue def del elif else except finally for from global if import in is lambda nonlocal not or pass raise return try while with yield'.split(' ');
    const cppLibrary = {
        iostream: 'cin cout cerr clog endl',
        string: 'string getline to_string stoi stol stoll stof stod',
        vector: 'vector',
        algorithm: 'sort reverse min max swap find count lower_bound upper_bound binary_search',
        cmath: 'abs sqrt pow floor ceil round sin cos tan log log10 exp',
        iomanip: 'fixed scientific setprecision setw setfill',
        numeric: 'accumulate gcd lcm',
        utility: 'pair make_pair move',
        map: 'map multimap',
        set: 'set multiset',
        queue: 'queue priority_queue',
        stack: 'stack',
        array: 'array',
        limits: 'numeric_limits'
    };
    const pythonBuiltins = 'abs all any bin bool chr dict enumerate filter float format input int isinstance len list map max min next oct ord pow print range reversed round set sorted str sum tuple type zip open'.split(' ');
    const pythonModules = {
        math: 'sqrt pow floor ceil trunc factorial gcd lcm sin cos tan log log10 exp pi e',
        random: 'randint randrange choice shuffle random',
        sys: 'stdin stdout stderr argv'
    };
    const snippets = {
        cpp: [
            { id: 'cout', title: 'cout — natija chiqarish', header: 'iostream', code: 'std::cout << @@CURSOR@@ << "\\n";' },
            { id: 'cin', title: 'cin — qiymat kiritish', header: 'iostream', code: 'std::cin >> @@CURSOR@@;' },
            { id: 'for', title: 'for — takrorlash', code: 'for (int i = 0; i < @@CURSOR@@; ++i) {\n    \n}' },
            { id: 'if', title: 'if — shart', code: 'if (@@CURSOR@@) {\n    \n}' },
            { id: 'function', title: 'Funksiya', code: 'int functionName() {\n    @@CURSOR@@\n    return 0;\n}' },
            { id: 'vector', title: 'vector — dinamik massiv', header: 'vector', code: 'std::vector<int> values;@@CURSOR@@' }
        ],
        python: [
            { id: 'print', title: 'print — natija chiqarish', code: 'print(@@CURSOR@@)' },
            { id: 'input', title: 'input — qiymat kiritish', code: 'value = input(@@CURSOR@@)' },
            { id: 'for', title: 'for — takrorlash', code: 'for i in range(@@CURSOR@@):\n    pass' },
            { id: 'if', title: 'if — shart', code: 'if @@CURSOR@@:\n    pass' },
            { id: 'def', title: 'def — funksiya', code: 'def function_name():\n    @@CURSOR@@pass' }
        ]
    };

    // Keep literal/comment contents opaque, including C++ raw strings and line splicing.
    function lexicalMask(source, language) {
        source = String(source).slice(0, MAX_SOURCE);
        const chars = source.split('');
        const spans = [];
        let position = 0;
        function hide(end, type, closed) {
            const start = position;
            for (; position < end; position += 1) {
                if (chars[position] !== '\n' && chars[position] !== '\r') chars[position] = ' ';
            }
            spans.push({ start, end, type, closed });
        }
        while (position < source.length) {
            const rest = source.slice(position);
            if ((language === 'python' && source[position] === '#') || (language !== 'python' && rest.startsWith('//'))) {
                let end = source.indexOf('\n', position);
                while (language !== 'python' && end !== -1 && /\\\r?$/.test(source.slice(position, end))) end = source.indexOf('\n', end + 1);
                hide(end < 0 ? source.length : end, 'comment', false);
                continue;
            }
            if (language !== 'python' && rest.startsWith('/*')) {
                const end = source.indexOf('*/', position + 2);
                hide(end < 0 ? source.length : end + 2, 'comment', end >= 0);
                continue;
            }
            const raw = language !== 'python' && /^(?:u8|u|U|L)?R"([^\s()\\]{0,16})\(/.exec(rest);
            if (raw) {
                const close = ')' + raw[1] + '"';
                const end = source.indexOf(close, position + raw[0].length);
                hide(end < 0 ? source.length : end + close.length, 'string', end >= 0);
                continue;
            }
            // C++ numeric separators belong to the number, not a character literal.
            // Do not consume the 8 in the u8 character/string prefix as a number.
            const number = language !== 'python' && !/\w/.test(source[position - 1] || '')
                && /^\d(?:[\w.]|'(?=[0-9a-fA-F]))*/.exec(rest);
            if (number) {
                position += number[0].length;
                continue;
            }
            const quote = source[position];
            if (quote === '"' || quote === "'") {
                const delimiter = language === 'python' && rest.startsWith(quote.repeat(3)) ? quote.repeat(3) : quote;
                let end = position + delimiter.length;
                let closed = false;
                while (end < source.length) {
                    if (source[end] === '\\') { end = Math.min(source.length, end + 2); continue; }
                    if (source.startsWith(delimiter, end)) { end += delimiter.length; closed = true; break; }
                    end += 1;
                }
                hide(end, 'string', closed);
                continue;
            }
            position += 1;
        }
        return { mask: chars.join(''), spans };
    }

    function isOpaque(scan, offset) {
        return scan.spans.some(span => offset > span.start && (offset < span.end || (offset === span.end && !span.closed)));
    }

    function cppStructure(source) {
        const scan = lexicalMask(source, 'cpp');
        const globalChars = scan.mask.split('');
        const includes = [];
        let braceDepth = 0;
        let conditionalDepth = 0;
        let continuedDirective = false;
        let leading = true;
        let firstDirective = null;
        let firstCode = null;
        let offset = 0;
        for (const line of scan.mask.split('\n')) {
            const original = source.slice(offset, offset + line.length);
            const end = Math.min(source.length, offset + line.length + 1);
            const directive = /^\s*#\s*([A-Za-z_]+)/.exec(line);
            if (directive || continuedDirective) {
                if (leading && firstDirective === null) firstDirective = offset;
                if (directive) {
                    const name = directive[1];
                    if (name === 'include' && braceDepth === 0 && conditionalDepth === 0) {
                        const header = /^\s*#\s*include\s*(?:<([^>\r\n]+)>|"([^"\r\n]+)")/.exec(original);
                        if (header) includes.push({ header: header[1] || header[2], start: offset, end, leading });
                    }
                    if (/^(if|ifdef|ifndef)$/.test(name)) conditionalDepth += 1;
                    else if (name === 'endif') conditionalDepth = Math.max(0, conditionalDepth - 1);
                }
                for (let index = offset; index < end; index += 1) if (globalChars[index] !== '\n') globalChars[index] = ' ';
                continuedDirective = /\\\r?$/.test(original);
            } else {
                if (line.trim() && firstCode === null) firstCode = offset;
                if (line.trim()) leading = false;
                for (let index = 0; index < line.length; index += 1) {
                    const character = line[index];
                    if (character === '{') braceDepth += 1;
                    if (braceDepth > 0 || conditionalDepth > 0) globalChars[offset + index] = ' ';
                    if (character === '}') braceDepth = Math.max(0, braceDepth - 1);
                }
            }
            offset = end;
        }
        const globalMask = globalChars.join('');
        const hasNamespace = /\busing\s+namespace\s+(?:::)?std\s*;/.test(globalMask);
        const leadingIncludes = includes.filter(include => include.leading);
        const insertion = leadingIncludes.length ? leadingIncludes[leadingIncludes.length - 1].end
            : firstDirective !== null ? firstDirective : firstCode !== null ? firstCode : source.length;
        return { scan, includes, hasNamespace, insertion };
    }

    function namespaceEdit(source) {
        if (source.length > MAX_SOURCE) return { error: 'Kod hajmi yordamchi uchun juda katta.' };
        const structure = cppStructure(source);
        if (structure.hasNamespace) return { already: true };
        const newline = source.includes('\r\n') ? '\r\n' : '\n';
        if (!source.trim()) return { from: 0, to: source.length, text: '#include <iostream>' + newline + newline + 'using namespace std;' + newline + newline };
        const offset = structure.insertion;
        if (isOpaque(structure.scan, offset)) return { error: 'Avval ochiq izoh yoki satrni yoping, so‘ng yana bosing.' };
        const before = offset > 0 ? (source[offset - 1] === '\n' ? newline : newline + newline) : '';
        const after = offset < source.length && !/^[\r\n]/.test(source.slice(offset)) ? newline : '';
        return { from: offset, to: offset, text: before + 'using namespace std;' + newline + after };
    }

    function includeEdit(source, header) {
        if (!header || !Object.prototype.hasOwnProperty.call(cppLibrary, header)) return null;
        const structure = cppStructure(source);
        if (structure.includes.some(include => include.header === header)) return null;
        const offset = structure.insertion;
        if (isOpaque(structure.scan, offset)) return null;
        const newline = source.includes('\r\n') ? '\r\n' : '\n';
        const before = offset > 0 && source[offset - 1] !== '\n' ? newline : '';
        const after = offset < source.length && !/^[\r\n]/.test(source.slice(offset)) ? newline : '';
        return { from: offset, to: offset, text: before + '#include <' + header + '>' + newline + after };
    }

    function completionData(source, offset, language) {
        if (source.length > MAX_SOURCE || offset < 0 || offset > source.length) return null;
        const scan = lexicalMask(source, language);
        if (isOpaque(scan, offset)) return null;
        const lineStart = source.lastIndexOf('\n', offset - 1) + 1;
        const before = source.slice(lineStart, offset);
        if (language === 'cpp' && /^\s*#/.test(before)) return null;
        const word = /[A-Za-z_][A-Za-z_0-9]*$/.exec(before);
        const prefix = word ? word[0] : '';
        const from = offset - prefix.length;
        const suffix = /^[A-Za-z_0-9]*/.exec(source.slice(offset))[0];
        const qualifierMatch = language === 'cpp' ? /\b([A-Za-z_][A-Za-z_0-9]*)::$/.exec(source.slice(lineStart, from)) : /\b([A-Za-z_][A-Za-z_0-9]*)\.$/.exec(source.slice(lineStart, from));
        const qualifier = qualifierMatch ? qualifierMatch[1] : '';
        if (language === 'cpp' && qualifier && qualifier !== 'std') return null;
        if (language === 'python' && qualifier && !pythonModules[qualifier]) return null;
        if (!qualifier && /(?:\.|->|::)$/.test(source.slice(lineStart, from))) return null;
        const candidates = [];
        const seen = new Set();
        function add(name, text, detail) {
            if (name.startsWith(prefix) && text !== prefix && !seen.has(text)) {
                seen.add(text);
                candidates.push({ text, name, detail });
            }
        }
        if (language === 'cpp') {
            const namespace = qualifier === 'std' || cppStructure(source).hasNamespace;
            for (const [header, names] of Object.entries(cppLibrary)) {
                for (const name of names.split(' ')) add(name, namespace ? name : 'std::' + name, '<' + header + '>');
            }
        } else if (qualifier) {
            for (const name of pythonModules[qualifier].split(' ')) add(name, name, 'import ' + qualifier);
        } else {
            pythonBuiltins.forEach(name => add(name, name, 'Python'));
            for (const [module, names] of Object.entries(pythonModules)) {
                add(module, module, 'import ' + module);
                names.split(' ').forEach(name => add(name, module + '.' + name, 'import ' + module));
            }
        }
        if (!qualifier) {
            (language === 'cpp' ? cppKeywords : pythonKeywords).forEach(name => add(name, name, 'Kalit so‘z'));
            const identifiers = scan.mask.match(/\b[A-Za-z_][A-Za-z_0-9]*\b/g) || [];
            const uniqueIdentifiers = new Set();
            for (const name of identifiers) {
                if (uniqueIdentifiers.size >= MAX_IDENTIFIERS) break;
                if (uniqueIdentifiers.has(name)) continue;
                uniqueIdentifiers.add(name);
                add(name, name, 'Kodingizdagi nom');
            }
        }
        candidates.sort((first, second) => first.name.localeCompare(second.name));
        return { from, to: offset + suffix.length, prefix, qualifier, list: candidates.slice(0, MAX_HINTS) };
    }

    function snippetEdit(source, from, to, language, id, options = {}) {
        const snippet = (snippets[language] || []).find(item => item.id === id);
        if (!snippet || source.length > MAX_SOURCE) return null;
        const scan = lexicalMask(source, language);
        if (isOpaque(scan, from)) return null;
        const start = source.lastIndexOf('\n', from - 1) + 1;
        const end = source.indexOf('\n', to);
        const before = source.slice(start, from);
        const after = source.slice(to, end < 0 ? source.length : end);
        const indent = /^[ \t]*/.exec(before)[0];
        let template = snippet.code;
        if (language === 'cpp' && options.cppStyle === 'allman') template = template.replace(/ \{/g, '\n{');
        if (language === 'cpp' && cppStructure(source).hasNamespace) template = template.replace(/\bstd::/g, '');
        const prefix = before.trim() ? '\n' + indent : '';
        const suffix = after.trim() ? '\n' + indent : '';
        const textWithCursor = prefix + template.replace(/\n/g, '\n' + indent) + suffix;
        const cursor = textWithCursor.indexOf('@@CURSOR@@');
        return { from, to, text: textWithCursor.replace('@@CURSOR@@', ''), cursor: from + cursor, header: snippet.header || null };
    }

    function attach(options) {
        const { editor, workspace, getLanguage } = options;
        const container = workspace && workspace.querySelector('#editorAssist');
        if (!container || container.dataset.completionAttached) return null;
        container.dataset.completionAttached = 'true';
        const document = container.ownerDocument;
        const notice = typeof options.notice === 'function' ? options.notice : function () {};
        function element(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text) node.textContent = text;
            return node;
        }
        function button(id, title) {
            const node = element('button', 'editor-button', title);
            node.id = id;
            node.type = 'button';
            return node;
        }
        const actions = element('div', 'editor-assist-actions');
        const trigger = button('completionTrigger', 'Kod takliflari');
        trigger.title = 'Ctrl+Space';
        trigger.setAttribute('aria-keyshortcuts', 'Control+Space');
        const autoLabel = element('label', 'editor-live-check');
        const auto = element('input');
        auto.type = 'checkbox';
        auto.id = 'completionAuto';
        auto.checked = true;
        try { auto.checked = localStorage.getItem('codestep.editor.completion.auto') !== 'off'; } catch (_) { /* Optional browser preference. */ }
        autoLabel.append(auto, document.createTextNode('Yozishda takliflar'));
        const snippetLabel = element('label', 'editor-assist-snippet-label', 'Tayyor kod ');
        const select = element('select');
        select.id = 'snippetSelect';
        snippetLabel.append(select);
        const insert = button('insertSnippet', 'Kursor joyiga qo‘shish');
        const namespace = button('insertStdNamespace', 'using namespace std; qo‘shish');
        const help = element('p', 'editor-help');
        help.id = 'editorAssistHelp';
        select.setAttribute('aria-describedby', help.id);
        actions.append(trigger, autoLabel, snippetLabel, insert, namespace);
        container.replaceChildren(actions, help);
        if (!editor || typeof editor.showHint !== 'function') {
            [trigger, auto, select, insert, namespace].forEach(control => { control.disabled = true; });
            help.textContent = 'Kod takliflari uchun muharrirni qayta yuklang.';
            return null;
        }
        let timer;
        let destroyed = false;
        function language() { return getLanguage() === 'python' ? 'python' : 'cpp'; }
        function closeHints() { clearTimeout(timer); editor.closeHint(); }
        function updateHelp() {
            const item = snippets[language()].find(snippet => snippet.id === select.value);
            help.textContent = 'Ctrl+Space — takliflar; ↑/↓ — tanlash; Enter yoki Tab — qo‘shish; Esc — yopish.'
                + (item && item.header ? ' Ushbu kod uchun #include <' + item.header + '> kerak bo‘lsa avtomatik qo‘shiladi.' : '')
                + (select.value === 'function' ? ' Funksiyani boshqa funksiyalardan tashqariga joylashtiring.' : '');
        }
        function updateControls() {
            closeHints();
            const previous = select.value;
            select.replaceChildren();
            for (const item of snippets[language()]) {
                const option = element('option', '', item.title);
                option.value = item.id;
                select.append(option);
            }
            if (snippets[language()].some(item => item.id === previous)) select.value = previous;
            namespace.hidden = language() !== 'cpp';
            updateHelp();
        }
        function currentHints() {
            if (editor.somethingSelected() || editor.listSelections().length !== 1) return null;
            const cursor = editor.getCursor();
            const token = editor.getTokenAt(cursor);
            if (token.type && /\b(?:string|comment)\b/.test(token.type)) return null;
            const data = completionData(editor.getValue(), editor.indexFromPos(cursor), language());
            if (!data) return null;
            return {
                from: editor.posFromIndex(data.from), to: editor.posFromIndex(data.to),
                list: data.list.map(item => ({
                    text: item.text,
                    displayText: item.text + ' · ' + item.detail,
                    render(node) {
                        const name = element('span', 'editor-completion-name', item.text);
                        const detail = element('span', 'editor-completion-detail', item.detail);
                        node.append(name, document.createTextNode(' '), detail);
                    }
                }))
            };
        }
        function show(manual) {
            clearTimeout(timer);
            if (destroyed) return;
            const data = currentHints();
            if (!data || !data.list.length) {
                editor.closeHint();
                if (manual) notice('Bu joyda mos kod taklifi yo‘q. So‘zning boshini yozib ko‘ring.');
                return;
            }
            editor.showHint({ hint: currentHints, completeSingle: false, closeOnUnfocus: true });
        }
        function inputRead(cm, change) {
            clearTimeout(timer);
            if (!auto.checked || !change || change.origin !== '+input' || editor.state.completionActive) return;
            const cursor = editor.getCursor();
            const prefix = /[A-Za-z_][A-Za-z_0-9]*$/.exec(editor.getLine(cursor.line).slice(0, cursor.ch));
            if (!prefix || prefix[0].length < 2) return;
            timer = setTimeout(() => { if (editor.hasFocus()) show(false); }, 160);
        }
        function applyEdit(edit, origin) {
            editor.replaceRange(edit.text, editor.posFromIndex(edit.from), editor.posFromIndex(edit.to), origin);
        }
        trigger.addEventListener('click', () => { editor.focus(); show(true); });
        auto.addEventListener('change', () => {
            closeHints();
            try { localStorage.setItem('codestep.editor.completion.auto', auto.checked ? 'on' : 'off'); } catch (_) { /* Optional browser preference. */ }
        });
        select.addEventListener('change', updateHelp);
        insert.addEventListener('click', () => {
            closeHints();
            if (editor.listSelections().length !== 1) { notice('Tayyor kod uchun bitta kursor joyini tanlang.'); return; }
            const edit = snippetEdit(editor.getValue(), editor.indexFromPos(editor.getCursor('from')), editor.indexFromPos(editor.getCursor('to')), language(), select.value, { cppStyle: workspace.querySelector('#cppStyle')?.value });
            if (!edit) { notice('Tayyor kodni izoh yoki matn ichidan tashqariga joylashtiring.'); return; }
            editor.getDoc().changeGeneration(true);
            editor.operation(() => {
                applyEdit(edit, 'codestep-snippet');
                editor.setCursor(editor.posFromIndex(edit.cursor));
                if (language() === 'cpp' && edit.header) {
                    const header = includeEdit(editor.getValue(), edit.header);
                    if (header) applyEdit(header, 'codestep-snippet');
                }
            });
            editor.getDoc().changeGeneration(true);
            editor.focus();
            editor.scrollIntoView(editor.getCursor());
            notice('Tayyor kod kursor joyiga qo‘shildi. Kerakli qiymat va nomlarni yozing.');
        });
        namespace.addEventListener('click', () => {
            closeHints();
            if (language() !== 'cpp') return;
            const edit = namespaceEdit(editor.getValue());
            if (edit.already) { notice('using namespace std; faylda allaqachon mavjud.'); return; }
            if (edit.error) { notice(edit.error); return; }
            editor.getDoc().changeGeneration(true);
            editor.operation(() => applyEdit(edit, 'codestep-namespace'));
            editor.getDoc().changeGeneration(true);
            editor.focus();
            notice('using namespace std; fayl boshiga qo‘shildi.');
        });
        const keyMap = { 'Ctrl-Space': () => show(true), 'Cmd-Space': () => show(true) };
        editor.addKeyMap(keyMap);
        editor.on('inputRead', inputRead);
        editor.on('swapDoc', updateControls);
        workspace.addEventListener('codestep:languagechange', updateControls);
        updateControls();
        return {
            show: () => show(true),
            destroy() {
                destroyed = true;
                closeHints();
                workspace.removeEventListener('codestep:languagechange', updateControls);
                editor.off('inputRead', inputRead);
                editor.off('swapDoc', updateControls);
                editor.removeKeyMap(keyMap);
            }
        };
    }

    return { attach, completionData, namespaceEdit, includeEdit, snippetEdit, lexicalMask };
}));
