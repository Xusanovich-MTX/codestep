"""Regression checks for real formatters, diagnostics, and non-execution."""
import ast
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import unittest
from unittest import mock
import uuid


ROOT = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location("code_tools", ROOT / "code_tools.py")
code_tools = importlib.util.module_from_spec(spec)
spec.loader.exec_module(code_tools)


class StaticToolsTests(unittest.TestCase):
    def request(self, code, language="python", action="check", style="kr"):
        return code_tools.handle({"code": code, "language": language, "action": action, "cpp_style": style})

    def test_python_diagnostics_have_real_line_numbers(self):
        result = self.request("value=1\nprint(missing_name)\n")
        self.assertEqual(result["tools"], ["Python AST", "flake8"])
        self.assertTrue(any(d["rule"] == "E225" and d["line"] == 1 for d in result["diagnostics"]))
        self.assertTrue(any(d["rule"] == "F821" and d["line"] == 2 for d in result["diagnostics"]))

    def test_syntax_error_blocks_formatter(self):
        code = "def invalid(:\n    pass\n"
        self.assertEqual(self.request(code)["diagnostics"][0]["rule"], "PY-SYNTAX")
        with self.assertRaises(code_tools.ToolError) as raised:
            self.request(code, action="format")
        self.assertEqual(raised.exception.status, 422)

    def test_indentation_errors_explain_the_problem_at_the_parser_location(self):
        cases = [
            ('    print("start")\n', "PY-INDENT", "ortiqcha surilish", 1),
            ('if True:\n    print(1)\n        print(2)\n', "PY-INDENT", "ortiqcha surilish", 3),
            ('if True:\nprint(1)\n', "PY-INDENT", "tanasi ichkariga", 2),
            ('def missing():\n    # only a comment\n', "PY-INDENT", "tanasi ichkariga", 2),
            ('if True:\n    print(1)\n  print(2)\n', "PY-INDENT", "mos tekislanmagan", 3),
            ('if True:\n\tprint(1)\n        print(2)\n', "PY-TAB", "Tab va bo'sh joy", 3),
        ]
        for code, rule, explanation, line in cases:
            with self.subTest(code=code):
                with self.assertRaises(IndentationError) as parser_error:
                    ast.parse(code)
                for action in ("syntax", "check"):
                    result = self.request(code, action=action)
                    self.assertEqual(result["tools"], ["Python AST"])
                    finding, = result["diagnostics"]
                    self.assertEqual(finding["rule"], rule)
                    self.assertEqual(finding["severity"], "error")
                    self.assertEqual(finding["line"], line)
                    self.assertEqual(finding["column"], max(1, parser_error.exception.offset or 1))
                    self.assertIn(explanation, finding["message"])
                with self.assertRaises(code_tools.ToolError) as format_error:
                    self.request(code, action="format")
                self.assertEqual(format_error.exception.status, 422)
                self.assertIn(explanation, str(format_error.exception))

    def test_live_syntax_accepts_valid_whitespace_without_requiring_a_linter(self):
        cases = [
            'total = 1 + 2\n\nprint(total)\n',
            'if True:\n    for number in range(2):\n        print(number)\n    print("done")\nprint("outside")\n',
            'if True:\n    result = sum(\n      [1, 2,\n       3]\n    )\n    print(result)\n',
            'total = 1 + \\\n  2\nprint(total)\n',
            'text = """first\n  keep this indentation\n\tand this tab\nlast"""\nprint(text)\n',
            '# indented blank and comment lines do not open a block\n    \n  # comment\nprint("ok")\n',
            # Four spaces are a style recommendation, not Python's syntax rule.
            'if True:\n  print("two spaces")\n',
            'if True:\n\tprint("consistent tab")\n',
        ]
        with mock.patch.object(code_tools, "require_module", side_effect=AssertionError("linter used")):
            for code in cases:
                with self.subTest(code=code):
                    result = self.request(code, action="syntax")
                    self.assertEqual(result["tools"], ["Python AST"])
                    self.assertEqual(result["diagnostics"], [])

    def test_python_format_preserves_strings_and_ast(self):
        code = 'text="a=b,# still text"\nother="""first\n  keep indentation\nlast"""\nif text:\n\tprint(text,other)\n'
        formatted = self.request(code, action="format")["code"]
        self.assertEqual(ast.dump(ast.parse(code)), ast.dump(ast.parse(formatted)))
        self.assertIn('text = "a=b,# still text"', formatted)
        self.assertIn("    print(text, other)", formatted)

    def test_comments_use_72_character_limit_and_noqa_cannot_hide_errors(self):
        code = "# " + "long " * 15 + "\nprint(missing_name)  # noqa\n"
        rules = {d["rule"] for d in self.request(code)["diagnostics"]}
        self.assertIn("W505", rules)
        self.assertIn("F821", rules)

    def test_python_formatter_fixes_checked_arithmetic_spacing(self):
        code = 'def add(first,second):\n    return first+second\n\nprint(add(2,3))\n'
        before_rules = {d["rule"] for d in self.request(code)["diagnostics"]}
        self.assertIn("E226", before_rules)
        formatted = self.request(code, action="format")["code"]
        self.assertIn("return first + second", formatted)
        self.assertEqual(ast.dump(ast.parse(code)), ast.dump(ast.parse(formatted)))
        self.assertEqual(self.request(formatted)["diagnostics"], [])

    def test_python_formatter_keeps_comparison_semantics(self):
        code = 'def compare(value):\n    return value==None or value==True\n'
        formatted = self.request(code, action="format")["code"]
        self.assertIn("value == None or value == True", formatted)
        self.assertEqual(ast.dump(ast.parse(code)), ast.dump(ast.parse(formatted)))
        remaining = {d["rule"] for d in self.request(formatted)["diagnostics"]}
        self.assertTrue({"E711", "E712"}.issubset(remaining))

    def test_cpp_brace_styles_and_literal_preservation(self):
        code = 'int main(){const char* text="a=b,{keep}";if(text){return 1;}return 0;}\n'
        kr = self.request(code, "cpp", "format", "kr")["code"]
        allman = self.request(code, "cpp", "format", "allman")["code"]
        self.assertIn("int main() {", kr)
        self.assertIn("int main()\n{", allman)
        self.assertIn('"a=b,{keep}"', kr)
        self.assertIn("    if (text)", allman)
        self.assertTrue(self.request(code, "cpp")["diagnostics"])
        self.assertFalse(self.request(kr, "cpp")["diagnostics"])

    def test_source_never_executes(self):
        marker = ROOT / ("must-not-exist-" + uuid.uuid4().hex + ".txt")
        code = "from pathlib import Path\nPath(" + repr(str(marker)) + ").write_text('executed')\n"
        self.request(code)
        self.request(code, action="syntax")
        self.request(code, action="format")
        self.assertFalse(marker.exists())

    def test_size_and_command_injection_inputs_rejected(self):
        for payload in [
            {"code": "a" * 65537, "language": "python"},
            {"code": "a\x00b", "language": "python"},
            {"code": "print(1)", "language": "python; whoami"},
            {"code": "int main(){}", "language": "cpp", "cpp_style": "kr; whoami"},
            {"code": "int main(){}", "language": "cpp", "action": "syntax"},
        ]:
            with self.assertRaises(code_tools.ToolError):
                code_tools.handle(payload)

    def test_json_process_protocol(self):
        result = subprocess.run(
            [sys.executable, "-I", "-S", str(ROOT / "code_tools.py")],
            input=json.dumps({"code": "x=1\n", "language": "python", "action": "format"}),
            capture_output=True, text=True, timeout=12,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(json.loads(result.stdout)["code"], "x = 1\n")

    def test_live_syntax_process_protocol(self):
        result = subprocess.run(
            [sys.executable, "-I", "-S", str(ROOT / "code_tools.py")],
            input=json.dumps({"code": '    print("salom")\n', "language": "python", "action": "syntax"}),
            capture_output=True, text=True, timeout=12,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        finding, = json.loads(result.stdout)["diagnostics"]
        self.assertEqual(finding["rule"], "PY-INDENT")
        self.assertEqual(finding["line"], 1)
        self.assertIn("ortiqcha surilish", finding["message"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
