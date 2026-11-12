"""Static editor checks and formatting. Submitted source is never executed."""

import ast
import contextlib
import difflib
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import sys


ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT / "python-packages"))
MAX_BYTES = 65536
MAX_DIAGNOSTICS = 250


class ToolError(Exception):
    def __init__(self, message, status=503):
        super().__init__(message)
        self.status = status


def diagnostic(line, column, severity, rule, message):
    return {
        "line": max(1, int(line or 1)),
        "column": max(1, int(column or 1)),
        "severity": severity,
        "rule": rule,
        "message": message,
    }


def parse_python(code):
    try:
        return ast.parse(code, filename="editor.py")
    except (SyntaxError, ValueError, RecursionError) as error:
        message = str(getattr(error, "msg", error))
        rule = "PY-SYNTAX"
        # Let Python decide what is a block, continuation, or string. In
        # particular, a multiple of four spaces can still be an unexpected
        # indent, while a valid continuation need not use four spaces.
        if isinstance(error, TabError):
            rule = "PY-TAB"
            message = (
                "Tab va bo'sh joy aralashgani sababli blok surilishi noaniq. "
                "Blok boshidagi tablarni bo'sh joyga almashtiring va bir "
                "blokdagi qatorlarni bir xil tekislang; har daraja uchun "
                "4 bo'sh joy tavsiya etiladi."
            )
        elif isinstance(error, IndentationError):
            rule = "PY-INDENT"
            if message.startswith("unexpected indent"):
                message = (
                    "Qator boshida ortiqcha surilish bor. Blokdan tashqaridagi "
                    "kod chap chetdan boshlanadi; blok ichidagi qatorni shu "
                    "blokdagi boshqa qatorlar bilan tekislang."
                )
            elif message.startswith("expected an indented block"):
                message = (
                    "Ikki nuqta (:) bilan tugagan blokning tanasi ichkariga "
                    "surilishi kerak. Keyingi kod qatorini blok sarlavhasidan "
                    "4 bo'sh joy ichkarida yozing; bo'sh blok uchun pass yozing."
                )
            elif message.startswith("unindent does not match"):
                message = (
                    "Qator oldingi blok darajalariga mos tekislanmagan. "
                    "Qator boshini tegishli blokning boshlanishiga tekislang; "
                    "har daraja uchun 4 bo'sh joy tavsiya etiladi."
                )
            else:
                message = "Blok surilishini tekshiring: " + message
        return diagnostic(
            getattr(error, "lineno", 1),
            getattr(error, "offset", 1),
            "error", rule, message,
        )


def require_module(name):
    if importlib.util.find_spec(name) is None:
        raise ToolError(
            name + " o'rnatilmagan. Serverda tools/setup-code-tools.py ni ishga tushiring."
        )


def python_syntax(code):
    tree = parse_python(code)
    return {
        "diagnostics": [tree] if isinstance(tree, dict) else [],
        "tools": ["Python AST"],
        "message": "Python sintaksisida xato topildi." if isinstance(tree, dict)
        else "Python sintaksisi tekshirildi.",
    }


def python_check(code):
    tree = parse_python(code)
    if isinstance(tree, dict):
        return {
            "diagnostics": [tree], "tools": ["Python AST"],
            "message": "Python sintaksisida xato topildi.",
        }
    require_module("flake8")
    from flake8.main.application import Application
    from flake8 import utils

    captured_bytes = io.BytesIO()
    capture = io.TextIOWrapper(captured_bytes, encoding="utf-8", write_through=True)
    # Supply source entirely in memory; it is never imported or written as a module.
    previous_stdin = sys.stdin
    source_input = io.TextIOWrapper(io.BytesIO(code.encode("utf-8")), encoding="utf-8")
    sys.stdin = source_input
    utils.stdin_get_value.cache_clear()
    try:
        app = Application()
        with contextlib.redirect_stdout(capture), contextlib.redirect_stderr(capture):
            app.run([
                "--isolated", "--jobs=1", "--max-line-length=79",
                "--max-doc-length=72", "--select=E,F,W,C90", "--max-complexity=10",
                "--disable-noqa", "--format=%(row)d|%(col)d|%(code)s|%(text)s",
                "--stdin-display-name=editor.py", "-",
            ])
        if app.catastrophic_failure:
            raise ToolError("flake8 tekshiruvi bajarilmadi.", 500)
    finally:
        sys.stdin = previous_stdin
        source_input.close()
        utils.stdin_get_value.cache_clear()
    diagnostics = []
    for row in captured_bytes.getvalue().decode("utf-8").splitlines():
        fields = row.split("|", 3)
        if len(fields) != 4 or not fields[0].isdigit() or not fields[1].isdigit():
            continue
        line, column, rule, message = fields
        severity = "error" if rule.startswith("F") or rule in {"E901", "E902", "E999"} else "warning"
        diagnostics.append(diagnostic(line, column, severity, rule, message))
        if len(diagnostics) == MAX_DIAGNOSTICS:
            break
    capture.close()
    return {
        "diagnostics": diagnostics, "tools": ["Python AST", "flake8"],
        "message": "Python sintaksisi va PEP 8 tekshirildi.",
    }


