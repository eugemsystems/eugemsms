<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\DownloadExaminationPaperFileResult;
use Modules\Academic\Domain\DataObjects\ReleaseExaminationPaperData;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;

/**
 * ACT-DownloadExaminationPaperFile (Book E ACA-07 §3, encryption at
 * rest). Decrypts and streams a paper's own file — every call first
 * runs the real `ReleaseExaminationPaperAction` (the `release_at`
 * time-lock with no override path, the access log, the per-user
 * download-limit alert), so a download attempt is gated exactly the
 * same way whether it is the first one (which also flips the paper to
 * `released`) or the hundredth.
 */
final class DownloadExaminationPaperFileAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ReleaseExaminationPaperAction $release,
    ) {}

    public function execute(ReleaseExaminationPaperData $data, string $fileType): DownloadExaminationPaperFileResult
    {
        $paper = $this->release->execute($data);

        $fileId = $fileType === 'marking_scheme' ? $paper->marking_scheme_file_id : $paper->paper_file_id;

        if ($fileId === null) {
            throw new InvalidArgumentException("Paper #{$paper->id} has no {$fileType} file attached.");
        }

        $file = File::findOrFail($fileId);
        $encrypted = Storage::disk($file->disk)->get($file->path);

        if ($encrypted === null) {
            throw new InvalidArgumentException("The stored file for paper #{$paper->id} is missing from disk.");
        }

        return new DownloadExaminationPaperFileResult(
            contents: Crypt::decryptString($encrypted),
            filename: $file->original_name,
            mimeType: $file->mime_type,
        );
    }
}
