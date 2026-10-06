<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\PublishReportCardsData;
use Modules\Academic\Domain\DataObjects\PublishReportCardsResult;
use Modules\Academic\Domain\Events\ReportCardPublished;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Models\SchoolClass;
use Modules\Finance\Domain\Actions\CheckReportGateAction;
use Modules\Finance\Domain\DataObjects\CheckReportGateData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Throwable;

/**
 * ACT-PublishReportCards (Book D ACA-05 §6, BR-ACA-05-015/017/018,
 * AC-ACA-05-005). Publication is a deliberate act per class or level. Only
 * results with a stored report card are published. A withheld result has its
 * fee gate re-checked: if the balance has cleared (or an override exists) the
 * stored card is released as it is — no regeneration — otherwise it stays
 * withheld. Guardians are told through the notification bus with a portal
 * link, never an attachment, and a failed notification never blocks the
 * release.
 */
final class PublishReportCardsAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly CheckReportGateAction $checkReportGate,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(PublishReportCardsData $data): PublishReportCardsResult
    {
        $classIds = $data->gradeLevelId === null ? null : SchoolClass::query()->where('grade_level_id', $data->gradeLevelId)->pluck('id');

        $results = TermResult::query()
            ->where('term_id', $data->termId)
            ->whereIn('status', ['computed', 'reviewed', 'approved', 'withheld'])
            ->when($data->classId !== null, fn ($q) => $q->where('class_id', $data->classId))
            ->when($classIds !== null, fn ($q) => $q->whereIn('class_id', $classIds))
            ->get();

        if ($results->isEmpty()) {
            throw new InvalidArgumentException('There are no report cards waiting to be published for that selection.');
        }

        $published = 0;
        $released = 0;
        $stillWithheld = [];
        $notGenerated = 0;

        foreach ($results as $result) {
            if ($result->report_document_id === null) {
                $notGenerated++;

                continue;
            }

            $wasWithheld = $result->status === 'withheld';
            $gate = $this->checkReportGate->execute(new CheckReportGateData($data->schoolId, $result->student_id, $result->term_id));

            if ($gate->isWithheld) {
                if (! $wasWithheld) {
                    $result->update(['status' => 'withheld', 'withheld_reason' => 'fee_balance']);
                }

                $stillWithheld[] = $result->student_id;

                continue;
            }

            $this->transaction(fn () => $result->update([
                'status' => 'published',
                'withheld_reason' => null,
                'published_at' => Carbon::now(),
                'approved_by' => $result->approved_by ?? $data->publishedByUserId,
            ]));

            $published++;
            $released += $wasWithheld ? 1 : 0;

            event(new ReportCardPublished($result->fresh()));
            $this->notifyGuardians($result);
        }

        return new PublishReportCardsResult($published, $released, count($stillWithheld), $notGenerated, $stillWithheld);
    }

    private function notifyGuardians(TermResult $result): void
    {
        $student = Student::query()->find($result->student_id);

        if ($student === null) {
            return;
        }

        $links = StudentGuardian::query()->where('student_id', $student->id)->where('status', 'active')->with('guardian')->get();

        foreach ($links as $link) {
            $guardian = $link->guardian;

            if ($guardian === null) {
                continue;
            }

            try {
                $this->dispatchNotification->execute(new DispatchNotificationData(
                    schoolId: $result->school_id,
                    notificationKey: 'academic.report_card_published',
                    recipientType: 'guardian',
                    addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                    context: ['student' => ['first_name' => $student->first_name], 'term' => ['id' => $result->term_id]],
                    recipientId: $guardian->id,
                    relatedType: 'report_card',
                    relatedId: $result->id,
                    dedupeWindowMinutes: 10080,
                ));
            } catch (Throwable) {
                // BR-ACA-05-018: telling a guardian never blocks the release itself.
            }
        }
    }
}
