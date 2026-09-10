<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Domain\Actions\Files\RecordImageVariantsAction;
use Modules\Core\Models\File;

/**
 * Book A CORE-10 BR-CORE-10-006/AC-CORE-10-003.
 */
final class GenerateImageVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $fileId,
    ) {}

    public function handle(RecordImageVariantsAction $recordImageVariants): void
    {
        $recordImageVariants->execute(File::query()->findOrFail($this->fileId));
    }
}
