// Personal-profile UI checks in an isolated database. Never creates live accounts.
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
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'codestep-profile-ui-'));
    let server, browser, page;
    try {
        const socket = net.createServer();
        await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
        const port = socket.address().port;
        await new Promise(resolve => socket.close(resolve));
        const base = `http://127.0.0.1:${port}`;
        server = spawn('C:/php/php.exe', ['-d','extension=pdo_sqlite','-d',`session.save_path="${temp.replaceAll('\\','/')}"`,'-S',`127.0.0.1:${port}`,'-t','public'], {
            cwd:root, windowsHide:true, stdio:'ignore', env:{...process.env, CODESTEP_DB_PATH:path.join(temp,'profile.sqlite')}
        });
        let startError;
        server.on('error', error => { startError = error; });
        for (let i = 0; i < 60; i++) {
            if (startError) throw startError;
            try { if ((await fetch(base)).ok) break; } catch (_) { }
            await delay(100);
        }
        browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const context = await browser.newContext({viewport:{width:1280,height:960}});
        await context.addCookies([{name:'visited',value:'true',url:base}]);
        // Menu and profile editing must work without font, icon or jQuery CDN availability.
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => { if (error.message !== '$ is not defined') errors.push(error.message); });
        await page.goto(base + '/?page=register', {waitUntil:'domcontentloaded'});
        await page.locator('#accountUsername').fill('madina_profile');
        await page.locator('#accountPassword').fill('Initial-password-328');
        await page.locator('#accountConfirm').fill('Initial-password-328');
        await page.locator('.account-form button').click();
        await page.waitForURL('**/?page=profile', {waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('#profileName').inputValue(), 'madina_profile');
        assert.deepEqual(await page.locator('[data-stat]').allTextContents(), ['0','0','0']);
        assert.equal(await page.locator('.drawer-menu input[value="logout"]').count(), 0);
        assert.equal(await page.locator('.drawer-menu a[href="?page=profile"]').count(), 1);
        assert.equal(await page.locator('.toolbar a[href="?page=solutions"]').count(), 0);

        await page.locator('#profileName').fill('Madina Karimova');
        await page.locator('#profileBio').fill('Python va C++ o‘rganaman.');
        await page.locator('#profileSave').click();
        await page.waitForFunction(() => !document.getElementById('profileSave').disabled);
        assert.match(await page.locator('#profileSaveStatus').innerText(), /saqlandi/);
        assert.equal(await page.locator('.profile-heading h1').innerText(), 'Madina Karimova');
        assert.equal(await page.locator('.drawer-profile-link h3').innerText(), 'Madina Karimova');
        await page.reload({waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('#profileName').inputValue(), 'Madina Karimova');
        assert.equal(await page.locator('#profileBio').inputValue(), 'Python va C++ o‘rganaman.');

        // Limits count Unicode characters consistently on client and server.
        await page.locator('#profileName').fill('😀'.repeat(80));
        await page.locator('#profileBio').fill('😀'.repeat(280));
        await page.locator('#profileSave').click();
        await page.waitForFunction(() => !document.getElementById('profileSave').disabled);
        assert.match(await page.locator('#profileSaveStatus').innerText(), /saqlandi/);
        assert.equal(await page.locator('#profileBioCount').innerText(),'280 / 280');
        await page.locator('#profileName').fill('Madina Karimova');
        await page.locator('#profileBio').fill('Python va C++ o‘rganaman.');
        await page.locator('#profileSave').click();
        await page.waitForFunction(() => !document.getElementById('profileSave').disabled);

        const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64');
        await page.locator('#profileAvatarInput').setInputFiles({name:'avatar.png',mimeType:'image/png',buffer:png});
        await page.waitForFunction(() => !document.getElementById('profileAvatarInput').disabled);
        assert.match(await page.locator('#profileAvatarStatus').innerText(), /saqlandi/);
        assert.equal(await page.locator('img[data-profile-avatar]').count(), 2);
        await page.waitForFunction(() => document.getElementById('profileAvatarMain').naturalWidth === 1);
        await page.reload({waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('img[data-profile-avatar]').count(), 2);
        await page.locator('#profileAvatarRemove').click();
        await page.waitForFunction(() => !document.getElementById('profileAvatarInput').disabled);
        assert.equal(await page.locator('img[data-profile-avatar]').count(), 0);
        assert.equal(await page.locator('#profileAvatarDrawer').innerText(), 'M');

        const previousToken = await page.locator('#profilePage').getAttribute('data-csrf-token');
        await page.locator('#profileCurrentPassword').fill('Initial-password-328');
        await page.locator('#profileNewPassword').fill('Updated-password-329');
        await page.locator('#profileConfirmPassword').fill('Updated-password-329');
        await page.locator('#profilePasswordSave').click();
        await page.waitForFunction(() => !document.getElementById('profilePasswordSave').disabled);
        assert.match(await page.locator('#profilePasswordStatus').innerText(), /yangilandi/);
        assert.notEqual(await page.locator('#profilePage').getAttribute('data-csrf-token'), previousToken);
        assert.equal(await page.locator('#profileNewPassword').inputValue(), '');
        // Other profile actions use the rotated token without reloading.
        await page.locator('#profileBio').fill('Yangi maqsad: algoritmlarni o‘rganish.');
        await page.locator('#profileSave').click();
        await page.waitForFunction(() => !document.getElementById('profileSave').disabled);
        assert.match(await page.locator('#profileSaveStatus').innerText(), /saqlandi/);

        fs.mkdirSync(path.join(root,'tests/artifacts'),{recursive:true});
        for (const theme of ['dark','light']) {
            await context.addCookies([{name:'theme',value:theme,url:base}]);
            await page.reload({waitUntil:'domcontentloaded'});
            await page.evaluate(() => window.scrollTo(0,0));
            await page.screenshot({path:path.join(root,`tests/artifacts/profile-${theme}.png`),fullPage:true});
            if (await page.locator('#menuToggle').getAttribute('aria-expanded') === 'false') await page.locator('#menuToggle').click();
            await page.waitForFunction(() => document.getElementById('navigationDrawer').getBoundingClientRect().left === 0);
            assert.equal(await page.locator('.drawer-menu a[aria-current="page"]').innerText(), 'Profil');
            const aligned = await page.locator('.drawer-profile-link').evaluate(link => {
                const avatar = link.querySelector('[data-profile-avatar]').getBoundingClientRect();
                const box = link.getBoundingClientRect();
                return Math.abs((avatar.left+avatar.right)/2-(box.left+box.right)/2)<1;
            });
            assert.equal(aligned,true,'avatar is centered');
            await page.locator('#navigationDrawer').screenshot({path:path.join(root,`tests/artifacts/profile-menu-${theme}.png`)});
            await page.locator('.drawer-menu a[href="?page=profile"]').focus();
            await page.keyboard.press('Escape');
        }
        await page.setViewportSize({width:390,height:844});
        await page.evaluate(() => window.scrollTo(0,0));
        await page.screenshot({path:path.join(root,'tests/artifacts/profile-mobile.png'),fullPage:true});
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true);
        await page.locator('.profile-footer button').click();
        await page.waitForURL('**/?page=home', {waitUntil:'domcontentloaded'});
        await page.goto(base + '/?page=profile', {waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('#profileForm').count(),0);
        assert.equal(await page.locator('[data-profile-avatar]').count(),0,'guest does not see account photo');
        await page.goto(base + '/?page=login', {waitUntil:'domcontentloaded'});
        await page.locator('#accountUsername').fill('madina_profile');
        await page.locator('#accountPassword').fill('Updated-password-329');
        await page.locator('.account-form button').click();
        await page.waitForURL('**/?page=profile', {waitUntil:'domcontentloaded'});
        assert.equal(await page.locator('#profileName').inputValue(),'Madina Karimova');
        const contactResponse = await context.request.get(base + '/?page=contact');
        assert.equal(contactResponse.status(),200);
        const contactHtml = await contactResponse.text();
        assert.equal((contactHtml.match(/href="https:\/\/instagram\.com\/codestep_programming"/g) || []).length,2);
        assert.ok(!contactHtml.includes('instagram.com/_mamurov_t1m'));
        assert.deepEqual(errors,[]);
        console.log('PASS: automatic profile, editing/reload, private avatar upload/removal, password rotation, subsequent save/logout/login, profile menu/no drawer logout, both themes, centered avatar, mobile, Instagram.');
    } catch (error) {
        if (page) console.error('Failure page:',page.url(),(await page.locator('body').innerText()).slice(0,1500));
        throw error;
    } finally {
        if (browser) await browser.close();
        if (server && server.exitCode === null) {
            const stopped = new Promise(resolve => server.once('exit',resolve));
            server.kill();
            await stopped;
        }
        const safeTemp = path.resolve(temp);
        assert.equal(path.dirname(safeTemp).toLowerCase(),path.resolve(os.tmpdir()).toLowerCase());
        assert.ok(path.basename(safeTemp).startsWith('codestep-profile-ui-'));
        fs.rmSync(safeTemp,{recursive:true,force:true,maxRetries:5,retryDelay:150});
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
