<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->originalPublicPath = app()->publicPath();
    $this->publicTmpDir = sys_get_temp_dir().'/pest-favicons-'.uniqid();

    mkdir($this->publicTmpDir.'/images', 0755, true);
    copy($this->originalPublicPath.'/images/logo.png', $this->publicTmpDir.'/images/logo.png');

    app()->usePublicPath($this->publicTmpDir);
});

afterEach(function (): void {
    app()->usePublicPath($this->originalPublicPath);
    removeDirectory($this->publicTmpDir);
});

test('it generates every favicon format from the source logo', function (): void {
    $this->artisan('app:generate-favicons')->assertExitCode(0);

    $expectedFiles = [
        'favicon-16x16.png',
        'favicon-32x32.png',
        'apple-touch-icon.png',
        'mstile-150x150.png',
        'android-chrome-192x192.png',
        'android-chrome-512x512.png',
        'favicon.ico',
    ];

    foreach ($expectedFiles as $expectedFile) {
        expect($this->publicTmpDir.'/'.$expectedFile)->toBeReadableFile();
    }
});

test('it sizes each PNG variant to its target dimensions', function (): void {
    $this->artisan('app:generate-favicons')->assertExitCode(0);

    $expectedSizes = [
        'favicon-16x16.png' => 16,
        'favicon-32x32.png' => 32,
        'apple-touch-icon.png' => 180,
        'mstile-150x150.png' => 150,
        'android-chrome-192x192.png' => 192,
        'android-chrome-512x512.png' => 512,
    ];

    foreach ($expectedSizes as $filename => $size) {
        [$width, $height] = getimagesize($this->publicTmpDir.'/'.$filename);

        expect([$width, $height])->toBe([$size, $size]);
    }
});

test('it fails when the source logo is missing', function (): void {
    unlink($this->publicTmpDir.'/images/logo.png');

    $this->artisan('app:generate-favicons')->assertExitCode(1);
});

test('it leaves the repository public directory untouched', function (): void {
    $before = faviconFingerprints($this->originalPublicPath);

    $this->artisan('app:generate-favicons')->assertExitCode(0);

    expect(faviconFingerprints($this->originalPublicPath))->toBe($before);
});
