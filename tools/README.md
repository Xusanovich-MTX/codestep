# Code editor static tools

Requirements: PHP 7.4+ with `proc_open`, Python 3.10+ and pip. Python 3.13 is
tested on Windows. The web document root must be `public/`; keep this directory
outside the document root.

From the project directory run:

```powershell
python tools/setup-code-tools.py
python -I -S tools/test_code_tools.py
php tools/test-code-tools.php
```

To verify HTTP status codes, CSRF and the complete request path, start the site
(`php -S 127.0.0.1:8898 -t public`) and in another terminal run
`python tools/test_code_tools_http.py http://127.0.0.1:8898`.

The setup script installs pinned dependencies into `tools/python-packages/`
and records the Python executable in `tools/code-tools.config.json`. Rerun it
after moving the project or changing Python. Alternatively set the server's
`CODESTEP_PYTHON` environment variable to the full executable path (without
quotes). OSPanel/Apache must be allowed to read these files and create temporary
files. No global PATH change or global pip installation is required.

`POST /api/code-tools.php` accepts JSON or form fields `code`, `language`
(`python`/`cpp`), `action` (`check`/`format`, or Python-only `syntax`), and optional `cpp_style`
(`kr`/`allman`). Send the session CSRF token as `X-CSRF-Token`. Check results
contain `diagnostics` with 1-based line/column, `severity`, `rule`, `message`,
plus the actually used `tools` and a summary `message`. The lightweight `syntax`
action only parses Python, and powers live indentation/syntax diagnostics.
Format results contain
`code`, `tools`, and `message`. Errors use an appropriate HTTP status and
`{ "error": true, "message": "..." }`.

Python uses AST syntax checking and flake8 with 79-character code lines,
72-character comments/docstrings, complexity 10, and no project/user config or
`noqa` suppression. Formatting uses autopep8, four spaces and a 79-character
limit. It only returns code if the before/after AST is identical. Some long
strings/comments and recommendations need manual edits. Semantic changes such
as variable renaming or replacing overloaded comparisons are never automatic.

C++ uses the bundled clang-format with four spaces, 79 columns, grouped system
and local includes, and selectable K&R/Allman braces. Checks report format
differences. This static check is **not a C++ compiler or complete syntax/type analysis**;
cppcheck, clang-tidy, pylint, and Black are not claimed as installed/running.
Browser guidance covers additional naming and design recommendations.

All editor entry points share `public/pages/editor-workspace.php` and the
browser rules in `public/assest/js/code-rules.js`. Both language tabs include
their language-specific guide and the shared review principles. Live checks
are advisory; the Check button adds the server results described above.
C++ displays the same 79-column limit used by clang-format, and loading a
starter example follows the selected K&R/Allman style. Design, meaningful
names, resource ownership and project-wide conventions still require review.

Run browser rule regressions with `node --test tests/code-rules.test.js`.
For the full editor check, start `php -S 127.0.0.1:8765 -t public`, then run
`node tests/editor-browser.cjs`. That script uses the local Playwright install
in `.tools/browser/node_modules/` and Chrome at its standard Windows path.
It verifies both language guides, token colors in both themes, visible indentation,
live Python indentation errors, real formatters, both brace styles, undo, drafts,
keyboard actions, responsive layout and real WASM execution in both languages.
To test the running OSPanel site instead of the temporary PHP server, set
`$env:CODESTEP_TEST_URL = 'http://codestep'` before running the same browser test.
The test uses a fresh browser context and leaves existing browser drafts intact.

The static tools never import, evaluate, compile or run submitted code. PHP invokes a
fixed helper using array arguments without a shell, with a 12-second deadline,
64 KB/8000-line source bounds and bounded output. Python starts with `-I -S`;
dependencies are explicit local files. The clang-format subprocess has a
6-second deadline. Temporary files are closed/removed on completion. Serving
untrusted public traffic still requires normal web-server request/concurrency
limits. The separate Run action now uses the project-local WebAssembly runner;
see [code-execution.md](../docs/code-execution.md) for setup, limits and verification.
