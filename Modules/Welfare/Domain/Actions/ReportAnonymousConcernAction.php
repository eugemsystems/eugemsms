<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\ReportAnonymousConcernData;
use Modules\Welfare\Domain\Events\ConcernReported;
use Modules\Welfare\Domain\Events\ImmediateRiskConcern;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * ACT-ReportAnonymousConcern (Book G BRD-08 §4 ⭐/BR-BRD-08-011/
 * AC-BRD-08-007). `reporter_user_id` is never set here — not
 * encrypted, not hashed, structurally absent, since this action takes
 * no user id as input at all. The token is generated once and
 * returned; it is the caller's (the learner's) responsibility to show
 * it exactly once and never persist it server-side elsewhere.
 *
 * This action, on its own, satisfies the "no reporter identity stored"
 * half of AC-BRD-08-007. The other half — no request log, application
 * log, or analytics event recording the submitting session — is an
 * HTTP-middleware/infrastructure concern with no Http layer built yet
 * in this codebase (consistent with every other module here); it is
 * NOT satisfied by this Domain-layer action alone and must be revisited
 * once an API layer exists.
 */
final class ReportAnonymousConcernAction extends Action
{
    public function execute(ReportAnonymousConcernData $data): SafeguardingConcern
    {
        return $this->transaction(function () use ($data): SafeguardingConcern {
            $concern = SafeguardingConcern::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'reported_at' => $data->reportedAt,
                'report_source' => 'anonymous',
                'reporter_user_id' => null,
                'anonymous_token' => (string) Str::ulid(),
                'concern_category' => $data->concernCategory,
                'description' => $data->description,
                'immediate_risk' => $data->immediateRisk,
                'triage_status' => 'awaiting_triage',
            ]);

            event(new ConcernReported($concern));

            if ($data->immediateRisk) {
                event(new ImmediateRiskConcern($concern));
            }

            return $concern;
        });
    }
}
