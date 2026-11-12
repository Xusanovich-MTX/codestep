# Code execution providers

Last updated: 2026-10-12.

The editor sends `POST /api/execute-code.php` with the page session cookie,
`X-CSRF-Token`, and JSON or form fields `language` (`cpp` or `python`), `code`,
and optional `stdin`. Code is limited to 64 KiB and stdin to 16 KiB.

Execution uses one explicitly selected provider. This installation selects
**Wandbox**, a public compilation service. CodeStep sends the submitted code and
stdin to `https://wandbox.org/api/compile.json` over verified HTTPS. The request
does not include a CodeStep account, session cookie or password, and does not
request a permanent sharing link. Code and stdin can themselves contain personal
data; the editor discloses the transfer beside Run and links to the privacy page.
No claim is made about Wandbox's retention period or service availability.

The server-side `tools/execution.config.json` selects the provider and compiler IDs:

```json
{
  "provider": "wandbox",
  "compilers": { "cpp": "gcc-13.2.0", "python": "cpython-3.12.7" }
}
```

Wandbox currently receives C++ with `-std=c++20` using GCC 13.2.0, or Python
using CPython 3.12.7. Compiler IDs are server configuration, never request input.
Changing them requires checking the service's compiler list and rerunning the
integration checks. `CODESTEP_EXECUTION_PROVIDER=local` or `wandbox` overrides
the configured provider. A nonempty `CODESTEP_RUNNER_URL` takes priority and
selects a private runner. Configuration or provider failures return an error;
they do not silently retry another provider or send code to another service.

Wandbox response regression checks: `php tests/wandbox-result.test.php`.
The endpoint tests in `tests/test_execute_code.py` force the local provider and
use a private mock service; fixture programs are never submitted to Wandbox.
The separate live browser smoke test is opt-in:

```powershell
$env:CODESTEP_LIVE_WANDBOX = '1'
node tests/wandbox-browser.cjs
```

It submits synthetic Python/C++ examples through the actual Run button and API,
including stdin, compiler warnings, compilation/runtime failures and an exercise
with three server-selected inputs. It uses a fresh guest browser context and
does not add runs to a real user's account.

The optional **local** provider runs C++17 and Python 3.12 inside WebAssembly
guests, with no host filesystem, process, network or inherited environment
access. In local mode execution does not send code to a public service. Static
syntax checks and formatting remain local to CodeStep and are separate from Run.

The browser editor uses local CodeMirror 5.65.16 with matching vendored hint,
comment and folding addons. `editor-completion.js` supplies bounded C++/Python
suggestions and snippets; C++ snippets add required headers and respect a global
`using namespace std;`. New C++ exercise drafts include that directive. Existing
drafts and explicit `std::` code remain valid. `editor-productivity.js` provides
literal search/replace, go-to-line, undoable file import, comments, folding,
clipboard controls, expanded view and persistent font/wrapping preferences.
Imported UTF-8 `.cpp`, `.py` and `.txt` files are limited to 64 KiB, with overwrite
confirmation and a guard against replacing edits made while a file is being read.

Editor helper regressions: `node tests/editor-completion.test.js` and
`node tests/code-rules.test.js`. `node tests/editor-features-browser.cjs` exercises
the UI against `http://codestep` (or `CODESTEP_TEST_URL`) with execution responses
stubbed. `node tests/editor-namespace.cjs` separately checks actual C++
execution with namespace and explicit `std::` styles, plus a judged exercise.

Problem pages also submit `topic` and `problem` to the same endpoint. The server
validates that pair against the curriculum and applies `includes/problem-rules.php`;
request-supplied expected answers or verdicts are ignored. The generic editor still
returns ordinary output without claiming to solve a problem.

The 55 current exercises have public acceptance requirements in the editor.
Fixed answers check output, binary width, signs, row layout, or decimal precision.
Input-reading exercises run three separate executions with positive, zero,
and negative server-selected inputs. A failed check stops the run. These requests
have a 100-second outer client deadline; single-test runs retain the 35-second
client deadline. The local guest limits below apply only to local execution.
Wandbox applies its own runtime limits; CodeStep does not promise that they match
the local provider. The editor shows the test input that produced the displayed
result, and invalidates an old verdict
as soon as the code changes. Syntax/style analysis is labelled separately.

