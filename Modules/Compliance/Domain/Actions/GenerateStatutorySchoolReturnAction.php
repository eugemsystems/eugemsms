<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\GenerateStatutorySchoolReturnData;
use Modules\Compliance\Domain\DataObjects\GenerateStatutorySchoolReturnResult;
use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-GenerateStatutorySchoolReturn (Book H3 CMP-02 §1/3 ⭐/BR-CMP-02-
 * 001/002/003/004 (AC-CMP-02-001/002)). Data quality validation
 * (BR-CMP-02-002) always runs first and is recorded on the return as
 * `validation_result` — quality issues are reported, never a hard
 * block on generation, matching AC-CMP-01-001's own literal wording
 * ("reports them before export", not "refuses"). The FIRST call for a
 * given `(school, return_type, period_reference)` creates the row and
 * freezes `data_snapshot`; every later call for the SAME period
 * leaves that frozen snapshot untouched (the model's own guard
 * enforces this) and instead returns a fresh diff against current
 * live data (AC-CMP-02-002) — this action itself never mutates
 * `data_snapshot` after creation.
 */
final class GenerateStatutorySchoolReturnAction extends Action
{
    public function __construct(
        private readonly RunDataQualityChecksAction $runDataQualityChecks,
        private readonly BuildEnrolmentSnapshotAction $buildEnrolmentSnapshot,
        private readonly BuildStaffEstablishmentSnapshotAction $buildStaffEstablishmentSnapshot,
    ) {}

    public function execute(GenerateStatutorySchoolReturnData $data): GenerateStatutorySchoolReturnResult
    {
        $qualityChecks = $this->runDataQualityChecks->execute($data->schoolId);
        $snapshot = $this->buildSnapshot($data->schoolId, $data->returnType);
        $qualityIssues = (int) $qualityChecks->where('severity', 'error')->sum('affected_count');

        $existing = StatutorySchoolReturn::where('school_id', $data->schoolId)
            ->where('return_type', $data->returnType)
            ->where('period_reference', $data->periodReference)
            ->first();

        if ($existing !== null) {
            return new GenerateStatutorySchoolReturnResult(
                return: $existing,
                wasAlreadyGenerated: true,
                divergence: $this->diff($existing->data_snapshot, $snapshot),
            );
        }

        $return = $this->transaction(fn (): StatutorySchoolReturn => StatutorySchoolReturn::create([
            'school_id' => $data->schoolId,
            'return_type' => $data->returnType,
            'period_reference' => $data->periodReference,
            'due_date' => $data->dueDate,
            'authority' => $data->authority,
            'data_snapshot' => $snapshot,
            'validation_result' => $qualityChecks->map(fn ($check): array => [
                'check_key' => $check->check_key,
                'affected_count' => $check->affected_count,
                'severity' => $check->severity,
            ])->values()->all(),
            'quality_issues' => $qualityIssues,
            'status' => 'validated',
            'generated_by' => $data->generatedByUserId,
        ]));

        return new GenerateStatutorySchoolReturnResult($return, wasAlreadyGenerated: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(int $schoolId, string $returnType): array
    {
        $snapshot = ['generated_at' => Carbon::now()->toIso8601String(), 'return_type' => $returnType];

        return match ($returnType) {
            'annual_schools_census' => [
                ...$snapshot,
                'enrolment' => $this->buildEnrolmentSnapshot->execute($schoolId),
                'staff_establishment' => $this->buildStaffEstablishmentSnapshot->execute($schoolId),
            ],
            'term_enrolment' => [...$snapshot, 'enrolment' => $this->buildEnrolmentSnapshot->execute($schoolId)],
            'staff_establishment' => [...$snapshot, 'staff_establishment' => $this->buildStaffEstablishmentSnapshot->execute($schoolId)],
            default => $snapshot,
        };
    }

    /**
     * @param  array<string, mixed>  $frozen
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function diff(array $frozen, array $current): array
    {
        $changed = [];

        $keys = array_unique([...array_keys($frozen), ...array_keys($current)]);

        foreach ($keys as $key) {
            if ($key === 'generated_at') {
                continue;
            }

            $frozenValue = $frozen[$key] ?? null;
            $currentValue = $current[$key] ?? null;

            if (json_encode($frozenValue) !== json_encode($currentValue)) {
                $changed[$key] = ['frozen' => $frozenValue, 'current' => $currentValue];
            }
        }

        return $changed;
    }
}
