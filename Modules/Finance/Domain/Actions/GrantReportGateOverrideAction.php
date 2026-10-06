<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\GrantReportGateOverrideData;
use Modules\Finance\Models\ReportGateOverride;
use Modules\People\Models\Student;

/**
 * ACT-GrantReportGateOverride (Book B FIN-03 §4/BR-FIN-03-018). One row
 * per (school, student, term) — the migration's own unique index is
 * what makes a fresh override required every term rather than a
 * standing exemption silently carried forward. Idempotent
 * (`firstOrCreate`, not `updateOrCreate`): `ReportGateOverride` is
 * append-only like every other financial-adjacent log in this book, so
 * granting the same term's override twice is a no-op rather than an
 * edit to who granted it or why.
 */
final class GrantReportGateOverrideAction extends Action
{
    public function execute(GrantReportGateOverrideData $data): ReportGateOverride
    {
        if (mb_strlen(trim($data->reason)) < 10) {
            throw new InvalidArgumentException('Give a reason of at least 10 characters for releasing this report card.');
        }

        if (! Student::query()->whereKey($data->studentId)->exists() || ! Term::query()->whereKey($data->termId)->exists()) {
            throw new InvalidArgumentException('That learner or term does not belong to this school.');
        }

        return $this->transaction(fn (): ReportGateOverride => ReportGateOverride::firstOrCreate(
            ['school_id' => $data->schoolId, 'student_id' => $data->studentId, 'term_id' => $data->termId],
            ['reason' => $data->reason, 'granted_by' => $data->grantedByUserId, 'created_at' => Carbon::now()],
        ));
    }
}
