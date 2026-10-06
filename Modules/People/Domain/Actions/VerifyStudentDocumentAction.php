<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\VerifyStudentDocumentData;
use Modules\People\Models\StudentDocument;

/**
 * ACT-VerifyDocument (Book C PPL-01 §5). Records who checked a learner's
 * document and whether the original was sighted. An expired document cannot be
 * verified: it proves nothing now.
 */
final class VerifyStudentDocumentAction extends Action
{
    public function execute(VerifyStudentDocumentData $data): StudentDocument
    {
        $document = StudentDocument::findOrFail($data->documentId);

        if ($document->expires_on !== null && $document->expires_on->isPast()) {
            throw new InvalidStateTransitionException(
                "Document #{$document->id} expired on {$document->expires_on->toDateString()} and cannot be verified.",
                ['document_id' => $document->id],
            );
        }

        return $this->transaction(function () use ($document, $data): StudentDocument {
            $document->update([
                'is_verified' => true,
                'verified_by' => $data->verifiedByUserId,
                'verified_at' => Carbon::now(),
                'is_original_sighted' => $data->originalSighted,
            ]);

            return $document->fresh();
        });
    }
}
