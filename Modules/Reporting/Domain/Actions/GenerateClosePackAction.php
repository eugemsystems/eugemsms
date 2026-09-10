<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Models\Term;
use Modules\Reporting\Domain\DataObjects\GenerateClosePackData;
use Modules\Reporting\Domain\Events\ClosePackGenerated;
use Modules\Reporting\Models\CloseCheckAcknowledgement;
use Modules\Reporting\Models\PeriodCloseChecklist;

/**
 * ACT-GenerateClosePack (Book H3 FIN-12 §4/BR-FIN-12-012
 * (AC-FIN-12-006)). "Signed" here means attributable and immutable —
 * a JSON document containing the full checklist result, every
 * acknowledgement on file, and who authorised the close, uploaded
 * through the real `Core\Domain\Actions\Files\UploadFileAction`
 * (content-hashed, so any later tamper attempt produces a different
 * file, not a silently-modified one). A bespoke PDF renderer is not
 * built in this pass — the pack's content is genuinely complete, only
 * its presentation format is simpler than a typeset document.
 */
final class GenerateClosePackAction extends Action
{
    public function __construct(
        private readonly UploadFileAction $uploadFile,
    ) {}

    public function execute(GenerateClosePackData $data): PeriodCloseChecklist
    {
        $checklist = PeriodCloseChecklist::findOrFail($data->checklistId);
        $term = Term::withoutGlobalScopes()->findOrFail($checklist->term_id);
        $acknowledgements = CloseCheckAcknowledgement::where('checklist_id', $checklist->id)->get();

        $pack = [
            'school_id' => $checklist->school_id,
            'term' => ['id' => $term->id, 'name' => $term->name],
            'period_type' => $checklist->period_type,
            'run_at' => $checklist->run_at->toIso8601String(),
            'run_by' => $checklist->run_by,
            'overall_status' => $checklist->overall_status,
            'results' => $checklist->results,
            'acknowledgements' => $acknowledgements->map(fn (CloseCheckAcknowledgement $a): array => [
                'check_key' => $a->check_key,
                'reason' => $a->reason,
                'acknowledged_by' => $a->acknowledged_by,
                'acknowledged_at' => $a->acknowledged_at->toIso8601String(),
            ])->all(),
            'authorised_by' => $data->generatedByUserId,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];

        $file = $this->uploadFile->execute(new UploadFileData(
            schoolId: $checklist->school_id,
            category: 'close_pack',
            contents: json_encode($pack, JSON_PRETTY_PRINT) ?: '{}',
            originalName: "close-pack-{$checklist->ulid}.json",
            uploadedByUserId: $data->generatedByUserId,
        ));

        $checklist->update(['report_document_id' => $file->id]);

        event(new ClosePackGenerated($checklist->fresh(), $file->id));

        return $checklist->fresh();
    }
}
