# Account and solution storage

CodeStep stores registered accounts, code drafts and execution history in SQLite. By default the database is `storage/codestep.sqlite`, outside the public web root. `CODESTEP_DB_PATH` can override this location for the PHP process, especially for isolated tests. Keep the configured directory outside `public` and writable by the PHP service account.

PHP needs `PDO` and `pdo_sqlite`. `solutionDb()` initializes schema version 2 from `database/schema.sql` on first use, or upgrades version 1 in a transaction without replacing accounts or solutions. Connections enable foreign keys, WAL mode and a five-second busy timeout. Repeated initialization preserves existing records. A database with a newer schema version is refused instead of silently downgraded.

## Data and ownership

- `users`: lowercase unique username, password hash and creation timestamp. Passwords are hashed with PHP's password API; plaintext passwords are not stored.
- `user_profiles`: one profile per account, automatically inserted with a new account by a database trigger. Upgrading backfills existing accounts. Display name, bio and optional avatar bytes belong to that account; the old shared `public/data/profile.json` is no longer read or updated by the profile flow.
- `auth_throttle`: bounded-window registration/login attempt counters.
- `solution_drafts`: one mutable code draft per account, topic, problem and language. Empty code is a valid saved draft.
- `solution_runs`: immutable submitted code, SHA-256 code hash, output, server verdict, test counts, test input and creation timestamp.

Authenticated account identity comes from the server session. Draft and history functions always filter by that identity. A submitted `owner_id` or verdict must never be used as authority. Guest browser drafts are local data; database saving requires an account.

Saving a draft requires its last known revision. A new draft uses revision 0. Each successful save advances the revision. Concurrent or stale saves return HTTP 409 with `current_revision`; callers must preserve the local code and resolve the conflict before retrying. A run's completion never writes to the draft table, so an older execution cannot replace newer edits.

The execution endpoint must pass the actual server judge result to `solutionSaveRun()`. Valid verdicts are `accepted`, `wrong_answer`, `structure_passed` and `error`. `structure_passed` means the required output structure matched; it does not establish factual or semantic correctness. Runtime or infrastructure failures remain `error`, even if another field resembles an accepted result.

## Sessions and requests

Registration, login and logout use POST forms with a session CSRF token. Draft saving uses POST with `X-CSRF-Token`. Login/logout rotate the session and CSRF token. Session cookies are HttpOnly and SameSite=Lax; HTTPS requests also use Secure cookies. Publish the site over HTTPS so account credentials and cookies are protected in transit.

The `next` destination defaults to the personal profile. Explicit saved-solutions and reconstructed local editor queries are also allowed. Owner identity, expected judge outputs and password hashes must not be exposed in client-controlled request contracts. Escape saved code and output when rendering history.

The sidebar has a Profile entry; logout is inside the profile page. Profile text and avatar changes use the authenticated session owner and CSRF checks, ignoring submitted user IDs. Avatars are at most 2 MB and 4096 × 4096 pixels (JPG/PNG/WebP). `api/profile-image.php` serves only the current account's image with `no-store` and `nosniff`; it never exposes another account's image by URL ID. Profile statistics count only that owner's records, and count each accepted problem once even if accepted in both languages. Structure-only results do not count as solved.

Password changes require the current password, CSRF and matching new passwords, rate-limit incorrect current-password attempts, and rotate the current session ID and CSRF token. The profile UI updates its tokens so subsequent edits and logout work. Other already authenticated browser sessions are not revoked by this version.

Release the session lock before long code execution. Keep SQLite transactions short: draft revision checks and writes are atomic, while compilation/execution runs outside a database transaction.

## Verification

Run the storage and judge unit checks:

```powershell
C:\php\php.exe -d extension=pdo_sqlite tests/solution-store.test.php
C:\php\php.exe tests/problem-judge.test.php
C:\php\php.exe -d extension=pdo_sqlite tests/profile-store.test.php
```

Run account/session and draft API integration checks:

```powershell
python tests/test_solution_accounts.py
```

The integration test starts a hidden PHP server bound only to `127.0.0.1`, with an isolated temporary SQLite database and session directory. It creates temporary test accounts, verifies ownership separation, CSRF, revision conflicts, login/logout rotation, input validation and rejection of client verdict claims. It stops the server and removes temporary data afterwards. It never creates users in the default OSPanel database and does not invoke the compiler. Set `CODESTEP_TEST_PHP` if the PHP executable is elsewhere.

Run the browser integration checks (requires the existing Playwright installation, Chrome and configured WebAssembly runners):

```powershell
node tests/solution-browser.cjs
node tests/profile-browser.cjs
```

This uses a temporary database and checks registration with a guest draft, Python/C++ draft saving, real judged-run persistence, history restoration, two-tab conflicts, load/undo, edits during slow saves or loads, logout isolation, login and mobile layouts. It also checks the second topic's example page. Screenshots are written to `tests/artifacts`. Load and save share an operation lock so an older load cannot replace a newly saved revision.

The editor keeps account drafts in separate browser namespaces. Unsaved local edits take precedence when opening a problem; use “Bazadagi kodni ochish” to explicitly load the database version. Ctrl+S or “Bazaga saqlash” saves the current language's draft. Running a problem saves its submitted code and server result to the history, without replacing the mutable draft. Guests can still edit and run, but must sign in to save to the database.

For backups, use SQLite's backup API or stop writes before copying the database. In WAL mode, copying only the main file while writers are active can omit recent changes.
