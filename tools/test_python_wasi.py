"""Exercise the real CPython WASI guest and its security/resource boundaries."""

import json
import os
from pathlib import Path
import subprocess
import sys
import time
import unittest


ROOT = Path(__file__).resolve().parent
HELPER = ROOT / "python-wasi-runner.py"


class PythonWasiTests(unittest.TestCase):
    def execute(self, code, stdin="", **kwargs):
        started = time.monotonic()
        completed = subprocess.run(
            [sys.executable, "-I", "-S", str(HELPER)],
            input=json.dumps({"code": code, "stdin": stdin}).encode("utf-8"),
            capture_output=True, timeout=25, **kwargs,
        )
        self.assertEqual(completed.returncode, 0, completed.stderr.decode("utf-8", errors="replace"))
        self.assertEqual(completed.stderr, b"")
        response = json.loads(completed.stdout)
        self.assertIsInstance(response["error"], bool)
        self.assertLessEqual(len(response["output"].encode("utf-8")), 65536)
        return response, time.monotonic() - started

    def test_real_input_and_standard_library(self):
        result, _ = self.execute("import math\na, b = map(int, input().split())\nprint(a + b, math.isqrt(81))", "12 30\n")
        self.assertEqual(result, {"output": "42 9\n", "error": False})

    def test_unicode_and_stdout_stderr(self):
        result, _ = self.execute("import sys\nprint('Salom, dunyo! 🌍')\nprint('izoh', file=sys.stderr)")
        self.assertFalse(result["error"])
        self.assertEqual(result["output"], "Salom, dunyo! 🌍\nizoh\n")

    def test_syntax_error(self):
        result, _ = self.execute("if True print('broken')")
        self.assertTrue(result["error"])
        self.assertIn("SyntaxError", result["output"])

    def test_runtime_error_and_exit_code(self):
        result, _ = self.execute("print('before')\n1 / 0")
        self.assertTrue(result["error"])
        self.assertIn("before\n", result["output"])
        self.assertIn("ZeroDivisionError", result["output"])
        result, _ = self.execute("raise SystemExit(7)")
        self.assertTrue(result["error"])

    def test_host_files_and_environment_are_not_visible(self):
        host_path = str(ROOT / "code-tools.config.json")
        code = (
            "import os\n"
            "print('CODESTEP_HOST_SECRET' in os.environ)\n"
            "try:\n"
            "    print(open(" + repr(host_path) + ").read())\n"
            "except OSError:\n"
            "    print('host file denied')\n"
        )
        environment = dict(os.environ, CODESTEP_HOST_SECRET="must-stay-outside")
        result, _ = self.execute(code, env=environment)
        self.assertEqual(result, {"output": "False\nhost file denied\n", "error": False})

    def test_network_and_subprocess_are_unavailable(self):
        code = (
            "for name in ('_socket', 'subprocess'):\n"
            "    try:\n"
            "        module = __import__(name)\n"
            "        if name == '_socket':\n"
            "            module.socket()\n"
            "        else:\n"
            "            module.run(['whoami'], check=True)\n"
            "    except (ImportError, OSError):\n"
            "        print(name + ' denied')\n"
        )
        result, _ = self.execute(code)
        self.assertEqual(result, {"output": "_socket denied\nsubprocess denied\n", "error": False})

    def test_memory_limit(self):
        result, _ = self.execute("data = bytearray(300 * 1024 * 1024)\nprint('unexpected allocation')")
        self.assertTrue(result["error"])
        self.assertIn("MemoryError", result["output"])
        self.assertNotIn("unexpected allocation\n", result["output"])

    def test_output_limit_preserves_valid_utf8(self):
        result, _ = self.execute("while True:\n    print('é' * 500)")
        self.assertTrue(result["error"])
        self.assertIn("64 KB", result["output"])

    def test_infinite_loop_has_deadline(self):
        result, elapsed = self.execute("while True:\n    pass")
        self.assertTrue(result["error"])
        self.assertIn("10 soniyalik", result["output"])
        self.assertLess(elapsed, 22)

    def test_request_validation(self):
        for code, stdin in [("", ""), ("a" * 65537, ""), ("print(1)", "a" * 16385),
                            (42, ""), ("print(1)\0", ""), ("print(1)", "\0")]:
            result, _ = self.execute(code, stdin)
            self.assertTrue(result["error"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
