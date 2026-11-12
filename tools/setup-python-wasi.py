"""Install verified project-local CPython/WASI assets (Windows x64)."""

import hashlib
import json
from pathlib import Path
import platform
import shutil
import subprocess
import sys
import tempfile
import urllib.request


ROOT = Path(__file__).resolve().parent
ASSETS = ROOT / "wasm-python"
PACKAGES = ROOT / "runner-python-packages"
WASM = {
    "filename": "python-3.12.0.wasm",
    "url": "https://github.com/vmware-labs/webassembly-language-runtimes/releases/download/python%2F3.12.0%2B20231211-040d5a6/python-3.12.0.wasm",
    "sha256": "e5dc5a398b07b54ea8fdb503bf68fb583d533f10ec3f930963e02b9505f7a763",
}
WHEEL = {
    "filename": "wasmtime-49.0.0-py3-none-win_amd64.whl",
    "url": "https://files.pythonhosted.org/packages/c7/41/b1d78d0ea1506abfa3005c8a50eee724b95a9cffbb2a0ed541f0965f5ab9/wasmtime-49.0.0-py3-none-win_amd64.whl",
    "sha256": "46a4e4220fd1bee45ad9c29a73cadad54373dad840eff104726490527bc7ef89",
}
LICENSES = [
    {
        "filename": "CPython-LICENSE.txt",
        "url": "https://raw.githubusercontent.com/python/cpython/v3.12.0/LICENSE",
        "sha256": "3b2f81fe21d181c499c59a256c8e1968455d6689d269aa85373bfb6af41da3bf",
    },
    {
        "filename": "CPython-THIRD-PARTY-LICENSES.rst",
        "url": "https://raw.githubusercontent.com/python/cpython/v3.12.0/Doc/license.rst",
        "sha256": "127cdab813c8caaaeb307b278892e82f4cd3427995663b5366c1bb53984a9055",
    },
    {
        "filename": "Wasm-Language-Runtimes-LICENSE.txt",
        "url": "https://raw.githubusercontent.com/vmware-labs/webassembly-language-runtimes/040d5a6/LICENSE",
        "sha256": "a7e361ef8f8946dbb02df66d10999fbf96a4d118618941199cfadc19efc668b5",
    },
    {
        "filename": "wasi-vfs-LICENSE.txt",
        "url": "https://raw.githubusercontent.com/kateinoigakukun/wasi-vfs/v0.4.0/LICENSE",
        "sha256": "268872b9816f90fd8e85db5a28d33f8150ebb8dd016653fb39ef1f94f2686bc5",
    },
    {
        "filename": "zlib-LICENSE.txt",
        "url": "https://raw.githubusercontent.com/madler/zlib/v1.2.13/LICENSE",
        "sha256": "845efc77857d485d91fb3e0b884aaa929368c717ae8186b66fe1ed2495753243",
    },
    {
        "filename": "bzip2-LICENSE.txt",
        "url": "https://raw.githubusercontent.com/libarchive/bzip2/bzip2-1.0.8/LICENSE",
        "sha256": "c6dbbf828498be844a89eaa3b84adbab3199e342eb5cb2ed2f0d4ba7ec0f38a3",
    },
]


def download(asset, destination):
    if destination.is_file() and hashlib.sha256(destination.read_bytes()).hexdigest() == asset["sha256"]:
        return
    request = urllib.request.Request(asset["url"], headers={"User-Agent": "CodeStep-WASI-setup"})
    with urllib.request.urlopen(request, timeout=60) as response:
        data = response.read(64 * 1024 * 1024)
    if hashlib.sha256(data).hexdigest() != asset["sha256"]:
        raise RuntimeError("Download checksum mismatch: " + asset["filename"])
    destination.write_bytes(data)


def main():
    if sys.platform != "win32" or platform.machine().lower() not in {"amd64", "x86_64"}:
        raise SystemExit("This pinned installer supports Windows x64 only.")
    ASSETS.mkdir(exist_ok=True)
    download(WASM, ASSETS / WASM["filename"])
    with tempfile.TemporaryDirectory(prefix="codestep-wasmtime-") as temporary:
        wheel_path = Path(temporary) / WHEEL["filename"]
        download(WHEEL, wheel_path)
        subprocess.run([
            sys.executable, "-m", "pip", "install", "--no-index", "--no-deps",
            "--upgrade", "--target", str(PACKAGES), str(wheel_path),
        ], check=True)
    license_directory = ASSETS / "licenses"
    license_directory.mkdir(exist_ok=True)
    for license_asset in LICENSES:
        download(license_asset, license_directory / license_asset["filename"])
    shutil.copyfile(
        PACKAGES / "wasmtime-49.0.0.dist-info" / "licenses" / "LICENSE",
        license_directory / "Wasmtime-LICENSE.txt",
    )
    manifest = {
        "python": "3.12.0", "wasmtime": "49.0.0",
        "artifacts": [WASM, WHEEL],
        "license_files": LICENSES,
        "provenance": "https://github.com/vmware-labs/webassembly-language-runtimes/releases/tag/python%2F3.12.0%2B20231211-040d5a6",
        "stdlib": "Embedded wasi-vfs; no host directory preopens required.",
        "licenses": {
            "CPython (PSF and historical licenses)": "https://github.com/python/cpython/blob/v3.12.0/LICENSE",
            "Wasm Language Runtimes (Apache-2.0)": "https://github.com/vmware-labs/webassembly-language-runtimes/blob/040d5a6/LICENSE",
            "Wasmtime (Apache-2.0 with LLVM exception)": "https://github.com/bytecodealliance/wasmtime/blob/v49.0.0/LICENSE",
            "wasi-vfs 0.4.0 (Apache-2.0 with LLVM exception)": "https://github.com/kateinoigakukun/wasi-vfs/blob/v0.4.0/LICENSE",
        },
    }
    (ASSETS / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    print("Installed CPython 3.12.0 WASI and Wasmtime 49.0.0 locally.")


if __name__ == "__main__":
    main()
