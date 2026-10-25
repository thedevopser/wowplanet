// Puts back the musl native binaries that an npm install run on the host removes, at the exact
// version and integrity of package-lock.json, nested ones included. Run by `make npm-ci`.
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { muslBinaries } from '../resources/js/utils/muslBinaries.js';

const ROOT = resolve(import.meta.dirname, '..');

const readJson = (path) => JSON.parse(readFileSync(path, 'utf8'));

function pack({ name, version, integrity }, destination) {
    const [archive] = JSON.parse(execFileSync('npm', ['pack', `${name}@${version}`, '--pack-destination', destination, '--json', '--silent'], { encoding: 'utf8' }));
    if (archive.integrity !== integrity) {
        throw new Error(`${name}@${version}: registry integrity does not match package-lock.json.`);
    }

    return join(destination, archive.filename);
}

function install(binary, downloads) {
    const archive = pack(binary, downloads);
    const target = resolve(ROOT, binary.path);

    rmSync(target, { recursive: true, force: true });
    mkdirSync(target, { recursive: true });
    execFileSync('tar', ['-xzf', archive, '--strip-components=1', '-C', target]);

    const installed = readJson(join(target, 'package.json')).version;
    if (installed !== binary.version) {
        throw new Error(`${binary.path}: installed ${installed}, expected ${binary.version}.`);
    }

    console.log(`${binary.path} ${installed}`);
}

const binaries = muslBinaries(readJson(resolve(ROOT, 'package-lock.json')).packages);
const downloads = mkdtempSync(join(tmpdir(), 'musl-binaries-'));

try {
    binaries.forEach((binary) => install(binary, downloads));
} finally {
    rmSync(downloads, { recursive: true, force: true });
}
