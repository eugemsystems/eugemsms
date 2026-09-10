<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\RecordDocumentDownloadData;
use Modules\Core\Domain\Events\Documents\DocumentDownloaded;
use Modules\Core\Models\Document;

/**
 * ACT-RecordDocumentDownload (Book A CORE-06 BR-CORE-06-015). Called by
 * the signed-URL download endpoint (a later wave) right before
 * streaming the file — direct storage paths are never exposed to a
 * caller, only this endpoint ever reads `file_path`.
 */
final class RecordDocumentDownloadAction extends Action
{
    public function execute(RecordDocumentDownloadData $data): Document
    {
        $document = Document::withoutGlobalScopes()->findOrFail($data->documentId);

        return $this->transaction(function () use ($document): Document {
            $document->increment('download_count');

            event(new DocumentDownloaded($document));

            return $document;
        });
    }
}
