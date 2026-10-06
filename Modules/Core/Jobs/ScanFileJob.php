<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Files\RecordScanResultAction;
use Modules\Core\Domain\Contracts\Files\VirusScanner;
use Modules\Core\Domain\DataObjects\Files\RecordScanResultData;
use Modules\Core\Models\File;
use RuntimeException;

/**
 * Book A CORE-10 BR-CORE-10-005. Every upload is scanned
 * asynchronously — this is that async step.
 */
final class ScanFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $fileId,
    ) {}

    public function handle(VirusScanner $scanner, RecordScanResultAction $recordScanResult): void
    {
        $file = File::query()->findOrFail($this->fileId);
        $stream = Storage::disk($file->disk)->readStream($file->path);

        if (! is_resource($stream)) {
            throw new RuntimeException("File [{$file->id}] could not be read from disk [{$file->disk}].");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'serp-scan-');

        try {
            $target = fopen($tempPath, 'wb');
            stream_copy_to_stream($stream, $target);
            fclose($target);
            fclose($stream);

            $result = $scanner->scan($tempPath);
        } finally {
            @unlink($tempPath);
        }

        $recordScanResult->execute(new RecordScanResultData($file->id, $result));
    }
}
