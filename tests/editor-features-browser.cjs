const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('../.tools/browser/node_modules/playwright');
const base = process.env.CODESTEP_TEST_URL || 'http://codestep';

(async () => {
    const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    let page;
    try {
        const context = await browser.newContext({viewport:{width:1440,height:1000}});
        await context.addCookies([{name:'visited',value:'true',url:base}]);
        await context.route('**/*', async route => {
            const request = route.request();
            const url = new URL(request.url());
            if (url.origin !== base) return route.abort();
            // UI tests isolate network results. Real execution is covered by editor-namespace.cjs.
            if (url.pathname === '/api/code-tools.php') return route.fulfill({json:{error:false,diagnostics:[],message:'UI test',tools:[]}});
            if (url.pathname === '/api/execute-code.php') return route.fulfill({json:{error:false,output:'42\n'}});
            return route.continue();
        });
        await context.addInitScript(() => {
            const original = document.execCommand.bind(document);
            document.execCommand = (command, ...args) => {
                if (command === 'copy') { window.testCopiedText = document.activeElement.value; return true; }
                return original(command,...args);
            };
        });
        page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => { if (error.message !== '$ is not defined') errors.push(error.message); });
        page.setDefaultTimeout(10000);
        const action = name => page.locator(`[data-editor-action="${name}"]`);
        const code = () => page.evaluate(() => window.codeEditor.getValue());
        const setCode = async value => page.evaluate(value => {
            const cm = window.codeEditor;
            cm.closeHint(); cm.setValue(value); cm.clearHistory();
            cm.setCursor(cm.posFromIndex(value.length)); cm.focus();
        }, value);
        const language = async lang => page.locator(`.editor-language[data-lang="${lang}"]`).click();
        await page.goto(base+'/?page=code-editor&topic=14&problem=1.1.1',{waitUntil:'domcontentloaded'});
        await page.waitForFunction(() => window.codeEditor && document.querySelector('[data-editor-action="find"]'));
        assert.match(await code(), /#include <iostream>\nusing namespace std;/);
        await page.goto(base+'/?page=code-editor&lang=cpp',{waitUntil:'domcontentloaded'});
        await page.locator('#completionAuto').uncheck();
        const qualified = '#include <iostream>\n\nint main() {\n    std::cout << 42;\n}\n';
        await setCode(qualified);
        await page.locator('#insertStdNamespace').click();
        assert.match(await code(), /using namespace std;/);
        assert.match(await code(), /std::cout/);
        const withNamespace = await code();
        await page.locator('#insertStdNamespace').click();
        assert.equal(await code(),withNamespace);
        await page.locator('#undoEdit').click();
        assert.equal(await code(),qualified);
        await action('redo').click();
        assert.equal(await code(),withNamespace);
        const starter = '#include <iostream>\nusing namespace std;\n\nint main() {\n    \n}\n';
        await setCode(starter);
        await page.evaluate(() => window.codeEditor.setCursor({line:4,ch:4}));
        await page.locator('#snippetSelect').selectOption('vector');
        await page.locator('#insertSnippet').click();
        assert.match(await code(),/    vector<int> values;/);
        assert.ok((await code()).indexOf('#include <vector>') < (await code()).indexOf('using namespace std;'));
        await page.locator('#undoEdit').click();
        assert.equal(await code(),starter,'snippet and header are one undo');
        await setCode('std::co');
        await page.keyboard.press('Control+Space');
        await page.locator('.CodeMirror-hints').getByText('cout',{exact:true}).click();
        assert.equal(await code(),'std::cout');
        await setCode('using namespace std;\nco');
        await page.locator('#completionTrigger').click();
        await page.locator('.CodeMirror-hints').getByText('cout',{exact:true}).click();
        assert.equal(await code(),'using namespace std;\ncout');
        await language('python');
        assert.equal(await page.locator('#insertStdNamespace').isVisible(),false);
        await setCode('pri');
        await page.keyboard.press('Control+Space');
        await page.locator('.CodeMirror-hints').getByText('print',{exact:true}).click();
        assert.equal(await code(),'print');
        await setCode('if True:\n    ');
        await page.locator('#snippetSelect').selectOption('for');
        await page.locator('#insertSnippet').click();
        assert.equal(await code(),'if True:\n    for i in range():\n        pass');
        console.log('PASS: namespace defaults/helper/undo, std-qualified and unqualified completion, header-aware snippets, Python completion');

        const searchSource = 'print("Ali")\nprint("ali")\nprint("Ali")\n';
        await setCode(searchSource);
        await page.keyboard.press('Control+h');
        await page.locator('#editorFindText').fill('Ali');
        assert.match(await page.locator('#editorSearchStatus').innerText(),/3 ta moslik/);
        await page.locator('#editorMatchCase').check();
        assert.match(await page.locator('#editorSearchStatus').innerText(),/2 ta moslik/);
        await page.locator('#editorReplaceText').fill('Vali');
        await action('replace-all').click();
        assert.equal(await code(),'print("Vali")\nprint("ali")\nprint("Vali")\n');
        await page.locator('#undoEdit').click();
        assert.equal(await code(),searchSource);
        await action('redo').click();
        assert.match(await code(),/Vali/);
        await page.locator('#editorFindText').focus();
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#editorSearchPanel').isVisible(),false);
        await setCode('value = "😀"\nprint(value)\nprint(value)');
        await action('replace').click();
        await page.locator('#editorFindText').fill('"😀"\nprint');
        await page.locator('#editorReplaceText').fill('"ok"\nprint');
        await action('replace-one').click();
        assert.equal(await code(),'value = "ok"\nprint(value)\nprint(value)');
        await action('close-search').click();
        await page.keyboard.press('Control+g');
        await page.locator('#editorGotoLine').fill('3');
        await action('goto-line').click();
        assert.equal(await page.evaluate(() => window.codeEditor.getCursor().line),2);
        await action('comment').click();
        assert.match(await code(),/# print\(value\)$/);
        await action('comment').click();
        assert.match(await code(),/\nprint\(value\)$/);
        await page.locator('.editor-extra-options summary').click();
        await action('font-larger').click();
        await page.locator('#editorLineWrapping').check();
        assert.equal(await page.evaluate(() => window.codeEditor.getOption('lineWrapping')),true);
        assert.equal(await page.locator('.CodeMirror').evaluate(el => getComputedStyle(el).fontSize),'15px');
        await page.reload({waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('#editorLineWrapping').isChecked(),true);
        assert.equal(await page.locator('.CodeMirror').evaluate(el => getComputedStyle(el).fontSize),'15px');
        await action('focus').click();
        assert.equal(await page.locator('#editorWorkspace').evaluate(el => el.classList.contains('editor-focus-mode')),true);
        await page.keyboard.press('Control+f');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#editorWorkspace').evaluate(el => el.classList.contains('editor-focus-mode')),true);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#editorWorkspace').evaluate(el => el.classList.contains('editor-focus-mode')),false);
        console.log('PASS: literal/multiline Unicode search, replace and undo/redo, comments, go-to, persisted font/wrap, focused view Escape');

        await language('cpp');
        await setCode(starter);
        await page.locator('.editor-extra-options summary').click();
        await action('fold-all').click();
        assert.ok(await page.locator('.CodeMirror-foldmarker').count());
        assert.equal(await code(),starter);
        await action('unfold-all').click();
        assert.equal(await page.locator('.CodeMirror-foldmarker').count(),0);
        page.once('dialog',dialog=>dialog.accept());
        const imported = 'print("salom")\n';
        await page.locator('#editorImportFile').setInputFiles({name:'example.py',mimeType:'text/plain',buffer:Buffer.from(imported)});
        await page.waitForFunction(value=>window.codeEditor.getValue()===value,imported);
        assert.equal(await page.locator('.editor-language[data-lang="python"]').getAttribute('aria-pressed'),'true');
        await language('cpp');
        assert.equal(await code(),starter,'other language draft stays intact');
        await language('python');
        await action('copy-code').click();
        assert.equal(await page.evaluate(()=>window.testCopiedText),imported);
        await page.locator('#editorImportFile').setInputFiles({name:'large.cpp',mimeType:'text/plain',buffer:Buffer.alloc(65537,65)});
        assert.match(await page.locator('#editorNotice').innerText(),/64 KB/);
        assert.equal(await code(),imported);
        await page.evaluate(() => {
            const read = File.prototype.arrayBuffer;
            File.prototype.arrayBuffer = async function () {
                File.prototype.arrayBuffer = read;
                const bytes = await read.call(this);
                await new Promise(resolve => { window.resumeTestFileRead = resolve; });
                return bytes;
            };
        });
        await page.locator('#editorImportFile').setInputFiles({name:'delayed.py',mimeType:'text/plain',buffer:Buffer.from('print("old")')});
        await page.waitForFunction(() => typeof window.resumeTestFileRead === 'function');
        await setCode('print("new edit")');
        await page.evaluate(() => window.resumeTestFileRead());
        await page.waitForFunction(() => document.getElementById('editorNotice').textContent.includes('Fayl o‘qilayotganda'));
        assert.equal(await code(),'print("new edit")','a delayed file read must preserve newer edits');
        await setCode(imported);
        await page.locator('#runCode').click();
        await page.waitForFunction(()=>!document.getElementById('runCode').disabled);
        await action('copy-output').click();
        assert.equal(await page.evaluate(()=>window.testCopiedText),'42\n');
        await action('clear-output').click();
        assert.match(await page.locator('#output').innerText(),/Natija shu yerda/);
        console.log('PASS: code folding, import confirmation/language isolation/size/concurrent edits, copy code/output, clear output');
        fs.mkdirSync(path.join(__dirname,'artifacts'),{recursive:true});
        for (const theme of ['dark','light']) {
            await context.addCookies([{name:'theme',value:theme,url:base}]);
            for (const width of [390,1440]) {
                await page.setViewportSize({width,height:1000});
                await page.reload({waitUntil:'domcontentloaded'});
                await action('replace').click();
                await page.locator('#editorFindText').fill('print');
                assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
                await page.locator('#editorAssist').scrollIntoViewIfNeeded();
                await page.screenshot({path:path.join(__dirname,`artifacts/editor-features-${theme}-${width}.png`)});
            }
        }
        assert.deepEqual(errors,[]);
        console.log('PASS: both themes and desktop/mobile layouts without horizontal overflow');
    } catch(error) {
        if(page) console.error('Page:',page.url(),'Notice:',await page.locator('#editorNotice').textContent());
        throw error;
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
