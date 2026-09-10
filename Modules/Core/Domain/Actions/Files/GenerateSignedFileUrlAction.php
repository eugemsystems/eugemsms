<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Files\GenerateSignedFileUrlData;
use Modules\Core\Domain\DataObjects\Files\RecordFileAccessData;
use Modules\Core\Domain\DataObjects\Files\SignedFileUrlResult;
use Modules\Core\Domain\Exceptions\FileAccessDeniedException;
use Modules\Core\Domain\Registry\FileCategoryRegistry;
use Modules\Core\Domain\Support\Files\SignedFileUrlGenerator;
use Modules\Core\Models\File;

/**
 * ACT-GenerateSignedFileUrl (Book A CORE-10 BR-CORE-10-003/005/006/007).
 * A `pending` or `infected` scan status refuses everyone but the
 * uploader (AC-CORE-10-002); a sensitive category's every grant is
 * logged to `file_access_log` (AC-CORE-10-004); an ungenerated variant
 * falls back to the original rather than 404ing.
 */
final class GenerateSignedFileUrlAction extends Action
{
    public function __construct(
        private readonly SignedFileUrlGenerator $urlGenerator,
        private readonly RecordFileAccessAction $recordFileAccess,
    ) {}

    public function execute(GenerateSignedFileUrlData $data): SignedFileUrlResult
    {
        $file = File::query()->findOrFail($data->fileId);

        if (! $file->isDownloadableBy($data->requestedByUserId)) {
            throw new FileAccessDeniedException(
                $file->isInfected()
                    ? 'This file was flagged by virus scanning and is unavailable.'
                    : 'This file is still being scanned and is only available to its uploader until then.',
            );
        }

        $servedVariant = $data->variant !== 'original' && isset($file->variants[$data->variant]) ? $data->variant : 'original';

        $category = FileCategoryRegistry::get($file->category);

        if ($category?->isSensitive === true) {
            $this->recordFileAccess->execute(new RecordFileAccessData($file->id, $data->requestedByUserId, $data->action, $data->ip));
        }

        return new SignedFileUrlResult(
            url: $this->urlGenerator->generate($file->ulid, $servedVariant),
            variantServed: $servedVariant,
        );
    }
}
