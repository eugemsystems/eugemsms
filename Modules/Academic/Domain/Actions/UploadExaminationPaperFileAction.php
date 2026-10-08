<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\UploadExaminationPaperFileData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidFileContentException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Exceptions\UnregisteredFileCategoryException;
use Modules\Core\Domain\Registry\FileCategoryRegistry;
use Modules\Core\Domain\Support\Files\MimeTypeInspector;
use Modules\Core\Models\File;

/**
 * ACT-UploadExaminationPaperFile (Book E ACA-07 §3, encryption at rest).
 * Deliberately bypasses the generic `UploadFileAction` — that pipeline
 * writes plaintext to disk and dispatches `ScanFileJob`/
 * `GenerateImageVariantsJob` against the stored bytes, neither of which
 * make sense against an encrypted blob. Mirrors `CreateBackupAction`'s
 * own `Crypt::encryptString()` cycle instead: the one other place in
 * this codebase that already needed exactly this. The MIME is detected
 * from the plaintext before encrypting (so the stored `File` row still
 * describes the real content type); `scan_status` is `skipped`, same
 * reasoning as a backup — scanning ciphertext finds nothing.
 *
 * Content is locked once a paper is sealed (`SealExaminationPaperAction`'s
 * own "one-way, cannot return to draft" doctrine) — a file may only be
 * attached or replaced while still `draft` or `vetted`.
 */
final class UploadExaminationPaperFileAction extends Action
{
    private const array FILE_TYPES = ['paper' => 'paper_file_id', 'marking_scheme' => 'marking_scheme_file_id'];

    public function __construct(
        private readonly MimeTypeInspector $mimeInspector,
    ) {}

    public function execute(UploadExaminationPaperFileData $data): ExaminationPaper
    {
        $column = self::FILE_TYPES[$data->fileType] ?? throw new InvalidArgumentException("[{$data->fileType}] is not an examination paper file type.");

        $paper = ExaminationPaper::findOrFail($data->paperId);

        if (! in_array($paper->status, ['draft', 'vetted'], true)) {
            throw new InvalidStateTransitionException(
                "Paper #{$paper->id} is {$paper->status} — its file can no longer be replaced.",
                ['paper_id' => $paper->id, 'status' => $paper->status],
            );
        }

        $category = $data->fileType === 'paper' ? 'examination_paper' : 'examination_marking_scheme';
        $definition = FileCategoryRegistry::get($category)
            ?? throw new UnregisteredFileCategoryException("File category [{$category}] is not registered.", ['category' => $category]);

        $mimeType = $this->mimeInspector->detectFromContents($data->contents);

        if (! in_array($mimeType, $definition->allowedMimes, true)) {
            throw new InvalidFileContentException(
                "Content inspection found [{$mimeType}], which is not permitted for [{$category}].",
                ['detected_mime' => $mimeType, 'category' => $category],
            );
        }

        if (strlen($data->contents) > $definition->maxSizeBytes) {
            throw new InvalidFileContentException(
                "File exceeds the {$definition->maxSizeBytes}-byte limit for [{$category}].",
                ['size_bytes' => strlen($data->contents), 'max_size_bytes' => $definition->maxSizeBytes],
            );
        }

        $extension = pathinfo($data->originalName, PATHINFO_EXTENSION) ?: 'bin';
        $disk = (string) config('filesystems.documents_disk');
        $ulid = (string) Str::ulid();
        $path = "school/{$paper->school_id}/examination-papers/{$ulid}.enc";

        return $this->transaction(function () use ($paper, $data, $column, $category, $mimeType, $extension, $disk, $ulid, $path): ExaminationPaper {
            Storage::disk($disk)->put($path, Crypt::encryptString($data->contents));

            $file = File::create([
                'ulid' => $ulid,
                'school_id' => $paper->school_id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $data->originalName,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => strlen($data->contents),
                'hash' => hash('sha256', $data->contents),
                'category' => $category,
                'is_sensitive' => true,
                'scan_status' => 'skipped',
                'uploaded_by' => $data->uploadedByUserId,
            ]);

            $paper->update([$column => $file->id]);

            return $paper->fresh();
        });
    }
}
