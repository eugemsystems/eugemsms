<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\DataObjects\ExecuteReportSpec;
use Modules\Intelligence\Domain\DataObjects\ReportResult;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Domain\Support\ReportConditionBuilder;
use Modules\Intelligence\Models\ReportExecution;
use Modules\Intelligence\Models\WarehouseSnapshot;

/**
 * ACT-ExecuteCustomReport (Book J INT-01 §3 ⭐/BR-INT-01-001/002/003/
 * 006/008/009). The exact structural defence the spec's own §3
 * pseudocode describes: the query starts from the entity's REAL
 * Eloquent model, `BelongsToSchool`'s global scope applies with no
 * code path around it, and every selected field is permission-checked
 * against the RUNNING user, not the report's creator.
 *
 * `$strict` is the one place this action's behaviour branches:
 * - `true` (an ad hoc build, or the field named directly by a caller)
 *   REFUSES outright on an unauthorised field — `AC-INT-01-001`'s
 *   "a crafted request naming it directly is refused server-side".
 * - `false` (running an already-saved/shared report) DROPS an
 *   unauthorised field silently rather than failing the whole run —
 *   `AC-INT-01-002`'s "only fields the class teacher's own
 *   permissions allow are returned, even though the head could see
 *   more". Re-evaluated fresh on every call either way — `AC-INT-01-003`.
 */
final class ExecuteCustomReportAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ReportConditionBuilder $conditionBuilder,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ExecuteReportSpec $spec, User $runner, bool $strict = true, ?int $reportId = null): ReportResult
    {
        $start = microtime(true);
        $scope = new ScopeChain(schoolId: $spec->schoolId);

        $entity = ReportFieldRegistry::getEntity($spec->primaryEntityKey)
            ?? throw new InvalidArgumentException("Unregistered report entity '{$spec->primaryEntityKey}'.");

        if ($spec->consolidateSchoolIds !== null) {
            $this->assertConsolidationPermitted($runner, $spec->schoolId);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $entity->baseModelClass;
        $query = $modelClass::query();

        if ($spec->consolidateSchoolIds !== null) {
            $query->forSchools($spec->consolidateSchoolIds);
        }

        $this->conditionBuilder->apply($query, $spec->filters);

        $rowLimit = (int) $this->settings->get('reporting.ad_hoc_row_limit', $scope);
        $estimatedCount = (clone $query)->count();

        if ($estimatedCount > $rowLimit) {
            $this->logExecution($spec, $runner, $estimatedCount, (int) ((microtime(true) - $start) * 1000));

            return new ReportResult(
                rows: [],
                rowCount: $estimatedCount,
                durationMs: (int) ((microtime(true) - $start) * 1000),
                wasRedirected: true,
                redirectReason: $this->redirectReason($spec->primaryEntityKey, $spec->schoolId, $rowLimit, $estimatedCount),
            );
        }

        $aliases = [];

        foreach ($spec->selectedFields as $selection) {
            $field = ReportFieldRegistry::getField($selection['entity'], $selection['field']);

            if ($field === null) {
                throw new InvalidArgumentException("Unregistered report field '{$selection['entity']}.{$selection['field']}'.");
            }

            $permitted = $runner->hasPermissionTo($field->requiredPermission)
                && (! $field->isSensitive || $runner->hasPermissionTo('report.sensitive_field.access'));

            if (! $permitted) {
                if ($strict) {
                    throw new InsufficientScopeException(
                        "You do not have permission to access field '{$field->entityKey}.{$field->fieldKey}'.",
                        ['field' => $field->fieldKey, 'required_permission' => $field->requiredPermission],
                    );
                }

                continue;
            }

            $alias = $selection['alias'] ?? $field->fieldKey;
            $aliases[] = $alias;
            $query->addSelect($field->fieldKey.' as '.$alias);
        }

        // Nothing survived the permission check (every selected field was
        // dropped, `$strict` false) — ⭐ never fall through to Eloquent's
        // default `SELECT *` for a query nothing explicitly selected on;
        // the honest answer is an empty result, not every column leaking.
        if ($aliases === []) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $this->logExecution($spec, $runner, 0, $durationMs, $reportId);

            return new ReportResult(rows: [], rowCount: 0, durationMs: $durationMs);
        }

        foreach ($spec->groupBy as $groupField) {
            $query->groupBy($groupField);
        }

        foreach ($spec->aggregations as $aggregation) {
            // ⭐ `function` and `field` both feed raw SQL — `function` is
            // matched against a fixed literal-string whitelist (never the
            // caller's own string interpolated in), and `field` must be
            // one of the columns already permission-checked into
            // `$aliases` above, never an arbitrary caller-supplied column.
            if (! in_array($aggregation['field'], $aliases, true)) {
                continue;
            }

            $sql = match ($aggregation['function']) {
                'sum' => "sum({$aggregation['field']})",
                'avg' => "avg({$aggregation['field']})",
                'count' => "count({$aggregation['field']})",
                'min' => "min({$aggregation['field']})",
                'max' => "max({$aggregation['field']})",
                default => throw new InvalidArgumentException("Unsupported aggregation function '{$aggregation['function']}'."),
            };
            $alias = "{$aggregation['field']}_{$aggregation['function']}";
            $query->selectRaw("{$sql} as {$alias}");
            $aliases[] = $alias;
        }

        $rows = $query->get()->map(fn ($row): array => $row->only($aliases))->all();
        $durationMs = (int) ((microtime(true) - $start) * 1000);

        $this->logExecution($spec, $runner, count($rows), $durationMs, $reportId);

        return new ReportResult(rows: $rows, rowCount: count($rows), durationMs: $durationMs);
    }

    private function assertConsolidationPermitted(User $runner, int $schoolId): void
    {
        if (! $runner->hasPermissionTo('core.school.view.group')) {
            throw new InsufficientScopeException(
                'Cross-school consolidated reporting requires core.school.view.group.',
                ['permission' => 'core.school.view.group'],
            );
        }

        $tenant = School::with('tenant')->findOrFail($schoolId)->tenant;

        if (! $tenant->is_group_reporting_enabled) {
            throw new InsufficientScopeException(
                'Cross-school consolidated reporting is not enabled for this tenant.',
                ['school_id' => $schoolId],
            );
        }
    }

    private function redirectReason(string $entityKey, int $schoolId, int $rowLimit, int $estimatedCount): string
    {
        $snapshot = WarehouseSnapshot::where('school_id', $schoolId)->where('entity_key', $entityKey)->latest('snapshot_date')->first();
        $snapshotNote = $snapshot?->isFresh() === true
            ? "a fresh warehouse snapshot from {$snapshot->snapshot_date->toDateString()} is available instead"
            : 'no fresh warehouse snapshot exists yet — schedule this as a background export instead';

        return "This query would scan {$estimatedCount} rows, above the configured limit of {$rowLimit}; {$snapshotNote}.";
    }

    private function logExecution(ExecuteReportSpec $spec, User $runner, int $rowCount, int $durationMs, ?int $reportId = null): void
    {
        $this->transaction(fn (): ReportExecution => ReportExecution::create([
            'school_id' => $spec->schoolId,
            'report_id' => $reportId,
            'executed_by' => $runner->id,
            'row_count' => $rowCount,
            'duration_ms' => $durationMs,
            'schools_included' => $spec->consolidateSchoolIds,
            'executed_at' => Carbon::now(),
        ]));
    }
}
