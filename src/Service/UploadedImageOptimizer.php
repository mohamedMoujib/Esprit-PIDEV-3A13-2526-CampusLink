<?php

namespace App\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resizes and recompresses images stored under public/ after upload (services, publications).
 */
final class UploadedImageOptimizer
{
    private const MAX_EDGE = 1600;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * @param string $relativePathFromPublic e.g. "uploads/abc.jpg"
     */
    public function optimizeRelativePath(string $relativePathFromPublic): void
    {
        $relativePathFromPublic = ltrim($relativePathFromPublic, '/');
        $absolute = $this->projectDir . '/public/' . $relativePathFromPublic;
        $this->optimizeAbsolutePath($absolute);
    }

    public function optimizeAbsolutePath(string $absolutePath): void
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return;
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($ext === 'gif') {
            return;
        }

        try {
            $imagine = new Imagine();
            $image = $imagine->open($absolutePath);
        } catch (\Throwable) {
            return;
        }

        $size = $image->getSize();
        $w = $size->getWidth();
        $h = $size->getHeight();

        if ($w > self::MAX_EDGE || $h > self::MAX_EDGE) {
            $ratio = min(self::MAX_EDGE / $w, self::MAX_EDGE / $h);
            $newW = max(1, (int) round($w * $ratio));
            $newH = max(1, (int) round($h * $ratio));
            $image->resize(new Box($newW, $newH));
        }

        $options = $this->saveOptionsForExtension($ext);
        try {
            $image->save($absolutePath, $options);
        } catch (\Throwable) {
            // Corrupt or unsupported combination — leave file as uploaded
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function saveOptionsForExtension(string $ext): array
    {
        return match ($ext) {
            'jpg', 'jpeg' => ['jpeg_quality' => 85],
            'png' => ['png_compression_level' => 7],
            'webp' => ['webp_quality' => 85],
            default => [],
        };
    }
}
