<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Files\ImageVariantGenerator;
use Modules\Core\Models\File;

/**
 * ACT-RecordImageVariants (Book A CORE-10 BR-CORE-10-006/AC-CORE-10-003).
 * Called from `GenerateImageVariantsJob`.
 */
final class RecordImageVariantsAction extends Action
{
    public function __construct(
        private readonly ImageVariantGenerator $generator,
    ) {}

    public function execute(File $file): File
    {
        if (! $this->generator->isSupported()) {
            return $file;
        }

        $contents = Storage::disk($file->disk)->get($file->path);
        $variants = $this->generator->generate((string) $contents);
        $paths = [];

        foreach ($variants as $name => $bytes) {
            $variantPath = preg_replace('/\.[^.]+$/', "-{$name}.webp", $file->path);
            Storage::disk($file->disk)->put($variantPath, $bytes);
            $paths[$name] = $variantPath;
        }

        return $this->transaction(function () use ($file, $paths): File {
            $file->forceFill(['variants' => $paths])->save();

            return $file;
        });
    }
}
