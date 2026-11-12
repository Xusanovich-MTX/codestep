const assert = require('node:assert/strict');
const completion = require('../public/assest/js/editor-completion.js');

function hints(marked, language = 'cpp') {
    const offset = marked.indexOf('|');
    return completion.completionData(marked.replace('|', ''), offset, language);
}
function apply(source, edit) {
    assert.ok(edit && !edit.error && !edit.already, 'expected an applicable edit');
    return source.slice(0, edit.from) + edit.text + source.slice(edit.to);
}

let result = hints('int main() { co| }');
assert.ok(result.list.some(item => item.text === 'std::cout' && item.detail === '<iostream>'));
result = hints('std::cou|t');
assert.ok(result.list.some(item => item.text === 'cout'));
assert.equal(apply('std::cout', { from: result.from, to: result.to, text: 'cout' }), 'std::cout');
assert.ok(!result.list.some(item => item.text.startsWith('std::')));
assert.ok(hints('std::|').list.some(item => item.text === 'vector'));
assert.equal(hints('other::co|'), null);
assert.equal(hints('object.co|'), null);
assert.equal(hints('object->co|'), null);
assert.equal(hints('#include <ve|'), null);
assert.ok(hints('using namespace std;\nco|').list.some(item => item.text === 'cout'));
assert.ok(hints('int firstValue = 3;\nfir|').list.some(item => item.text === 'firstValue'));
assert.ok(!hints('const char* text = "secretIdentifier";\nsec|').list.some(item => item.text === 'secretIdentifier'));
assert.ok(hints('pri|', 'python').list.some(item => item.text === 'print'));
assert.ok(hints('math.sq|rt', 'python').list.some(item => item.text === 'sqrt'));
assert.equal(hints('obj.pr|', 'python'), null);
assert.ok(hints('quantity = 3\nquan|', 'python').list.some(item => item.text === 'quantity'));
for (const source of ['// co|', '/* co| */', '"co|"', "'c|'"]) assert.equal(hints(source), null);
assert.equal(hints('R"tag(first\nco|\n)tag"'), null);
assert.equal(hints('u8R"x(// co|)x"'), null);
assert.equal(hints("u8'c|'"), null);
for (const literal of ["1'000", "0xDEAD'BEEF", "0b1010'0011", "1.25'00"]) {
    assert.ok(hints(`auto value = ${literal};\nco|`).list.some(item => item.text === 'std::cout'));
    const source = `auto value = ${literal};\n`;
    assert.equal(completion.snippetEdit(source, source.length, source.length, 'cpp', 'cin').header, 'iostream');
}
assert.equal(hints('// ignored \\\nco|'), null);
assert.equal(hints('# pri|', 'python'), null);
assert.equal(hints('"""one\npri|\n"""', 'python'), null);
assert.equal(hints('value = "escaped \\" pri|"', 'python'), null);
assert.ok(hints('|').list.length <= 60);
assert.equal(completion.completionData('a'.repeat(131073), 131073, 'cpp'), null);

const starter = '#include <iostream>\n\nint main() { std::cout << 1; }\n';
const withNamespace = apply(starter, completion.namespaceEdit(starter));
assert.match(withNamespace, /#include <iostream>\n\nusing namespace std;/);
assert.ok(withNamespace.includes('std::cout << 1;'));
assert.deepEqual(completion.namespaceEdit(withNamespace), { already: true });
assert.deepEqual(completion.namespaceEdit('using /* global */ namespace\nstd;\nint main() {}'), { already: true });
assert.deepEqual(completion.namespaceEdit('using namespace ::std;'), { already: true });
const commentFake = '// using namespace std;\n#include <iostream>\nint main() {}';
assert.equal((apply(commentFake, completion.namespaceEdit(commentFake)).match(/using namespace std;/g) || []).length, 2);
const localOnly = '#include <iostream>\nvoid foo() { using namespace std; }\n';
assert.ok(!completion.namespaceEdit(localOnly).already);
const raw = 'const char* source = R"x(\n#include <vector>\nusing namespace std;\n)x";\nint main() {}';
assert.ok(apply(raw, completion.namespaceEdit(raw)).startsWith('using namespace std;\n'));
const macro = '#define FAKE \\\nusing namespace std;\nint main() {}';
assert.ok(!completion.namespaceEdit(macro).already);
assert.ok(completion.namespaceEdit('/* unclosed comment').error);
assert.equal(apply('', completion.namespaceEdit('')), '#include <iostream>\n\nusing namespace std;\n\n');
const crlf = '#include <iostream>\r\nint main() {}';
assert.ok(apply(crlf, completion.namespaceEdit(crlf)).includes('\r\n\r\nusing namespace std;\r\n'));

const vectorHeader = completion.includeEdit(withNamespace, 'vector');
const withVector = apply(withNamespace, vectorHeader);
assert.ok(withVector.indexOf('#include <vector>') < withVector.indexOf('using namespace std;'));
assert.equal(completion.includeEdit(withVector, 'vector'), null);
assert.equal(completion.includeEdit(withVector, 'untrusted-header'), null);
assert.ok(completion.includeEdit(raw, 'vector'), 'raw-string include must not suppress real header');
assert.ok(completion.includeEdit('#if 0\n#include <vector>\n#endif\nint main() {}', 'vector'), 'conditional include is not assumed active');

const indented = 'int main() {\n    \n}\n';
const cursor = indented.indexOf('\n') + 5;
const forEdit = completion.snippetEdit(indented, cursor, cursor, 'cpp', 'for');
assert.equal(forEdit.text, 'for (int i = 0; i < ; ++i) {\n        \n    }');
const allmanFor = completion.snippetEdit(indented, cursor, cursor, 'cpp', 'for', { cppStyle: 'allman' });
assert.equal(allmanFor.text, 'for (int i = 0; i < ; ++i)\n    {\n        \n    }');
assert.ok(apply(indented, forEdit).startsWith('int main() {\n    for (int i'));
const vectorEdit = completion.snippetEdit(withNamespace, withNamespace.length, withNamespace.length, 'cpp', 'vector');
assert.equal(vectorEdit.text, 'vector<int> values;');
assert.equal(vectorEdit.header, 'vector');
assert.equal(completion.snippetEdit('', 0, 0, 'cpp', 'vector').text, 'std::vector<int> values;');
assert.equal(completion.snippetEdit('// here', 7, 7, 'cpp', 'cout'), null);
const python = 'if True:\n    ';
const pythonFor = completion.snippetEdit(python, python.length, python.length, 'python', 'for');
assert.equal(pythonFor.text, 'for i in range():\n        pass');
assert.equal(apply('old()', completion.snippetEdit('old()', 0, 5, 'python', 'print')), 'print()');
console.log('PASS: completion prefixes/ranges, opaque literals/comments, bounded suggestions, namespace/header edits and indented snippets');
