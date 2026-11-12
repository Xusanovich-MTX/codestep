"""Real C++ compile/run regressions for the isolated WASM host."""
import json
from pathlib import Path
import shutil
import subprocess
import time
import unittest

ROOT = Path(__file__).resolve().parent


class CppWasmTests(unittest.TestCase):
    def run_code(self, code, stdin=""):
        result = subprocess.run(
            [shutil.which("node"), str(ROOT / "cpp-wasm-runner.cjs")],
            input=json.dumps({"code": code, "stdin": stdin}).encode(),
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=35,
        )
        self.assertEqual(result.returncode, 0, result.stderr.decode(errors="replace"))
        response = json.loads(result.stdout)
        self.assertIsInstance(response["output"], str)
        self.assertIsInstance(response["error"], bool)
        return response

    def test_cpp17_stdin_and_standard_library(self):
        result = self.run_code("""#include <iostream>
#include <vector>
#include <numeric>
int main() {
    int first, second;
    std::cin >> first >> second;
    std::vector<int> values{first, second};
    if constexpr (sizeof(int) >= 2) {
        std::cout << std::accumulate(values.begin(), values.end(), 0) << "\\n";
    }
}
""", "12 30\n")
        self.assertEqual(result, {"output": "42\n", "error": False})

    def test_utf8_round_trip(self):
        result = self.run_code('#include <iostream>\n#include <string>\nint main() { std::string s; std::getline(std::cin, s); std::cout << "Salom, " << s; }', "O‘zbekiston 😀")
        self.assertEqual(result, {"output": "Salom, O‘zbekiston 😀", "error": False})

    def test_compile_error_is_real(self):
        result = self.run_code('int main() { missing_name; }')
        self.assertTrue(result["error"])
        self.assertIn('missing_name', result["output"])
        self.assertIn('main.cpp:1:', result["output"])

    def test_empty_output_and_nonzero_exit(self):
        self.assertEqual(self.run_code('int main() {}'), {"output": "", "error": False})
        self.assertTrue(self.run_code('int main() { return 7; }')["error"])

    def test_host_files_are_unavailable(self):
        root_file = json.dumps(str(ROOT / "wasm-runner.config.json"))
        result = self.run_code('#include <fstream>\n#include <iostream>\nint main() { std::ifstream f(' + root_file + '); std::cout << (f.is_open() ? "exposed" : "isolated"); }')
        self.assertEqual(result, {"output": "isolated", "error": False})

    def test_output_is_bounded(self):
        result = self.run_code('#include <iostream>\nint main() { for(int i=0;i<80000;++i) std::cout << "x"; }')
        self.assertTrue(result["error"])
        self.assertLessEqual(len(result["output"].encode()), 65536)
        self.assertIn('64 KB', result["output"])

    def test_infinite_loop_is_terminated(self):
        started = time.monotonic()
        result = self.run_code('int main() { volatile int n = 0; while (true) { n = 1; } }')
        self.assertTrue(result["error"])
        self.assertIn('vaqti tugadi', result["output"])
        self.assertLess(time.monotonic() - started, 30)


if __name__ == '__main__':
    unittest.main(verbosity=2)
