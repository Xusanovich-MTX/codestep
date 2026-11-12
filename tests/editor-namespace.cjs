'use strict';

const assert = require('node:assert/strict');
const { request } = require('../.tools/browser/node_modules/playwright');

const baseUrl = (process.env.CODESTEP_TEST_URL || 'http://codestep').replace(/\/+$/, '') + '/';

function standardLibraryProgram(qualified) {
    const prefix = qualified ? 'std::' : '';
    return [
        '#include <algorithm>',
        '#include <iostream>',
        '#include <string>',
        '#include <vector>',
        ...(qualified ? [] : ['using namespace std;']),
        '',
        'int main() {',
        `    ${prefix}string name;`,
        `    ${prefix}vector<int> values(3);`,
        `    ${prefix}cin >> name;`,
        '    for (int& value : values) {',
        `        ${prefix}cin >> value;`,
        '    }',
        `    ${prefix}sort(values.begin(), values.end());`,
        `    ${prefix}cout << name << ":";`,
        '    for (int value : values) {',
        `        ${prefix}cout << " " << value;`,
        '    }',
        `    ${prefix}cout << "\\n";`,
        '    return 0;',
        '}',
        ''
    ].join('\n');
}

async function main() {
    // A fresh HTTP-only guest session never signs in or writes solution history.
    const context = await request.newContext({ baseURL: baseUrl, timeout: 35000 });
    try {
        const login = await context.get('?page=login');
        assert.equal(login.status(), 200, 'Login page must establish a guest session');
        const html = await login.text();
        const csrfInput = [...html.matchAll(/<input\b[^>]*>/gi)]
            .map(match => match[0])
            .find(input => /\bname\s*=\s*["']csrf_token["']/i.test(input));
        const csrfToken = csrfInput && /\bvalue\s*=\s*["']([a-f0-9]{64})["']/i.exec(csrfInput)?.[1];
        assert.ok(csrfToken, 'Login form must expose a valid CSRF token');

        const cases = [
            {
                name: 'namespace directive with cin, cout, string, vector and sort',
                payload: { language: 'cpp', code: standardLibraryProgram(false), stdin: 'Ali\n9 -2 4\n' },
                output: 'Ali: -2 4 9\n'
            },
            {
                name: 'equivalent std:: qualified standard-library program',
                payload: { language: 'cpp', code: standardLibraryProgram(true), stdin: 'Ali\n9 -2 4\n' },
                output: 'Ali: -2 4 9\n'
            },
            {
                name: 'problem 1.1.21 accepts namespace-style output',
                payload: {
                    language: 'cpp', topic: '15', problem: '1.1.21', stdin: '',
                    code: [
                        '#include <iostream>',
                        'using namespace std;',
                        '',
                        'int main() {',
                        '    for (int index = 0; index < 5; ++index) {',
                        '        cout << "Dasturlash asoslari";',
                        '        cout << (index == 4 ? "\\n" : " ");',
                        '    }',
                        '    return 0;',
                        '}',
                        ''
                    ].join('\n')
                },
                output: Array(5).fill('Dasturlash asoslari').join(' ') + '\n',
                accepted: true
            }
        ];

        // Each bounded WASM compilation finishes before the next request begins.
        for (const scenario of cases) {
            console.log('RUN: ' + scenario.name);
            const started = Date.now();
            const response = await context.post('api/execute-code.php', {
                data: new URLSearchParams(scenario.payload).toString(),
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                timeout: 35000
            });
            const result = await response.json();
            assert.equal(response.status(), 200, scenario.name + ': ' + JSON.stringify(result));
            assert.equal(result.error, false, scenario.name + ': ' + result.output);
            assert.equal(result.output, scenario.output, scenario.name + ' must produce the exact console result');
            assert.equal(Object.hasOwn(result, 'saved_run_id'), false, 'Guest execution must not save account history');
            if (scenario.accepted) {
                assert.equal(result.verdict?.status, 'accepted');
                assert.equal(result.verdict.scope, 'output');
                assert.ok(result.verdict.total_checks > 0);
                assert.equal(result.verdict.checks_passed, result.verdict.total_checks);
                assert.equal(result.verdict.topic, 15);
                assert.equal(result.verdict.problem, '1.1.21');
            }
            console.log('PASS: ' + scenario.name + ' (' + ((Date.now() - started) / 1000).toFixed(1) + 's)');
        }
        console.log('PASS: 3 sequential guest HTTP C++ execution checks; both namespace styles and judged output work.');
    } finally {
        await context.dispose();
    }
}

main().catch(error => {
    console.error('FAIL: ' + (error.stack || error));
    process.exitCode = 1;
});
