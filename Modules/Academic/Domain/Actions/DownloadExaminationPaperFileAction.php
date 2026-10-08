<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\DownloadExaminationPaperFileResult;
use Modules\Academic\Domain\DataObjects\ReleaseExaminationPaperData;
use Modules\Academic\Domain\Support\PdfWatermarker;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;
use setasign\Fpdi\FpdiException;

/**
 * ACT-DownloadExaminationPaperFile (Book E ACA-07 §3, encryption at
 * rest + visible watermarking). Decrypts and streams a paper's own
 * file — every call first runs the real `ReleaseExaminationPaperAction`
 * (the `release_at` time-lock with no override path, the access log,
 * the per-user download-limit alert), so a download attempt is gated
 * exactly the same way whether it is the first one (which also flips
 * the paper to `released`) or the hundredth.
 *
 * **Gap closed (2026-10-08): visible watermarking.** A PDF file is
 * stamped with the downloading user's own name and the download
 * timestamp via `PdfWatermarker` (FPDI/FPDF, pure PHP — no
 * Imagick/Ghostscript dependency; chosen over the originally-picked
 * `intervention/image` once that was found unable to open a PDF at
 * all in this environment — user-approved "do what's best"). The
 * stamp is applied to the decrypted bytes on every call, never baked
 * into what's stored on disk — the ciphertext is untouched, so two
 * different users downloading the same paper each get a copy naming
 * themselves, not each other. If the stored bytes are not a parseable
 * PDF (`FpdiException`), the original bytes are served unstamped
 * rather than failing the download — a release that is gated and
 * logged must still succeed even if the file behind it is malformed.
 */
final class DownloadExaminationPaperFileAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ReleaseExaminationPaperAction $release,
        private readonly PdfWatermarker $watermarker,
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

        $contents = Crypt::decryptString($encrypted);

        if ($file->mime_type === 'application/pdf') {
            try {
                $contents = $this->watermarker->stamp($contents, $this->watermarkLabel($data->requestedByUserId));
            } catch (FpdiException) {
                // Stored bytes are not a parseable PDF — serve the original rather than fail the download.
            }
        }

        return new DownloadExaminationPaperFileResult(
            contents: $contents,
            filename: $file->original_name,
            mimeType: $file->mime_type,
        );
    }

    private function watermarkLabel(int $userId): string
    {
        $user = User::find($userId);
        $name = $user === null ? "user #{$userId}" : $user->name;

        return "Downloaded by {$name} on ".Carbon::now()->format('Y-m-d H:i');
    }
}
