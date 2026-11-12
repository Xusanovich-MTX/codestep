'use strict';
const assert = require('node:assert/strict');
const notifications = require('../public/assest/js/notifications.js');
const catalog = [{id: '14', title: 'Xotirada ifodalash'}, {id: '15', title: 'Chop etish'}];
let state = notifications.observeTopics(null, catalog, true, 1000);
assert.equal(state.initialized, true);
assert.equal(state.items.length, 0, 'First visit establishes the baseline without old topic notifications');
state = notifications.observeTopics(state, catalog.concat({id: '16', title: 'Yangi mavzu'}), true, 2000);
assert.equal(state.items.length, 1);
assert.equal(state.items[0].id, 'topic:16');
assert.equal(state.items[0].href, '?page=topic-detail&id=16');
state = notifications.markRead(state, 'topic:16');
assert.equal(state.items[0].read, true);
state = notifications.observeTopics(state, catalog.concat({id: '16', title: 'Yangi mavzu'}), true, 3000);
assert.equal(state.items.length, 1, 'Revisiting does not duplicate a topic or reset its read state');
assert.equal(state.items[0].read, true);
state = notifications.dismiss(state, 'topic:16');
state = notifications.observeTopics(state, [{id: '16', title: 'Yangi mavzu'}], true, 4000);
assert.equal(state.items.length, 0, 'Dismissed topic stays dismissed');
state = notifications.observeTopics(state, [{id: '17', title: 'O‘chirilgan sozlama'}], false, 5000);
state = notifications.observeTopics(state, [{id: '17', title: 'O‘chirilgan sozlama'}], true, 6000);
assert.equal(state.items.length, 0, 'Disabled updates still advance the baseline');

const settings = {dailyReminder: true, reminderTime: '20:00'};
const before = new Date(2026, 9, 11, 19, 59);
const due = new Date(2026, 9, 11, 20, 0);
const after = new Date(2026, 9, 11, 22, 30);
assert.equal(notifications.addDailyReminder(state, settings, before, true).items.length, 0);
assert.equal(notifications.addDailyReminder(state, settings, due, false).items.length, 0, 'Background tab does not create reminders');
assert.equal(notifications.addDailyReminder(state, {dailyReminder: false, reminderTime: '20:00'}, due, true).items.length, 0);
state = notifications.addDailyReminder(state, settings, due, true);
assert.equal(state.items.length, 1);
assert.equal(state.items[0].id, 'daily:2026-10-11');
state = notifications.dismiss(state);
state = notifications.addDailyReminder(JSON.parse(JSON.stringify(state)), settings, after, true);
assert.equal(state.items.length, 0, 'A dismissed reminder must not return after reload on the same day');
state = notifications.addDailyReminder(state, settings, new Date(2026, 9, 12, 20, 1), true);
assert.equal(state.items.length, 1, 'A new day gets its own reminder');
assert.equal(state.items[0].id, 'daily:2026-10-12');
assert.equal(notifications.addDailyReminder(null, {...settings, reminderTime: '25:99'}, due, true).items.length, 0);

const invalid = notifications.normalizeState({initialized: 'true', topicIds: ['14', '14', '<script>', 15], items: [
    {id: 'test:1', kind: 'test', title: '<img onerror="bad()">', body: 'Safe text', href: 'javascript:alert(1)', createdAt: 1},
    {id: 'test:1', kind: 'test', title: 'Duplicate', createdAt: 1},
    {id: 'test:2', kind: 'unsupported', title: 'Bad kind', createdAt: 2},
    {id: 'test:3', kind: 'test', title: 'Bad timestamp', createdAt: Infinity}
]});
assert.equal(invalid.initialized, false);
assert.deepEqual(invalid.topicIds, ['14']);
assert.equal(invalid.items.length, 1);
assert.equal(invalid.items[0].href, '?page=home', 'External and script URLs never enter rendered links');
assert.equal(invalid.items[0].title, '<img onerror="bad()">', 'Notification titles are plain text, not HTML');
assert.equal(notifications.normalizeState({items: Array.from({length: 70}, (_, i) => ({id: 'test:' + i, kind: 'test', title: 'Item', createdAt: i}))}).items.length, 50);
assert.deepEqual(notifications.normalizeCatalog([{id: '1', title: 'Topic'}, {id: '1', title: 'Duplicate'}, {id: '<script>', title: 'Invalid'}]), [{id: '1', title: 'Topic', href: '?page=topic-detail&id=1'}]);
console.log('Notification state tests passed.');
