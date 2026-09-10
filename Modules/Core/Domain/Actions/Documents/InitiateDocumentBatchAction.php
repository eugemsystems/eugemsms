<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\InitiateDocumentBatchData;
use Modules\Core\Models\DocumentBatch;

/**
 * ACT-InitiateDocumentBatch (Book A CORE-06 §10 `JOB-GenerateDocumentBatch`
 * / BR-CORE-06-013). Creates the batch header only — the fan-out loop
 * that actually generates each document is a queued job, deferred to
 * a later wave along with every other CORE background job in Book A.
 */
final class InitiateDocumentBatchAction extends Action
{
    public function execute(InitiateDocumentBatchData $data): DocumentBatch
    {
        return $this->transaction(fn (): DocumentBatch => DocumentBatch::create([
            'school_id' => $data->schoolId,
            'document_type' => $data->documentType,
            'template_id' => $data->templateId,
            'total_count' => $data->totalCount,
            'status' => 'queued',
            'requested_by' => $data->requestedByUserId,
        ]));
    }
}
