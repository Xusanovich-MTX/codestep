"""HTTP regression checks against an already running CodeStep development site."""
import http.cookiejar
import json
import re
import sys
import urllib.error
import urllib.request


base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8898").rstrip("/")
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
with opener.open(base + "/index.php", timeout=15) as response:
    page = response.read().decode("utf-8")
token = re.search(r"['\"]X-CSRF-Token['\"]\s*:\s*['\"]([a-f0-9]{64})['\"]", page)
assert token, "Session CSRF token not found on page"


def request(payload=None, csrf=True, method=None, raw=None):
    body = raw if raw is not None else json.dumps(payload).encode() if payload is not None else None
    headers = {"Content-Type": "application/json"}
    if csrf:
        headers["X-CSRF-Token"] = token.group(1)
    req = urllib.request.Request(base + "/api/code-tools.php", data=body, headers=headers, method=method)
    try:
        response = opener.open(req, timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, json.loads(response.read())


assert request(method="GET")[0] == 405
assert request({"code": "x=1", "language": "python"}, csrf=False)[0] == 403
assert request(raw=b"{invalid json")[0] == 400
assert request({"code": "x", "language": "python;whoami"})[0] == 400
assert request({"code": "x" * 65537, "language": "python"})[0] == 413
status, formatted = request({"code": "value=1\n", "language": "python", "action": "format"})
assert status == 200 and formatted["code"] == "value = 1\n", formatted
status, checked = request({"code": "print(missing)\n", "language": "python", "action": "check"})
assert status == 200 and checked["diagnostics"][0]["rule"] == "F821", checked
assert request({"code": "def invalid(:", "language": "python", "action": "format"})[0] == 422
status, cpp = request({"code": "int main(){return 0;}", "language": "cpp", "action": "format", "cpp_style": "allman"})
assert status == 200 and "int main()\n{" in cpp["code"], cpp
print("PASS: 9 HTTP checks (method, CSRF, malformed input, language, size, Python format/lint/syntax, C++ format)")
