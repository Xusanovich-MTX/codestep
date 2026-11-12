(function () {
    'use strict';

    if (!window.CodeMirror || typeof window.CodeMirror.runMode !== 'function') return;
    const modes = { python: { name: 'python', version: 3 }, cpp: 'text/x-c++src' };

    document.querySelectorAll('.sample-source code[data-language]').forEach(block => {
        const mode = modes[block.dataset.language];
        if (!mode) return;
        const highlighted = document.createDocumentFragment();
        // Tokenize text without changing whitespace or interpreting code as HTML.
        window.CodeMirror.runMode(block.textContent, mode, (text, style) => {
            if (!style) {
                highlighted.append(document.createTextNode(text));
                return;
            }
            const token = document.createElement('span');
            token.className = style.split(/\s+/).map(name => 'cm-' + name).join(' ');
            token.textContent = text;
            highlighted.append(token);
        });
        block.replaceChildren(highlighted);
    });
}());
