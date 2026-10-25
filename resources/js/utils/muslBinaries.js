// The vite container runs on Alpine (musl) and shares node_modules with the glibc host, whose
// npm only installs the native binaries of its own platform. Used by scripts/restore-musl-binaries.mjs.
const CONTAINER_PLATFORM = { os: 'linux', cpu: 'x64', libc: 'musl' };
const NODE_MODULES = 'node_modules/';

export class InvalidLockEntryError extends Error {
    constructor(message) {
        super(message);
        this.name = 'InvalidLockEntryError';
    }
}

const targetsContainer = (entry) => Object.entries(CONTAINER_PLATFORM).every(([field, value]) => entry[field]?.includes(value));

const packageNameOf = (path, entry) => entry.name ?? path.slice(path.lastIndexOf(NODE_MODULES) + NODE_MODULES.length);

function toBinary(path, entry) {
    if (!entry.version || !entry.integrity) {
        throw new InvalidLockEntryError(`${path} has no version or integrity in package-lock.json.`);
    }

    return { path, name: packageNameOf(path, entry), version: entry.version, integrity: entry.integrity };
}

export function muslBinaries(lockPackages) {
    if (typeof lockPackages !== 'object' || lockPackages === null) {
        throw new InvalidLockEntryError('package-lock.json has no "packages" section.');
    }

    return Object.entries(lockPackages)
        .filter(([, entry]) => targetsContainer(entry))
        .map(([path, entry]) => toBinary(path, entry));
}
