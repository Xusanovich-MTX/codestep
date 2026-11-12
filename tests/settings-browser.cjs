const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('../.tools/browser/node_modules/playwright');
const base = (process.env.CODESTEP_TEST_URL || 'http://codestep').replace(/\/+$/, '');
(async () => {
    const browser = await chromium.launch({executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true});
    try {
        const context = await browser.newContext({viewport:{width:1440,height:1000}});
        await context.addCookies([{name:'visited', value:'true', url:base}]);
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const goto = query => page.goto(base + '/?page=' + query, {waitUntil:'domcontentloaded'});
        await goto('settings');
        assert.equal(await page.locator('#siteVersion').innerText(), '2.0.0');
        assert.equal(await page.locator('#language').count(), 0);
        assert.match(await page.locator('.footer-bottom').innerText(), /2026/);
        assert.equal(await page.locator('.notification-item').count(),0, 'first visit establishes baseline');
        const normal = await page.locator('.settings-preview p').evaluate(el => parseFloat(getComputedStyle(el).fontSize));
        await page.locator('#increaseFont').click();
        await page.locator('#increaseFont').click();
        assert.equal(await page.locator('html').evaluate(el => getComputedStyle(el).fontSize),'20px');
        assert.equal(await page.locator('.settings-preview p').evaluate(el => parseFloat(getComputedStyle(el).fontSize)),normal * 1.25);
        assert.equal(await page.locator('#increaseFont').isDisabled(),true);
        await page.reload({waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('#currentFontSize').innerText(),'20 px');
        await page.locator('#themeToggle2').check();
        assert.equal(await page.locator('body').evaluate(el=>el.classList.contains('light-mode')),true);
        await page.reload({waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('#themeToggle2').isChecked(),true);
        await page.locator('#testNotification').click();
        await page.locator('#notificationBell').click();
        assert.equal(await page.locator('#notificationPopover').isVisible(),true);
        assert.equal(await page.locator('.notification-item').count(),1);
        await page.getByRole('button',{name:'Barchasini o‘qilgan deb belgilash',exact:true}).click();
        assert.equal(await page.locator('.notification-badge').isVisible(),false);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#notificationPopover').isVisible(),false);
        await page.reload({waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('.notification-item').count(),1);
        await page.locator('#notifDaily').check();
        await page.locator('#reminderTime').fill('00:00');
        await page.locator('#reminderTime').dispatchEvent('change');
        assert.equal(await page.locator('.notification-item').count(),2);
        await page.reload({waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('.notification-item').count(),2,'same day reminder does not repeat');
        assert.equal(await page.locator('#reminderTime').inputValue(),'00:00');
        await page.locator('#notifDaily').uncheck();
        assert.equal(await page.locator('#reminderTime').isDisabled(),true);
        await page.locator('#notifTopics').uncheck();
        await page.locator('#editorFontSize').selectOption('18');
        await page.locator('#lineNumbers').uncheck();
        await goto('code-editor');
        assert.equal(await page.locator('.CodeMirror').evaluate(el=>getComputedStyle(el).fontSize),'18px');
        assert.equal(await page.evaluate(()=>window.codeEditor.getOption('lineNumbers')),false);
        const editorPage = page;
        const settingsPage = await context.newPage();
        settingsPage.on('pageerror', error=>errors.push(error.message));
        await settingsPage.goto(base+'/?page=settings',{waitUntil:'domcontentloaded'});
        await settingsPage.locator('#editorFontSize').selectOption('21');
        await editorPage.waitForFunction(()=>getComputedStyle(document.querySelector('.CodeMirror')).fontSize === '21px');
        await settingsPage.locator('#lineNumbers').check();
        await editorPage.waitForFunction(()=>window.codeEditor.getOption('lineNumbers') === true);
        await settingsPage.close();
        await goto('privacy');
        assert.equal(await page.locator('#privacy-title').innerText(),'Maxfiylik siyosati');
        assert.equal(await page.locator('html').evaluate(el=>getComputedStyle(el).fontSize),'20px');
        assert.match(await page.locator('#privacy-browser').innerText(),/Hisobdan chiqish brauzerdagi kod nusxalarini o‘chirmaydi/);
        for (const link of await page.locator('.terms-toc a').evaluateAll(links=>links.map(a=>a.getAttribute('href')))) {
            assert.equal(await page.locator(link).count(),1);
        }
        fs.mkdirSync(path.join(__dirname,'artifacts'),{recursive:true});
        for (const theme of ['dark','light']) {
            await context.addCookies([{name:'theme',value:theme,url:base}]);
            for (const width of [320,390,1440]) {
                await page.setViewportSize({width,height:1000});
                for (const route of ['settings','privacy','home']) {
                    await goto(route);
                    const bounds=await page.evaluate(()=>({width:innerWidth,body:document.body.scrollWidth,doc:document.documentElement.scrollWidth}));
                    assert.ok(bounds.body<=width && bounds.doc<=width,theme+' '+width+' '+route+' overflow '+JSON.stringify(bounds));
                }
                await goto('settings');
                await page.locator('#notificationBell').click();
                const box=await page.locator('#notificationPopover').boundingBox();
                assert.ok(box.x>=0 && box.x+box.width<=width+1,'popover fits at '+width);
                await page.keyboard.press('Escape');
                if (width !== 320) await page.screenshot({path:path.join(__dirname,'artifacts/settings-'+theme+'-'+width+'.png'),fullPage:true});
            }
        }
        await page.evaluate(()=>localStorage.setItem('codestep.editor.v2.keep-test','keep this code'));
        page.once('dialog',dialog=>dialog.accept());
        await page.locator('#resetSettings').click();
        assert.equal(await page.locator('#currentFontSize').innerText(),'16 px');
        assert.equal(await page.evaluate(()=>localStorage.getItem('codestep.editor.v2.keep-test')),'keep this code');
        assert.equal(await page.locator('#editorFontSize').inputValue(),'14');
        assert.equal(await page.locator('#notifDaily').isChecked(),false);
        assert.deepEqual(errors,[]);
        console.log('PASS: version/footer/privacy/language removal, text size and theme persistence, real in-site notifications, once/day reminder, editor preferences and cross-tab updates, safe reset, 320/390/1440px dark/light layouts.');
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
