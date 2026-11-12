const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('../.tools/browser/node_modules/playwright');

const baseUrl = (process.env.CODESTEP_TEST_URL || 'http://codestep').replace(/\/$/, '');
const origin = new URL(baseUrl).origin;
const catalog = JSON.parse(fs.readFileSync(path.join(__dirname, '../public/data/programming-topics.json'), 'utf8'));
const chapters = catalog.sections.flatMap(section => section.chapters.map(chapter => ({ section, chapter })));
const topics = chapters.flatMap(({ section, chapter }) => chapter.topics.map(topic => ({ section, chapter, topic })));
const generalChapters = chapters.filter(({ chapter }) => chapter.id);
const artifactDirectory = path.join(__dirname, 'artifacts');
const urlFor = params => `${baseUrl}/?${new URLSearchParams(params)}`;
const paramsOf = href => Object.fromEntries(new URL(href, `${baseUrl}/`).searchParams);
const assertLink = (href, params, message) => assert.deepEqual(paramsOf(href), Object.fromEntries(Object.entries(params).map(([key, value]) => [key, String(value)])), message);

assert.equal(catalog.sections.length, 3);
assert.deepEqual(catalog.sections.map(section => section.chapters.length), [9, 10, 7]);
assert.equal(chapters.length, 26);
assert.equal(topics.length, 64);
assert.deepEqual(generalChapters.map(({ chapter }) => chapter.id), [65, 79]);
assert.equal(topics.reduce((count, { topic }) => count + topic.problems.length, 0), 100);