Free-form exercises use explicit field or table formats. Passing these checks
returns `structure_passed`, **not** `accepted`: the checker cannot prove that a
poem, personal information, book, or city fact is meaningful or true. A matching
output is also not proof of a particular implementation or algorithm. The UI
states these limits alongside the requirements and never treats runtime or
infrastructure failures as accepted solutions.

Pure checker regression tests: `php tests/problem-judge.test.php`.
API/runner contract tests: `python tests/test_execute_code.py` (set
`CODESTEP_TEST_PHP` if PHP is not on PATH).

## Wandbox transport

Wandbox uses PHP cURL with certificate and hostname verification. Keep a trusted
CA bundle configured for PHP; do not disable TLS validation to work around a
certificate error. CSRF validation remains required on the CodeStep endpoint.
The gateway limits a Wandbox request to 25 seconds, bounds the incoming response
size, checks the response structure, and converts the result to the editor's
`{ "output": "...", "error": false }` contract. On success, `output` contains
stdout; optional `diagnostics` carries compiler warnings and stderr separately
so they do not contaminate exercise answers. Compiler warnings or stderr alone
do not mean compilation or execution failed; the service's exit status and
termination signal determine failure. Empty stdout is a valid result.
HTTP errors, malformed results and timeouts are errors, never accepted solutions.

CodeStep's source, stdin and returned-output limits still apply. These gateway
limits do not establish Wandbox's guest memory, CPU, filesystem or networking
policy. The local WebAssembly limits below must not be presented as guarantees
of the external service. No external code is submitted merely by opening a page.

## Optional local WebAssembly provider

Install the project-local assets on Windows x64 with Node.js and Python available:

```powershell
python tools/setup-code-tools.py
python tools/setup-cpp-wasm.py
python tools/setup-python-wasi.py
```

The C++ installer writes `tools/wasm-runner.config.json` with the Node executable
and `enabled: true`. Python uses the executable in `tools/code-tools.config.json`.
All assets stay outside `public/`; system settings and PATH are unchanged.
Downloads are pinned and SHA-256 verified, with licenses beside the binaries.
Select `CODESTEP_EXECUTION_PROVIDER=local` after setup to use these assets; a
separate service is not required. Local execution does not require PHP cURL, but
does require `proc_open`. The Python WASI installer currently supports Windows
x64 only, so it cannot be copied unchanged to a Linux server.

