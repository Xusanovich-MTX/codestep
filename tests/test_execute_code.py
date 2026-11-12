"""Exercise the real PHP endpoint without executing submitted programs."""
import http.cookiejar
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import threading
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid


ROOT = Path(__file__).resolve().parents[1]


def php_executable():
    return os.environ.get("CODESTEP_TEST_PHP") or shutil.which("php")


class PhpApiFixture(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        php = php_executable()
        if not php:
            raise unittest.SkipTest("PHP CLI is required")
        work = ROOT / "tests" / (".execute-" + uuid.uuid4().hex)
        work.mkdir()
        if not work.resolve().is_relative_to(ROOT / "tests"):
            raise RuntimeError("Temporary directory must remain inside tests")
        cls.addClassCleanup(shutil.rmtree, work)
        cls.marker = work / "must-not-be-created.txt"
        router = work / "router.php"
        router.write_text("""<?php
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/test-session') {
    session_start();
    $_SESSION['csrf_token'] = 'test-csrf-token';
    header('Content-Type: application/json');
    echo json_encode(['token' => $_SESSION['csrf_token']]);
    return true;
}
return false;
""", encoding="utf-8")
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        env = os.environ.copy()
        env.pop("CODESTEP_RUNNER_URL", None)
        env.pop("CODESTEP_RUNNER_TOKEN", None)
        # Never submit test-fixture programs to a public service.
        env["CODESTEP_EXECUTION_PROVIDER"] = "local"
        env["CODESTEP_LOCAL_RUNNER"] = "1" if getattr(cls, "use_local_runner", False) else "0"
        if hasattr(cls, "runner_url"):
            env["CODESTEP_RUNNER_URL"] = cls.runner_url
            env["CODESTEP_RUNNER_TOKEN"] = "test-service-token"
        cls.server = subprocess.Popen(
            [php, *getattr(cls, "php_options", []), "-d", "session.save_path=" + str(work), "-S", f"127.0.0.1:{port}",
             "-t", str(ROOT / "public"), str(router)],
            env=env, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=subprocess.CREATE_NO_WINDOW if os.name == "nt" else 0,
        )
        cls.addClassCleanup(cls.stop_server)
        cls.base = f"http://127.0.0.1:{port}"
        cls.client = urllib.request.build_opener(
            urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
        )
        for _ in range(100):
            try:
                with cls.client.open(cls.base + "/test-session", timeout=1) as response:
                    cls.token = json.load(response)["token"]
                break
            except (urllib.error.URLError, OSError):
                if cls.server.poll() is not None:
                    raise RuntimeError("PHP test server exited")
                time.sleep(0.05)
        else:
            raise RuntimeError("PHP test server did not start")

    @classmethod
    def stop_server(cls):
        cls.server.terminate()
        try:
            cls.server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            cls.server.kill()
            cls.server.wait(timeout=5)

    def request(self, payload=None, *, token=True, method="POST", raw=None,
                content_type="application/json"):
        body = raw if raw is not None else json.dumps(
            payload if payload is not None else {"language": "python", "code": "print(1)"}
        ).encode()
        headers = {"Content-Type": content_type}
        if token:
            headers["X-CSRF-Token"] = self.token if token is True else token
        request = urllib.request.Request(self.base + "/api/execute-code.php",
                                         data=body if method != "GET" else None,
                                         headers=headers, method=method)
        try:
            response = self.client.open(request, timeout=40)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            result = json.load(response)
            self.assertIsInstance(result["output"], str)
            self.assertIsInstance(result["error"], bool)
            return response.status, result, response.headers


class ExecutionApiTests(PhpApiFixture):
    def test_post_only(self):
        status, _, headers = self.request(method="GET")
        self.assertEqual(status, 405)
        self.assertEqual(headers["Allow"], "POST")

    def test_csrf_required(self):
        for token in (False, "incorrect-token"):
            with self.subTest(token=token):
                self.assertEqual(self.request(token=token)[0], 403)

    def test_valid_code_requires_runner(self):
        for language, code in [("cpp", "#include <iostream>\nint main() { std::cout << 1; }"),
                               ("python", "print(input())")]:
            with self.subTest(language=language):
                status, result, headers = self.request({"language": language, "code": code, "stdin": "42\n"})
                self.assertEqual(status, 503)
                self.assertIn("sozlanmagan", result["output"])
                self.assertIs(result["error"], True)
                self.assertIn("no-store", headers["Cache-Control"])

    def test_user_program_does_not_run_on_host(self):
        code = "from pathlib import Path\nPath(" + repr(str(self.marker)) + ").write_text('executed')"
        self.assertEqual(self.request({"language": "python", "code": code})[0], 503)
        self.assertFalse(self.marker.exists())

    def test_form_requests(self):
        body = urllib.parse.urlencode({"language": "cpp", "code": "int main() {}", "stdin": ""}).encode()
        self.assertEqual(self.request(raw=body, content_type="application/x-www-form-urlencoded")[0], 503)

    def test_wrong_field_types(self):
        for field in ("language", "code", "stdin"):
            for value in ([], {}, 123, None):
                with self.subTest(field=field, value=value):
                    payload = {"language": "python", "code": "print(1)", "stdin": ""}
                    payload[field] = value
                    self.assertEqual(self.request(payload)[0], 400)

    def test_empty_missing_unsupported_fields(self):
        for payload in ({}, {"code": "print(1)"}, {"language": "python"},
                        {"language": "php", "code": "echo 1;"},
                        {"language": "python", "code": "  \n\t"}):
            with self.subTest(payload=payload):
                self.assertEqual(self.request(payload)[0], 400)

    def test_malformed_json_and_content_type(self):
        for body in (b"{", b"[]", b"null", b'"text"'):
            with self.subTest(body=body):
                self.assertEqual(self.request(raw=body)[0], 400)
        self.assertEqual(self.request(raw=b"print(1)", content_type="text/plain")[0], 415)

    def test_size_limits(self):
        for field, limit in (("code", 65536), ("stdin", 16384)):
            with self.subTest(field=field):
                payload = {"language": "python", "code": "print(1)", field: "a" * limit}
                self.assertEqual(self.request(payload)[0], 503)
                payload[field] += "a"
                self.assertEqual(self.request(payload)[0], 413)
        self.assertEqual(self.request(raw=b" " * 262145)[0], 413)

    def test_null_bytes_rejected(self):
        for field in ("code", "stdin"):
            with self.subTest(field=field):
                payload = {"language": "python", "code": "print(1)", field: "x\0y"}
                self.assertEqual(self.request(payload)[0], 400)


class RunnerGatewayTests(PhpApiFixture):
    # A configured external service always takes priority over local WASM.
    use_local_runner = True

    @classmethod
    def setUpClass(cls):
        php = php_executable()
        if not php:
            raise unittest.SkipTest("PHP CLI is required")
        probe = "exit(extension_loaded('curl') ? 0 : 1);"
        cls.php_options = []
        if subprocess.run([php, "-r", probe], capture_output=True).returncode:
            cls.php_options = ["-d", "extension=curl"]
            if subprocess.run([php, *cls.php_options, "-r", probe], capture_output=True).returncode:
                raise unittest.SkipTest("PHP cURL extension is required for runner adapter tests")
        cls.received = []

        class MockRunner(BaseHTTPRequestHandler):
            def log_message(self, *args):
                pass

            def do_POST(self):
                payload = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
                cls.received.append((payload, self.headers.get("Authorization")))
                scenario = payload["code"]
                body = {"output": "42\n", "error": False}
                status = 200
                if scenario == "judge-memory":
                    body["output"] = "0000010011100110\n"
                elif scenario == "judge-echo":
                    body["output"] = payload["stdin"]
                elif scenario == "judge-prefix":
                    body["output"] = "Siz raqam kiritdingiz " + payload["stdin"]
                elif scenario == "judge-city":
                    body["output"] = "Shahar: Toshkent\nDavlat: Uzbekistan\nAholi: 3000000\n"
                elif scenario == "compile-error":
                    body = {"output": "compiler diagnostic", "error": True}
                elif scenario == "empty":
                    body = {"output": "", "error": False}
                elif scenario == "wrong-error":
                    body = {"output": "bad", "error": "false"}
                elif scenario == "missing-output":
                    body = {"error": False}
                elif scenario == "oversized-output":
                    body = {"output": "a" * 65537, "error": False}
                elif scenario == "oversized-body":
                    body = {"output": "a" * 410000, "error": False}
                elif scenario == "http-error":
                    status = 503
                elif scenario == "redirect":
                    status = 302
                elif scenario == "timeout":
                    time.sleep(16)
                self.send_response(status)
                self.send_header("Content-Type", "application/json")
                if scenario == "redirect":
                    self.send_header("Location", "/must-not-follow")
                self.end_headers()
                raw = b"not json" if scenario == "malformed" else json.dumps(body).encode()
                try:
                    self.wfile.write(raw)
                except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
                    pass

        cls.runner = ThreadingHTTPServer(("127.0.0.1", 0), MockRunner)
        cls.addClassCleanup(cls.runner.server_close)
        cls.addClassCleanup(cls.runner.shutdown)
        thread = threading.Thread(target=lambda: cls.runner.serve_forever(poll_interval=0.05), daemon=True)
        thread.start()
        cls.runner_url = f"http://127.0.0.1:{cls.runner.server_port}/execute"
        super().setUpClass()

    def test_output_contract_and_stdin_forwarded(self):
        for code, expected in (("success", {"output": "42\n", "error": False}),
                               ("compile-error", {"output": "compiler diagnostic", "error": True}),
                               ("empty", {"output": "", "error": False})):
            with self.subTest(code=code):
                status, result, _ = self.request({"language": "cpp", "code": code, "stdin": "21\n"})
                self.assertEqual(status, 200)
                self.assertEqual(result, expected)
                payload, auth = self.received[-1]
                self.assertEqual(payload["language"], "cpp")
                self.assertEqual(payload["code"], code)
                self.assertEqual(payload["stdin"], "21\n")
                self.assertEqual(payload["limits"]["output_bytes"], 65536)
                self.assertEqual(auth, "Bearer test-service-token")

    def test_invalid_runner_responses(self):
        for code in ("wrong-error", "missing-output", "oversized-output", "oversized-body",
                     "malformed", "http-error", "redirect"):
            with self.subTest(code=code):
                status, result, _ = self.request({"language": "python", "code": code})
                self.assertEqual(status, 502)
                self.assertIs(result["error"], True)

    def test_runner_timeout(self):
        status, result, _ = self.request({"language": "python", "code": "timeout"})
        self.assertEqual(status, 504)
        self.assertIs(result["error"], True)

    def test_problem_identity_is_validated_before_running(self):
        for extra, expected in (({"topic": [], "problem": "1.1.1"}, 400),
                                ({"topic": 14}, 400),
                                ({"topic": None, "problem": "1.1.1"}, 400),
                                ({"topic": 15, "problem": "1.1.1"}, 404),
                                ({"topic": 14, "problem": "unknown"}, 404)):
            with self.subTest(extra=extra):
                before = len(self.received)
                status, result, _ = self.request({"language": "python", "code": "success", **extra})
                self.assertEqual(status, expected)
                self.assertNotIn("verdict", result)
                self.assertEqual(len(self.received), before)

    def test_verdict_uses_runner_output_and_server_rules(self):
        for language in ("cpp", "python"):
            for code, expected in (("judge-memory", "accepted"), ("success", "wrong_answer")):
                with self.subTest(language=language, code=code):
                    status, result, _ = self.request({
                        "language": language, "code": code, "stdin": "untrusted input",
                        "topic": "14", "problem": "1.1.1", "expected": "42",
                        "verdict": {"status": "accepted"},
                    })
                    self.assertEqual(status, 200)
                    self.assertFalse(result["error"])
                    self.assertEqual(result["verdict"]["status"], expected)
                    self.assertEqual(result["verdict"]["topic"], 14)
                    self.assertEqual(result["verdict"]["problem"], "1.1.1")
                    self.assertEqual(self.received[-1][0]["stdin"], "1254 16\n")
        result = self.request({"language": "cpp", "code": "compile-error", "topic": 14, "problem": "1.1.1"})[1]
        self.assertTrue(result["error"])
        self.assertNotIn("verdict", result)

    def test_input_tasks_use_multiple_server_inputs(self):
        for number, code in ((50, "judge-echo"), (51, "judge-prefix"), (52, "judge-echo")):
            before = len(self.received)
            status, result, _ = self.request({"language": "python", "code": code, "stdin": "42", "topic": 15, "problem": f"1.1.{number}"})
            self.assertEqual(status, 200)
            self.assertEqual(result["verdict"]["status"], "accepted")
            self.assertEqual(result["verdict"]["checks_passed"], 3)
            inputs = [int(payload["stdin"]) for payload, _ in self.received[before:]]
            self.assertEqual(len(inputs), 3)
            self.assertGreater(inputs[0], 0)
            self.assertEqual(inputs[1], 0)
            self.assertLess(inputs[2], 0)
        result = self.request({"language": "cpp", "code": "success", "topic": 15, "problem": "1.1.50"})[1]
        self.assertEqual(result["verdict"]["status"], "wrong_answer")

    def test_structural_checks_do_not_claim_content_acceptance(self):
        result = self.request({"language": "python", "code": "judge-city", "topic": 15, "problem": "1.1.29"})[1]
        self.assertEqual(result["verdict"]["status"], "structure_passed")
        self.assertEqual(result["verdict"]["scope"], "structure_only")


if __name__ == "__main__":
    unittest.main(verbosity=2)
