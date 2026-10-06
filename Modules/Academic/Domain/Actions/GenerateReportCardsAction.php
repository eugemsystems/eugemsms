<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\GenerateReportCardsData;
use Modules\Academic\Domain\Events\ReportCardGenerated;
use Modules\Academic\Domain\Events\ReportCardWithheld;
use Modules\Academic\Domain\Support\ReportCardTemplate;
use Modules\Academic\Models\ReportCardRun;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CheckReportGateAction;
use Modules\Finance\Domain\DataObjects\CheckReportGateData;
use Throwable;

/**
 * ACT-GenerateReportCards (Book D ACA-05 §6, BR-ACA-05-014/015/016,
 * AC-ACA-05-004). Renders a stored report card for every approved result in
 * scope, with the template version in force at the time. Where the fee
 * report gate is on and a learner's gate-counting balance exceeds the
 * threshold, the card is still generated and stored, but the result is set to
 * `withheld` with reason `fee_balance` — it is never published until the
 * balance clears or the bursar grants an override. One learner failing never
 * stops the rest; failures are counted on the run.
 */
final class GenerateReportCardsAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly BuildReportCardDataAction $buildData,
        private readonly GenerateDocumentAction $generateDocument,
        private readonly CreateDocumentTemplateAction $createTemplate,
        private readonly CheckReportGateAction $checkReportGate,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(GenerateReportCardsData $data): ReportCardRun
    {
        $term = Term::query()->find($data->termId);

        if ($term === null) {
            throw new InvalidArgumentException('That term does not belong to this school.');
        }

        $gradeLevelClassIds = null;

        if ($data->classId !== null && ! SchoolClass::query()->whereKey($data->classId)->exists()) {
            throw new InvalidArgumentException('That class does not belong to this school.');
        }

        if ($data->gradeLevelId !== null) {
            $gradeLevelClassIds = SchoolClass::query()->where('grade_level_id', $data->gradeLevelId)->pluck('id');
        }

        $template = $this->resolveTemplate($data);
        $requireApproval = (bool) $this->settings->get('academic.require_moderation', new ScopeChain(schoolId: $data->schoolId));

        $results = TermResult::query()
            ->where('term_id', $data->termId)
            ->whereIn('status', $requireApproval ? ['approved', 'withheld'] : ['computed', 'reviewed', 'approved', 'withheld'])
            ->when($data->classId !== null, fn ($q) => $q->where('class_id', $data->classId))
            ->when($gradeLevelClassIds !== null, fn ($q) => $q->whereIn('class_id', $gradeLevelClassIds))
            ->orderBy('class_id')->orderBy('student_id')
            ->get();

        if ($results->isEmpty()) {
            throw new InvalidArgumentException($requireApproval
                ? 'There are no approved results to generate report cards for.'
                : 'There are no computed results to generate report cards for.');
        }

        $run = $this->transaction(fn (): ReportCardRun => ReportCardRun::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'scope_filter' => array_filter(['class_id' => $data->classId, 'grade_level_id' => $data->gradeLevelId]) ?: null,
            'template_id' => $template->id,
            'template_version' => $template->version,
            'total_count' => $results->count(),
            'status' => 'running',
            'requested_by' => $data->requestedByUserId,
            'started_at' => Carbon::now(),
        ]));

        $generated = 0;
        $withheld = 0;
        $failed = 0;

        foreach ($results as $result) {
            try {
                $isWithheld = $this->generateOne($result, $template, $data);
                $generated++;
                $withheld += $isWithheld ? 1 : 0;
            } catch (Throwable) {
                $failed++;
            }
        }

        $run->update([
            'generated_count' => $generated,
            'withheld_count' => $withheld,
            'failed_count' => $failed,
            'status' => $generated === 0 ? 'failed' : 'completed',
            'completed_at' => Carbon::now(),
        ]);

        return $run->fresh();
    }

    private function generateOne(TermResult $result, DocumentTemplate $template, GenerateReportCardsData $data): bool
    {
        $gate = $this->checkReportGate->execute(new CheckReportGateData($data->schoolId, $result->student_id, $result->term_id));
        $version = $result->report_version + 1;

        $document = $this->generateDocument->execute(new GenerateDocumentData(
            schoolId: $data->schoolId,
            documentType: ReportCardTemplate::REPORT_TYPE,
            data: $this->buildData->execute($result, $version),
            generatedByUserId: $data->requestedByUserId,
            templateId: $template->id,
            academicYearId: $result->academic_year_id,
            termId: $result->term_id,
            documentableType: $result->getMorphClass(),
            documentableId: $result->id,
            allocateNumber: false,
        ));

        $result = $this->transaction(function () use ($result, $document, $version, $gate): TermResult {
            $result->update([
                'report_document_id' => $document->id,
                'report_version' => $version,
                'report_generated_at' => Carbon::now(),
                'status' => $gate->isWithheld ? 'withheld' : ($result->status === 'withheld' ? 'approved' : $result->status),
                'withheld_reason' => $gate->isWithheld ? 'fee_balance' : null,
            ]);

            return $result->fresh();
        });

        event($gate->isWithheld ? new ReportCardWithheld($result) : new ReportCardGenerated($result));

        return $gate->isWithheld;
    }

    private function resolveTemplate(GenerateReportCardsData $data): DocumentTemplate
    {
        ReportCardTemplate::registerVariables();

        if ($data->templateId !== null) {
            return DocumentTemplate::query()
                ->whereKey($data->templateId)->where('template_type', ReportCardTemplate::REPORT_TYPE)->where('is_active', true)
                ->first() ?? throw new InvalidArgumentException('Choose one of this school\'s active report card templates.');
        }

        $default = DocumentTemplate::query()
            ->where('school_id', $data->schoolId)->where('template_type', ReportCardTemplate::REPORT_TYPE)
            ->where('is_active', true)->where('is_default', true)->first();

        return $default ?? $this->createTemplate->execute(new CreateDocumentTemplateData(
            schoolId: $data->schoolId,
            templateType: ReportCardTemplate::REPORT_TYPE,
            name: 'Standard report card',
            content: ReportCardTemplate::reportContent(),
            isDefault: true,
            createdByUserId: $data->requestedByUserId,
        ));
    }
}
