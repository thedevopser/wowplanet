<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateFaviconsCommand extends Command
{
    protected $signature = 'app:generate-favicons';

    protected $description = 'Generate all favicon formats from public/images/logo.png';

    private const SIZES = [
        'favicon-16x16.png' => 16,
        'favicon-32x32.png' => 32,
        'apple-touch-icon.png' => 180,
        'mstile-150x150.png' => 150,
        'android-chrome-192x192.png' => 192,
        'android-chrome-512x512.png' => 512,
    ];

    private const ICO_SIZES = [16, 32, 48];

    public function handle(): int
    {
        $sourcePath = public_path('images/logo.png');

        if (! file_exists($sourcePath)) {
            $this->error('Source logo not found: '.$sourcePath);

            return self::FAILURE;
        }

        $mime = mime_content_type($sourcePath);
        $source = match ($mime) {
            'image/png' => imagecreatefrompng($sourcePath),
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($source === false) {
            $this->error(sprintf('Failed to load image (detected: %s).', $mime));

            return self::FAILURE;
        }

        imagealphablending($source, true);
        imagesavealpha($source, true);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        // Generate PNG variants
        foreach (self::SIZES as $filename => $size) {
            $resized = $this->resize($source, $sourceWidth, $sourceHeight, $size);
            $outputPath = public_path($filename);

            imagepng($resized, $outputPath, 9);

            $this->info(sprintf('  ✓ %s (%sx%s)', $filename, $size, $size));
        }

        // Generate favicon.ico (multi-size)
        $icoPath = public_path('favicon.ico');
        $this->generateIco($source, $sourceWidth, $sourceHeight, $icoPath);
        $this->info('  ✓ favicon.ico (16x16 + 32x32 + 48x48)');

        $this->newLine();
        $this->info('All favicons generated in public/');

        return self::SUCCESS;
    }

    /**
     * @param  positive-int  $size
     */
    private function resize(\GdImage $gdImage, int $srcW, int $srcH, int $size): \GdImage
    {
        $dest = imagecreatetruecolor($size, $size);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);

        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);

        if ($transparent !== false) {
            imagefill($dest, 0, 0, $transparent);
        }

        imagecopyresampled($dest, $gdImage, 0, 0, 0, 0, $size, $size, $srcW, $srcH);

        return $dest;
    }

    private function generateIco(\GdImage $gdImage, int $srcW, int $srcH, string $outputPath): void
    {
        $images = [];

        foreach (self::ICO_SIZES as $size) {
            $resized = $this->resize($gdImage, $srcW, $srcH, $size);

            // Capture PNG data
            ob_start();
            imagepng($resized);
            $pngData = ob_get_clean();

            $images[] = ['size' => $size, 'data' => $pngData];
        }

        // Build ICO file
        // ICO header: 6 bytes
        $numImages = count($images);
        $header = pack('vvv', 0, 1, $numImages); // reserved=0, type=1 (ICO), count

        $entries = '';
        $dataBlocks = '';
        $dataOffset = 6 + ($numImages * 16); // header + entries

        foreach ($images as $image) {
            $size = $image['size'];
            /** @var string $data */
            $data = $image['data'];
            $dataLen = strlen($data);

            // ICO directory entry: 16 bytes
            $entries .= pack('CCCCvvVV',
                $size,       // width (0 = 256)
                $size,       // height (0 = 256)
                0,           // color palette count
                0,           // reserved
                1,           // color planes
                32,          // bits per pixel
                $dataLen,    // data size
                $dataOffset  // data offset
            );

            $dataBlocks .= $data;
            $dataOffset += $dataLen;
        }

        file_put_contents($outputPath, $header.$entries.$dataBlocks);
    }
}
