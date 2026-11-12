(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.CodeStepRules = api;
    }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    // This is an advisory style checker, not a parser or an automatic formatter.
    // Strings and comments stay opaque so examples inside them cannot become code.
    function scan(source, language) {
        var mask = source.split('');
        var tokens = [];
        var spans = [];
        var errors = [];
        var line = 1;
        var column = 1;
        var position = 0;
        var python = language === 'python';

        function consume(end, type, closed) {
            var start = position;
            var startLine = line;
            var startColumn = column;
            for (; position < end; position += 1) {
                if (type === 'string' || type === 'comment') {
                    if (source[position] !== '\n' && source[position] !== '\r') {
                        mask[position] = ' ';
                    }
                }
                if (source[position] === '\n') {
                    line += 1;
                    column = 1;
                } else {
                    column += 1;
                }
            }
            if (type === 'string' || type === 'comment') {
                spans.push({ start: start, end: end, type: type });
            }
            if (type !== 'space' && type !== 'comment') {
                tokens.push({
                    value: type === 'string' ? '<string>' : source.slice(start, end),
                    type: type,
                    start: start,
                    end: end,
                    line: startLine,
                    column: startColumn,
                    endLine: line
                });
            }
            if (closed === false) {
                errors.push({ line: startLine, column: startColumn });
            }
        }

        while (position < source.length) {
            var rest = source.slice(position);
            var character = source[position];
            var match;
            var end;
            if (/\s/.test(character)) {
                consume(position + 1, 'space');
                continue;
            }
            if ((python && character === '#') || (!python && rest.slice(0, 2) === '//')) {
                end = source.indexOf('\n', position);
                // Backslash-newline splicing also applies to C++ line comments.
                while (!python && end !== -1 && /\\\r?$/.test(source.slice(position, end))) {
                    end = source.indexOf('\n', end + 1);
                }
                consume(end === -1 ? source.length : end, 'comment');
                continue;
            }
            if (!python && rest.slice(0, 2) === '/*') {
                end = source.indexOf('*/', position + 2);
                consume(end === -1 ? source.length : end + 2, 'comment', end !== -1);
                continue;
            }
            match = !python && /^(?:u8|u|U|L)?R"([^\s()\\]{0,16})\(/.exec(rest);
            if (match) {
                var rawClose = ')' + match[1] + '"';
                end = source.indexOf(rawClose, position + match[0].length);
                consume(end === -1 ? source.length : end + rawClose.length, 'string', end !== -1);
                continue;
            }
            match = python
                ? /^(?:[rRuUbBfF]{1,2})?("""|'''|"|')/.exec(rest)
                : /^(?:u8|u|U|L)?("|')/.exec(rest);
            if (match) {
                var quote = match[1];
                var triple = quote.length === 3;
                var formatted = python && /f/i.test(match[0].slice(0, -quote.length));
                var replacementDepth = 0;
                var closed = false;
                end = position + match[0].length;
                while (end < source.length) {
                    if (source[end] === '\\') {
                        // Raw Python strings still escape quote delimiters.
                        end += source[end + 1] === '\r' && source[end + 2] === '\n' ? 3 : 2;
                    } else if (formatted && source[end] === '{') {
                        if (replacementDepth === 0 && source[end + 1] === '{') {
                            end += 2;
                        } else {
                            replacementDepth += 1;
                            end += 1;
                        }
                    } else if (formatted && replacementDepth > 0 && source[end] === '}') {
                        replacementDepth -= 1;
                        end += 1;
                    } else if (formatted && replacementDepth > 0 && source[end] === '#') {
                        var commentEnd = source.indexOf('\n', end);
                        end = commentEnd === -1 ? source.length : commentEnd;
                    } else if (formatted && replacementDepth > 0 && /["']/.test(source[end])) {
                        // Python 3.12 permits the outer quote inside a replacement
                        // expression, e.g. f"{mapping["key"]}".
                        var innerQuote = source.slice(end, end + 3) === source[end].repeat(3)
                            ? source[end].repeat(3) : source[end];
                        end += innerQuote.length;
                        while (end < source.length) {
                            if (source[end] === '\\') {
                                end += 2;
                            } else if (source.slice(end, end + innerQuote.length) === innerQuote) {
                                end += innerQuote.length;
                                break;
                            } else {
                                end += 1;
                            }
                        }
                    } else if (replacementDepth === 0 && source.slice(end, end + quote.length) === quote) {
                        end += quote.length;
                        closed = true;
                        break;
                    } else if (!triple && replacementDepth === 0 && (source[end] === '\n' || source[end] === '\r')) {
                        break;
                    } else {
                        end += 1;
                    }
                }
                consume(Math.min(end, source.length), 'string', closed);
                continue;
            }
            // Include non-ASCII names as a single token; naming heuristics below
            // intentionally apply only to the ASCII names they understand.
            match = /^[A-Za-z_\u0080-\uffff][\w\u0080-\uffff]*/.exec(rest);
            if (match) {
                consume(position + match[0].length, 'word');
                continue;
            }
            // C++ digit separators are part of a number, not character literals.
            match = /^\d(?:[\w.]|'(?=[0-9a-fA-F]))*/.exec(rest);
            if (match) {
                consume(position + match[0].length, 'number');
                continue;
            }
            match = /^(?:<=>|>>=|<<=|\*\*=|\/\/=|->\*|\.\.\.|==|!=|<=|>=|\+=|-=|\*=|\/=|%=|@=|&=|\|=|\^=|:=|->|::|&&|\|\||\+\+|--|<<|>>|\*\*|\/\/)/.exec(rest);
            consume(position + (match ? match[0].length : 1), 'symbol');
        }
        return { mask: mask.join(''), tokens: tokens, spans: spans, errors: errors };
    }

    function analyze(code, language, options) {
        var source = String(code == null ? '' : code).replace(/\r\n?/g, '\n');
        var lang = String(language || '').toLowerCase();
        if (lang === 'c++' || lang === 'cxx') {
            lang = 'cpp';
        }
        if (lang !== 'python' && lang !== 'cpp') {
            return [];
        }
        options = options || {};
        var lexed = scan(source, lang);
        var lines = source.split('\n');
        var maskedLines = lexed.mask.split('\n');
        var tokens = lexed.tokens;
        var docstrings = [];
        var findings = [];
        var keys = Object.create(null);
        var python = lang === 'python';
        var offset = 0;
        var continuation = 0;
        var previousContinuation = false;
        var byLine = Object.create(null);

        function add(line, column, rule, message, severity) {
            var key = line + ':' + rule;
            if (!keys[key]) {
                keys[key] = true;
                findings.push({ line: line, column: column, severity: severity || 'warning', rule: rule, message: message });
            }
        }

        function insideString(start, end) {
            return lexed.spans.some(function (span) {
                return span.type === 'string' && start < span.end && end > span.start;
            });
        }

        function markDocstring(token) {
            if (token && token.type === 'string' && /^(?:[rRuU])?["']/.test(source.slice(token.start, token.end))) {
                docstrings.push(token);
            }
        }

        if (python) {
            markDocstring(tokens[0]);
            tokens.forEach(function (token, index) {
                if (token.value !== 'def' && token.value !== 'class') { return; }
                var depth = 0;
                for (var cursor = index + 1; cursor < tokens.length; cursor += 1) {
                    var value = tokens[cursor].value;
                    if (/^[([{]$/.test(value)) { depth += 1; }
                    if (/^[)\]}]$/.test(value)) { depth -= 1; }
                    if (value === ':' && depth === 0) {
                        markDocstring(tokens[cursor + 1]);
                        break;
                    }
                }
            });
        }

        tokens.forEach(function (token) {
            (byLine[token.line] || (byLine[token.line] = [])).push(token);
        });
        lines.forEach(function (text, index) {
            var lineNumber = index + 1;
            var masked = maskedLines[index];
            var active = masked.trim().length > 0;
            var prefix = /^[ \t]*/.exec(text)[0];
            var trailing = /[ \t]+$/.exec(text);
            if (trailing && !insideString(offset + trailing.index, offset + text.length)) {
                add(lineNumber, trailing.index + 1, 'trailing-whitespace', "Qator oxiridagi ortiqcha bo'sh joylarni olib tashlang.");
            }
            if (active && prefix.indexOf('\t') !== -1 && !insideString(offset, offset + prefix.length)) {
                add(lineNumber, prefix.indexOf('\t') + 1, 'indent-tabs', "Indentatsiyada tab o'rniga bo'sh joy ishlating.");
            }
            if (python) {
                var commentLine = /^\s*#/.test(text) && !insideString(offset + prefix.length, offset + prefix.length + 1);
                var docstringLine = docstrings.some(function (token) { return token.line <= lineNumber && token.endLine >= lineNumber; });
                var limit = commentLine || docstringLine ? 72 : 79;
                var length = Array.from(text).length;
                if (length > limit) {
                    add(lineNumber, limit + 1, commentLine ? 'python-comment-length' : (docstringLine ? 'python-docstring-length' : 'python-line-length'),
                        "Qator " + length + " belgidan iborat; " + (commentLine || docstringLine ? "izoh va docstring uchun 72" : "Python kodi uchun 79") + " belgilik chegaraga rioya qiling.");
                }
                if (active && continuation === 0 && !previousContinuation && prefix.indexOf('\t') === -1 && prefix.length % 4 !== 0) {
                    add(lineNumber, 1, 'python-indent', "Blok indentatsiyasini 4 ta bo'sh joy qadamida yozing.");
                }
                (byLine[lineNumber] || []).forEach(function (token) {
                    if (/^[([{]$/.test(token.value)) { continuation += 1; }
                    if (/^[)\]}]$/.test(token.value)) { continuation = Math.max(0, continuation - 1); }
                });
                previousContinuation = /\\\s*$/.test(masked);
            }
            offset += text.length + 1;
        });

        lexed.errors.forEach(function (error) {
            add(error.line, error.column, 'unclosed-literal', "Matn yoki izohning yopilish belgisini tekshiring.", 'error');
        });

        var parentheses = 0;
        tokens.forEach(function (token, index) {
            var value = token.value;
            var prev = tokens[index - 1];
            var next = tokens[index + 1];
            var samePrevious = prev && prev.endLine === token.line;
            var sameNext = next && token.endLine === next.line;
            var before = prev ? source.slice(prev.end, token.start) : '';
            var after = next ? source.slice(token.end, next.start) : '';
            if (value === '(') { parentheses += 1; }
            if (value === ')') { parentheses = Math.max(0, parentheses - 1); }
            if (value === ',' && sameNext && !/^[)\]}]$/.test(next.value) && after.length === 0) {
                add(token.line, token.column, 'comma-spacing', "Verguldan keyin bitta bo'sh joy qo'ying.");
            }
            // '=' in Python argument/default lists deliberately has its own style.
            var binary = /^(==|!=|<=|>=|\+=|-=|\*=|\/=|%=|&=|\|=|\^=|<<=|>>=|:=)$/.test(value) ||
                (python && /^(<|>|\*\*=|\/\/=|@=)$/.test(value)) ||
                (value === '=' && (!python || parentheses === 0));
            if (binary && samePrevious && sameNext && !(lang === 'cpp' && prev.value === 'operator') && (before.length === 0 || after.length === 0)) {
                add(token.line, token.column, 'operator-spacing', "Bu operatorning ikki tomonida bo'sh joy qoldiring.");
            }
            if (python) {
                if (/^[([{]$/.test(value) && sameNext && /^[ \t]+$/.test(after)) {
                    add(token.line, token.column + 1, 'python-bracket-spacing', "Ochuvchi qavsdan keyin ortiqcha bo'sh joy qoldirmang.");
                }
                if (/^[)\]}]$/.test(value) && samePrevious && /^[ \t]+$/.test(before)) {
                    add(token.line, token.column, 'python-bracket-spacing', "Yopuvchi qavsdan oldin ortiqcha bo'sh joy qoldirmang.");
                }
                if ((value === '==' || value === '!=') && ((prev && prev.value === 'None') || (next && next.value === 'None'))) {
                    add(token.line, token.column, 'python-none-comparison', "None bilan taqqoslashda 'is None' yoki 'is not None' ishlating.");
                }
                if ((value === '==' || value === '!=') && ((prev && /^(True|False)$/.test(prev.value)) || (next && /^(True|False)$/.test(next.value)))) {
                    add(token.line, token.column, 'python-bool-comparison', "Mantiqiy qiymatni bevosita yoki 'not' bilan tekshirish mosligini ko'rib chiqing.", 'info');
                }
            } else {
                if (value === 'NULL') {
                    add(token.line, token.column, 'cpp-nullptr', "Null ko'rsatkich uchun NULL o'rniga nullptr ishlating.");
                }
                if ((value === 'new' || value === 'delete') && !(prev && (prev.value === 'operator' || prev.value === '='))) {
                    add(token.line, token.column, 'cpp-manual-memory', "Qo'lda xotira boshqaruvi o'rniga RAII, standart konteyner yoki smart pointer mosligini tekshiring.", 'info');
                }
                // '= new' is allocation, whereas '= delete' declares a deleted member.
                if (value === 'new' && prev && prev.value === '=') {
                    add(token.line, token.column, 'cpp-manual-memory', "Qo'lda xotira boshqaruvi o'rniga RAII, standart konteyner yoki smart pointer mosligini tekshiring.", 'info');
                }
                if (value === '{') {
                    var declaration = maskedLines[token.line - 1].slice(0, token.column - 1).trim();
                    // The last code token survives intervening blank/comment lines;
                    // indentation before the brace is not a declaration itself.
                    var previousDeclaration = prev ? maskedLines[prev.line - 1] : '';
                    var block = prev && (prev.value === ')' || /^(else|try|do)$/.test(prev.value) ||
                        /\b(class|struct|namespace|enum)\s+[\w:]+[^;{}]*$/.test(declaration || previousDeclaration));
                    if (block && options.cppStyle === 'allman' && samePrevious) {
                        add(token.line, token.column, 'cpp-brace-style', "Tanlangan Allman uslubida ochuvchi figurali qavsni yangi qatorga o'tkazing.");
                    } else if (block && options.cppStyle !== 'allman' && !samePrevious) {
                        add(token.line, token.column, 'cpp-brace-style', "Tanlangan K&R uslubida ochuvchi figurali qavsni oldingi ifoda qatoriga qo'ying.");
                    }
                }
            }
        });

        maskedLines.forEach(function (text, index) {
            var match;
            var lineNumber = index + 1;
            if (python) {
                match = /^\s*(?:async\s+)?def\s+([A-Za-z_]\w*)\s*\(/.exec(text);
                if (match && !/^_*[a-z][a-z0-9_]*$/.test(match[1])) {
                    add(lineNumber, text.indexOf(match[1]) + 1, 'python-function-name', "Funksiya nomini kichik harflarda snake_case uslubida yozing.");
                }
                var definition = /^(?:async\s+)?(?:def|class)\s+/.test(text);
                if (definition) {
                    // Decorators belong to the definition, as do attached comments.
                    var start = index;
                    while (start > 0 && /^\s*(?:@|#)/.test(lines[start - 1])) { start -= 1; }
                    var cursor = start - 1;
                    var empty = 0;
                    while (cursor >= 0 && lines[cursor].trim() === '') { empty += 1; cursor -= 1; }
                    var priorCode = cursor >= 0 && maskedLines.slice(0, cursor + 1).some(function (prior) { return prior.trim(); });
                    if (priorCode && empty < 2) {
                        add(lineNumber, 1, 'python-definition-spacing', "Yuqori darajadagi funksiya va klass oldidan ikkita bo'sh qator qoldiring.");
                    }
                }
                match = /^\s*class\s+([A-Za-z_]\w*)(?![\w\u0080-\uffff])/.exec(text);
                if (match && !/^_*[A-Z][A-Za-z0-9]*$/.test(match[1])) {
                    add(lineNumber, text.indexOf(match[1]) + 1, 'python-class-name', "Klass nomini PascalCase uslubida yozing.");
                }
                match = /^\s*([A-Za-z_]\w*)\s*(?::[^=]+)?=(?!=)/.exec(text);
                if (match && !/^_*[a-z][a-z0-9_]*$/.test(match[1]) && !/^_*[A-Z][A-Z0-9_]*$/.test(match[1]) && match[1] !== '_') {
                    add(lineNumber, text.indexOf(match[1]) + 1, 'python-variable-name', "O'zgaruvchilar uchun snake_case, konstantalar uchun UPPER_CASE nomlashni qo'llang.");
                }
                if (/^\s*import\s+[^#]+,/.test(text)) {
                    add(lineNumber, text.indexOf('import') + 1, 'python-import-lines', "Alohida modullarni alohida import qatorlarida yozing.");
                }
                if (/^\s*except\s*:/.test(text)) {
                    add(lineNumber, text.indexOf('except') + 1, 'python-bare-except', "Imkon qadar aniq istisno turini except blokida ko'rsating; barcha xatolarni yashirmang.", 'info');
                }
            } else {
                match = /^\s*(?:class|struct)\s+(?:\[\[.*?\]\]\s*)?([A-Za-z_]\w*)(?![\w\u0080-\uffff])/.exec(text);
                if (match && !/^[A-Z][A-Za-z0-9]*$/.test(match[1])) {
                    add(lineNumber, text.indexOf(match[1]) + 1, 'cpp-class-name', "Klass va struct nomlarini PascalCase uslubida yozing.");
                }
                match = /^\s*(?:inline\s+)?namespace\s+([A-Za-z_]\w*(?:::\w+)*)(?![\w\u0080-\uffff:])/.exec(text);
                if (match && !/^[a-z][a-z0-9_]*(?:::[a-z][a-z0-9_]*)*$/.test(match[1])) {
                    add(lineNumber, text.indexOf(match[1]) + 1, 'cpp-namespace-name', "Namespace nomlarini kichik harflarda yozing.");
                }
            }
        });
        return findings.sort(function (left, right) {
            return left.line - right.line || left.column - right.column || left.rule.localeCompare(right.rule);
        });
    }

    var sharedGuide = [
        { title: 'DRY, KISS, YAGNI va SOLID', body: "Takroriy kodni birlashtiring (DRY), yechimni sodda tuting (KISS), hozir kerak bo'lmagan imkoniyatlarni qo'shmang (YAGNI). Obyektga yo'naltirilgan dizaynda SOLID tamoyillarini vazifaga mos qo'llang.", kind: 'review' },
        { title: 'Mazmunli nomlar', body: "Vazifani tushuntiradigan nom tanlang, tushunarsiz qisqartmalardan qoching. Mantiqiy qiymatlar uchun is, has, can kabi prefikslar foydali. Nomning ma'nosini inson tekshiradi.", kind: 'review' },
        { title: 'Foydali izohlar', body: "Kod imkon qadar o'zini tushuntirsin. Izoh murakkab qarorning nima uchun qabul qilinganini aytsin; eskirgan izohni yangilang yoki o'chiring.", kind: 'review' },
        { title: 'Git va commit xabarlari', body: "Versiya nazoratidan foydalaning. Commit xabarlarida yangi imkoniyat uchun feat:, tuzatish uchun fix:, hujjat uchun docs:, qayta tuzish uchun refactor:, test uchun test: prefikslarini ishlating.", kind: 'review' },
        { title: "1. Bir xil uslub", body: "Butun loyihada bir xil format, nomlash va sozlamalarni saqlang.", kind: 'review' },
        { title: "2. Mazmunli nomlar", body: "O'zgaruvchi, funksiya va klass nomi uning vazifasini ifodalasin.", kind: 'review' },
        { title: "3. To'g'ri indentatsiya", body: "Blok chegaralari ko'rinib tursin. Tab va bo'sh joyni aralashtirmang; Python bloklarida 4 bo'sh joy ishlating.", kind: 'automatic' },
        { title: "4. Me'yoridagi izohlar", body: "Foydali izoh qoldiring, tushunarli kodni keraksiz izoh bilan takrorlamang.", kind: 'review' },
        { title: "5. Kichik funksiyalar", body: "Har bir funksiya bitta aniq vazifani bajarsin; uzunlikdan ko'ra vazifa va tushunarlilikni baholang.", kind: 'review' },
        { title: "6. Xatolarni boshqarish", body: "Kutiladigan xatolarni aniq boshqaring, xatoni jim yutib yubormang va foydali xabar bering.", kind: 'review' },
        { title: "7. Takrorlanishni kamaytirish", body: "Bir xil mantiq takrorlansa, ma'noli umumiy funksiya yoki komponentga ajratishni ko'rib chiqing.", kind: 'review' },
        { title: "8. Test yozish", body: "Oddiy holatlar bilan birga bo'sh kirish, chegara qiymatlar va xatoli kirishlarni sinang.", kind: 'review' },
        { title: "9. Linter va formatter", body: "Tilga mos linter va formatter ishlating. Oynadagi maslahatlar to'liq sintaktik, tur yoki loyiha tahlilining o'rnini bosa olmaydi.", kind: 'review' },
        { title: "10. Versiya nazorati", body: "O'zgarishlarni Git orqali kuzating va mazmunli, kichik commitlar yarating.", kind: 'review' }
    ];

    var pythonGuide = [
        { title: 'Indentatsiya', body: "Blokdan tashqaridagi kodni qator boshidan, oldiga bo'sh joy qo'ymasdan yozing. if, for, while, def, class kabi : bilan tugagan bloklarning ichidagi kodni surish kerak; har daraja uchun 4 bo'sh joy tavsiya etiladi. Bir blokdagi qatorlar bir xil tekislansin, ichma-ich blok yana 4 bo'sh joy suriladi. Noto'g'ri surilish IndentationError beradi; tab va bo'sh joyni aralashtirish TabError berishi mumkin.", kind: 'automatic', example: 'print("Boshlanish")\nif True:\n    print("Blok ichida")\nprint("Blok tugadi")\n' },
        { title: "Bo'sh joy qayerda mumkin?", body: "Bo'sh joylarning hammasi xato emas: total = 1 + 2 to'g'ri yozilgan kod. Bo'sh qatorlar ham mumkin. Qavs ichida davom ettirilgan qatorlarni qavsga tekislash mumkin; ular yangi blok hisoblanmaydi. Qo'shtirnoq ichidagi bo'sh joy va ko'p qatorli matndagi surilish matnning bir qismidir. Barcha bo'sh joylarni avtomatik olib tashlamang; kod mazmuni o'zgarishi mumkin.", kind: 'review' },
        { title: 'Qator uzunligi', body: "Kod qatorlari 79, alohida izoh qatorlari 72 belgidan oshmasin. Docstring matnini ham imkon qadar 72 belgida o'rang; mazmunli satrlarni avtomatik kesmang.", kind: 'automatic' },
        { title: "Bo'sh qatorlar", body: "Yuqori darajadagi funksiya va klasslar orasida 2 bo'sh qator qoldiring. Klass metodlari orasida 1, mantiqiy bo'limlar orasida kerak bo'lsa 1 bo'sh qator qoldiring.", kind: 'review' },
        { title: 'Nomlash', body: "Funksiya va o'zgaruvchilar snake_case, klasslar PascalCase, konstantalar UPPER_CASE bo'lsin. Muharrir aniq ajrata oladigan e'lonlarni tekshiradi.", kind: 'automatic' },
        { title: 'Modul, paket va ichki nomlar', body: "Modul va paketlar uchun qisqa, kichik harfli nom tanlang; modul nomida kerak bo'lsa pastki chiziq ishlating. Ichki nomlar oldiga _ qo'ying. Fayl va paket nomlarini loyiha darajasida tekshiring.", kind: 'review' },
        { title: 'Importlar', body: "Importlarni odatda fayl boshida: standart kutubxonalar, uchinchi tomon kutubxonalari, mahalliy modullar tartibida 3 guruhga ajrating; guruhlar orasida bo'sh qator qoldiring. Alohida modullar alohida import qatorida bo'lsin.", kind: 'review' },
        { title: 'Izoh va docstring', body: "Izoh # bilan boshlanadi; ko'p qatorli izohning har qatorida # ishlating. Uch qo'shtirnoqli matn izoh emas, satr literalidir. Modul, funksiya yoki klass tanasining birinchi ifodasi bo'lsa, docstring hisoblanadi va vazifani qisqa tushuntiradi.", kind: 'review' },
        { title: 'Funksiya va standart parametrlar', body: "Parametrlarni qavs ichida vergul bilan ajrating. Tur ko'rsatilmagan standart parametr va nomli argumentda = atrofiga bo'sh joy qo'ymang: size=10. Tur bilan: size: int = 10. Qaytarish turini -> int kabi ko'rsatish mumkin.", kind: 'review' },
        { title: 'Type hints', body: "Parametrlar, qaytariladigan qiymat va kerakli o'zgaruvchilarga tur izohlarini yozing. Ular o'qish va statik tahlilga yordam beradi, ammo o'z-o'zidan bajarilish vaqtida turni majburlamaydi.", kind: 'review' },
        { title: 'Xatolarni boshqarish', body: "try/except bilan aniq istisnolarni boshqaring. else istisno chiqmaganda, finally esa blokdan chiqishda tozalash uchun ishlaydi. Xatoni yashiradigan keng except bloklaridan saqlaning.", kind: 'review' },
        { title: "Operator, vergul va qavslar", body: "Taqqoslash va tayinlash operatorlari atrofida bo'sh joy qoldiring; verguldan keyin bo'sh joy qo'ying; qavs ichida chekka bo'sh joy qoldirmang. Parametr standartlari va kesimlar kabi kontekstlar alohida uslubga ega.", kind: 'automatic' },
        { title: 'None va mantiqiy tekshiruvlar', body: "None bilan is None yoki is not None orqali taqqoslang. Mantiqiy qiymatni odatda if is_ready yoki if not is_ready ko'rinishida tekshiring; True/False bilan tenglikni keraksiz yozmang.", kind: 'automatic' },
        { title: 'Python vositalari', body: "pylint va flake8 lint uchun, black va autopep8 format uchun ishlatiladi. Ushbu 79 belgilik siyosat uchun Black sozlamasini --line-length 79 bilan moslang; formatlashdan keyin testlarni tekshiring.", kind: 'review' }
    ];

    var cppGuide = [
        { title: 'Sintaksis', body: "C++ katta-kichik harflarni farqlaydi; bloklar { } bilan ajratiladi. Oddiy operator va e'lonlar ; bilan tugaydi. Har bir blokdan keyin ; talab qilinmaydi: if va funksiya tanasidan keyin yo'q, class/struct e'lonidan keyin esa bor.", kind: 'review' },
        { title: 'Indentatsiya va qator uzunligi', body: "Ushbu loyiha C++ bloklarida 4 bo'sh joy, tab o'rniga bo'sh joy va 79 belgilik qator chegarasidan foydalanadi. Avtomatik formatlash ushbu sozlamalarni qo'llaydi; uzun satr literallari va boshqa bo'linmaydigan matnlarni qo'lda ko'rib chiqing.", kind: 'review' },
        { title: 'Nomlash uslubi', body: "O'zgaruvchilar uchun camelCase yoki snake_case, funksiyalar uchun camelCase yoki PascalCase tanlang. Klasslar PascalCase; konstantalar UPPER_CASE yoki kValue; namespace kichik harflarda bo'lsin. Loyiha ichida tanlangan uslubni saqlang.", kind: 'review' },
        { title: "Klass a'zolari", body: "Ushbu loyiha uslubida a'zo o'zgaruvchilar m_ prefiksini oladi. Encapsulation kerak bo'lsa getter/setter yozing; har bir maydonga avtomatik getter/setter yaratish shart emas. Holatni o'zgartirmaydigan metodni const belgilang.", kind: 'review' },
        { title: 'Sarlavha fayllari', body: "Tizim sarlavhalarini <vector> kabi, mahalliylarini \"my_header.h\" kabi yozing. Standart va mahalliy include guruhlarini bo'sh qator bilan ajrating.", kind: 'review' },
        { title: 'Namespace', body: "Include qatorlaridan keyin using namespace std; yozsangiz, cout, cin, cerr, string va vector nomlarini std:: prefiksisiz ishlatishingiz mumkin. std::cout, std::cin, std::string va std::vector uslubi ham qo'llanadi. Har ikki uslubda kerakli sarlavhalarni qo'shing: cout/cin/cerr uchun <iostream>, string uchun <string>, vector uchun <vector>.", kind: 'review' },
        { title: 'auto va range-based for', body: "Tur ravshan bo'lganda auto ishlating. Butun konteyner bo'ylab yurishda range-based for qulay; katta elementlarni keraksiz nusxalamaslik uchun const auto& mosligini tekshiring.", kind: 'review' },
        { title: 'nullptr', body: "Null ko'rsatkich uchun NULL yoki 0 o'rniga nullptr ishlating. Ko'rsatkichni tekshirishda ma'noni ravshan saqlang.", kind: 'automatic' },
        { title: 'const va constexpr', body: "O'zgarmaydigan obyektni const, kompilyatsiya vaqtida hisoblanishi kerak va mumkin bo'lgan qiymatni constexpr bilan ifodalang.", kind: 'review' },
        { title: 'Funksiyalar va parametrlar', body: "Mazmunli funksiya nomini tanlang. Katta obyektni faqat o'qish uchun const T& orqali uzatish mosligini tekshiring. Standart argumentlar odatda oxirgi parametrlarda keladi. Funksiyani ishlatishdan oldin e'lon yoki ta'rif mavjud bo'lsin.", kind: 'review' },
        { title: 'Rule of Zero va Rule of Five', body: "Har bir klassga barcha maxsus metodlarni yozish shart emas. Avval Rule of Zero: resurslarni RAII turlariga topshiring. Resursni bevosita boshqarsangiz, destruktor, copy konstruktor, copy assignment, move konstruktor va move assignmentni birgalikda ko'rib chiqing (Rule of Five). Oddiy konstruktor bu beshlikka kirmaydi.", kind: 'review' },
        { title: 'Xatolar va natija turlari', body: "try/catch bilan istisnolarni odatda const std::exception& orqali ushlang; catch (...) barcha istisnolarni ushlaydi. Qiymat yo'qligi uchun std::optional (C++17), qiymat yoki xato uchun std::expected (C++23) mos kelishi mumkin; standart va kutubxona qo'llovini tekshiring.", kind: 'review' },
        { title: 'RAII va xotira', body: "Resurs umrini obyekt umriga bog'lang (RAII). Qo'lda new/delete o'rniga standart konteyner, std::unique_ptr va zarur bo'lsa std::shared_ptr ishlating; egalik modelini aniq belgilang.", kind: 'review' },
        { title: 'K&R yoki Allman', body: "K&R ochuvchi qavsni ifoda qatorida, Allman yangi qatorda qo'yadi. Muharrirdagi tanlangan uslubga butun faylda rioya qiling; boshlang'ich qiymat ro'yxatlari blok bilan bir xil emas.", kind: 'automatic' },
        { title: 'Operatorlar va ifodalar', body: "Operatorlar atrofida bo'sh joy qoldiring; murakkab mantiqiy ifodada tushunarli qavslar qo'llang. Eski qiymat kerak bo'lmasa, ayniqsa iteratorlar uchun ++it afzal; it++ qiymati ishlatilsa uni ko'r-ko'rona almashtirmang.", kind: 'review' },
        { title: 'C++ vositalari', body: "clang-tidy va cppcheck chuqur tahlil uchun, clang-format format uchun ishlatiladi. Kompilyator va loyiha standartini bir xil sozlang; muharrirdagi yengil tahlil kompilyatorning o'rnini bosmaydi.", kind: 'review' }
    ];

    function guide(language) {
        var lang = String(language || '').toLowerCase();
        var specific = lang === 'python' ? pythonGuide : (/^(cpp|c\+\+|cxx)$/.test(lang) ? cppGuide : []);
        return specific.concat(sharedGuide).map(function (item) {
            var entry = { title: item.title, body: item.body, kind: item.kind };
            if (item.example) { entry.example = item.example; }
            return entry;
        });
    }

    var examples = {
        python: 'def calculate_sum(first: int, second: int) -> int:\n' +
            '    """Return the sum of two integers."""\n' +
            '    return first + second\n\n\n' +
            'def main() -> None:\n' +
            '    """Read two integers and print their sum."""\n' +
            '    try:\n' +
            '        first, second = map(int, input().split())\n' +
            '    except ValueError:\n' +
            '        print("Ikkita butun son kiriting.")\n' +
            '        return\n\n' +
            '    print(calculate_sum(first, second))\n\n\n' +
            'if __name__ == "__main__":\n' +
            '    main()\n',
        cpp: '#include <iostream>\n' +
            'using namespace std;\n\n' +
            'int calculateSum(int first, int second) {\n' +
            '    return first + second;\n' +
            '}\n\n' +
            'int main() {\n' +
            '    int first = 0;\n' +
            '    int second = 0;\n\n' +
            '    if (!(cin >> first >> second)) {\n' +
            '        cerr << "Ikkita butun son kiriting.\\n";\n' +
            '        return 1;\n' +
            '    }\n\n' +
            '    cout << calculateSum(first, second) << "\\n";\n' +
            '    return 0;\n' +
            '}\n'
    };

    function example(language, options) {
        var lang = String(language || '').toLowerCase();
        if (lang === 'c++' || lang === 'cxx') { lang = 'cpp'; }
        if (lang !== 'python' && lang !== 'cpp') { return ''; }
        var source = examples[lang] || '';
        if (lang === 'cpp' && options && options.cppStyle === 'allman') {
            // Only the controlled starter is converted; never format user code.
            return source.replace(/^( *)([^\n]+) \{$/gm, '$1$2\n$1{');
        }
        return source;
    }

    return { analyze: analyze, guide: guide, example: example, examples: examples };
}));