def python_format(code):
    before = parse_python(code)
    if isinstance(before, dict):
        raise ToolError("Avval Python sintaksisini tuzating: " + before["message"], 422)
    require_module("autopep8")
    import autopep8

    formatted = autopep8.fix_code(code, options={
        "max_line_length": 79, "indent_size": 4,
        # Include spacing rules such as E226 that flake8 checks but autopep8
        # ignores by default. Opposing line-break rules cannot both be fixed.
        "select": ["E", "W"], "ignore": ["W503", "W504"],
        "aggressive": 0, "pep8_passes": 5,
    })
    after = parse_python(formatted)
    if isinstance(after, dict) or ast.dump(before) != ast.dump(after):
        raise ToolError("Formatlash kod mazmunini o'zgartirishi mumkin; asl kod saqlandi.", 422)
    return {
        "code": formatted, "tools": ["autopep8", "Python AST"],
        "message": "PEP 8 bo'yicha formatlandi; Python AST o'zgarmagani tekshirildi.",
    }


def clang_binary():
    name = "clang-format.exe" if sys.platform == "win32" else "clang-format"
    bundled = ROOT / "python-packages" / "clang_format" / "data" / "bin" / name
    if bundled.is_file():
        return bundled
    raise ToolError("clang-format o'rnatilmagan. Serverda tools/setup-code-tools.py ni ishga tushiring.")


def cpp_format(code, style):
    options = {
        "BasedOnStyle": "LLVM", "IndentWidth": 4, "TabWidth": 4,
        "UseTab": "Never", "ColumnLimit": 79,
        "BreakBeforeBraces": "Allman" if style == "allman" else "Attach",
        "AllowShortFunctionsOnASingleLine": "None",
        "AllowShortIfStatementsOnASingleLine": "Never",
        "AllowShortLoopsOnASingleLine": False,
        "NamespaceIndentation": "All", "IndentAccessModifiers": True,
        "SortIncludes": "CaseSensitive", "IncludeBlocks": "Regroup",
        "IncludeCategories": [
            {"Regex": "^<.*>$", "Priority": 1},
            {"Regex": '^".*"$', "Priority": 2},
        ],
    }
    try:
        completed = subprocess.run(
            [str(clang_binary()), "--style=" + json.dumps(options),
             "--assume-filename=editor.cpp"],
            input=code, text=True, encoding="utf-8", capture_output=True,
            timeout=6, cwd=str(ROOT), shell=False,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == "win32" else 0,
        )
    except subprocess.TimeoutExpired as error:
        raise ToolError("C++ formatlash vaqt chegarasidan oshdi.", 504) from error
    if completed.returncode:
        message = completed.stderr.strip()[:1000]
        raise ToolError("C++ formatlanmadi: " + message, 422)
    if len(completed.stdout.encode("utf-8")) > 524288:
        raise ToolError("Formatlangan kod hajmi chegaradan oshdi.", 413)
    return {
        "code": completed.stdout, "tools": ["clang-format"],
        "message": "C++ " + ("Allman" if style == "allman" else "K&R") + " uslubida formatlandi.",
    }


def cpp_check(code, style):
    try:
        formatted = cpp_format(code, style)
    except ToolError as error:
        if error.status != 422:
            raise
        return {
            "diagnostics": [diagnostic(1, 1, "error", "CLANG-FORMAT", str(error))],
            "tools": ["clang-format"], "message": "C++ formatini tekshirishda xato topildi.",
        }
    diagnostics = []
    old_lines, new_lines = code.splitlines(), formatted["code"].splitlines()
    for operation, start, end, _, _ in difflib.SequenceMatcher(
            None, old_lines, new_lines, autojunk=False).get_opcodes():
        if operation == "equal":
            continue
        diagnostics.append(diagnostic(
            min(start + 1, max(1, len(old_lines))), 1, "warning", "CLANG-FORMAT",
            "Tanlangan C++ uslubiga moslash uchun Formatlash tugmasini bosing.",
        ))
        if len(diagnostics) == MAX_DIAGNOSTICS:
            break
    return {
        "diagnostics": diagnostics, "tools": formatted["tools"],
        "message": "C++ formati tekshirildi. To'liq kompilyatsiya tekshiruvi bajarilmadi.",
    }


def handle(payload):
    if not isinstance(payload, dict):
        raise ToolError("So'rov obyekt bo'lishi kerak.", 400)
    code, language = payload.get("code"), payload.get("language")
    action, style = payload.get("action", "check"), payload.get("cpp_style", "kr")
    if not isinstance(code, str) or "\x00" in code:
        raise ToolError("Kod matn bo'lishi va NUL belgisi saqlamasligi kerak.", 400)
    if len(code.encode("utf-8")) > MAX_BYTES or len(code.splitlines()) > 8000:
        raise ToolError("Kod hajmi 64 KB yoki 8000 qatordan oshmasligi kerak.", 413)
    if (language not in ("python", "cpp")
            or action not in ("check", "format", "syntax")
            or (action == "syntax" and language != "python")
            or style not in ("kr", "allman")):
        raise ToolError("Til, amal yoki C++ uslubi noto'g'ri.", 400)
    if language == "python":
        if action == "syntax":
            return python_syntax(code)
        return python_check(code) if action == "check" else python_format(code)
    return cpp_check(code, style) if action == "check" else cpp_format(code, style)


def main():
    try:
        raw = sys.stdin.buffer.read(393217)
        if len(raw) > 393216:
            raise ToolError("So'rov hajmi chegaradan oshdi.", 413)
        result = handle(json.loads(raw.decode("utf-8")))
    except ToolError as error:
        result = {"error": True, "message": str(error), "status": error.status}
    except (UnicodeError, json.JSONDecodeError) as error:
        result = {"error": True, "message": "JSON yoki UTF-8 matni noto'g'ri.", "status": 400}
    except Exception:
        # Details and local paths do not belong in an editor API response.
        result = {"error": True, "message": "Kod tekshirish vositasida ichki xato yuz berdi.", "status": 500}
    sys.stdout.buffer.write(json.dumps(result, ensure_ascii=True).encode("ascii"))


if __name__ == "__main__":
    main()
