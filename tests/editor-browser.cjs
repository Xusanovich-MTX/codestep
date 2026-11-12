const assert = require('node:assert/strict');
const { chromium } = require('../.tools/browser/node_modules/playwright');
const fs = require('node:fs');
const baseUrl = (process.env.CODESTEP_TEST_URL || 'http://127.0.0.1:8765').replace(/\/$/, '');

(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.addCookies([{ name: 'visited', value: 'true', url: baseUrl }]);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto(baseUrl + '/?page=code-editor&lang=python');
        await page.waitForFunction(() => window.codeEditor);
        assert.equal(await page.locator('.editor-language[data-lang="python"]').getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator('#cppStyleControl').isVisible(), false);
        assert.equal(await page.evaluate(() => window.codeEditor.getOption('indentWithTabs')), false);
        assert.equal(await page.locator('#rulesList details').count(), await page.evaluate(() => window.CodeStepRules.guide('python').length));
        assert.deepEqual(await page.evaluate(() => window.codeEditor.getOption('rulers').map(ruler => ruler.column)), [72, 79]);
        assert.equal(await page.locator('#pythonIndentHelp').isVisible(), true);
        assert.ok(await page.locator('.editor-rule-example').count() > 0);
        // Real token styles remain different, including after theme changes.
        const colorSample = '# comment\ndef greet():\n    value = 42\n    print("hello", value)\n';
        await page.evaluate(code => window.codeEditor.setValue(code), colorSample);
        for (const theme of ['dark', 'light']) {
            await page.evaluate(theme => {
                document.body.classList.toggle('light-mode', theme === 'light');
                document.body.classList.toggle('dark-mode', theme === 'dark');
                window.codeEditor.setOption('theme', 'codestep-' + theme);
            }, theme);
            const colors = await page.evaluate(() => ['keyword', 'string', 'number', 'comment', 'def'].map(kind => getComputedStyle(document.querySelector('.CodeMirror .cm-' + kind)).color));
            assert.equal(new Set(colors).size, 5);
        }
        assert.ok(await page.locator('.CodeMirror .cm-leading-space').count() > 0);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), colorSample);
        await page.locator('#showIndent').uncheck();
        assert.equal(await page.locator('#editorWorkspace').evaluate(el => el.classList.contains('show-indent')), false);
        await page.locator('#showIndent').check();
        // Even four spaces at top level are a syntax error; valid block spaces are kept.
        await page.evaluate(() => window.codeEditor.setValue('    print("hello")\n'));
        await page.waitForFunction(() => document.getElementById('diagnosticsList').textContent.includes('PY-INDENT'));
        assert.match(await page.locator('#diagnosticsList').innerText(), /ortiqcha/);
        await page.evaluate(() => window.codeEditor.setValue('if True:\nprint("hello")\n'));
        await page.waitForFunction(() => document.getElementById('diagnosticsList').textContent.includes('PY-INDENT'));
        assert.match(await page.locator('#diagnosticsList').innerText(), /4 bo/);
        await page.evaluate(() => window.codeEditor.setValue('if True:\n    print("hello world")\n\nprint(1 + 2)\n'));
        await page.waitForFunction(() => document.getElementById('analysisStatus').textContent.includes('Python sintaksisi tekshirildi'));
        assert.equal(await page.locator('.editor-diagnostic[data-severity="error"]').count(), 0);
        // Live rules also cover strict comparisons and compound assignments.
        await page.evaluate(() => window.codeEditor.setValue('value=2\nif value<3:\n    value**=2\n'));
        await page.waitForFunction(() => document.getElementById('diagnosticsList').textContent.includes('operator-spacing'));
        await page.locator('#liveCheck').uncheck();
        assert.equal(await page.locator('.editor-diagnostic').count(), 0);
        await page.locator('#checkCode').click();
        await page.waitForFunction(() => !document.getElementById('checkCode').disabled);
        assert.match(await page.locator('#diagnosticsList').innerText(), /operator-spacing/);
        await page.locator('#liveCheck').check();
        // Real Python formatter and undo preserve the exact source.
        const python = 'def add(first,second):\n    return first+second\n\nprint(add(2,3))\n';
        await page.evaluate(code => window.codeEditor.setValue(code), python);
        await page.locator('#formatCode').click();
        await page.waitForFunction(() => !document.getElementById('formatCode').disabled);
        const formattedPython = await page.evaluate(() => window.codeEditor.getValue());
        assert.match(formattedPython, /def add\(first, second\)/);
        assert.match(formattedPython, /return first \+ second/);
        await page.locator('#undoEdit').click();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), python);
        // Syntax errors really come from AST checks and select the right line.
        await page.evaluate(() => window.codeEditor.setValue('if True\n    print(1)\n'));
        await page.locator('#checkCode').click();
        await page.waitForFunction(() => !document.getElementById('checkCode').disabled);
        assert.match(await page.locator('#diagnosticsList').innerText(), /PY-SYNTAX/);
        await page.locator('.editor-diagnostic').first().click();
        assert.equal(await page.evaluate(() => window.codeEditor.getCursor().line), 0);
        // Enter uses four spaces; Tab never inserts a tab character.
        await page.evaluate(() => { window.codeEditor.setValue('if True:'); window.codeEditor.setCursor({line: 0, ch: 8}); window.codeEditor.focus(); });
        await page.keyboard.press('Enter');
        await page.keyboard.type('pass');
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), 'if True:\n    pass');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#checkCode').evaluate(el => el === document.activeElement), true);
        // Drafts and stdin remain separate, survive switching and reload.
        await page.evaluate(code => window.codeEditor.setValue(code), python);
        await page.locator('#stdinInput').fill('python input');
        await page.locator('.editor-language[data-lang="cpp"]').click();
        assert.equal(await page.locator('#pythonIndentHelp').isVisible(), false);
        assert.equal(await page.locator('#rulesList details').count(), await page.evaluate(() => window.CodeStepRules.guide('cpp').length));
        assert.deepEqual(await page.evaluate(() => window.codeEditor.getOption('rulers').map(ruler => ruler.column)), [79]);
        assert.match(await page.locator('#editorLimits').innerText(), /79/);
        const cpp = '#include <iostream>\nint main(){std::cout<<"ok";return 0;}\n';
        await page.evaluate(code => window.codeEditor.setValue(code), cpp);
        await page.locator('#stdinInput').fill('cpp input');
        await page.locator('#cppStyle').selectOption('allman');
        await page.locator('#formatCode').click();
        await page.waitForFunction(() => !document.getElementById('formatCode').disabled);
        assert.match(await page.evaluate(() => window.codeEditor.getValue()), /int main\(\)\n\{/);
        await page.locator('#undoEdit').click();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), cpp);
        // Loading a starter must follow the selected brace policy and remain undoable.
        await page.locator('#loadExample').click();
        assert.match(await page.evaluate(() => window.codeEditor.getValue()), /int main\(\)\n\{/);
        assert.deepEqual(await page.evaluate(() => window.CodeStepRules.analyze(window.codeEditor.getValue(), 'cpp', { cppStyle: 'allman' })), []);
        await page.locator('#checkCode').click();
        await page.waitForFunction(() => !document.getElementById('checkCode').disabled);
        assert.equal(await page.locator('.editor-diagnostic').count(), 0);
        await page.locator('#undoEdit').click();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), cpp);
        await page.locator('.editor-language[data-lang="python"]').click();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), python);
        assert.equal(await page.locator('#stdinInput').inputValue(), 'python input');
        await page.reload();
        await page.waitForFunction(() => window.codeEditor);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), python);
        await page.locator('.editor-language[data-lang="cpp"]').click();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), cpp);
        assert.equal(await page.locator('#stdinInput').inputValue(), 'cpp input');
        // C++ runs through the installed WASM compiler, including actual output.
        await page.locator('#runCode').click();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.equal(await page.locator('#output').evaluate(el => el.classList.contains('error')), false);
        assert.equal(await page.locator('#output').innerText(), 'ok');
        await page.locator('.editor-language[data-lang="python"]').click();
        await page.evaluate(() => window.codeEditor.setValue('first, second = map(int, input().split())\nprint(first + second)\n'));
        await page.locator('#stdinInput').fill('12 30');
        await page.locator('#runCode').click();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.equal(await page.locator('#output').evaluate(el => el.classList.contains('error')), false);
        assert.equal((await page.locator('#output').innerText()).trim(), '42');
        await page.locator('.editor-language[data-lang="cpp"]').click();
        // No response/source is ever interpreted as HTML.
        await page.route('**/api/execute-code.php', route => route.fulfill({ json: { output: '<img src=x onerror=alert(1)>\n0', error: false } }));
        await page.locator('#runCode').click();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.equal(await page.locator('#output img').count(), 0);
        assert.match(await page.locator('#output').innerText(), /<img/);
        await page.unroute('**/api/execute-code.php');
        // A late formatter response may not replace edits made while it ran.
        let releaseFormat;
        await page.route('**/api/code-tools.php', async route => {
            await new Promise(resolve => { releaseFormat = resolve; });
            await route.fulfill({ json: { code: 'stale formatted code', tools: [], message: 'test' } });
        });
        await page.locator('#formatCode').click();
        await page.waitForTimeout(100);
        await page.evaluate(() => window.codeEditor.setValue('int main() { return 1; }\n'));
        releaseFormat();
        await page.waitForFunction(() => !document.getElementById('formatCode').disabled);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), 'int main() { return 1; }\n');
        await page.unroute('**/api/code-tools.php');
        // Results belong to the exact code and stdin, including errors and later edits.
        await page.locator('.editor-language[data-lang="python"]').click();
        await page.locator('#liveCheck').uncheck();
        const inputProgram = 'print(input())\n';
        await page.evaluate(code => window.codeEditor.setValue(code), inputProgram);
        await page.locator('#stdinInput').fill('old input');
        async function delayedExecution(result) {
            let release, received;
            const gate = new Promise(resolve => { release = resolve; });
            const started = new Promise(resolve => { received = resolve; });
            await page.route('**/api/execute-code.php', async route => {
                received();
                await gate;
                await route.fulfill({ json: result });
            }, { times: 1 });
            return { started, release };
        }
        let pending = await delayedExecution({ output: 'old input\n', error: false });
        await page.locator('#runCode').click();
        await pending.started;
        await page.locator('#stdinInput').fill('new input');
        assert.equal(await page.locator('#outputStatus').isVisible(), true);
        pending.release();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.equal(await page.locator('#output').textContent(), 'old input\n');
        assert.match(await page.locator('#outputStatus').innerText(), /Qayta bajaring/);
        await page.locator('#stdinInput').fill('old input');
        assert.equal(await page.locator('#outputStatus').isVisible(), false);
        await page.evaluate(() => window.codeEditor.setValue('print("edited")\n'));
        assert.equal(await page.locator('#outputStatus').isVisible(), true);
        await page.evaluate(code => window.codeEditor.setValue(code), inputProgram);
        assert.equal(await page.locator('#outputStatus').isVisible(), false);
        // A pending result is retained in its own language when tabs change.
        pending = await delayedExecution({ output: 'python only\n', error: false });
        await page.locator('#runCode').click();
        await pending.started;
        await page.locator('.editor-language[data-lang="cpp"]').click();
        pending.release();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.notEqual(await page.locator('#output').textContent(), 'python only\n');
        await page.locator('.editor-language[data-lang="python"]').click();
        assert.equal(await page.locator('#output').textContent(), 'python only\n');
        assert.equal(await page.locator('#outputStatus').isVisible(), false);
        pending = await delayedExecution({ output: 'old runtime error', error: true });
        await page.locator('#runCode').click();
        await pending.started;
        await page.evaluate(() => window.codeEditor.setValue('print("corrected")\n'));
        pending.release();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.equal(await page.locator('#output').textContent(), 'old runtime error');
        assert.equal(await page.locator('#output').evaluate(el => el.classList.contains('error')), true);
        assert.equal(await page.locator('#outputStatus').isVisible(), true);
        // Clear also discards a response that is still in flight.
        pending = await delayedExecution({ output: 'discarded output', error: false });
        await page.locator('#runCode').click();
        await pending.started;
        await page.locator('#clearEditor').click();
        pending.release();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.equal(await page.locator('#output').innerText(), 'Natija shu yerda ko‘rsatiladi.');
        assert.equal(await page.locator('#outputStatus').isVisible(), false);
        // stdin is limited by UTF-8 bytes, with the boundary still accepted.
        await page.evaluate(code => window.codeEditor.setValue(code), inputProgram);
        let executionRequests = 0;
        await page.route('**/api/execute-code.php', route => {
            executionRequests += 1;
            return route.fulfill({ json: { output: '', error: false } });
        });
        await page.locator('#stdinInput').fill('é'.repeat(8193));
        await page.locator('#runCode').click();
        assert.match(await page.locator('#editorNotice').innerText(), /16 KB/);
        assert.equal(executionRequests, 0);
        await page.locator('#stdinInput').fill('é'.repeat(8192));
        await page.locator('#runCode').click();
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.equal(executionRequests, 1);
        assert.equal(await page.locator('#editorNotice').innerText(), '');
        assert.equal(await page.locator('#output').evaluate(el => el.classList.contains('error')), false);
        assert.match(await page.locator('#output').innerText(), /chiqish ma’lumotisiz/);
        await page.unroute('**/api/execute-code.php');
        await page.locator('#stdinInput').fill('');
        // Live syntax resumes when edits arrive during a slow explicit check.
        await page.evaluate(() => window.codeEditor.setValue('print(1)\n'));
        await page.locator('#liveCheck').check();
        let releaseCheck, checkReceived;
        const checkGate = new Promise(resolve => { releaseCheck = resolve; });
        const checkStarted = new Promise(resolve => { checkReceived = resolve; });
        await page.route('**/api/code-tools.php', async route => {
            const action = new URLSearchParams(route.request().postData()).get('action');
            if (action !== 'check') return route.continue();
            checkReceived();
            await checkGate;
            await route.fulfill({ json: { diagnostics: [], tools: [], message: 'old check' } });
        });
        await page.locator('#checkCode').click();
        await checkStarted;
        await page.evaluate(() => window.codeEditor.setValue('if True\n    print(1)\n'));
        await page.waitForTimeout(900);
        releaseCheck();
        await page.waitForFunction(() => !document.getElementById('checkCode').disabled);
        await page.waitForFunction(() => document.getElementById('diagnosticsList').textContent.includes('PY-SYNTAX'));
        await page.unroute('**/api/code-tools.php');
        await page.locator('.editor-language[data-lang="cpp"]').click();
        // Search, clear, undo, empty draft persistence, responsive layout.
        await page.locator('#ruleSearch').fill('RAII');
        assert.ok(await page.locator('#rulesList details').count() > 0);
        await page.locator('#ruleSearch').fill('');
        await page.locator('#clearEditor').click();
        await page.reload();
        await page.waitForFunction(() => window.codeEditor);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), '');
        await page.locator('#loadExample').click();
        await page.locator('#undoEdit').click();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), '');
        await page.locator('#loadExample').click();
        fs.mkdirSync('tests/artifacts', { recursive: true });
        await page.screenshot({ path: 'tests/artifacts/editor-desktop.png', fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
        await page.screenshot({ path: 'tests/artifacts/editor-mobile.png', fullPage: true });
        await page.evaluate(() => { document.body.classList.remove('dark-mode'); document.body.classList.add('light-mode'); window.codeEditor.setOption('theme', 'codestep-light'); });
        await page.screenshot({ path: 'tests/artifacts/editor-light.png', fullPage: true });
        assert.deepEqual(errors, []);
        console.log('PASS: browser editor formatting, diagnostics, undo, keyboard, persistence, execution result identity, stdin limits, syntax recovery, output escaping, stale responses, mobile and light theme');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
