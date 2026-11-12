"""Install local static tools and record this interpreter for PHP/OSPanel."""
import json
from pathlib import Path
import subprocess
import sys

root = Path(__file__).resolve().parent
subprocess.run([
    sys.executable, "-m", "pip", "install", "--upgrade", "--target",
    str(root / "python-packages"), "-r", str(root / "requirements-code-tools.txt"),
], check=True)
(root / "code-tools.config.json").write_text(
    json.dumps({"python": sys.executable}, indent=2) + "\n", encoding="utf-8"
)
print("Static tools installed. PHP interpreter configured:", sys.executable)
