// Account/draft UI integration. Uses a temporary database, never the site's accounts.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { chromium } = require('../.tools/browser/node_modules/playwright');
const root = path.resolve(__dirname, '..');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'codestep-ui-'));
    let server, browser, debugPage;
    try {
        const socket = net.createServer();
        await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
        const port = socket.address().port;
        await new Promise(resolve => socket.close(resolve));
        const base = `http://127.0.0.1:${port}`;
        server = spawn('C:/php/php.exe', ['-d', 'extension=pdo_sqlite', '-d', `session.save_path="${temp.replaceAll('\\', '/')}"`, '-S', `127.0.0.1:${port}`, '-t', 'public'], {
            cwd: root, windowsHide: true, stdio: 'ignore', env: { ...process.env, CODESTEP_DB_PATH: path.join(temp, 'test.sqlite') }
        });
        let startupError;
        server.on('error', error => { startupError = error; });
        for (let i = 0; i < 60; i++) {
            if (startupError) throw startupError;
            try { if ((await fetch(base)).ok) break; } catch (_) { /* Wait for local PHP only. */ }
            await delay(100);
        }
        browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
        const context = await browser.newContext({ viewport: { width: 1366, height: 950 } });
        await context.addCookies([{ name: 'visited', value: 'true', url: base }]);
        // Syntax tooling is independent of account storage and is deliberately stubbed here.
        await context.route('**/api/code-tools.php', route => route.fulfill({ json: { diagnostics: [], message: 'Syntax unavailable in account test' } }));
        await context.route(/https:\/\/(?!code\.jquery\.com).*/, route => route.abort());
        const page = await context.newPage();
        debugPage = page;
        const errors = [];
        context.on('page', p => p.on('pageerror', error => errors.push(error.message)));
        page.on('pageerror', error => errors.push(error.message));
        const editorPath = '/?page=code-editor&topic=14&problem=1.1.1&lang=python';
        async function openEditor(p = page) {
            await p.goto(base + editorPath);
            await p.waitForFunction(() => window.codeEditor);
            await p.locator('#liveCheck').uncheck();
        }
        async function runAndSave() {
            await page.locator('#runCode').click();
            await page.waitForFunction(() => !document.getElementById('runCode').disabled, null, {timeout:45000});
            assert.equal(await page.locator('#problemVerdict').getAttribute('data-state'), 'accepted');
            assert.match(await page.locator('#cloudStatus').innerText(), /tarixiga saqlandi/);
        }
        const source = 'print("0000010011100110")\n';
        await openEditor();
        await page.evaluate(code => window.codeEditor.setValue(code), source);
        await page.locator('#signInToSave').click();
        await page.locator('.account-switch').click();
        await page.locator('#accountUsername').fill('ui_user');
        await page.locator('#accountPassword').fill('test-password-728');
        await page.locator('#accountConfirm').fill('test-password-728');
        const registered = page.waitForResponse(response => response.url().endsWith('/api/auth.php'));
        await page.locator('.account-form button').click();
        const registrationResponse = await registered;
        assert.equal(registrationResponse.status(), 303, 'registration response: ' + (registrationResponse.status() !== 303 ? await registrationResponse.text() : 'redirect'));
        await page.waitForFunction(() => window.codeEditor);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), source, 'guest draft survives registration');
        await page.locator('#saveCode').click();
        await page.waitForFunction(() => !document.getElementById('saveCode').disabled);
        assert.match(await page.locator('#cloudStatus').innerText(), /bazaga saqlandi/);
        await runAndSave();
        await page.locator('.editor-language[data-lang="cpp"]').click();
        const cpp = '#include <iostream>\nint main(){std::cout << "0000010011100110";}\n';
        await page.evaluate(code => window.codeEditor.setValue(code), cpp);
        await page.locator('#saveCode').click();
        await page.waitForFunction(() => !document.getElementById('saveCode').disabled);
        await runAndSave();
        await page.goto(base + '/?page=solutions');
        assert.equal(await page.locator('.solution-draft').count(), 2, 'languages saved separately');
        assert.equal(await page.locator('.solution-run').count(), 2, 'real judged runs saved to history');
        assert.equal(await page.locator('.solution-verdict[data-verdict="accepted"]').count(), 2);
        fs.mkdirSync(path.join(root, 'tests/artifacts'), { recursive: true });
        await page.screenshot({ path: path.join(root, 'tests/artifacts/solutions-dark.png'), fullPage: true });
        await context.addCookies([{name:'theme',value:'light',url:base}]);
        await page.reload();
        await page.screenshot({ path: path.join(root, 'tests/artifacts/solutions-light.png'), fullPage: true });
        await page.locator('.solution-run .account-button').first().click();
        await page.waitForFunction(() => window.codeEditor);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), cpp, 'history opens the exact submitted source');
        await openEditor();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), source);

        // Two tabs: a stale editor cannot silently replace the other tab's saved source.
        const second = await context.newPage();
        await openEditor(second);
        const newest = source + '# newest saved version\n';
        await page.evaluate(code => window.codeEditor.setValue(code), newest);
        await page.locator('#saveCode').click();
        await page.waitForFunction(() => !document.getElementById('saveCode').disabled);
        const stale = source + '# unsaved second tab\n';
        await second.evaluate(code => window.codeEditor.setValue(code), stale);
        await second.locator('#saveCode').click();
        await second.waitForFunction(() => !document.getElementById('saveCode').disabled);
        assert.match(await second.locator('#cloudStatus').innerText(), /boshqa oynada/);
        assert.equal(await second.evaluate(() => window.codeEditor.getValue()), stale);
        await second.locator('#loadSavedCode').click();
        await second.waitForFunction(() => !document.getElementById('loadSavedCode').disabled);
        assert.equal(await second.evaluate(() => window.codeEditor.getValue()), newest);
        await second.locator('#undoEdit').click();
        assert.equal(await second.evaluate(() => window.codeEditor.getValue()), stale, 'load supports undo');
        await second.close();

        // A late save must preserve code typed while the request is in flight.
        await page.route('**/api/solutions.php', async route => {
            const response = await route.fetch();
            await delay(350);
            await route.fulfill({ response });
        });
        const submitted = newest + '# sent\n';
        const duringSave = newest + '# typed while saving\n';
        await page.evaluate(code => window.codeEditor.setValue(code), submitted);
        await page.locator('#saveCode').click();
        await page.evaluate(code => window.codeEditor.setValue(code), duringSave);
        await page.waitForFunction(() => !document.getElementById('saveCode').disabled);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), duringSave);
        assert.match(await page.locator('#cloudStatus').innerText(), /Keyingi o.zgarishlar/);
        await page.unroute('**/api/solutions.php');

        // A delayed load and a save cannot race and rewind the saved revision.
        await page.route('**/api/solutions.php', async route => {
            const response = await route.fetch();
            await delay(350);
            await route.fulfill({ response });
        });
        await page.locator('#loadSavedCode').click();
        assert.equal(await page.locator('#saveCode').isDisabled(), true);
        const duringLoad = newest + '# typed while loading\n';
        await page.evaluate(code => window.codeEditor.setValue(code), duringLoad);
        await page.waitForFunction(() => !document.getElementById('loadSavedCode').disabled);
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), duringLoad);
        assert.match(await page.locator('#cloudStatus').innerText(), /Yuklash vaqtida/);
        await page.unroute('**/api/solutions.php');

        // Logout returns to the guest namespace, which cannot display account-only drafts.
        if (await page.locator('#menuToggle').isVisible() && await page.locator('#menuToggle').getAttribute('aria-expanded') === 'false') await page.locator('#menuToggle').click();
        await page.locator('.drawer-menu a[href="?page=profile"]').click();
        await page.locator('.profile-footer button').click();
        await openEditor();
        assert.equal(await page.evaluate(() => window.codeEditor.getValue()), source);
        assert.equal(await page.locator('#signInToSave').count(), 1);
        await page.goto(base + '/?page=login');
        await page.locator('#accountUsername').fill('ui_user');
        await page.locator('#accountPassword').fill('test-password-728');
        await page.locator('.account-form button').click();
        await page.waitForURL('**/?page=profile');
        await page.goto(base + '/?page=solutions');
        assert.equal(await page.locator('.solution-draft').count(), 2);
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({ path: path.join(root, 'tests/artifacts/solutions-mobile.png'), fullPage: true });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'mobile has no horizontal overflow');
        // Topic 15 examples remain reachable from every exercise editor, with safe code rendering.
        await page.goto(base + '/?page=sample-problems&topic=15&problem=1.1.50&lang=python');
        assert.equal(await page.locator('.sample-card').count(), 2);
        assert.equal(await page.locator('.sample-source').count(), 4);
        assert.match(await page.locator('.sample-source').nth(1).innerText(), /cin >> a;/);
        assert.equal(await page.locator('.sample-return').first().getAttribute('href'), '?page=code-editor&topic=15&problem=1.1.50&lang=python');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'examples fit mobile');
        await page.screenshot({ path: path.join(root, 'tests/artifacts/examples-output-mobile.png'), fullPage: true });
        await page.setViewportSize({width:1366,height:950});
        await context.addCookies([{name:'theme',value:'light',url:base}]);
        await page.reload();
        await page.screenshot({ path: path.join(root, 'tests/artifacts/examples-output-light.png'), fullPage: true });
        assert.deepEqual(errors, []);
        console.log('PASS: registration handoff, Python/C++ draft and judged-run saving, history restore, revision conflict, load/undo, save/load race, logout isolation, login, mobile, topic 15 examples.');
    } catch (error) {
        if (debugPage) {
            console.error('Page at failure:', debugPage.url());
            console.error((await debugPage.locator('body').innerText({timeout:3000})).slice(0, 2200));
            console.error('Form validation:', await debugPage.locator('.account-form input').evaluateAll(inputs => inputs.filter(input => !input.checkValidity()).map(input => ({name:input.name,message:input.validationMessage}))));
        }
        throw error;
    } finally {
        if (browser) await browser.close();
        if (server && server.exitCode === null) {
            const stopped = new Promise(resolve => server.once('exit', resolve));
            server.kill();
            await stopped;
        }
        // The only deletion target is this test-created absolute temp directory.
        const safeTemp = path.resolve(temp);
        assert.equal(path.dirname(safeTemp).toLowerCase(), path.resolve(os.tmpdir()).toLowerCase());
        assert.ok(path.basename(safeTemp).startsWith('codestep-ui-'));
        fs.rmSync(safeTemp, { recursive: true, force: true, maxRetries: 5, retryDelay: 150 });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