C++ uses the [binji WASM Clang/LLD distribution](https://github.com/binji/wasm-clang/tree/648c4a89997a351eef75cdaec3ef5b89d4937dec)
(Clang 8, C++17, libc++). The compiler, linker and output program all run as WASM
with an in-memory filesystem. Platform headers, native libraries, threads and
C++ exceptions are not supported by this toolchain. Python uses
[CPython 3.12.0 with embedded WASI stdlib](https://github.com/vmware-labs/webassembly-language-runtimes/releases/tag/python%2F3.12.0%2B20231211-040d5a6)
under Wasmtime; third-party Python packages and desktop/network/process modules
are unavailable. The static syntax checker uses the server's Python version.

The C++ adapter explicitly links the compiler-rt builtins archive already bundled
in the pinned sysroot. libc++ algorithms such as `sort` require those helpers;
omitting the archive can cause undefined-symbol errors such as `__lttf2`.

Programs receive stdin and return actual stdout/stderr or compiler/runtime errors.
The editor keeps each language's latest result while switching tabs. If the code
or stdin changes, a separate notice marks that result as outdated, including
errors and responses that arrive after an edit. Clearing the editor discards its
result, including any pending response. The editor checks the 16 KiB UTF-8 stdin
limit before submitting; the endpoint independently enforces the same limit.
The browser bounds the complete execution request (including reading its response)
to 35 seconds, shows a timeout error, and re-enables Run even if the transport
does not acknowledge cancellation. Updated editor assets use versioned URLs.

In local mode each request gets fresh guest state. Execution is limited to
10 seconds, 128 MiB
guest memory, 64 KiB output, 64 KiB source and 16 KiB stdin. C++ compilation has
a 24-second overall worker deadline, with capped compiler and virtual-filesystem
memory (512 MiB per compiler instance, 256 MiB for memfs). PHP imposes an outer
30-second deadline and a project lock admits one execution at a time. A busy
runner returns HTTP 429; time, memory and output failures are shown in the editor.

Set `CODESTEP_LOCAL_RUNNER=0` to disable the local runner. When the local provider
is selected without an enabled runner, the endpoint returns HTTP 503. Disabling
the local runner does not disable a separately selected Wandbox/private provider.

## Optional private runner

An optional **trusted isolated private runner** takes precedence over the
configured local or Wandbox provider.
To use one, enable PHP cURL and set these server-side environment variables:

```text
CODESTEP_RUNNER_URL=https://your-private-runner.example/execute
CODESTEP_RUNNER_TOKEN=your-service-authentication-token
```

The URL is read only from server configuration. Plain HTTP is accepted only for
`localhost`, `127.0.0.1`, or `[::1]`, for a runner listening on a local interface.
TLS certificate validation is enabled for HTTPS; redirects are disabled. The
token is optional for a local service, and is sent as `Authorization: Bearer ...`
when configured. Configure authentication and network access controls before
exposing a runner beyond loopback. Never point this at a script that executes
programs directly on the web host.

The endpoint sends JSON:

```json
{
  "language": "python",
  "code": "print(input())",
  "stdin": "Salom\n",
  "limits": {
    "wall_time_seconds": 10,
    "memory_bytes": 134217728,
    "output_bytes": 65536
  }
}
```

The external runner must enforce these limits itself, including compilation, terminate
all descendant processes on timeout, and return HTTP 200 JSON for both program
success and compiler/runtime errors:

```json
{"output":"Salom\n","error":false}
```

`output` must be a UTF-8 string of at most 64 KiB; `error` must be a JSON boolean.
On a compilation/runtime error, return the actual diagnostics in `output` and
`error: true`. The PHP gateway forwards this contract unchanged and has a
3-second connection timeout, 15-second request timeout, and bounded response
buffer. It returns HTTP 502 for unavailable/malformed runner responses and
HTTP 504 for timeouts, always using `{ "output": "...", "error": true }`.

For an external native-code runner, use disposable containers, microVMs, or an equivalent maintained sandbox for
each execution with no network, no host secrets or writable host mounts, an
unprivileged user, a read-only base filesystem and bounded temporary storage,
CPU/memory/process/output limits, and a wall-clock timeout. Allow only fixed
language runtimes, never interpolate program text into shell commands, enforce
concurrency/rate limits, and destroy each sandbox after execution. Client-side
diagnostics and source substring denylists do not provide runtime isolation.

## Verification

Run local runner tests with `python tools/test_cpp_wasm.py`,
`python tools/test_python_wasi.py`, and `python tests/test_wasm_execution.py`.
The last suite verifies the actual PHP endpoint, both local language runtimes,
isolation, bounded output, timeouts and recovery. `node tests/editor-browser.cjs`
also verifies real execution in the editor against a site on localhost:8765.
`node tests/editor-run.cjs` checks typed programs, stdin, compile/runtime errors,
execution deadlines, and recovery after stalled requests. Set
`$env:CODESTEP_TEST_URL = 'http://codestep'` to run either browser test against
the actual OSPanel site. Browser tests that invoke Run use the server's selected
provider and may send their synthetic test programs to Wandbox. Select the local
provider when checking local isolation/resource limits. Use the PHP-served URL;
a static Live Server does not execute the PHP endpoints.

Run the endpoint validation checks with `python tests/test_execute_code.py`.
They start a temporary local PHP server, verify CSRF and request limits, and
check that valid code is not executed when a runner is absent. When cURL is
available they also use a local mock runner to check stdin/auth forwarding,
compiler errors, empty output, invalid/oversized responses, redirects, and the
15-second timeout. A live isolated runner integration test remains
deployment-specific; the mock never executes submitted programs.
