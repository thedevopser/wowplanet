<?php

/**
 * Generate the OG default image (1200x630) for social sharing.
 * Run: docker compose exec app php scripts/generate-og-image.php
 */
$width = 1200;
$height = 630;
$outputPath = __DIR__.'/../public/images/og-default.png';
$fontDir = __DIR__.'/../storage/app/fonts';
$logoPath = __DIR__.'/../public/images/logo.png';

// Download Inter font if not cached
if (! is_dir($fontDir)) {
    mkdir($fontDir, 0755, true);
}

$fontBold = $fontDir.'/Inter-Bold.ttf';
$fontRegular = $fontDir.'/Inter-Regular.ttf';

$ctx = stream_context_create(['http' => ['header' => 'User-Agent: Mozilla/5.0']]);

if (! file_exists($fontBold)) {
    echo "Downloading Inter Bold...\n";
    $url = 'https://fonts.gstatic.com/s/inter/v20/UcCO3FwrK3iLTeHuS_nVMrMxCp50SjIw2boKoduKmMEVuFuYMZg.ttf';
    $data = file_get_contents($url, false, $ctx);
    if (! $data) {
        exit("Failed to download Inter Bold. Check network.\n");
    }
    file_put_contents($fontBold, $data);
    echo "Font saved.\n";
}

if (! file_exists($fontRegular)) {
    echo "Downloading Inter Regular...\n";
    $url = 'https://fonts.gstatic.com/s/inter/v20/UcCO3FwrK3iLTeHuS_nVMrMxCp50SjIw2boKoduKmMEVuLyfMZg.ttf';
    $data = file_get_contents($url, false, $ctx);
    if (! $data) {
        exit("Failed to download Inter Regular. Check network.\n");
    }
    file_put_contents($fontRegular, $data);
    echo "Font saved.\n";
}

// Create image
$img = imagecreatetruecolor($width, $height);

// Colors (matching site dark theme)
$bgColor = imagecolorallocate($img, 15, 23, 42);         // slate-900 #0f172a
$accentDark = imagecolorallocate($img, 30, 41, 59);       // slate-800
$blue500 = imagecolorallocate($img, 59, 130, 246);        // blue-500
$blue400 = imagecolorallocate($img, 96, 165, 250);        // blue-400
$blue600 = imagecolorallocate($img, 37, 99, 235);         // blue-600
$white = imagecolorallocate($img, 255, 255, 255);
$slate300 = imagecolorallocate($img, 203, 213, 225);      // slate-300
$slate400 = imagecolorallocate($img, 148, 163, 184);      // slate-400
$slate500 = imagecolorallocate($img, 100, 116, 139);      // slate-500
$slate700 = imagecolorallocate($img, 51, 65, 85);         // slate-700

// Fill background
imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);

// Subtle grid pattern
for ($x = 0; $x < $width; $x += 40) {
    imageline($img, $x, 0, $x, $height, $slate700);
}
for ($y = 0; $y < $height; $y += 40) {
    imageline($img, 0, $y, $width, $y, $slate700);
}

// Overlay to soften grid
$overlay = imagecolorallocatealpha($img, 15, 23, 42, 80);
imagefilledrectangle($img, 0, 0, $width, $height, $overlay);

// Top accent bar (gradient effect via multiple lines)
for ($i = 0; $i < 4; $i++) {
    $r = 59 + ($i * 10);
    $g = 130 - ($i * 8);
    $b = 246 - ($i * 3);
    $c = imagecolorallocate($img, min($r, 255), max($g, 0), max($b, 0));
    imageline($img, 0, $i, $width, $i, $c);
}

// Bottom accent bar
for ($i = 0; $i < 3; $i++) {
    imageline($img, 0, $height - 1 - $i, $width, $height - 1 - $i, $blue600);
}

// Decorative circles (subtle background elements)
$circleColor = imagecolorallocatealpha($img, 59, 130, 246, 115);
imagefilledellipse($img, 950, 120, 300, 300, $circleColor);
$circleColor2 = imagecolorallocatealpha($img, 37, 99, 235, 120);
imagefilledellipse($img, 1050, 500, 200, 200, $circleColor2);

// Load and place logo
if (file_exists($logoPath)) {
    $logo = imagecreatefromjpeg($logoPath);
    if ($logo) {
        $logoSize = 160;
        $logoX = 80;
        $logoY = ($height - $logoSize) / 2 - 40;

        // Draw circular border behind logo
        $borderColor = imagecolorallocatealpha($img, 59, 130, 246, 60);
        imagefilledellipse($img, (int) ($logoX + $logoSize / 2), (int) ($logoY + $logoSize / 2), $logoSize + 16, $logoSize + 16, $borderColor);

        // Resize and place logo
        imagecopyresampled($img, $logo, (int) $logoX, (int) $logoY, 0, 0, $logoSize, $logoSize, 640, 640);
        imagedestroy($logo);

        $textX = $logoX + $logoSize + 50;
    } else {
        $textX = 80;
    }
} else {
    $textX = 80;
}

// Title: "WowPlanet"
$titleSize = 64;
$titleY = 220;
imagettftext($img, $titleSize, 0, (int) $textX, (int) $titleY, $white, $fontBold, 'WowPlanet');

// Subtitle
$subtitleSize = 26;
$subtitleY = $titleY + 55;
imagettftext($img, $subtitleSize, 0, (int) $textX, (int) $subtitleY, $blue400, $fontBold, 'Suivi de progression World of Warcraft');

// Description
$descSize = 18;
$descY = $subtitleY + 50;
imagettftext($img, $descSize, 0, (int) $textX, (int) $descY, $slate400, $fontRegular, 'Quêtes · Hauts-faits · Montures · Mascottes');

// Stats bar at bottom
$barY = $height - 80;
$barBg = imagecolorallocatealpha($img, 30, 41, 59, 40);
imagefilledrectangle($img, 0, $barY - 15, $width, $height, $barBg);

$stats = ['21 000+ Quêtes', '8 600+ Hauts-faits', '1 569 Montures', '2 117 Mascottes'];
$statX = 80;
foreach ($stats as $i => $stat) {
    if ($i > 0) {
        // Separator dot
        imagefilledellipse($img, (int) $statX, (int) ($barY + 18), 5, 5, $slate500);
        $statX += 20;
    }
    imagettftext($img, 14, 0, (int) $statX, (int) ($barY + 24), $slate300, $fontRegular, $stat);
    $bbox = imagettfbbox(14, 0, $fontRegular, $stat);
    $statX += ($bbox[2] - $bbox[0]) + 30;
}

// Border
imagerectangle($img, 0, 0, $width - 1, $height - 1, $slate700);

// Save
imagepng($img, $outputPath, 5);
imagedestroy($img);

echo "OG image generated: {$outputPath}\n";
echo 'Size: '.round(filesize($outputPath) / 1024)." KB\n";
