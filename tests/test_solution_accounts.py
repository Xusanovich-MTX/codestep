"""Account, profile and draft HTTP contracts against isolated PHP and SQLite.

Run: python tests/test_solution_accounts.py
Optional: CODESTEP_TEST_PHP=C:/php/php.exe
Never targets the live OSPanel database and never invokes the code compiler.
"""

from __future__ import annotations

import http.cookiejar
import copy
from contextlib import closing
from html import escape
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import shutil
import socket
import sqlite3
import struct
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import zlib


ROOT = Path(__file__).resolve().parents[1]


def png_pixel(red, green, blue):
    """Small valid PNG fixtures, without external imaging dependencies."""
    def chunk(kind, value):
        return struct.pack(">I", len(value)) + kind + value + struct.pack(">I", zlib.crc32(kind + value))
    return (b"\x89PNG\r\n\x1a\n"
            + chunk(b"IHDR", struct.pack(">IIBBBBB", 1, 1, 8, 2, 0, 0, 0))
            + chunk(b"IDAT", zlib.compress(bytes((0, red, green, blue))))
            + chunk(b"IEND", b""))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class TokenParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.token = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if attrs.get("name") == "csrf_token":
            self.token = attrs.get("value")
        if "data-csrf-token" in attrs:
            self.token = attrs["data-csrf-token"]


class Client:
    def __init__(self, base):
        self.base = base
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(self.cookies),
            NoRedirect(),
        )

    def request(self, path, data=None, headers=None, *, raw=False):
        body = data if data is None or isinstance(data, bytes) else urllib.parse.urlencode(data).encode("utf-8")
        req = urllib.request.Request(self.base + path, data=body, headers=headers or {})
        try:
            response = self.opener.open(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read()
            return response.status, response.headers, body if raw else body.decode("utf-8", "replace")

    def upload(self, token, image, **fields):
        boundary = "codestep-test-" + os.urandom(12).hex()
        parts = []
        for key, value in fields.items():
            parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n'
                          f'{value}\r\n').encode("utf-8"))
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="profile_image"; '
                      'filename="avatar.png"\r\nContent-Type: image/png\r\n\r\n').encode("ascii"))
        parts.extend([image, f"\r\n--{boundary}--\r\n".encode("ascii")])
        return self.request("api/upload-profile.php", b"".join(parts), {
            "X-CSRF-Token": token, "Content-Type": f"multipart/form-data; boundary={boundary}",
        })

    def token(self, path="?page=register"):
        status, _, body = self.request(path)
        if status != 200:
            raise AssertionError("Token page did not return HTTP 200")
        parser = TokenParser()
        parser.feed(body)
        if not parser.token:
            raise AssertionError("CSRF token was not rendered")
        return parser.token

    def session_id(self):
        return next((cookie.value for cookie in self.cookies if cookie.name == "PHPSESSID"), None)


