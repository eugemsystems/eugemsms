<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;

/**
 * ACT-ExportStatutorySchoolReturn (Book H3 CMP-02 §2). Exports the
 * return's own FROZEN `data_snapshot` — never a freshly rebuilt one,
 * so a re-export always matches what was (or will be) submitted
 * (BR-CMP-02-001).
 */
final class ExportStatutorySchoolReturnAction extends Action
{
    public function __construct(
        private readonly UploadFileAction $uploadFile,
    ) {}

    public function execute(int $returnId, int $exportedByUserId): StatutorySchoolReturn
    {
        return $this->transaction(function () use ($returnId, $exportedByUserId): StatutorySchoolReturn {
            $return = StatutorySchoolReturn::findOrFail($returnId);

            $file = $this->uploadFile->execute(new UploadFileData(
                schoolId: $return->school_id,
                category: 'statutory_return_export',
                contents: json_encode($return->data_snapshot, JSON_PRETTY_PRINT) ?: '',
                originalName: "{$return->return_type}-{$return->period_reference}.json",
                uploadedByUserId: $exportedByUserId,
            ));

            $return->update(['export_file_id' => $file->id]);

            return $return;
        });
    }
}
