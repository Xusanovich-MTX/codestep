'use strict';

// The Node host loads fixed compiler assets only. Submitted C++ is compiled and
// executed in WebAssembly with a fresh in-memory filesystem, never as native code.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { Worker, isMainThread, parentPort, workerData } = require('node:worker_threads');
const assets = path.join(__dirname, 'wasm-cpp');
const maxOutput = 65536;

function varuint(bytes, cursor) {
    let value = 0;
    for (let shift = 0; shift < 35; shift += 7) {
        if (cursor.offset >= bytes.length) throw new Error('Yaroqsiz WASM fayli.');
        const byte = bytes[cursor.offset++];
        value += (byte & 127) * 2 ** shift;
        if (!(byte & 128)) return value;
    }
    throw new Error('Yaroqsiz WASM uzunligi.');
}

function encode(value) {
    const bytes = [];
    do {
        let byte = value % 128;
        value = Math.floor(value / 128);
        if (value) byte |= 128;
        bytes.push(byte);
    } while (value);
    return Buffer.from(bytes);
}

// Enforce a memory maximum even for older toolchain modules without one.
function boundMemory(input, pages) {
    const bytes = Buffer.from(input);
    if (!bytes.subarray(0, 8).equals(Buffer.from([0, 97, 115, 109, 1, 0, 0, 0]))) {
        throw new Error('Yaroqsiz WASM sarlavhasi.');
    }
    const cursor = { offset: 8 };
    const parts = [bytes.subarray(0, 8)];
    let found = false;
    while (cursor.offset < bytes.length) {
        const start = cursor.offset;
        const id = bytes[cursor.offset++];
        const size = varuint(bytes, cursor);
        const end = cursor.offset + size;
        if (end > bytes.length) throw new Error('Yaroqsiz WASM bo‘limi.');
        if (id === 5) {
            const count = varuint(bytes, cursor);
            if (count !== 1 || found) throw new Error('Bitta WASM xotirasi talab qilinadi.');
            found = true;
            const flags = varuint(bytes, cursor);
            if (flags > 1) throw new Error('Bu WASM xotira turi qo‘llanmaydi.');
            const initial = varuint(bytes, cursor);
            const maximum = flags === 1 ? Math.min(pages, varuint(bytes, cursor)) : pages;
            if (initial > maximum || cursor.offset !== end) throw new Error('Xotira chegarasi oshdi.');
            const memory = Buffer.concat([encode(1), encode(1), encode(initial), encode(maximum)]);
            parts.push(Buffer.from([5]), encode(memory.length), memory);
        } else {
            parts.push(bytes.subarray(start, end));
        }
        cursor.offset = end;
    }
    if (!found) throw new Error('WASM xotirasi topilmadi.');
    return Buffer.concat(parts);
}

