// Opt-in: sends only synthetic examples to Wandbox through the local API.
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const {chromium} = require('../.tools/browser/node_modules/playwright');
const base = (process.env.CODESTEP_TEST_URL || 'http://codestep').replace(/\/+$/, '');
if (process.env.CODESTEP_LIVE_WANDBOX !== '1') {
    console.log('Skipped live Wandbox test. Set CODESTEP_LIVE_WANDBOX=1 to submit synthetic examples.');
    process.exit(0);
}
(async () => {
    const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    try {
        const context = await browser.newContext({viewport:{width:1440,height:1000}});
        await context.addCookies([{name:'visited',value:'true',url:base}]);
        await context.route('**/*',route => {
            const url = new URL(route.request().url());
            if (url.origin !== base) return route.abort();
            if (url.pathname === '/api/code-tools.php') return route.fulfill({json:{error:false,diagnostics:[],message:'Static analysis omitted in execution smoke',tools:[]}});
            return route.continue();
        });
        const page = await context.newPage();
        page.setDefaultTimeout(10000);
        const errors=[];
        page.on('pageerror', error=>errors.push(error.message));
        await page.goto(base+'/?page=code-editor&lang=python',{waitUntil:'domcontentloaded'});
        assert.match(await page.locator('#executionPrivacyNotice').innerText(),/Wandbox/);
        await page.locator('#liveCheck').uncheck();
        async function run(name, language, code, stdin, expected, error=false) {
            await page.locator('.editor-language[data-lang="'+language+'"]').click();
            await page.evaluate(code=>window.codeEditor.setValue(code),code);
            if (await page.locator('#stdinInput').isEditable()) await page.locator('#stdinInput').fill(stdin);
            const responsePromise=page.waitForResponse(response=>response.url().endsWith('/api/execute-code.php'),{timeout:105000});
            const started=Date.now();
            await page.locator('#runCode').click();
            const response=await responsePromise;
            const result=await response.json();
            assert.equal(response.status(),200, name+': '+JSON.stringify(result));
            assert.ok((await response.request().allHeaders())['x-csrf-token'],'CSRF token sent');
            assert.equal(result.error,error,name+': '+JSON.stringify(result));
            if (expected instanceof RegExp) assert.match(result.output,expected,name);
            else assert.equal(result.output,expected,name);
            assert.equal(Object.hasOwn(result,'saved_run_id'),false,'guest must not create account history');
            await page.waitForFunction(()=>!document.getElementById('runCode').disabled);
            const shown=await page.locator('#output').textContent();
            assert.equal(shown,result.output || 'Dastur chiqish ma’lumotisiz yakunlandi.');
            assert.equal(await page.locator('#output').evaluate(el=>el.classList.contains('error')),error);
            console.log('PASS '+name+' '+Math.round((Date.now()-started)/100)/10+'s');
            return result;
        }
        await run('Python stdin -> Natija: 45','python','a, b = map(int, input().split())\nprint("Natija:", a + b)','20 25\n','Natija: 45\n');
        await run('C++ namespace + stdin -> 42','cpp','#include <iostream>\nusing namespace std;\nint main() { int n; cin >> n; cout << n * 2 << endl; }','21\n','42\n');
        const warning=await run('C++ warnings/stderr do not reject correct output','cpp','#include <iostream>\n#warning example warning\nusing namespace std;\nint main() { cout << 42; cerr << "diagnostic note"; }','','42');
        assert.match(warning.diagnostics,/warning/);
        assert.match(await page.locator('#executionDiagnostics').innerText(),/diagnostic note/);
        await run('Compiler error is visible and Run recovers','cpp','int main() { missing_name; }','',/missing_name/,true);
        assert.equal(await page.locator('#executionDiagnostics').isVisible(),false,'old diagnostics clear');
        await run('Python runtime error is visible','python','print(1 / 0)\n','',/ZeroDivisionError/,true);
        await page.goto(base+'/?page=code-editor&topic=15&problem=1.1.50&lang=python',{waitUntil:'domcontentloaded'});
        const judged=await run('Exercise checks three real server inputs','python','print(int(input()))\n','',/^\-\d+\n$/);
        assert.equal(judged.verdict.status,'accepted');
        assert.equal(judged.verdict.checks_passed,3);
        assert.match(await page.locator('#verdictTitle').innerText(),/Qabul qilindi/);
        assert.deepEqual(errors,[]);
        fs.mkdirSync(path.join(__dirname,'artifacts'),{recursive:true});
        await page.locator('#runCode').scrollIntoViewIfNeeded();
        await page.screenshot({path:path.join(__dirname,'artifacts/wandbox-accepted.png')});
        console.log('PASS: live browser -> CSRF-protected PHP API -> verified HTTPS Wandbox -> output/verdict.');
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
