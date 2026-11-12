"""Execute submitted Python exclusively inside a resource-limited WASI guest.

The CPython module embeds its standard library. No host directory, environment,
socket or process capability is exposed to submitted code.
"""

import json
from pathlib import Path
import sys
import tempfile
import threading


ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT / "runner-python-packages"))
MAX_BYTES = 65536
MAX_STDIN_BYTES = 16384
MEMORY_BYTES = 128 * 1024 * 1024
TIMEOUT_SECONDS = 10
MODULE_PATH = ROOT / "wasm-python" / "python-3.12.0.wasm"


def failure(message):
    return {"output": message, "error": True}


def run(code, stdin):
    try:
        import wasmtime
    except ImportError:
        return failure("Python bajarish muhiti tayyor emas. tools/setup-python-wasi.py ni ishga tushiring.")
    if not MODULE_PATH.is_file():
        return failure("Python WASI moduli topilmadi. tools/setup-python-wasi.py ni ishga tushiring.")

    config = wasmtime.Config()
    config.epoch_interruption = True
    config.wasm_threads = False
    config.wasm_memory64 = False
    engine = wasmtime.Engine(config)
    output = bytearray()
    expired = threading.Event()
    exceeded = threading.Event()

    def capture(data):
        remaining = MAX_BYTES - len(output)
        output.extend(data[:remaining])
        if len(data) > remaining:
            exceeded.set()
            engine.increment_epoch()
        return len(data)

    def expire():
        expired.set()
        engine.increment_epoch()

    def result(error=False, message=""):
        # A guest can exit immediately after a write or timeout. The limit still
        # applies even when _start returns before the next epoch trap occurs.
        if exceeded.is_set():
            error, message = True, "Natija 64 KB chegaradan oshdi."
        elif expired.is_set():
            error, message = True, "Dastur 10 soniyalik vaqt chegarasidan oshdi."
        if message:
            suffix = ("\n" + message).encode("utf-8")
            del output[max(0, MAX_BYTES - len(suffix)):]
            output.extend(suffix)
        # A truncation may split a UTF-8 sequence; never emit broken JSON text.
        text = output.decode("utf-8", errors="replace").encode("utf-8")[:MAX_BYTES].decode("utf-8", errors="ignore")
        return {"output": text, "error": error}

    try:
        module = wasmtime.Module.from_file(engine, str(MODULE_PATH))
        with tempfile.TemporaryDirectory(prefix="codestep-python-input-") as temporary:
            input_path = Path(temporary) / "stdin.txt"
            input_path.write_bytes(stdin.encode("utf-8"))
            wasi = wasmtime.WasiConfig()
            wasi.argv = ["python", "-I", "-B", "-u", "-c", code]
            wasi.env = []
            # This opens fd 0 only; it does not preopen its containing directory.
            wasi.stdin_file = str(input_path)
            wasi.stdout_custom = capture
            wasi.stderr_custom = capture
            with wasmtime.Store(engine) as store:
                store.set_limits(memory_size=MEMORY_BYTES, memories=1, instances=1, tables=1)
                store.set_epoch_deadline(1)
                store.set_wasi(wasi)
                linker = wasmtime.Linker(engine)
                linker.define_wasi()
                timer = threading.Timer(TIMEOUT_SECONDS, expire)
                timer.daemon = True
                timer.start()
                try:
                    instance = linker.instantiate(store, module)
                    instance.exports(store)["_start"](store)
                except wasmtime.ExitTrap as error:
                    return result(error.code != 0)
                except wasmtime.Trap as error:
                    if exceeded.is_set():
                        return result(True, "Natija 64 KB chegaradan oshdi.")
                    if expired.is_set():
                        return result(True, "Dastur 10 soniyalik vaqt chegarasidan oshdi.")
                    return result(True, "Python bajarilish xatosi: " + str(error))
                finally:
                    timer.cancel()
                    timer.join()
        return result()
    except Exception as error:
        return result(True, "Python bajarish muhiti xatosi: " + str(error))


def main():
    try:
        raw = sys.stdin.buffer.read(MAX_BYTES * 12 + 1)
        if len(raw) > MAX_BYTES * 12:
            raise ValueError("So'rov hajmi chegaradan oshdi.")
        request = json.loads(raw.decode("utf-8-sig"))
        if not isinstance(request, dict):
            raise ValueError("Kod va kirish ma'lumotlari obyekt bo'lishi kerak.")
        code, stdin = request.get("code"), request.get("stdin", "")
        if not isinstance(code, str) or not isinstance(stdin, str):
            raise ValueError("Kod va kirish ma'lumotlari matn bo'lishi kerak.")
        if "\0" in code or "\0" in stdin:
            raise ValueError("Kod va kirish ma'lumotlarida NUL belgisi bo'lmasligi kerak.")
        if len(code.encode("utf-8")) > MAX_BYTES or len(stdin.encode("utf-8")) > MAX_STDIN_BYTES:
            raise ValueError("Kod 64 KB yoki kirish ma'lumotlari 16 KB chegaradan oshdi.")
        if not code.strip():
            raise ValueError("Ishga tushirish uchun kod kiriting.")
        response = run(code, stdin)
    except (ValueError, UnicodeError) as error:
        response = failure(str(error))
    sys.stdout.buffer.write(json.dumps(response, ensure_ascii=False).encode("utf-8"))


if __name__ == "__main__":
    main()