async function execute(payload) {
    let output = [];
    let outputBytes = 0;
    let overflow = false;
    const hostWrite = text => {
        if (overflow) throw new Error('Natija hajmi 64 KB dan oshdi.');
        const chunk = Buffer.from(text, 'latin1');
        if (outputBytes + chunk.length > maxOutput) {
            output.push(chunk.subarray(0, maxOutput - outputBytes));
            overflow = true;
            throw new Error('Natija hajmi 64 KB dan oshdi.');
        }
        outputBytes += chunk.length;
        output.push(chunk);
    };
    // This trusted, pinned adapter exposes only WASI memfs and numeric canvas
    // stubs to the WASM instance. It exposes no Node filesystem or network API.
    const source = fs.readFileSync(path.join(assets, 'shared.js'), 'utf8');
    const apiSource = source.replace('return API;', 'return { API, App };');
    if (apiSource === source) throw new Error('Kompilyator adapteri mos emas.');
    const { API, App } = vm.runInNewContext(apiSource + '\nAPI;', {
        WebAssembly, Uint8Array, Uint32Array, ArrayBuffer,
        console: { log() {} }, setTimeout, ctx2d: null, canvas: null
    });
    API.prototype.hostLog = function () {};
    API.prototype.hostLogAsync = async function (_, promise) { return promise; };
    API.prototype.run = async function (module, ...args) {
        // The pinned adapter omits compiler-rt. libc++ algorithms also reference
        // its long-double helpers (for example __lttf2), even for integer sort.
        // Link the real builtins already bundled in the same pinned sysroot.
        if (args[0] === 'wasm-ld') {
            args.push('lib/clang/8.0.1/lib/wasi/libclang_rt.builtins-wasm32.a');
        }
        const app = new App(module, this.memfs, ...args);
        await app.run();
    };
    const allowed = new Set(['clang', 'lld', 'memfs', 'sysroot.tar']);
    const readAsset = name => {
        if (!allowed.has(name)) throw new Error('Noma’lum kompilyator fayli.');
        return fs.readFileSync(path.join(assets, name));
    };
    const api = new API({
        hostWrite,
        readBuffer: async name => readAsset(name),
        compileStreaming: async name => WebAssembly.compile(boundMemory(readAsset(name), name === 'memfs' ? 4096 : 8192))
    });
    api.clangCommonArgs = api.clangCommonArgs.filter(arg => arg !== '-fcolor-diagnostics');
    api.clangCommonArgs.push('-std=c++17');
    try {
        await api.ready;
        await api.compile({ input: 'main.cpp', obj: 'main.o', contents: Buffer.from(payload.code, 'utf8') });
        await api.link('main.o', 'main.wasm');
        const program = await WebAssembly.compile(boundMemory(api.memfs.getFileContents('main.wasm'), 2048));
        // Compilation messages do not become the program's stdout.
        output = [];
        outputBytes = 0;
        api.memfs.setStdinStr(Buffer.from(payload.stdin, 'utf8').toString('latin1'));
        parentPort.postMessage({ phase: 'run' });
        await api.run(program, 'main.wasm');
        return { output: Buffer.concat(output).toString('utf8'), error: false };
    } catch (error) {
        if (overflow) return { output: 'Natija hajmi 64 KB dan oshdi. Kodni tekshiring.', error: true };
        const diagnostic = Buffer.concat(output).toString('utf8').replace(/\x1b\[[0-9;]*m/g, '');
        return { output: diagnostic || ('C++ xatosi: ' + error.message), error: true };
    }
}

function runInWorker(payload) {
    return new Promise(resolve => {
        const worker = new Worker(__filename, { workerData: payload, resourceLimits: { maxOldGenerationSizeMb: 192 } });
        let completed = false;
        let timer;
        const started = Date.now();
        const finish = async result => {
            if (completed) return;
            completed = true;
            clearTimeout(timer);
            await worker.terminate();
            resolve(result);
        };
        const deadline = milliseconds => {
            clearTimeout(timer);
            timer = setTimeout(() => finish({ output: 'Bajarish vaqti tugadi. Cheksiz sikl yoki murakkab hisoblashni tekshiring.', error: true }), milliseconds);
        };
        deadline(24000);
        worker.on('message', result => {
            if (result.phase === 'run') deadline(Math.max(1, Math.min(10000, 24000 - (Date.now() - started))));
            else finish(result);
        });
        worker.on('error', error => finish({ output: 'C++ muhiti xatosi: ' + error.message, error: true }));
        worker.on('exit', () => finish({ output: 'C++ muhiti kutilmaganda to‘xtadi.', error: true }));
    });
}

async function main() {
    let input = '';
    for await (const chunk of process.stdin) {
        input += chunk;
        if (Buffer.byteLength(input) > 393216) throw new Error('So‘rov hajmi juda katta.');
    }
    const payload = JSON.parse(input);
    if (!payload || typeof payload.code !== 'string' || typeof payload.stdin !== 'string' ||
        !payload.code.trim() || Buffer.byteLength(payload.code) > 65536 || Buffer.byteLength(payload.stdin) > 16384 ||
        payload.code.includes('\0') || payload.stdin.includes('\0')) throw new Error('Kod yoki kirish ma’lumotlari yaroqsiz.');
    const result = await runInWorker(payload);
    process.stdout.write(JSON.stringify(result));
}

if (isMainThread) {
    main().catch(error => process.stdout.write(JSON.stringify({ output: error.message, error: true })));
} else {
    execute(workerData).then(result => parentPort.postMessage(result)).catch(error => {
        parentPort.postMessage({ output: 'C++ muhiti ishga tushmadi: ' + error.message, error: true });
    });
}
