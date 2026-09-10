<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Events\Files\FileUploaded;
use Modules\Core\Domain\Exceptions\InvalidFileContentException;
use Modules\Core\Domain\Exceptions\MissingExpiryDateException;
use Modules\Core\Domain\Exceptions\StorageQuotaExceededException;
use Modules\Core\Domain\Exceptions\UnregisteredFileCategoryException;
use Modules\Core\Domain\Registry\FileCategoryRegistry;
use Modules\Core\Domain\Support\Files\MimeTypeInspector;
use Modules\Core\Jobs\GenerateImageVariantsJob;
use Modules\Core\Jobs\ScanFileJob;
use Modules\Core\Models\File;
use Modules\Core\Models\StorageQuota;

/**
 * ACT-UploadFile (Book A CORE-10 §2..4 ⭐). Rejects on an unregistered
 * category (BR-CORE-10-001), content-inspected MIME mismatch
 * (BR-CORE-10-002/AC-CORE-10-001), a missing required expiry
 * (BR-CORE-10-009), or a full storage quota for genuinely new bytes
 * (BR-CORE-10-010/AC-CORE-10-005) — identical content already stored
 * for this school (BR-CORE-10-008) skips both the disk write and the
 * quota check entirely, since no new bytes are being stored.
 */
final class UploadFileAction extends Action
{
    public function __construct(
        private readonly MimeTypeInspector $mimeInspector,
    ) {}

    public function execute(UploadFileData $data): File
    {
        $definition = FileCategoryRegistry::get($data->category)
            ?? throw new UnregisteredFileCategoryException("File category [{$data->category}] is not registered.", ['category' => $data->category]);

        $mimeType = $this->mimeInspector->detectFromContents($data->contents);

        if (! in_array($mimeType, $definition->allowedMimes, true)) {
            throw new InvalidFileContentException(
                "Content inspection found [{$mimeType}], which is not permitted for [{$data->category}].",
                ['detected_mime' => $mimeType, 'category' => $data->category],
            );
        }

        $sizeBytes = strlen($data->contents);

        if ($sizeBytes > $definition->maxSizeBytes) {
            throw new InvalidFileContentException(
                "File exceeds the {$definition->maxSizeBytes}-byte limit for [{$data->category}].",
                ['size_bytes' => $sizeBytes, 'max_size_bytes' => $definition->maxSizeBytes],
            );
        }

        if ($definition->requiresExpiry && $data->expiresOn === null) {
            throw new MissingExpiryDateException("Category [{$data->category}] requires an expiry date.");
        }

        $hash = hash('sha256', $data->contents);

        return $this->transaction(function () use ($data, $definition, $mimeType, $sizeBytes, $hash): File {
            $existing = File::where('school_id', $data->schoolId)->where('hash', $hash)->first();
            $disk = 'local';
            $extension = pathinfo($data->originalName, PATHINFO_EXTENSION) ?: 'bin';
            $ulid = (string) Str::ulid();

            if ($existing !== null) {
                $path = $existing->path;
                $disk = $existing->disk;
            } else {
                $quota = StorageQuota::where('school_id', $data->schoolId)->first();

                if ($quota !== null && ! $quota->hasRoomFor($sizeBytes)) {
                    throw new StorageQuotaExceededException(
                        'This school has reached its storage quota. Existing files remain accessible.',
                        ['quota_bytes' => $quota->quota_bytes, 'used_bytes' => $quota->used_bytes],
                    );
                }

                $path = "school/{$data->schoolId}/{$data->category}/{$ulid}.{$extension}";
                Storage::disk($disk)->put($path, $data->contents);
                $quota?->increment('used_bytes', $sizeBytes);
            }

            $file = File::create([
                'ulid' => $ulid,
                'school_id' => $data->schoolId,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $data->originalName,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $sizeBytes,
                'hash' => $hash,
                'category' => $data->category,
                'attachable_type' => $data->attachableType,
                'attachable_id' => $data->attachableId,
                'is_sensitive' => $definition->isSensitive,
                'scan_status' => 'pending',
                'expires_on' => $data->expiresOn,
                'uploaded_by' => $data->uploadedByUserId,
            ]);

            ScanFileJob::dispatch($file->id);

            if ($definition->generatesVariants && str_starts_with($mimeType, 'image/')) {
                GenerateImageVariantsJob::dispatch($file->id);
            }

            event(new FileUploaded($file));

            return $file;
        });
    }
}
