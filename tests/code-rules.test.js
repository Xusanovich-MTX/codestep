'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const rules = require('../public/assest/js/code-rules.js');

function ids(code, language, options) {
    return rules.analyze(code, language, options).map((finding) => finding.rule);
}

test('ships a browser global and usable, clean starter programs', () => {
    const context = {};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/assest/js/code-rules.js'), 'utf8'), context);
    assert.equal(typeof context.CodeStepRules.analyze, 'function');
    assert.deepEqual(rules.analyze(rules.examples.python, 'python'), []);
    assert.deepEqual(rules.analyze(rules.examples.cpp, 'cpp'), []);
    assert.deepEqual(rules.analyze('anything', 'javascript'), []);
});

test('starter examples follow the selected C++ brace style without changing the defaults', () => {
    const defaultCpp = rules.examples.cpp;
    assert.equal(rules.example('python'), rules.examples.python);
    assert.equal(rules.example('cpp'), defaultCpp);
    assert.equal(rules.example('javascript'), '');
    assert.equal(rules.example('constructor'), '');
    assert.match(defaultCpp, /^#include <iostream>\nusing namespace std;\n/);
    assert.doesNotMatch(defaultCpp, /std::/);
    ['cin >>', 'cout <<', 'cerr <<'].forEach((stream) => assert.ok(defaultCpp.includes(stream)));
    ['cpp', 'c++', 'cxx', 'CPP'].forEach((language) => {
        const source = rules.example(language, { cppStyle: 'allman' });
        assert.match(source, /^#include <iostream>\nusing namespace std;\n/);
        assert.doesNotMatch(source, /std::/);
        assert.match(source, /int main\(\)\n\{/);
        assert.match(source, /\n    if \([^\n]+\)\n    \{/);
        assert.deepEqual(rules.analyze(source, language, { cppStyle: 'allman' }), []);
    });
    assert.equal(rules.examples.cpp, defaultCpp);
    assert.deepEqual(rules.analyze(rules.example('cpp', { cppStyle: 'kr' }), 'cpp'), []);
});

test('Python checks actionable style issues with accurate locations', () => {
    const findings = rules.analyze('def BadName(a,b):\n\tif a==None:  \n        return b==False\n', 'python');
    const found = new Set(findings.map((finding) => finding.rule));
    ['python-function-name', 'comma-spacing', 'indent-tabs', 'operator-spacing',
        'python-none-comparison', 'trailing-whitespace', 'python-bool-comparison'].forEach((rule) => assert.ok(found.has(rule), rule));
    assert.equal(findings.find((finding) => finding.rule === 'python-none-comparison').line, 2);
    assert.equal(findings.find((finding) => finding.rule === 'indent-tabs').column, 1);
    assert.ok(findings.every((finding) => finding.line > 0 && finding.column > 0 && finding.message));
});

test('Python multiline, prefixed, escaped strings and comments remain opaque', () => {
    const source = [
        'message = r"if a==None: import os,sys"',
        'documentation = """',
        'def BadName(a,b):',
        '  a==None  ',
        '"""',
        "quote = 'don\\'t compare x==False'",
        'formatted = f"value: {message}"',
        'nested = f"{mapping["if a==None"]}"',
        '# value==None',
        'value = None'
    ].join('\n');
    assert.deepEqual(rules.analyze(source, 'python'), []);
});

test('Python multiline continuation indentation and defaults are accepted', () => {
    const source = 'result = sum(\n  [1, 2, 3]\n)\n' +
        'result = 1 + \\\n  2\n\n\n' +
        'def calculate(value=1, count: int = 2):\n    return value + count\n';
    assert.deepEqual(rules.analyze(source, 'python'), []);
    assert.ok(ids('if True:\n  value = 1\n', 'python').includes('python-indent'));
});

test('Python nested blocks, blank lines and literal whitespace avoid indentation false positives', () => {
    const source = [
        'text = """keep the next line exactly',
        '  two spaces and a tab\t  ',
        '"""',
        '',
        'if text:',
        '    for number in range(2):',
        '        total = sum(',
        '          [1, 2, number]',
        '        )',
        '        print(text, total)',
        '    print("Block finished")',
        '',
        'print("All finished")',
        ''
    ].join('\n');
    assert.deepEqual(rules.analyze(source, 'python'), []);
    const style = rules.analyze('if True:\n  print("valid Python")\n', 'python');
    assert.equal(style.find((item) => item.rule === 'python-indent').severity, 'warning');
    assert.ok(style.every((item) => item.severity !== 'error'));
});

test('Python strict comparisons and every augmented assignment require surrounding spaces', () => {
    ['<', '>', '**=', '//=', '@=', '+=', '-=', '*=', '/=', '%=', '&=', '|=', '^=', '<<=', '>>='].forEach((operator) => {
        assert.ok(ids('value' + operator + '2', 'python').includes('operator-spacing'), operator);
        assert.ok(ids('value ' + operator + '2', 'python').includes('operator-spacing'), operator);
        assert.ok(ids('value' + operator + ' 2', 'python').includes('operator-spacing'), operator);
        assert.ok(!ids('value ' + operator + ' 2', 'python').includes('operator-spacing'), operator);
    });
    assert.deepEqual(rules.analyze('std::vector<int> values;', 'cpp'), []);
});

test('Python code and standalone comments use separate line length limits', () => {
    assert.ok(ids('# ' + 'a'.repeat(71), 'python').includes('python-comment-length'));
    assert.ok(!ids('# ' + 'a'.repeat(70), 'python').includes('python-comment-length'));
    assert.ok(ids('value = "' + 'a'.repeat(70) + '"', 'python').includes('python-line-length'));
    assert.ok(!ids('value = "' + 'a'.repeat(69) + '"', 'python').includes('python-line-length'));
});

test('Python docstrings use 72 columns while ordinary multiline values use 79', () => {
    assert.ok(ids('"""' + 'a'.repeat(70) + '"""', 'python').includes('python-docstring-length'));
    assert.ok(ids('def explain():\n    """' + 'a'.repeat(67) + '"""', 'python').includes('python-docstring-length'));
    assert.ok(!ids('value = """' + 'a'.repeat(62) + '"""', 'python').includes('python-docstring-length'));
});

test('Python handles comma spacing beside string arguments without inspecting contents', () => {
    assert.ok(ids('print("one","two")', 'python').includes('comma-spacing'));
    assert.deepEqual(rules.analyze('print("one", "x==None")', 'python'), []);
    assert.ok(ids('items = [ 1, 2 ]', 'python').includes('python-bracket-spacing'));
    assert.ok(!ids('items = [\n    1,\n    2,\n]', 'python').includes('python-bracket-spacing'));
});

test('Python definition spacing respects attached decorators and initial comments', () => {
    assert.ok(ids('def first():\n    pass\n\ndef second():\n    pass', 'python').includes('python-definition-spacing'));
    assert.deepEqual(rules.analyze('# Module comment\n\n@decorate\ndef first():\n    pass', 'python'), []);
    assert.deepEqual(rules.analyze('def first():\n    pass\n\n\n@decorate\ndef second():\n    pass', 'python'), []);
});

test('C++ accepts namespace directives and both qualified and unqualified standard names', () => {
    assert.deepEqual(rules.analyze('using namespace std;\n', 'cpp'), []);
    assert.deepEqual(rules.analyze('int main() {\n    using namespace std;\n}', 'cpp'), []);
    for (const qualified of [false, true]) {
        const prefix = qualified ? 'std::' : '';
        const source = [
            '#include <iostream>',
            '#include <string>',
            '#include <vector>',
            ...(qualified ? [] : ['using namespace std;']),
            '',
            'int main() {',
            `    ${prefix}string name;`,
            `    ${prefix}vector<int> values = {1, 2};`,
            `    ${prefix}cin >> name;`,
            `    ${prefix}cout << name << values[0] << "\\n";`,
            `    ${prefix}cerr << "Done\\n";`,
            '    return 0;',
            '}'
        ].join('\n');
        assert.deepEqual(rules.analyze(source, 'cpp'), [], qualified ? 'std:: style' : 'namespace style');
    }
});

test('C++ still detects null pointers and manual ownership with a namespace directive', () => {
    const findings = rules.analyze('using namespace std;\nint main() {\n    int* value = new int(1);\n    if (value != NULL) {\n        delete value;\n    }\n}', 'cpp');
    ['cpp-nullptr', 'cpp-manual-memory'].forEach((rule) => assert.ok(findings.some((finding) => finding.rule === rule)));
    assert.ok(!ids('class Value {\n    Value(const Value&) = delete;\n};', 'cpp').includes('cpp-manual-memory'));
});

test('C++ raw strings, comments and character literals cannot create diagnostics', () => {
    const source = [
        '#include <string>',
        'const auto text = u8R"tag(',
        'using namespace std;',
        'int* value = new int; delete value; NULL',
        ')tag";',
        'const auto second = R"(class bad_name { NULL })";',
        "char quote = '\\'';",
        '// using namespace std; NULL new delete',
        '/* using namespace std;\nclass bad_name {}; NULL */',
        'const auto value = 1\'000;'
    ].join('\n');
    assert.deepEqual(rules.analyze(source, 'cpp'), []);
});

test('C++ continued line comments and escaped newlines remain opaque', () => {
    assert.deepEqual(rules.analyze('// comment \\\nusing namespace std; NULL new delete\nint main() {\n}', 'cpp'), []);
    assert.deepEqual(rules.analyze('const char* text = "hello \\\nNULL new delete";', 'cpp'), []);
});

test('C++ brace checks respect selected style and do not flag initializer lists', () => {
    const allman = 'int main()\n{\n    int values[] = {1, 2};\n    return 0;\n}';
    assert.ok(ids(allman, 'cpp').includes('cpp-brace-style'));
    assert.deepEqual(rules.analyze(allman, 'cpp', { cppStyle: 'allman' }), []);
    assert.ok(ids('int main() {\n}', 'cpp', { cppStyle: 'allman' }).includes('cpp-brace-style'));
    assert.deepEqual(rules.analyze('int main() {\n    int values[] = {1, 2};\n}', 'cpp'), []);
});

test('C++ declaration braces are checked through indentation, blank lines and comments', () => {
    const source = 'namespace sample\n\n// Types\n{\n    class Value\n    // Members\n    {\n    };\n}';
    const findings = rules.analyze(source, 'cpp');
    assert.deepEqual(findings.filter((finding) => finding.rule === 'cpp-brace-style').map((finding) => finding.line), [4, 7]);
    assert.deepEqual(rules.analyze(source, 'cpp', { cppStyle: 'allman' }), []);
});

test('ASCII naming checks do not diagnose fragments of Unicode identifiers', () => {
    assert.deepEqual(rules.analyze('class d\u00e9mo:\n    pass', 'python'), []);
    assert.deepEqual(rules.analyze('class d\u00e9mo {};\nnamespace D\u00e9mo {}', 'cpp'), []);
    assert.deepEqual(rules.analyze('namespace Outer::d\u00e9mo {}', 'cpp'), []);
    assert.ok(ids('class bad_name {};', 'cpp').includes('cpp-class-name'));
    assert.ok(ids('class bad_name:\n    pass', 'python').includes('python-class-name'));
});

test('C++ overloaded operator declarations do not require operator-name spaces', () => {
    assert.deepEqual(rules.analyze('bool operator==(const Value& other) const;', 'cpp'), []);
    assert.deepEqual(rules.analyze('Value& operator=(const Value&) = default;', 'cpp'), []);
});

test('unterminated strings and block comments have lexical errors', () => {
    assert.equal(rules.analyze('text = "unterminated', 'python').find((item) => item.rule === 'unclosed-literal').severity, 'error');
    assert.ok(ids('/* unfinished', 'cpp').includes('unclosed-literal'));
    assert.ok(ids('auto text = R"tag(unclosed', 'cpp').includes('unclosed-literal'));
});

test('guidance covers manual principles and corrects source misconceptions', () => {
    const python = rules.guide('python');
    const cpp = rules.guide('cpp');
    const pythonText = python.map((entry) => entry.body).join(' ');
    const cppText = cpp.map((entry) => entry.body).join(' ');
    assert.match(pythonText, /izoh emas/);
    assert.match(pythonText, /pylint/);
    assert.match(pythonText, /autopep8/);
    const indentation = python.find((entry) => entry.title === 'Indentatsiya');
    assert.ok(indentation.example);
    assert.deepEqual(rules.analyze(indentation.example, 'python'), []);
    assert.match(indentation.example, /^print\([^\n]+\)\nif True:\n {4}print\([^\n]+\)\nprint\(/);
    assert.match(cppText, /Rule of Zero/);
    assert.match(cppText, /C\+\+23/);
    assert.match(cppText, /Har bir blokdan keyin ; talab qilinmaydi/);
    assert.match(cppText, /4 bo'sh joy.*79 belgilik/);
    const namespace = cpp.find((entry) => entry.title === 'Namespace');
    assert.equal(namespace.kind, 'review');
    ['using namespace std;', 'std::cout', 'std::cin', '<iostream>', '<string>', '<vector>'].forEach((term) => assert.ok(namespace.body.includes(term)));
    [python, cpp].forEach((guide) => {
        for (let number = 1; number <= 10; number += 1) {
            assert.ok(guide.some((entry) => entry.title.startsWith(number + '. ')));
        }
        assert.ok(guide.every((entry) => entry.title && entry.body && ['automatic', 'review'].includes(entry.kind)));
    });
    python[0].title = 'changed';
    assert.notEqual(rules.guide('python')[0].title, 'changed');
});
