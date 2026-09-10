<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\RaiseComplaintData;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\Actions\ReportSafeguardingConcernAction;
use Modules\Welfare\Domain\DataObjects\ReportSafeguardingConcernData;

/**
 * ACT-RaiseComplaint (Book I COM-08 §3 ⭐/BR-COM-08-003/006
 * (AC-COM-08-002/004)). `sla_due_at` is computed from the category's
 * own `sla_hours` at intake — the literal moment `BR-COM-08-003` asks
 * for, visible on the returned `Complaint` immediately.
 *
 * **Safeguarding routing.** Research into `BR-BRD-07-018` (the rule
 * this module is told to mirror) found it is a pre-flagged-CATEGORY
 * match, never free-text scanning of a description —
 * `Modules\Welfare\Domain\Actions\RecordBehaviourAction` only checks
 * `$category->is_safeguarding_trigger`. There is no safe, real
 * text-detection mechanism anywhere in this codebase to mirror
 * instead, and inventing a keyword scanner for a safeguarding-
 * critical routing decision would be worse than admitting the honest
 * boundary: this action routes when the complaint's OWN category is
 * flagged (`is_safeguarding_trigger`) OR when whoever is raising it
 * explicitly ticks `suspectedSafeguardingConcern` at intake — a human
 * judgement call, not a machine's guess at language. Routing calls
 * the real `Modules\Welfare\Domain\Actions\ReportSafeguardingConcernAction`
 * directly (no shared cross-module entry point exists — confirmed;
 * `SafeguardingRouter` is hard-typed to BRD-07's own `BehaviourRecord`)
 * and sets `status = 'escalated'` immediately, so a routed complaint
 * is visibly NOT sitting in the ordinary `received` → `investigating`
 * flow.
 */
final class RaiseComplaintAction extends Action
{
    public function __construct(
        private readonly ReportSafeguardingConcernAction $reportSafeguardingConcern,
    ) {}

    public function execute(RaiseComplaintData $data): Complaint
    {
        $category = ComplaintCategory::findOrFail($data->categoryId);
        $shouldRoute = $category->is_safeguarding_trigger || $data->suspectedSafeguardingConcern;

        return $this->transaction(function () use ($data, $category, $shouldRoute): Complaint {
            $complaint = Complaint::create([
                'school_id' => $data->schoolId,
                'complaint_number' => $this->nextComplaintNumber($data->schoolId),
                'category_id' => $category->id,
                'raised_by_type' => $data->raisedByType,
                'raised_by_id' => $data->raisedById,
                'subject' => $data->subject,
                'description' => $data->description,
                'related_student_id' => $data->relatedStudentId,
                'severity' => $data->severity,
                'sla_due_at' => Carbon::now()->addHours($category->sla_hours),
                'status' => $shouldRoute ? 'escalated' : 'received',
            ]);

            if ($shouldRoute) {
                $concern = $this->reportSafeguardingConcern->execute(new ReportSafeguardingConcernData(
                    schoolId: $data->schoolId,
                    reportSource: 'complaint_system',
                    concernCategory: $category->name,
                    description: $data->description,
                    reportedAt: Carbon::now(),
                    studentId: $data->relatedStudentId,
                    reporterUserId: $data->reporterUserId,
                ));

                $complaint->update(['safeguarding_concern_id' => $concern->id]);
            }

            return $complaint;
        });
    }

    private function nextComplaintNumber(int $schoolId): string
    {
        $sequence = Complaint::where('school_id', $schoolId)->count() + 1;

        return 'CMP/'.Carbon::now()->format('Y').'/'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
