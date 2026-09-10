<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\VerificationResult;
use Modules\Core\Domain\DataObjects\Documents\VerifyDocumentData;
use Modules\Core\Domain\Exceptions\VerificationCodeNotFoundException;
use Modules\Core\Models\Document;

/**
 * ACT-VerifyDocument (Book A CORE-06 §7/BR-CORE-06-012/AC-CORE-06-005).
 * Backs the PUBLIC, unauthenticated `/verify/{code}` endpoint —
 * `VerificationResult` is the entire surface a caller may see: no
 * content, no learner/staff-identifying detail, ever.
 */
final class VerifyDocumentAction extends Action
{
    public function execute(VerifyDocumentData $data): VerificationResult
    {
        $document = Document::withoutGlobalScopes()
            ->with('school:id,name')
            ->where('verification_code', $data->verificationCode)
            ->first();

        if ($document === null) {
            throw new VerificationCodeNotFoundException('No document matches this verification code.');
        }

        return new VerificationResult(
            documentType: $document->document_type,
            schoolName: $document->school->name,
            issuedAt: $document->generated_at,
            isValid: $document->expires_at === null || $document->expires_at->isFuture(),
        );
    }
}
