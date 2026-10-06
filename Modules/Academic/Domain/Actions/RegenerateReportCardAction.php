<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\Events\ReportCardGenerated;
use Modules\Academic\Domain\Support\ReportCardTemplate;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Models\Document;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Throwable;

/**
 * ACT-RegenerateReportCard (Book D ACA-05 §4, BR-ACA-05-010, AC-ACA-05-003).
 * After a published mark is amended the learner's report card is generated
 * again as a NEW version on the same template, and the guardian is told an
 * amended report is available. The previous document is kept; the result
 * simply points at the newer one. A report that was never generated has
 * nothing to regenerate.
 */
final class RegenerateReportCardAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly BuildReportCardDataAction $buildData,
        private readonly GenerateDocumentAction $generateDocument,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(TermResult $result, int $generatedByUserId, bool $notifyGuardians = true): TermResult
    {
        if ($result->report_document_id === null) {
            throw new InvalidArgumentException('That learner has no generated report card to regenerate.');
        }

        ReportCardTemplate::registerVariables();

        $previous = Document::query()->find($result->report_document_id);
        $version = $result->report_version + 1;

        $document = $this->generateDocument->execute(new GenerateDocumentData(
            schoolId: $result->school_id,
            documentType: ReportCardTemplate::REPORT_TYPE,
            data: $this->buildData->execute($result, $version),
            generatedByUserId: $generatedByUserId,
            templateId: $previous?->template_id,
            academicYearId: $result->academic_year_id,
            termId: $result->term_id,
            documentableType: $result->getMorphClass(),
            documentableId: $result->id,
            allocateNumber: false,
        ));

        $result = $this->transaction(function () use ($result, $document, $version): TermResult {
            $result->update(['report_document_id' => $document->id, 'report_version' => $version, 'report_generated_at' => Carbon::now()]);

            return $result->fresh();
        });

        event(new ReportCardGenerated($result));

        if ($notifyGuardians && $result->status === 'published') {
            $this->notify($result);
        }

        return $result;
    }

    private function notify(TermResult $result): void
    {
        $student = Student::query()->find($result->student_id);

        if ($student === null) {
            return;
        }

        foreach (StudentGuardian::query()->where('student_id', $student->id)->where('status', 'active')->with('guardian')->get() as $link) {
            if ($link->guardian === null) {
                continue;
            }

            try {
                $this->dispatchNotification->execute(new DispatchNotificationData(
                    schoolId: $result->school_id,
                    notificationKey: 'academic.report_card_amended',
                    recipientType: 'guardian',
                    addresses: ['sms' => (string) $link->guardian->primary_phone, 'email' => (string) $link->guardian->email],
                    context: ['student' => ['first_name' => $student->first_name], 'term' => ['id' => $result->term_id]],
                    recipientId: $link->guardian->id,
                    relatedType: 'report_card_version',
                    relatedId: $result->id,
                    dedupeWindowMinutes: 60,
                ));
            } catch (Throwable) {
                // Telling a guardian never blocks the regeneration.
            }
        }
    }
}
