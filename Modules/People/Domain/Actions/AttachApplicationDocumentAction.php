<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;
use Modules\People\Domain\DataObjects\AttachApplicationDocumentData;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationDocument;

/**
 * ACT-AttachApplicationDocument (Book C PPL-02 §2). A supporting document with
 * an application. Documents of an application that has become a learner are
 * carried to the learner file by conversion, so none can be added after.
 */
final class AttachApplicationDocumentAction extends Action
{
    public function execute(AttachApplicationDocumentData $data): ApplicationDocument
    {
        $application = Application::findOrFail($data->applicationId);

        if (! in_array($data->documentType, AttachStudentDocumentAction::TYPES, true)) {
            throw new InvalidArgumentException("Unknown document type [{$data->documentType}].");
        }

        if ($application->student_id !== null) {
            throw new InvalidArgumentException('This application has already become a learner; add the document to the learner instead.');
        }

        if (! File::query()->whereKey($data->fileId)->exists()) {
            throw new InvalidArgumentException('That file does not belong to this school.');
        }

        return $this->transaction(fn (): ApplicationDocument => ApplicationDocument::create([
            'school_id' => $application->school_id,
            'application_id' => $application->id,
            'document_type' => $data->documentType,
            'file_id' => $data->fileId,
            'is_verified' => false,
        ]));
    }
}
