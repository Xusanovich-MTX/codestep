// Read-only navigation and guide checks against the local site. No account changes.
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const {chromium} = require('../.tools/browser/node_modules/playwright');
const base = process.env.CODESTEP_TEST_URL || 'http://codestep';
const artifacts = path.join(__dirname, 'artifacts');

(async () => {
    const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    try {
        fs.mkdirSync(artifacts,{recursive:true});
        const context = await browser.newContext({viewport:{width:1440,height:960}});
        await context.addCookies([{name:'visited',value:'true',url:base}]);
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => { if (error.message !== '$ is not defined') errors.push(error.message); });
        const goto = async route => {
            const response = await page.goto(base + '/?page=' + route,{waitUntil:'domcontentloaded'});
            assert.equal(response.status(),200);
        };
        const toggle = page.locator('#menuToggle');
        const drawer = page.locator('#navigationDrawer');
        const checkOpen = async () => {
            assert.equal(await toggle.getAttribute('aria-expanded'),'true');
            assert.equal(await drawer.evaluate(el => el.inert),false);
            const menuBox = await drawer.boundingBox();
            const mainBox = await page.locator('.main-content').boundingBox();
            assert.equal(menuBox.x,0);
            assert.ok(mainBox.x >= menuBox.width,'desktop content stays beside the menu');
        };
        await goto('home');
        await checkOpen();
        assert.equal(await page.locator('#drawerOverlay').count(),0);
        await toggle.click();
        assert.equal(await toggle.getAttribute('aria-expanded'),'false');
        assert.equal(await drawer.evaluate(el => el.inert),true);
        assert.equal((await page.locator('.main-content').boundingBox()).x,0);
        await goto('section&section=I');
        assert.equal(await toggle.getAttribute('aria-expanded'),'false','desktop choice survives navigation');
        await toggle.click();
        await page.waitForFunction(() => document.getElementById('navigationDrawer').getBoundingClientRect().x === 0);
        await checkOpen();
        await page.keyboard.press('Escape');
        assert.equal(await toggle.getAttribute('aria-expanded'),'true','Escape outside menu leaves it open');
        await drawer.locator('a[href="?page=home"]').focus();
        await page.keyboard.press('Escape');
        assert.equal(await toggle.getAttribute('aria-expanded'),'false');
        assert.equal(await toggle.evaluate(el => document.activeElement === el),true);

        // A saved desktop collapse never hides navigation on a small screen.
        await page.setViewportSize({width:390,height:844});
        await page.waitForFunction(() => !document.getElementById('navigationDrawer').inert);
        for (const theme of ['dark','light']) {
            await context.addCookies([{name:'theme',value:theme,url:base}]);
            await goto('terms');
            assert.equal(await toggle.isVisible(),false);
            assert.equal(await drawer.evaluate(el => el.inert),false);
            assert.equal(await drawer.locator('.menu-item[aria-current="page"]').getAttribute('href'),'?page=settings');
            for (const link of await drawer.locator('a.menu-item').all()) {
                const box = await link.boundingBox();
                assert.ok(box && box.x >= 0 && box.x+box.width <= 390 && box.y >= 0 && box.y+box.height <= 844,'each mobile destination stays visible');
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true);
            assert.equal(await page.locator('.terms-card').count(),9);
            assert.equal(await page.locator('.terms-toc a').evaluateAll(links => links.every(link => document.querySelector(link.hash))),true);
            await page.screenshot({path:path.join(artifacts,`navigation-mobile-${theme}.png`)});
            await page.locator('.terms-toc a[href="#terms-saving"]').click();
            assert.ok((await page.locator('#terms-saving').boundingBox()).y >= 70,'section heading clears toolbar');
            await page.evaluate(() => window.scrollTo(0,document.body.scrollHeight));
            const footer = await page.locator('.footer-bottom').boundingBox();
            const menu = await drawer.boundingBox();
            assert.ok(footer.y+footer.height <= menu.y,'last content clears bottom navigation');
            assert.equal(await drawer.isVisible(),true,'navigation remains visible after scrolling');
        }
        await page.setViewportSize({width:1024,height:768});
        assert.equal(await toggle.getAttribute('aria-expanded'),'false','returning to desktop restores collapsed state');
        await toggle.click();
        await page.waitForFunction(() => document.getElementById('navigationDrawer').getBoundingClientRect().x === 0);
        await checkOpen();
        await goto('code-editor&topic=15&problem=1.1.21');
        await page.waitForFunction(() => document.querySelector('.CodeMirror'));
        assert.equal(await page.locator('.editor-layout').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length),1,'editor stacks in remaining desktop width');
        await page.screenshot({path:path.join(artifacts,'navigation-editor-1024.png')});
        for (const size of [{width:320,height:640},{width:844,height:390},{width:1023,height:768}]) {
            await page.setViewportSize(size);
            await page.waitForFunction(() => !document.getElementById('navigationDrawer').inert);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true);
            assert.ok((await drawer.boundingBox()).y >= 0);
        }
        await page.setViewportSize({width:1440,height:960});
        for (const theme of ['dark','light']) {
            await context.addCookies([{name:'theme',value:theme,url:base}]);
            await goto('terms');
            await checkOpen();
            await page.screenshot({path:path.join(artifacts,`navigation-desktop-${theme}.png`)});
        }
        await goto('settings');
        await page.locator('.container a[href="?page=terms"]').click();
        await page.waitForURL('**/?page=terms',{waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('.footer a[href="?page=terms"]').count(),1);
        await page.locator('.terms-faq summary').first().click();
        assert.equal(await page.locator('.terms-faq details').first().getAttribute('open'),'');
        assert.deepEqual(errors,[]);
        console.log('PASS: desktop open/collapse/persistence/focus, mobile always-visible destinations, resize, both themes, editor width, Terms anchors/FAQ/settings/footer links.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
