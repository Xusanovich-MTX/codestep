"""Install the pinned, local-only C++ WebAssembly compiler and its licenses."""
import hashlib
import json
from pathlib import Path
import shutil
import urllib.request

ROOT = Path(__file__).resolve().parent
COMMIT = "648c4a89997a351eef75cdaec3ef5b89d4937dec"
BASE = "https://raw.githubusercontent.com/binji/wasm-clang/" + COMMIT + "/"
FILES = {
    "clang": "2a466f0e990329d3230b869d04fc20803eae96a7feb3a3f6c93e25a77b8aed1d",
    "lld": "36419ed202011765222098d7701218378b67f634d50f0a4625059ae2c9860f48",
    "memfs": "2c72ee42bd9430029dda8c6bafc9f37143f6fe88d5f1ea950a70259ab748bcfe",
    "sysroot.tar": "2435a7b549af30c2be7ec249c405bc2e911ab0c6003012f0909ec3c131bff867",
    "shared.js": "4de0be862bda1a7571c1f0632abe3c62c47a56e734cc6b703155805a4b160b1c",
    "LICENSE": "cfc7749b96f63bd31c3c42b5c471bf756814053e847c10f3eb003417bc523d30",
    "LICENSE.llvm": "ebcd9bbf783a73d05c53ba4d586b8d5813dcdf3bbec50265860ccc885e606f47",
}


def main():
    node = shutil.which("node")
    if not node:
        raise SystemExit("Node.js is required for the WASM compiler host.")
    target = ROOT / "wasm-cpp"
    target.mkdir(exist_ok=True)
    manifest = {"repository": "https://github.com/binji/wasm-clang", "commit": COMMIT, "sha256": {}}
    for name, checksum in FILES.items():
        destination = target / name
        data = destination.read_bytes() if destination.is_file() else b""
        if hashlib.sha256(data).hexdigest() != checksum:
            with urllib.request.urlopen(BASE + name, timeout=90) as response:
                data = response.read(80000000)
        if hashlib.sha256(data).hexdigest() != checksum:
            raise RuntimeError("Download checksum mismatch: " + name)
        if name in ("clang", "lld", "memfs") and not data.startswith(b"\0asm"):
            raise RuntimeError("Not a WebAssembly module: " + name)
        destination.write_bytes(data)
        manifest["sha256"][name] = hashlib.sha256(data).hexdigest()
        print(name + ": " + str(len(data)) + " bytes", flush=True)
    (target / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    (ROOT / "wasm-runner.config.json").write_text(
        json.dumps({"enabled": True, "node": node}, indent=2) + "\n", encoding="utf-8")
    print("C++ WebAssembly compiler installed. No system settings changed.")


if __name__ == "__main__":
    main()