class SolutionAccounts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.php = os.environ.get("CODESTEP_TEST_PHP") or (
            "C:/php/php.exe" if Path("C:/php/php.exe").is_file() else shutil.which("php")
        )
        if not cls.php:
            raise unittest.SkipTest("PHP CLI is unavailable")
        cls.temp = tempfile.TemporaryDirectory(prefix="codestep-account-test-")
        cls.database = Path(cls.temp.name) / "accounts.sqlite"
        cls.env = os.environ.copy()
        cls.env["CODESTEP_DB_PATH"] = str(cls.database)
        cls.php_options = ["-d", "extension=pdo_sqlite"]
        cls.creationflags = getattr(subprocess, "CREATE_NO_WINDOW", 0)
        cls.logs = open(Path(cls.temp.name) / "php-server.log", "w+b")
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]
        cls.base = f"http://127.0.0.1:{port}/"
        cls.server = subprocess.Popen(
            [cls.php, *cls.php_options, "-d", f'session.save_path="{Path(cls.temp.name).as_posix()}"',
             "-d", "display_errors=0", "-S", f"127.0.0.1:{port}", "-t", str(ROOT / "public")],
            cwd=ROOT, env=cls.env, stdin=subprocess.DEVNULL,
            stdout=cls.logs, stderr=subprocess.STDOUT, creationflags=cls.creationflags,
        )
        try:
            for _ in range(50):
                if cls.server.poll() is not None:
                    raise RuntimeError("Isolated PHP server exited before readiness")
                try:
                    if Client(cls.base).request("")[0] == 200:
                        return
                except (OSError, urllib.error.URLError):
                    pass
                time.sleep(0.1)
            raise RuntimeError("Isolated PHP server did not become ready")
        except BaseException:
            cls.tearDownClass()
            raise

    @classmethod
    def tearDownClass(cls):
        if getattr(cls, "server", None) is not None:
            cls.server.terminate()
            try:
                cls.server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                cls.server.kill()
                cls.server.wait(timeout=5)
        if getattr(cls, "logs", None) is not None:
            cls.logs.close()
        if getattr(cls, "temp", None) is not None:
            cls.temp.cleanup()

    def save(self, client, token, **overrides):
        payload = {"topic": "14", "problem": "1.1.1", "language": "python", "code": "print(1)", "revision": "0"}
        payload.update(overrides)
        status, headers, body = client.request("api/solutions.php", payload, {"X-CSRF-Token": token})
        try:
            result = json.loads(body)
        except json.JSONDecodeError as error:
            raise AssertionError(f"Draft endpoint returned HTTP {status} without JSON: {body[:160]!r}") from error
        return status, headers, result

    def register(self, client, username, password,
                 next_url="?page=code-editor&topic=14&problem=1.1.1&lang=python"):
        token = client.token()
        previous_session = client.session_id()
        payload = {
            "action": "register", "username": username, "password": password,
            "password_confirm": password, "csrf_token": token,
        }
        if next_url is not None:
            payload["next"] = next_url
        status, headers, _ = client.request("api/auth.php", payload)
        self.assertEqual(status, 303)
        destination = next_url or "?page=profile"
        self.assertEqual(headers.get("Location"), "../" + destination)
        self.assertTrue(client.session_id() != previous_session, "Registration must rotate the session")
        return client.token(destination)

    def profile(self, client, token, **overrides):
        payload = {"action": "save", "display_name": "Learner", "bio": "Learning Python"}
        payload.update(overrides)
        status, headers, body = client.request("api/profile.php", payload, {"X-CSRF-Token": token})
        return status, headers, json.loads(body)

    def test_01_auth_helpers_and_throttling(self):
        php = r'''
require $argv[1] . '/public/includes/auth.php';
function ensure($value, $label) { if (!$value) throw new RuntimeException($label); }
$_SESSION = ['csrf_token' => str_repeat('a', 64)];
ensure(codeStepCsrfValid(str_repeat('a', 64)), 'valid csrf');
foreach ([null, [], '', str_repeat('b', 64)] as $bad) ensure(!codeStepCsrfValid($bad), 'bad csrf');
$_SESSION['csrf_token'] = '';
ensure(!codeStepCsrfValid(''), 'empty csrf');
foreach ([null, ['id'=>0,'username'=>'zero'], ['id'=>-1,'username'=>'negative'], ['id'=>1,'username'=>''], ['id'=>'1','username'=>'string']] as $bad) {
    $_SESSION['account'] = $bad; ensure(codeStepUser() === null, 'invalid session account');
}
$_SESSION['account'] = ['id'=>1,'username'=>'learner'];
ensure(codeStepUser()['id'] === 1, 'valid account');
foreach (['https://example.invalid', '//example.invalid', '?page=login', '?page=code-editor&topic[]=14&problem=1.1.1', "?page=home\r\nLocation:https://example.invalid"] as $bad) {
    ensure(codeStepNextUrl($bad) === '?page=profile', 'unsafe next');
}
ensure(codeStepNextUrl(null) === '?page=profile', 'default next');
ensure(codeStepNextUrl('?page=profile') === '?page=profile', 'profile next');
ensure(codeStepNextUrl('?page=solutions') === '?page=solutions', 'solutions next');
ensure(codeStepNextUrl('?page=code-editor&topic=14&problem=1.1.1&lang=python') === '?page=code-editor&topic=14&problem=1.1.1&lang=python', 'valid next');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$bucket = codeStepAuthBucket('login', 'helper_only');
ensure(!codeStepAuthLimited($bucket, 2), 'new bucket');
codeStepAuthFailure($bucket);
ensure(!codeStepAuthLimited($bucket, 2), 'first failure');
codeStepAuthFailure($bucket);
ensure(codeStepAuthLimited($bucket, 2), 'persistent throttle');
$db=solutionDb(); $update=$db->prepare('UPDATE auth_throttle SET expires_at = ? WHERE bucket = ?'); $update->execute([time()-1,$bucket]);
ensure(!codeStepAuthLimited($bucket, 2), 'expired throttle');
codeStepAuthFailure($bucket);
ensure(!codeStepAuthLimited($bucket, 2), 'expired bucket reset');
echo 'PASS: auth helper/session/redirect/throttle contracts';
'''
        result = subprocess.run(
            [self.php, *self.php_options, "-r", php, str(ROOT)], cwd=ROOT, env=self.env,
            capture_output=True, text=True, timeout=15, creationflags=self.creationflags,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("PASS:", result.stdout)

    def test_02_accounts_drafts_csrf_isolation_and_revisions(self):
        guest = Client(self.base)
        guest_token = guest.token()
        status, _, result = self.save(guest, guest_token)
        self.assertEqual(status, 401)
        self.assertTrue(result["error"])

        password = "Temporary-" + os.urandom(16).hex()
        alpha = Client(self.base)
        beta = Client(self.base)
        alpha_token = self.register(alpha, "Learner_Alpha", password)
        beta_token = self.register(beta, "learner_beta", password)
        self.assertTrue(alpha_token != beta_token, "Accounts must have independent CSRF tokens")
        with closing(sqlite3.connect(self.database)) as database:
            users = database.execute("SELECT id, username, password_hash FROM users ORDER BY id").fetchall()
        self.assertEqual([user[1] for user in users], ["learner_alpha", "learner_beta"])
        self.assertTrue(all(user[0] > 0 for user in users), "Registered account IDs must be positive")
        self.assertTrue(all(user[2] != password for user in users), "Only password hashes belong in storage")
        alpha_id, beta_id = users[0][0], users[1][0]

        self.assertEqual(self.save(alpha, "incorrect-token")[0], 403)
        self.assertEqual(self.save(beta, alpha_token)[0], 403)
        code_alpha = 'print("account alpha")'
        status, headers, result = self.save(alpha, alpha_token, code=code_alpha)
        self.assertEqual(status, 200)
        self.assertEqual(result["revision"], 1)
        self.assertFalse(result["error"])
        self.assertIn("no-store", headers.get("Cache-Control", ""))
        status, _, result = self.save(alpha, alpha_token, code="stale overwrite", revision="0")
        self.assertEqual(status, 409)
        self.assertEqual(result["current_revision"], 1)
        self.assertEqual(self.save(beta, beta_token, code="beta draft")[0], 200)
        self.assertEqual(self.save(alpha, alpha_token, code="new alpha draft", revision="1")[0], 200)
        self.assertEqual(self.save(alpha, alpha_token, code="cpp draft", language="cpp")[0], 200)
        status, _, loaded = self.save(alpha, alpha_token, action="load", code="", revision="0")
        self.assertEqual(status, 200)
        self.assertEqual(loaded["draft"]["code"], "new alpha draft")
        self.assertEqual(loaded["draft"]["revision"], 2)
        self.assertEqual(self.save(beta, beta_token, action="load", code="", revision="0")[2]["draft"]["code"], "beta draft")
        self.assertIsNone(self.save(beta, beta_token, action="load", code="", revision="0", language="cpp")[2]["draft"])

        # A draft endpoint cannot choose ownership or turn posted claims into runs.
        status, _, _ = self.save(alpha, alpha_token, problem="1.1.2", code="injection attempt",
                                 owner_id=str(beta_id), verdict="accepted", checks_passed="1", total_checks="1")
        self.assertIn(status, (200, 400))
        with closing(sqlite3.connect(self.database)) as database:
            rows = database.execute("SELECT owner_id, problem_number, language, code, revision FROM solution_drafts").fetchall()
            runs = database.execute("SELECT COUNT(*) FROM solution_runs").fetchone()[0]
        lookup = {(row[0], row[1], row[2]): (row[3], row[4]) for row in rows}
        self.assertEqual(lookup[(alpha_id, "1.1.1", "python")], ("new alpha draft", 2))
        self.assertEqual(lookup[(beta_id, "1.1.1", "python")], ("beta draft", 1))
        self.assertNotIn((beta_id, "1.1.2", "python"), lookup)
        self.assertEqual(runs, 0, "A client-submitted verdict must not create a judged run")

        self.assertEqual(self.save(alpha, alpha_token, problem="1.1.999")[0], 404)
        self.assertEqual(self.save(alpha, alpha_token, topic="15", problem="1.1.1")[0], 404)
        self.assertEqual(self.save(alpha, alpha_token, language="javascript")[0], 400)
        self.assertEqual(self.save(alpha, alpha_token, revision="-1")[0], 400)
        self.assertEqual(self.save(alpha, alpha_token, code="x" * 65537)[0], 400)
        self.assertEqual(self.save(alpha, alpha_token, code="", revision="2")[0], 200)
        with closing(sqlite3.connect(self.database)) as database:
            cleared = database.execute("SELECT code, revision FROM solution_drafts WHERE owner_id=? AND problem_number='1.1.1' AND language='python'", (alpha_id,)).fetchone()
        self.assertEqual(cleared, ("", 3), "Clearing code must be a saved empty draft")

        # Logout invalidates authenticated access, then login rotates identity again.
        before_logout = alpha.session_id()
        status, _, _ = alpha.request("api/auth.php", {"action": "logout", "csrf_token": alpha_token})
        self.assertEqual(status, 303)
        self.assertTrue(alpha.session_id() != before_logout, "Logout must rotate session ID")
        self.assertEqual(self.save(alpha, alpha_token)[0], 401)
        login_token = alpha.token("?page=login")
        status, headers, _ = alpha.request("api/auth.php", {"action": "login", "username": "LEARNER_ALPHA",
            "password": password, "csrf_token": login_token, "next": "https://example.invalid"})
        self.assertEqual(status, 303)
        self.assertEqual(headers.get("Location"), "../?page=profile")
        token_after_login = alpha.token("?page=code-editor&topic=14&problem=1.1.1")
        self.assertTrue(token_after_login != login_token, "Login must rotate CSRF token")
        self.assertEqual(self.save(alpha, login_token, revision="3")[0], 403)
        self.assertEqual(self.save(alpha, token_after_login, code="restored account", revision="3")[0], 200)
        self.assertEqual(self.save(beta, beta_token, code="still beta", revision="1")[0], 200)

    def test_03_auth_failures_cookie_flags_and_methods(self):
        client = Client(self.base)
        status, headers, _ = client.request("?page=register")
        self.assertEqual(status, 200)
        cookie_headers = " ".join(headers.get_all("Set-Cookie", []))
        self.assertIn("httponly", cookie_headers.lower())
        self.assertIn("samesite=lax", cookie_headers.lower())
        token = client.token()
        auth_status, _, auth_body = client.request("api/auth.php")
        self.assertEqual(auth_status, 405, f"Auth GET returned {auth_body[:160]!r}")
        self.assertEqual(client.request("api/solutions.php")[0], 405)
        self.assertEqual(client.request("api/auth.php", {"action": "logout", "csrf_token": "invalid"})[0], 403)
        status, headers, _ = client.request("api/auth.php", {"action": "register", "username": "bad_confirm",
            "password": "not-a-used-password", "password_confirm": "different", "csrf_token": token})
        self.assertEqual(status, 303)
        self.assertIn("page=register", headers.get("Location", ""))
        status, headers, _ = client.request("api/auth.php", {"action": "login", "username": "nonexistent_account",
            "password": "not-a-used-password", "csrf_token": token})
        self.assertEqual(status, 303)
        self.assertIn("page=login", headers.get("Location", ""))
        self.assertEqual(self.save(client, token)[0], 401)
        with closing(sqlite3.connect(self.database)) as database:
            self.assertEqual(database.execute("SELECT COUNT(*) FROM users WHERE username='bad_confirm'").fetchone()[0], 0)

    def test_04_profiles_avatar_ownership_and_escaped_page(self):
        guest = Client(self.base)
        guest_token = guest.token()
        status, _, page = guest.request("?page=profile")
        self.assertEqual(status, 200)
        self.assertIn("?page=login", page)
        self.assertNotIn('id="profileForm"', page)
        self.assertEqual(self.profile(guest, guest_token)[0], 401)
        self.assertEqual(guest.upload(guest_token, png_pixel(255, 0, 0))[0], 401)
        self.assertEqual(guest.request("api/profile-image.php")[0], 401)
        self.assertEqual(guest.request("api/profile.php")[0], 405)
        self.assertEqual(guest.request("api/upload-profile.php")[0], 405)

        password = "Profile-" + os.urandom(16).hex()
        alpha, beta = Client(self.base), Client(self.base)
        alpha_token = self.register(alpha, "profile_alpha", password, next_url=None)
        beta_token = self.register(beta, "profile_beta", password, next_url="?page=profile")
        with closing(sqlite3.connect(self.database)) as database:
            profiles = database.execute("""SELECT u.id, u.username, p.display_name, p.bio, p.avatar
                FROM users u JOIN user_profiles p ON p.user_id=u.id
                WHERE u.username IN ('profile_alpha', 'profile_beta') ORDER BY u.username""").fetchall()
        self.assertEqual(len(profiles), 2, "Registration must automatically create each profile")
        self.assertEqual(profiles[0][1:], ("profile_alpha", "profile_alpha", "", None))
        self.assertEqual(profiles[1][1:], ("profile_beta", "profile_beta", "", None))
        alpha_id, beta_id = profiles[0][0], profiles[1][0]
        self.assertEqual(alpha.request("api/profile-image.php")[0], 404)
        self.assertEqual(self.profile(alpha, "invalid")[0], 403)
        self.assertEqual(self.profile(beta, alpha_token)[0], 403)

        name = "<img src=x onerror=alert(1)>"
        bio = "</textarea><script>alert(1)</script>\nPython & C++"
        status, headers, result = self.profile(alpha, alpha_token, display_name=name,
                                               bio=bio, owner_id=beta_id)
        self.assertEqual(status, 200)
        self.assertFalse(result["error"])
        self.assertEqual(result["profile"]["display_name"], name)
        self.assertEqual(result["profile"]["bio"], bio)
        self.assertFalse(result["profile"]["has_avatar"])
        self.assertNotIn("password_hash", result["profile"])
        self.assertIn("no-store", headers.get("Cache-Control", ""))
        self.assertEqual(headers.get("X-Content-Type-Options"), "nosniff")
        for changes in ({"display_name": " "}, {"display_name": "x" * 81},
                        {"display_name": "two\nlines"}, {"bio": "x" * 281},
                        {"display_name[]": "not text", "display_name": None},
                        {"action": "unknown"}):
            with self.subTest(invalid_profile=changes):
                self.assertEqual(self.profile(alpha, alpha_token, **changes)[0], 400)
        with closing(sqlite3.connect(self.database)) as database:
            rows = database.execute("SELECT user_id, display_name, bio FROM user_profiles WHERE user_id IN (?, ?)",
                                    (alpha_id, beta_id)).fetchall()
        self.assertEqual({row[0]: row[1:] for row in rows},
                         {alpha_id: (name, bio), beta_id: ("profile_beta", "")})

        # The page reflects only the current account's saved work and escapes both text contexts.
        self.assertEqual(self.save(alpha, alpha_token)[0], 200)
        self.assertEqual(self.save(alpha, alpha_token, language="cpp")[0], 200)
        self.assertEqual(self.save(beta, beta_token)[0], 200)
        with closing(sqlite3.connect(self.database)) as database:
            # Fixtures stand in for persisted judge results; this suite never executes code.
            database.executemany("""INSERT INTO solution_runs
                (owner_id, topic_id, problem_number, language, code, code_hash, output,
                 verdict, checks_passed, total_checks, test_stdin, created_at)
                VALUES (?, 14, ?, ?, '', ?, '', ?, ?, 1, '', '2026-10-01T00:00:00Z')""", [
                (alpha_id, "1.1.1", "python", "a" * 64, "accepted", 1),
                (alpha_id, "1.1.1", "cpp", "b" * 64, "accepted", 1),
                (alpha_id, "1.1.2", "python", "c" * 64, "wrong_answer", 0),
                (beta_id, "1.1.2", "python", "d" * 64, "accepted", 1),
            ])
            database.commit()
        status, _, page = alpha.request(f"?page=profile&owner_id={beta_id}")
        self.assertEqual(status, 200)
        self.assertIn("@profile_alpha", page)
        self.assertNotIn("@profile_beta", page)
        self.assertIn(escape(name), page)
        self.assertIn(escape(bio), page)
        self.assertNotIn(name, page)
        self.assertNotIn(bio, page)
        for key, value in {"solved": 1, "drafts": 2, "attempts": 3}.items():
            self.assertIn(f'data-stat="{key}">{value}</strong>', page)
        beta_page = beta.request("?page=profile")[2]
        self.assertNotIn(escape(name), beta_page)
        self.assertIn('data-stat="drafts">1</strong>', beta_page)
        self.assertIn('data-stat="attempts">1</strong>', beta_page)

        alpha_image, beta_image = png_pixel(255, 0, 0), png_pixel(0, 0, 255)
        self.assertEqual(alpha.upload("invalid", alpha_image)[0], 403)
        self.assertEqual(beta.upload(alpha_token, beta_image)[0], 403)
        self.assertEqual(alpha.upload(alpha_token, b"not a PNG despite the MIME type")[0], 415)
        self.assertEqual(alpha.upload(alpha_token, b"x" * (2097152 + 1))[0], 413)
        self.assertEqual(alpha.request("api/profile-image.php")[0], 404)
        status, _, body = alpha.upload(alpha_token, alpha_image, owner_id=beta_id)
        self.assertEqual(status, 200)
        alpha_url = json.loads(body)["image"]
        self.assertTrue(alpha_url.startswith("api/profile-image.php?"))
        self.assertEqual(beta.request(alpha_url)[0], 404, "An avatar URL grants no cross-account access")
        status, _, body = beta.upload(beta_token, beta_image, owner_id=alpha_id)
        self.assertEqual(status, 200)
        beta_url = json.loads(body)["image"]
        for client, path, expected in ((alpha, beta_url, alpha_image), (beta, alpha_url, beta_image),
                                       (alpha, f"{alpha_url}&owner_id={beta_id}", alpha_image)):
            status, headers, body = client.request(path, raw=True)
            self.assertEqual(status, 200)
            self.assertEqual(body, expected, "Image identity comes only from the authenticated session")
            self.assertEqual(headers.get("Content-Type"), "image/png")
            self.assertIn("no-store", headers.get("Cache-Control", ""))
            self.assertEqual(headers.get("X-Content-Type-Options"), "nosniff")
        self.assertEqual(guest.request(alpha_url)[0], 401)
        self.assertEqual(alpha.request("api/profile-image.php", {})[0], 405)
        result = self.profile(alpha, alpha_token, display_name=name, bio=bio)[2]
        self.assertTrue(result["profile"]["has_avatar"])
        status, _, result = self.profile(alpha, alpha_token, action="remove_avatar", owner_id=beta_id)
        self.assertEqual(status, 200)
        self.assertFalse(result["profile"]["has_avatar"])
        self.assertEqual(alpha.request(alpha_url)[0], 404)
        self.assertEqual(beta.request(beta_url, raw=True)[2], beta_image)
        with closing(sqlite3.connect(self.database)) as database:
            stored = database.execute("SELECT user_id, avatar, avatar_mime FROM user_profiles WHERE user_id IN (?, ?)",
                                      (alpha_id, beta_id)).fetchall()
        self.assertEqual({row[0]: row[1:] for row in stored},
                         {alpha_id: (None, None), beta_id: (beta_image, "image/png")})

    def test_05_password_change_rotates_session_and_preserves_ownership(self):
        guest = Client(self.base)
        guest_token = guest.token()
        original = "Original-" + os.urandom(16).hex()
        replacement = "Replacement-" + os.urandom(16).hex()

        def change(client, token, **overrides):
            payload = {"current_password": original, "new_password": replacement,
                       "password_confirm": replacement}
            payload.update(overrides)
            status, headers, body = client.request("api/profile-password.php", payload, {"X-CSRF-Token": token})
            return status, headers, json.loads(body)

        self.assertEqual(change(guest, guest_token)[0], 401)
        self.assertEqual(guest.request("api/profile-password.php")[0], 405)
        alpha, beta = Client(self.base), Client(self.base)
        alpha_token = self.register(alpha, "password_alpha", original, next_url=None)
        self.register(beta, "password_beta", original, next_url=None)
        with closing(sqlite3.connect(self.database)) as database:
            users = database.execute("SELECT id, username, password_hash FROM users WHERE username IN ('password_alpha', 'password_beta')").fetchall()
        user_ids = {row[1]: row[0] for row in users}
        old_hashes = {row[1]: row[2] for row in users}
        self.assertEqual(change(alpha, "invalid")[0], 403)
        self.assertEqual(change(alpha, alpha_token, current_password="incorrect-password")[0], 400)
        self.assertEqual(change(alpha, alpha_token, password_confirm="does-not-match")[0], 400)
        self.assertEqual(change(alpha, alpha_token, new_password="short", password_confirm="short")[0], 400)

        old_session = alpha.session_id()
        stale_session = Client(self.base)
        for cookie in alpha.cookies:
            stale_session.cookies.set_cookie(copy.copy(cookie))
        status, headers, result = change(alpha, alpha_token, owner_id=user_ids["password_beta"])
        self.assertEqual(status, 200)
        self.assertFalse(result["error"])
        self.assertIn("no-store", headers.get("Cache-Control", ""))
        self.assertNotEqual(alpha.session_id(), old_session)
        new_token = result["csrf_token"]
        self.assertTrue(isinstance(new_token, str) and len(new_token) >= 32)
        self.assertNotEqual(new_token, alpha_token)
        self.assertEqual(alpha.token("?page=profile"), new_token)
        self.assertEqual(self.profile(alpha, alpha_token)[0], 403)
        self.assertEqual(self.profile(alpha, new_token)[0], 200)
        self.assertEqual(self.profile(stale_session, alpha_token)[0], 401)
        with closing(sqlite3.connect(self.database)) as database:
            new_hashes = dict(database.execute("SELECT username, password_hash FROM users WHERE username IN ('password_alpha', 'password_beta')"))
        self.assertNotEqual(new_hashes["password_alpha"], old_hashes["password_alpha"])
        self.assertNotEqual(new_hashes["password_alpha"], replacement)
        self.assertEqual(new_hashes["password_beta"], old_hashes["password_beta"])

        login = Client(self.base)
        login_token = login.token("?page=login")
        status, headers, _ = login.request("api/auth.php", {"action": "login", "username": "password_alpha",
            "password": original, "csrf_token": login_token})
        self.assertEqual(status, 303)
        self.assertIn("page=login", headers.get("Location", ""))
        self.assertEqual(self.profile(login, login_token)[0], 401)
        status, headers, _ = login.request("api/auth.php", {"action": "login", "username": "password_alpha",
            "password": replacement, "csrf_token": login_token})
        self.assertEqual(status, 303)
        self.assertEqual(headers.get("Location"), "../?page=profile")
        self.assertIn("@password_alpha", login.request("?page=profile")[2])

    def test_06_missing_profile_still_allows_logout(self):
        client = Client(self.base)
        token = self.register(client, "missing_profile", "Temporary-" + os.urandom(16).hex(), next_url=None)
        with closing(sqlite3.connect(self.database)) as database:
            user_id = database.execute("SELECT id FROM users WHERE username=?", ("missing_profile",)).fetchone()[0]
            removed = database.execute("DELETE FROM user_profiles WHERE user_id=?", (user_id,))
            self.assertEqual(removed.rowcount, 1)
            database.commit()

        status, _, page = client.request("?page=profile")
        self.assertEqual(status, 200)
        self.assertNotIn('id="profileForm"', page)
        self.assertIn('action="api/auth.php"', page)
        self.assertIn('name="action" value="logout"', page)
        parser = TokenParser()
        parser.feed(page)
        self.assertEqual(parser.token, token, "The missing-profile page must retain a usable logout CSRF token")
        status, headers, _ = client.request("api/auth.php", {"action": "logout", "csrf_token": parser.token})
        self.assertEqual(status, 303)
        self.assertEqual(headers.get("Location"), "../?page=home")
        self.assertEqual(self.profile(client, token)[0], 401)
        self.assertEqual(client.request("api/profile-image.php")[0], 401)


if __name__ == "__main__":
    unittest.main(verbosity=2)
