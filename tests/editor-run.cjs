const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('../.tools/browser/node_modules/playwright');
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
        await page.locator('#liveCheck').uncheck();
        assert.match(await page.locator('script[src*="/js/editor.js"]').getAttribute('src'), /\?v=\d+$/);

        async function writeProgram(language, code, stdin = '') {
            await page.locator(`.editor-language[data-lang="${language}"]`).click();
            await page.evaluate(() => window.codeEditor.focus());
            await page.keyboard.press('Control+a');
            await page.keyboard.insertText(code);
            await page.locator('#stdinInput').fill(stdin);
            assert.equal(await page.evaluate(() => window.codeEditor.getValue()), code);
        }
        async function run(expected, error = false) {
            const started = Date.now();
            await page.locator('#runCode').click();
            await page.waitForFunction(() => !document.getElementById('runCode').disabled, null, { timeout: 45000 });
            const output = await page.locator('#output').textContent();
            if (expected instanceof RegExp) assert.match(output, expected);
            else assert.equal(output, expected);
            assert.equal(await page.locator('#output').evaluate(el => el.classList.contains('error')), error);
            assert.equal(await page.locator('#runCode').innerText(), 'Kodni bajarish');
            console.log(`PASS: ${await page.locator('#editorFilename').innerText()} ${error ? 'error' : 'output'} in ${Date.now() - started} ms`);
        }

        await writeProgram('python', 'print("Salom, dunyo!")\n');
        await run('Salom, dunyo!\n');
        await writeProgram('python', 'first, second = map(int, input().split())\nprint(first + second)\n', '17 25');
        await run('42\n');
        await writeProgram('cpp', '#include <iostream>\nint main() { std::cout << "Salom, dunyo!" << std::endl; }\n');
        await run('Salom, dunyo!\n');
        await writeProgram('cpp', '#include <iostream>\nint main() { int a, b; std::cin >> a >> b; std::cout << a + b; }\n', '17 25');
        await run('42');
        await writeProgram('cpp', 'int main() { return unknown_name; }\n');
        await run(/unknown_name/, true);
        await writeProgram('python', 'print(1 / 0)\n');
        await run(/ZeroDivisionError/, true);
        await writeProgram('python', 'print(input())\n');
        await run(/EOFError/, true);
        await writeProgram('python', 'while True:\n    pass\n');
        await run(/10 soniyalik|vaqt/, true);
        await writeProgram('python', 'print(6 * 7)\n');
        await run('42\n');

        // A transport that ignores abort must still release Run at the deadline.
        await page.clock.install();
        await page.evaluate(() => {
            window.originalEditorFetch = window.fetch;
            window.fetch = (url, options) => String(url).includes('/execute-code.php')
                ? new Promise(resolve => { window.releaseStuckFetch = resolve; })
                : window.originalEditorFetch(url, options);
        });
        await page.locator('#runCode').click();
        assert.equal(await page.locator('#runCode').isDisabled(), true);
        await page.clock.fastForward(35001);
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.match(await page.locator('#output').innerText(), /belgilangan vaqtda/);
        assert.equal(await page.locator('#output').evaluate(el => el.classList.contains('error')), true);

        // A response with a stalled body has the same deadline.
        await page.evaluate(() => {
            window.fetch = (url, options) => String(url).includes('/execute-code.php')
                ? Promise.resolve({ ok: true, json: () => new Promise(() => {}) })
                : window.originalEditorFetch(url, options);
        });
        await page.locator('#runCode').click();
        await page.clock.fastForward(35001);
        await page.waitForFunction(() => !document.getElementById('runCode').disabled);
        assert.match(await page.locator('#output').innerText(), /belgilangan vaqtda/);

        // Empty or malformed response contracts cannot be displayed as success.
        for (const response of [null, {}, { output: 42, error: false }]) {
            await page.evaluate(result => {
                window.fetch = (url, options) => String(url).includes('/execute-code.php')
                    ? Promise.resolve({ ok: true, json: async () => result })
                    : window.originalEditorFetch(url, options);
            }, response);
            await page.locator('#runCode').click();
            await page.waitForFunction(() => !document.getElementById('runCode').disabled);
            assert.equal(await page.locator('#output').evaluate(el => el.classList.contains('error')), true);
        }
        await page.evaluate(() => { window.fetch = window.originalEditorFetch; });
        await run('42\n');
        await page.evaluate(() => window.releaseStuckFetch({ ok: true, json: async () => ({ output: 'late output', error: false }) }));
        assert.equal(await page.locator('#output').textContent(), '42\n');
        console.log('PASS: stalled transport/body deadlines, invalid responses, retry and late-response isolation');

        fs.mkdirSync('tests/artifacts', { recursive: true });
        await page.locator('#output').scrollIntoViewIfNeeded();
        await page.locator('.editor-sidebar > section').first().screenshot({ path: 'tests/artifacts/editor-run-result.png' });
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
