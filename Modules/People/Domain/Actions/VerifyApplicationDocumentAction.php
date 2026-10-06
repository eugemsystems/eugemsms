<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\VerifyApplicationDocumentData;
use Modules\People\Models\ApplicationDocument;

/**
 * ACT-VerifyApplicationDocument (Book C PPL-02 §2). Marks an application
 * document as checked, and by whom.
 */
final class VerifyApplicationDocumentAction extends Action
{
    public function execute(VerifyApplicationDocumentData $data): ApplicationDocument
    {
        $document = ApplicationDocument::findOrFail($data->documentId);

        return $this->transaction(function () use ($document, $data): ApplicationDocument {
            $document->update(['is_verified' => true, 'verified_by' => $data->verifiedByUserId]);

            return $document->fresh();
        });
    }
}
