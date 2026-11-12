'use strict';
// Isolated browser checks: no running PHP server or AI credentials are needed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('../.tools/browser/node_modules/playwright');
const chatScript = fs.readFileSync(path.join(__dirname, '../public/assest/js/chat.js'), 'utf8');

(async () => {
  const browser = await chromium.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true
  });
  try {
    for (const basePath of ['/', '/codestep/']) {
      const context = await browser.newContext();
      const page = await context.newPage();
      const errors = [];
      const requests = [];
      let answer = { status: 200, body: JSON.stringify({ reply: 'Avval masala shartini tahlil qiling.' }) };
      page.on('pageerror', error => errors.push(error.message));
      await context.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === basePath + 'assest/js/chat.js') {
          await route.fulfill({ contentType: 'application/javascript; charset=utf-8', body: chatScript });
        } else if (url.searchParams.get('api') === 'chat') {
          requests.push({ url: url.href, method: route.request().method(), body: route.request().postDataJSON() });
          if (answer === 'pending') return;
          if (answer === 'offline') return route.abort('failed');
          await route.fulfill({ contentType: 'application/json', ...answer });
        } else {
          await route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<!doctype html><html><head><meta charset="utf-8"></head><body><script src="assest/js/chat.js?v=test"></script></body></html>' });
        }
      });
      await page.goto('http://chat.test' + basePath + '?page=topics');
      await page.locator('#aiChatBtn').click();

      const send = async (message, enter = false) => {
        await page.locator('#aiChatInput').fill(message);
        if (enter) await page.locator('#aiChatInput').press('Enter');
        else await page.locator('#aiChatSend').click();
      };
      const recovered = async () => {
        await page.waitForFunction(() => !document.getElementById('aiChatSend').disabled);
        assert.equal(await page.locator('#aiChatInput').isEnabled(), true);
        assert.equal(await page.locator('.ai-typing').count(), 0);
      };
      const lastReply = () => page.locator('.ai-bot').last().innerText();

      await send('  Sikl qanday ishlaydi?  ', true);
      await recovered();
      assert.equal(await lastReply(), 'Avval masala shartini tahlil qiling.');
      assert.deepEqual(requests[0], {
        url: 'http://chat.test' + basePath + 'index.php?api=chat',
        method: 'POST',
        body: { message: 'Sikl qanday ishlaydi?', history: [] }
      });

      answer = { status: 200, body: JSON.stringify({ reply: '## Tushuntirish\nMasala **bosqichma-bosqich** yechiladi.\n\n1. Shartni ajrating.\n2. `n` qiymatini tekshiring.\n\n> Eslatma: shartdagi chegarani tekshiring.\n\n```text\nn = 4\n```\n\n[Qo‘llanma](https://example.test/guide)' }) };
      await send('Tushuntirib ber');
      await recovered();
      assert.equal(await page.locator('.ai-bot h4').last().innerText(), 'Tushuntirish');
      assert.equal(await page.locator('.ai-bot strong').last().innerText(), 'bosqichma-bosqich');
      assert.equal(await page.locator('.ai-bot ol li').count(), 2);
      assert.match(await page.locator('.ai-bot blockquote').last().innerText(), /Eslatma/);
      assert.equal(await page.locator('.ai-bot pre code').last().innerText(), 'n = 4');
      assert.equal(await page.locator('.ai-bot a').last().getAttribute('rel'), 'noopener noreferrer');

      answer = { status: 503, body: JSON.stringify({ reply: 'AI xizmati hozir sozlanmagan.' }) };
      await send('Salom');
      await recovered();
      assert.equal(await lastReply(), 'AI xizmati hozir sozlanmagan.');

      answer = { status: 405, contentType: 'text/html', body: '<h1>Private server diagnostics</h1>' };
      await send('Yana urinaman');
      await recovered();
      assert.match(await lastReply(), /HTTP 405/);
      assert.doesNotMatch(await page.locator('#aiChatMsgs').innerText(), /Private server diagnostics/);

      answer = { status: 502, body: '{}' };
      await send('Xatodan keyin');
      await recovered();
      assert.match(await lastReply(), /HTTP 502/);

      for (const body of ['null', '{"reply":"   "}', '{"reply":42}']) {
        answer = { status: 200, body };
        await send('Bo‘sh javob');
        await recovered();
        assert.match(await lastReply(), /bo‘sh javob/);
      }

      answer = 'offline';
      await send('Ulanish');
      await recovered();
      assert.match(await lastReply(), /Internet ulanishini tekshiring/);

      await page.clock.install();
      answer = 'pending';
      const previousCount = requests.length;
      await send('Uzoq so‘rov');
      await page.waitForFunction(() => document.querySelector('.ai-typing'));
      assert.equal(await page.locator('#aiChatInput').isDisabled(), true);
      await page.locator('#aiChatSend').dispatchEvent('click');
      await page.clock.runFor(40001);
      await recovered();
      assert.equal(requests.length, previousCount + 1, 'Disabled controls prevent duplicate requests');
      assert.match(await lastReply(), /kutilganidan uzoq/);

      answer = { status: 200, body: JSON.stringify({ reply: '<img src=x onerror="alert(1)"> Qayta ishladi.' }) };
      await send('Qayta yuborish');
      await recovered();
      assert.match(await lastReply(), /Qayta ishladi/);
      assert.equal(await page.locator('.ai-bot img').count(), 0, 'Replies remain plain text');

      const previousConversation = await page.locator('#aiChatMsgs').innerText();
      await page.goto('http://chat.test' + basePath + 'index.php?page=problems');
      await page.locator('#aiChatBtn').click();
      assert.equal(await page.locator('#aiChatMsgs').innerText(), previousConversation, 'Conversation survives page navigation.');

      await page.locator('#aiChatNew').click();
      assert.equal(await page.locator('.ai-msg-row.user').count(), 0, 'New conversation starts empty.');
      await page.locator('#aiChatHistoryToggle').click();
      assert.ok(await page.locator('.ai-chat-history-item').count() >= 2, 'Older conversations remain in history.');
      await page.locator('.ai-chat-history-item').filter({ hasText: 'Sikl qanday ishlaydi?' }).click();
      assert.match(await page.locator('#aiChatMsgs').innerText(), /Sikl qanday ishlaydi\?/);
      assert.match(await page.locator('#aiChatMsgs').innerText(), /Qayta ishladi\./);

      await page.locator('#aiChatHistoryToggle').click();
      await page.locator('#aiChatHistoryList .ai-chat-history-row').filter({ hasText: 'Yangi suhbat' }).locator('.ai-chat-history-delete').click();
      assert.equal(await page.locator('.ai-chat-history-item').count(), 1, 'Selected conversation can be deleted individually.');
      page.once('dialog', dialog => dialog.accept());
      await page.locator('#aiChatClearHistory').click();
      assert.equal(await page.locator('.ai-chat-history-item').count(), 0, 'All conversations can be cleared.');
      assert.match(await page.locator('#aiChatHistoryList').innerText(), /Hozircha suhbatlar yo‘q/);
      assert.deepEqual(errors, []);
      await context.close();
    }
    console.log('PASS: chat sends and recovers after HTTP, malformed/empty reply, network and timeout errors; root/subdirectory routing and safe text rendering.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
