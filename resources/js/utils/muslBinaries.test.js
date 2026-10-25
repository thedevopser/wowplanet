import { describe, it, expect } from 'vitest';
import { InvalidLockEntryError, muslBinaries } from './muslBinaries';

const native = (libc, overrides = {}) => ({
    version: '1.2.0',
    integrity: 'sha512-abc',
    optional: true,
    os: ['linux'],
    cpu: ['x64'],
    libc: [libc],
    ...overrides,
});

describe('muslBinaries', () => {
    it('lists the linux x64 musl binaries with their lockfile version and integrity', () => {
        const packages = {
            '': { name: 'wowplanet' },
            'node_modules/@rolldown/binding-linux-x64-musl': native('musl', { version: '1.2.12', integrity: 'sha512-rolldown' }),
            'node_modules/@rolldown/binding-linux-x64-gnu': native('glibc'),
        };

        expect(muslBinaries(packages)).toEqual([
            {
                path: 'node_modules/@rolldown/binding-linux-x64-musl',
                name: '@rolldown/binding-linux-x64-musl',
                version: '1.2.12',
                integrity: 'sha512-rolldown',
            },
        ]);
    });

    it('keeps a binary nested under another package at its own version', () => {
        const packages = {
            'node_modules/lightningcss-linux-x64-musl': native('musl', { version: '1.32.0' }),
            'node_modules/vite/node_modules/lightningcss-linux-x64-musl': native('musl', { version: '1.33.0' }),
        };

        expect(muslBinaries(packages).map(({ path, name, version }) => ({ path, name, version }))).toEqual([
            { path: 'node_modules/lightningcss-linux-x64-musl', name: 'lightningcss-linux-x64-musl', version: '1.32.0' },
            { path: 'node_modules/vite/node_modules/lightningcss-linux-x64-musl', name: 'lightningcss-linux-x64-musl', version: '1.33.0' },
        ]);
    });

    it('takes the published name of an aliased package', () => {
        const packages = { 'node_modules/alias-musl': native('musl', { name: 'real-binding-linux-x64-musl' }) };

        expect(muslBinaries(packages)[0].name).toBe('real-binding-linux-x64-musl');
    });

    it.each([
        ['another cpu', { cpu: ['arm64'] }],
        ['another os', { os: ['darwin'] }],
    ])('ignores a musl binary built for %s', (_, overrides) => {
        expect(muslBinaries({ 'node_modules/binding-musl': native('musl', overrides) })).toEqual([]);
    });

    it('ignores packages that declare no libc', () => {
        expect(muslBinaries({ 'node_modules/vue': { version: '3.5.0', integrity: 'sha512-vue' } })).toEqual([]);
    });

    it.each([
        ['version', { version: undefined }],
        ['integrity', { integrity: undefined }],
    ])('refuses a musl entry without %s', (_, overrides) => {
        expect(() => muslBinaries({ 'node_modules/binding-linux-x64-musl': native('musl', overrides) })).toThrow(InvalidLockEntryError);
    });

    it('refuses a lockfile without packages', () => {
        expect(() => muslBinaries(undefined)).toThrow(InvalidLockEntryError);
    });
});
