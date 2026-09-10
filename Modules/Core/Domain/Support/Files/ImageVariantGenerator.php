<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Files;

use GdImage;
use RuntimeException;

/**
 * Book A CORE-10 BR-CORE-10-006. Thumbnail (150px), medium (600px),
 * and large (1200px) WebP variants — built on PHP's bundled GD
 * extension (no new dependency; this environment's GD build has WebP
 * support). Resizing preserves aspect ratio, capping the LONGEST edge
 * at the target size.
 */
final class ImageVariantGenerator
{
    /**
     * @var array<string, int>
     */
    public const array SIZES = ['thumb' => 150, 'medium' => 600, 'large' => 1200];

    public function isSupported(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /**
     * @return array<string, string> variant name => WebP-encoded bytes
     */
    public function generate(string $contents): array
    {
        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Unable to read image contents for variant generation.');
        }

        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);
        $variants = [];

        foreach (self::SIZES as $name => $maxEdge) {
            $variants[$name] = $this->resizeToWebp($source, $originalWidth, $originalHeight, $maxEdge);
        }

        imagedestroy($source);

        return $variants;
    }

    private function resizeToWebp(GdImage $source, int $originalWidth, int $originalHeight, int $maxEdge): string
    {
        $scale = min(1.0, $maxEdge / max($originalWidth, $originalHeight));
        $width = max(1, (int) round($originalWidth * $scale));
        $height = max(1, (int) round($originalHeight * $scale));

        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);

        ob_start();
        imagewebp($resized, null, 85);
        $bytes = (string) ob_get_clean();

        imagedestroy($resized);

        return $bytes;
    }
}