(async () => {
    const browser = await chromium.launch({
        executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        headless: true
    });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    const forbiddenRequests = [];
    await context.route('**/*', route => {
        const request = route.request();
        const target = new URL(request.url());
        if (target.origin !== origin && ['http:', 'https:'].includes(target.protocol)) return route.abort();
        // Navigation checks never execute code or write account/server data.
        if (!['GET', 'HEAD'].includes(request.method())) {
            forbiddenRequests.push(`${request.method()} ${target.pathname}`);
            return route.abort();
        }
        return route.continue();
    });
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    page.on('pageerror', error => {
        // The remote jQuery script is deliberately blocked with other CDNs.
        if (error.message !== '$ is not defined') errors.push(error.message);
    });

    async function snapshot(html = null) {
        return page.evaluate(source => {
            const doc = source === null ? document : new DOMParser().parseFromString(source, 'text/html');
            const links = selector => Array.from(doc.querySelectorAll(selector), element => ({
                href: element.getAttribute('href'),
                title: element.querySelector('h2')?.textContent.trim() || element.textContent.trim(),
                section: element.getAttribute('data-section')
            }));
            return {
                heading: doc.querySelector('.container h1')?.textContent.trim(),
                sections: links('.book-section-card'),
                chapters: links('.book-chapter-card'),
                topics: links('.book-topic-card'),
                problemLinks: links('.curriculum-problem-link'),
                navigation: links('.curriculum-navigation a[rel]'),
                breadcrumbs: links('.curriculum-breadcrumb a'),
                current: doc.querySelector('.curriculum-breadcrumb [aria-current="page"]')?.textContent.trim(),
                back: doc.querySelector('.programming-topic-detail > .back-button')?.getAttribute('href'),
                menuLinks: links('#navigationDrawer a'),
                empty: doc.querySelector('.curriculum-empty')?.textContent.trim(),
                friendlyError: doc.querySelector('.book-notice, .book-empty')?.textContent.trim()
            };
        }, html);
    }

    async function getDocument(params, expectedStatus = 200) {
        const response = await context.request.get(urlFor(params));
        assert.equal(response.status(), expectedStatus, `${response.url()} status`);
        const html = await response.text();
        assert.doesNotMatch(html, /\b(?:Warning|Fatal error|Parse error|Notice):/, `${response.url()} PHP diagnostics`);
        const document = await snapshot(html);
        await response.dispose();
        return document;
    }

    async function goto(params) {
        const response = await page.goto(urlFor(params), { waitUntil: 'domcontentloaded' });
        assert.equal(response.status(), 200, page.url());
    }

    async function clickRoute(selector, params) {
        await Promise.all([
            page.waitForURL(url => Object.entries(params).every(([key, value]) => url.searchParams.get(key) === String(value)), { waitUntil: 'domcontentloaded' }),
            page.locator(selector).click()
        ]);
        assertLink(page.url(), params);
    }

    function assertNoOldTopicsMenu(document) {
        assert.equal(document.menuLinks.some(link => paramsOf(link.href).page === 'topics'), false, 'obsolete topics menu link');
        assert.equal(document.menuLinks.some(link => /Barcha mavzular/.test(link.title)), false, 'obsolete topics menu label');
    }

    function expectedBreadcrumbs(section, chapter, topic) {
        const expected = [{ page: 'home' }];
        if (section) expected.push({ page: 'section', section: section.number });
        if (chapter && topic) expected.push({ page: 'chapter', chapter: chapter.number });
        return expected;
    }

    function assertBreadcrumbs(document, expected) {
        assert.equal(document.breadcrumbs.length, expected.length);
        document.breadcrumbs.forEach((link, index) => assertLink(link.href, expected[index], 'breadcrumb destination'));
        assert.ok(document.current, 'current breadcrumb has aria-current');
    }

    async function checkOverflow(label) {
        const layout = await page.evaluate(() => {
            const width = window.innerWidth;
            const selectors = '.book-section-card, .book-chapter-card, .book-topic-card, .book-page-heading, .book-home-heading, .curriculum-detail-heading, .curriculum-problem, .curriculum-breadcrumb';
            const outside = Array.from(document.querySelectorAll(selectors)).filter(element => {
                const rect = element.getBoundingClientRect();
                return rect.width > 0 && (rect.right > width + 1 || rect.left < -1);
            }).map(element => element.className);
            return { width, documentWidth: document.documentElement.scrollWidth, bodyWidth: document.body.scrollWidth, outside };
        });
        assert.ok(layout.documentWidth <= layout.width + 1 && layout.bodyWidth <= layout.width + 1, `${label}: horizontal overflow ${JSON.stringify(layout)}`);
        assert.deepEqual(layout.outside, [], `${label}: content outside viewport`);
    }

    try {
        await goto({ page: 'home' });
        await page.waitForFunction(() => {
            const splash = document.getElementById('splash');
            return !splash || (getComputedStyle(splash).opacity === '0' && getComputedStyle(splash).pointerEvents === 'none');
        });
        const home = await snapshot();
        assert.equal(home.sections.length, 3);
        assert.deepEqual(home.sections.map(link => link.section), ['I', 'II', 'III']);
        home.sections.forEach((link, index) => {
            assertLink(link.href, { page: 'section', section: catalog.sections[index].number });
            assert.equal(link.title, catalog.sections[index].title);
        });
        assertNoOldTopicsMenu(home);

        for (const section of catalog.sections) {
            await clickRoute(`.book-section-card[data-section="${section.number}"]`, { page: 'section', section: section.number });
            const actual = await snapshot();
            assert.equal(actual.heading, section.title);
            assert.deepEqual(actual.chapters.map(link => paramsOf(link.href)), section.chapters.map(chapter => ({ page: 'chapter', chapter: chapter.number })));
            assert.deepEqual(actual.chapters.map(link => link.title), section.chapters.map(chapter => chapter.title));
            assert.equal(actual.topics.length, 0, 'section does not mix in topic links');
            assertNoOldTopicsMenu(actual);
            await clickRoute('.book-back', { page: 'home' });
        }
        console.log('PASS: three home cards and exact 9/10/7 chapter lists via clicks');

        let listedTopics = 0;
        for (const { section, chapter } of chapters) {
            const actual = await getDocument({ page: 'chapter', chapter: chapter.number });
            const expectedIds = [...(chapter.id ? [chapter.id] : []), ...chapter.topics.map(topic => topic.id)];
            assert.equal(actual.heading, chapter.title);
            assert.deepEqual(actual.topics.map(link => Number(paramsOf(link.href).id)), expectedIds, `${chapter.number} child topic IDs`);
            actual.topics.forEach(link => assertLink(link.href, { page: 'topic-detail', id: paramsOf(link.href).id }));
            assert.equal(actual.chapters.length, 0);
            assertNoOldTopicsMenu(actual);
            listedTopics += actual.topics.length;
        }
        assert.equal(listedTopics, 66);

        let problemCount = 0;
        for (const { section, chapter, topic } of topics) {
            const actual = await getDocument({ page: 'topic-detail', id: topic.id });
            assert.equal(actual.heading, topic.title);
            assertLink(actual.back, { page: 'chapter', chapter: chapter.number });
            assertBreadcrumbs(actual, expectedBreadcrumbs(section, chapter, topic));
            const position = chapter.topics.findIndex(item => item.id === topic.id);
            const siblings = [chapter.topics[position - 1], chapter.topics[position + 1]].filter(Boolean);
            assert.deepEqual(actual.navigation.map(link => Number(paramsOf(link.href).id)), siblings.map(item => item.id), `${topic.number} same-chapter previous/next`);
            assert.deepEqual(actual.problemLinks.map(link => paramsOf(link.href)), topic.problems.map(problem => ({ page: 'code-editor', topic: String(topic.id), problem: problem.number })), `${topic.number} editor links`);
            problemCount += actual.problemLinks.length;
            if (!topic.problems.length) assert.match(actual.empty, /hali masalalar/);
        }
        assert.equal(problemCount, 100);
        for (const { section, chapter } of generalChapters) {
            const actual = await getDocument({ page: 'topic-detail', id: chapter.id });
            assertLink(actual.back, { page: 'chapter', chapter: chapter.number });
            assertBreadcrumbs(actual, expectedBreadcrumbs(section));
            assert.equal(actual.navigation.length, 0);
            assert.ok(actual.current.startsWith(`${chapter.number}-bob.`));
        }
        for (const record of [catalog.introduction, ...catalog.references]) {
            const actual = await getDocument({ page: 'topic-detail', id: record.id });
            assertLink(actual.back, { page: 'home' });
            assertBreadcrumbs(actual, [{ page: 'home' }]);
            assert.equal(actual.navigation.length, 0);
        }
        console.log('PASS: 26 chapters, 64 topics, two general chapters, 100 editor links and sibling navigation');

        await goto({ page: 'home' });
        await clickRoute('.book-section-card[data-section="I"]', { page: 'section', section: 'I' });
        await clickRoute('.book-chapter-card[href="?page=chapter&chapter=1.1"]', { page: 'chapter', chapter: '1.1' });
        await clickRoute('.book-topic-card[href="?page=topic-detail&id=14"]', { page: 'topic-detail', id: '14' });
        await clickRoute('#problem-1-1-1 .curriculum-problem-link', { page: 'code-editor', topic: '14', problem: '1.1.1' });
        await page.waitForFunction(() => window.codeEditor);
        assert.equal(await page.locator('#editorWorkspace').getAttribute('data-problem-key'), '14:1.1.1');
        assert.equal(await page.locator('#editorProblemStatement').innerText(), topics.find(({ topic }) => topic.id === 14).topic.problems[0].statement);
        for (const language of ['cpp', 'python']) {
            await page.locator(`.editor-language[data-lang="${language}"]`).click();
            assertLink(await page.locator('#sampleProblemsLink').getAttribute('href'), { page: 'sample-problems', topic: '14', problem: '1.1.1', lang: language });
            await clickRoute('#sampleProblemsLink', { page: 'sample-problems', topic: '14', problem: '1.1.1', lang: language });
            assert.equal(await page.locator('.sample-card').count(), 3);
            const samples = await snapshot();
            assertBreadcrumbs(samples, [{ page: 'home' }, { page: 'section', section: 'I' }, { page: 'chapter', chapter: '1.1' }, { page: 'topic-detail', id: '14' }]);
            for (const href of await page.locator('.sample-return').evaluateAll(links => links.map(link => link.getAttribute('href')))) {
                assertLink(href, { page: 'code-editor', topic: '14', problem: '1.1.1', lang: language });
            }
            await clickRoute('.sample-problems > .sample-return', { page: 'code-editor', topic: '14', problem: '1.1.1', lang: language });
            await page.waitForFunction(() => window.codeEditor);
            assert.equal(await page.locator(`.editor-language[data-lang="${language}"]`).getAttribute('aria-pressed'), 'true');
        }
        console.log('PASS: home → section → chapter → problem → editor → samples; C++/Python return context');

        for (const section of catalog.sections.slice(1)) {
            const chapter = section.chapters.find(item => item.topics.length);
            const topic = chapter.topics.find(item => !item.problems.length);
            await goto({ page: 'home' });
            await clickRoute(`.book-section-card[data-section="${section.number}"]`, { page: 'section', section: section.number });
            await clickRoute(`.book-chapter-card[href="?page=chapter&chapter=${chapter.number}"]`, { page: 'chapter', chapter: chapter.number });
            await clickRoute(`.book-topic-card[href="?page=topic-detail&id=${topic.id}"]`, { page: 'topic-detail', id: String(topic.id) });
            assert.match(await page.locator('.curriculum-empty').innerText(), /hali masalalar/);
            await clickRoute('.programming-topic-detail > .back-button', { page: 'chapter', chapter: chapter.number });
            await clickRoute('.book-back', { page: 'section', section: section.number });
            const general = section.chapters.find(item => item.id);
            await clickRoute(`.book-chapter-card[href="?page=chapter&chapter=${general.number}"]`, { page: 'chapter', chapter: general.number });
            assert.equal(await page.locator('.book-topic-number').count(), 0, 'general chapter has no fabricated subtopic');
            await clickRoute('.book-general-card', { page: 'topic-detail', id: String(general.id) });
            assert.equal(await page.locator('.curriculum-navigation a[rel]').count(), 0);
        }
        console.log('PASS: real clicks through empty II/III topics and general-problem chapters');

        for (const [params, heading] of [
            [{ page: 'section' }, 'Bo‘lim topilmadi'],
            [{ page: 'section', section: 'IV' }, 'Bo‘lim topilmadi'],
            [{ page: 'section', 'section[]': 'I' }, 'Bo‘lim topilmadi'],
            [{ page: 'chapter' }, 'Bob topilmadi'],
            [{ page: 'chapter', chapter: '9.9' }, 'Bob topilmadi'],
            [{ page: 'chapter', 'chapter[]': '1.1' }, 'Bob topilmadi']
        ]) {
            const response = await context.request.get(urlFor(params), {maxRedirects:0});
            assert.equal(response.status(), 302);
            const destination = {page:'home', notice:params.page === 'section' ? 'section-not-found' : 'chapter-not-found'};
            assertLink(response.headers().location, destination);
            await response.dispose();
            const actual = await getDocument(destination);
            assert.ok(actual.friendlyError.startsWith(heading));
            assert.equal(actual.sections.length, 3);
        }
        const redirect = await context.request.get(urlFor({ page: 'topics' }), { maxRedirects: 0 });
        assert.equal(redirect.status(), 302);
        assertLink(redirect.headers().location, { page: 'home' });
        await redirect.dispose();
        const redirected = await page.goto(urlFor({ page: 'topics' }), { waitUntil: 'domcontentloaded' });
        assert.equal(redirected.status(), 200);
        assertLink(page.url(), { page: 'home' });
        assert.equal(await page.locator('.book-section-card').count(), 3);
        console.log('PASS: malformed/missing routes return home with a recovery notice; legacy topics redirects home');

        fs.mkdirSync(artifactDirectory, { recursive: true });
        const responsivePages = [
            ['home', { page: 'home' }],
            ['section-III', { page: 'section', section: 'III' }],
            ['chapter-3-2', { page: 'chapter', chapter: '3.2' }],
            ['topic-14', { page: 'topic-detail', id: '14' }]
        ];
        for (const theme of ['dark', 'light']) {
            await context.addCookies([{name:'theme', value:theme, url:baseUrl}]);
            for (const width of [320, 390, 1024, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                for (const [name, params] of responsivePages) {
                    await goto(params);
                    await page.evaluate(() => window.scrollTo(0, 0));
                    await checkOverflow(`${name} ${theme} ${width}px`);
                    if (theme === 'light') {
                        const color = await page.locator('body').evaluate(element => getComputedStyle(element).backgroundColor);
                        assert.notEqual(color, 'rgb(255, 255, 255)', 'day background must remain cream');
                    }
                    if (['home', 'section-III'].includes(name) && [390, 1440].includes(width)) {
                        await page.screenshot({ path: path.join(artifactDirectory, `book-${name}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                    }
                }
            }
        }
        assert.deepEqual(errors, [], 'unexpected browser exceptions');
        assert.equal(forbiddenRequests.some(request => /execute-code|solutions\.php|register|login/.test(request)), false, 'navigation attempted a server mutation or code execution');
        console.log('PASS: dark/cream layouts at 320/390/1024/1440px, no overflow; eight book screenshots saved');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
