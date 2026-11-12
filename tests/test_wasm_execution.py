"""Exercise real WASM compilation/execution through the PHP endpoint."""
import json
import time
import unittest

from test_execute_code import PhpApiFixture, ROOT


class LocalWasmExecutionTests(PhpApiFixture):
    use_local_runner = True

    @classmethod
    def setUpClass(cls):
        config_path = ROOT / "tools" / "wasm-runner.config.json"
        if not config_path.is_file() or json.loads(config_path.read_text())["enabled"] is not True:
            raise unittest.SkipTest("Local WASM runner is not enabled")
        for name in ("cpp-wasm-runner.cjs", "python-wasi-runner.py"):
            if not (ROOT / "tools" / name).is_file():
                raise unittest.SkipTest("Install both WebAssembly language helpers first")
        super().setUpClass()

    def execute(self, language, code, stdin=""):
        status, result, _ = self.request({"language": language, "code": code, "stdin": stdin})
        self.assertEqual(status, 200, result)
        self.assertLessEqual(len(result["output"].encode("utf-8")), 65536)
        return result

    def test_python_real_input_output_and_errors(self):
        result = self.execute("python", 'first, second = map(int, input().split())\nprint(first + second)', "17 25\n")
        self.assertEqual(result, {"output": "42\n", "error": False})
        self.assertEqual(self.execute("python", 'print("Salom, dunyo — o‘zbekcha")')["output"], "Salom, dunyo — o‘zbekcha\n")
        for code, expected in (("if True print(1)", "SyntaxError"), ("print(1 / 0)", "ZeroDivisionError")):
            with self.subTest(code=code):
                result = self.execute("python", code)
                self.assertIs(result["error"], True)
                self.assertIn(expected, result["output"])

    def test_cpp_real_compilation_and_input(self):
        code = '#include <iostream>\nint main() { int first, second; std::cin >> first >> second; std::cout << first + second << "\\n"; }'
        self.assertEqual(self.execute("cpp", code, "17 25\n"), {"output": "42\n", "error": False})
        result = self.execute("cpp", "int main() { return unknown_name; }")
        self.assertIs(result["error"], True)
        self.assertIn("unknown_name", result["output"])

    def test_host_files_are_not_writable(self):
        marker = str(self.marker)
        python = "open(" + repr(marker) + ", 'w').write('escaped')"
        self.assertIs(self.execute("python", python)["error"], True)
        self.assertFalse(self.marker.exists())
        cpp = '#include <cstdio>\nint main() { FILE* file = std::fopen(' + json.dumps(marker) + ', "w"); if (file) { std::fputs("escaped", file); std::fclose(file); } }'
        self.execute("cpp", cpp)
        self.assertFalse(self.marker.exists())

    def test_empty_output_is_successful(self):
        for language, code in (("python", "pass"), ("cpp", "int main() { return 0; }")):
            with self.subTest(language=language):
                self.assertEqual(self.execute(language, code), {"output": "", "error": False})

    def test_output_limits(self):
        programs = (("python", 'print("x" * 70000)'),
                    ("cpp", '#include <cstdio>\nint main() { for (int i = 0; i < 70000; ++i) std::putchar(120); }'))
        for language, code in programs:
            with self.subTest(language=language):
                self.assertIs(self.execute(language, code)["error"], True)

    def test_infinite_loops_end_and_next_request_recovers(self):
        programs = (("python", "while True:\n    pass"),
                    ("cpp", "int main() { volatile unsigned int counter = 0; for (;;) ++counter; }"))
        for language, code in programs:
            with self.subTest(language=language):
                started = time.monotonic()
                result = self.execute(language, code)
                self.assertIs(result["error"], True)
                self.assertLess(time.monotonic() - started, 30)
                followup = "print(42)" if language == "python" else '#include <cstdio>\nint main() { std::puts("42"); }'
                self.assertEqual(self.execute(language, followup), {"output": "42\n", "error": False})


if __name__ == "__main__":
    unittest.main(verbosity=2)
