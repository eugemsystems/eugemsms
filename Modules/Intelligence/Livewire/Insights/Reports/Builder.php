<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Insights\Reports;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\CreateCustomReportAction;
use Modules\Intelligence\Domain\Actions\ExecuteCustomReportAction;
use Modules\Intelligence\Domain\Actions\GetAvailableFieldsForUserAction;
use Modules\Intelligence\Domain\DataObjects\CreateCustomReportData;
use Modules\Intelligence\Domain\DataObjects\ExecuteReportSpec;
use Modules\Intelligence\Domain\DataObjects\ReportFieldDefinition;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Livewire\Concerns\PresentsReportResults;

/**
 * `Intelligence\Reports\Builder` (Book J INT-01 §5 ⭐, `report.build`).
 * The field picker is whatever `GetAvailableFieldsForUserAction`
 * returns for the signed-in user, so a field they may not read is not
 * offered (AC-INT-01-001). That is a convenience, not the defence: the
 * component's properties are client-tamperable, so every run and save
 * goes through `ExecuteCustomReportAction` / `CreateCustomReportAction`,
 * which re-check every selected, filtered and grouped field against the
 * *running user's* permissions and refuse anything else. The preview
 * runs in strict mode, so a crafted field is refused rather than
 * dropped. Nothing here builds SQL — values are bound by the query
 * builder and column names come only from the field registry.
 *
 * Group-wide consolidation is offered only when the user holds
 * `core.school.view.group` and the tenant allows it, and is refused
 * server-side otherwise (AC-INT-01-004). The spec's "chart preview" is
 * not built: the chart type is stored with the report for a later
 * renderer.
 */
#[Title('Report builder')]
#[Layout('layouts.app')]
final class Builder extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use PresentsReportResults;
    use Toasts;

    /** @var array<int, string> */
    public const array OPERATORS = ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'contains', 'is_null', 'is_not_null'];

    /** @var array<int, string> */
    public const array AGGREGATES = ['count', 'sum', 'avg', 'min', 'max'];

    public string $entity = '';

    /** @var array<int, string> */
    public array $selected = [];

    /** @var array<int, array{field: string, operator: string, value: string, group: int}> */
    public array $filters = [];

    /** @var array<int, string> */
    public array $groupBy = [];

    /** @var array<int, array{field: string, function: string}> */
    public array $aggregations = [];

    public bool $consolidate = false;

    public string $name = '';

    public string $description = '';

    public string $chartType = '';

    /** @var array{rows: array<int, array<string, mixed>>, rowCount: int, durationMs: int, wasRedirected: bool, redirectReason: ?string, truncated: bool}|null */
    public ?array $result = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('report.build');
    }

    public function updatedEntity(): void
    {
        $this->selected = [];
        $this->filters = [];
        $this->groupBy = [];
        $this->aggregations = [];
        $this->result = null;
    }

    public function addFilter(): void
    {
        $this->filters[] = ['field' => '', 'operator' => 'eq', 'value' => '', 'group' => 1];
    }

    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
    }

    public function addAggregation(): void
    {
        $this->aggregations[] = ['field' => '', 'function' => 'count'];
    }

    public function removeAggregation(int $index): void
    {
        unset($this->aggregations[$index]);
        $this->aggregations = array_values($this->aggregations);
    }

    public function run(): void
    {
        $this->authorizePermission('report.build');
        $this->resetErrorBag();

        $spec = $this->buildSpec();

        if ($spec === null) {
            return;
        }

        try {
            $this->result = $this->presentResult(app(ExecuteCustomReportAction::class)->execute($spec, $this->user(), strict: true));
        } catch (InsufficientScopeException|InvalidArgumentException $exception) {
            $this->result = null;
            $this->addError('entity', $exception->getMessage());
        }
    }

    public function save(): void
    {
        $this->authorizePermission('report.build');
        $this->resetErrorBag();

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'chartType' => ['nullable', 'in:bar,line,pie,table'],
        ]);

        $spec = $this->buildSpec();

        if ($spec === null) {
            return;
        }

        // A report may only be saved from a query the author can actually run.
        try {
            app(ExecuteCustomReportAction::class)->execute($spec, $this->user(), strict: true);

            app(CreateCustomReportAction::class)->execute(new CreateCustomReportData(
                schoolId: $this->school->id,
                name: $this->name,
                primaryEntityKey: $this->entity,
                selectedFields: $spec->selectedFields,
                createdByUserId: (int) auth()->id(),
                description: $this->description !== '' ? $this->description : null,
                filters: $spec->filters,
                groupBy: $spec->groupBy,
                aggregations: $spec->aggregations,
                chartType: $this->chartType !== '' ? $this->chartType : null,
            ));
        } catch (InsufficientScopeException|InvalidArgumentException $exception) {
            $this->addError('entity', $exception->getMessage());

            return;
        }

        $this->reset(['name', 'description', 'chartType']);
        $this->toast(__('Report saved. Find it under My reports.'));
    }

    public function render(): View
    {
        $user = $this->user();
        $fields = $this->entity !== '' ? app(GetAvailableFieldsForUserAction::class)->execute($this->entity, $user) : [];
        $resolver = app(PermissionScopeResolver::class);

        return view('intelligence::insights.reports.builder', [
            'entities' => array_keys(ReportFieldRegistry::allEntities()),
            'fields' => $fields,
            'filterable' => array_values(array_filter($fields, fn (ReportFieldDefinition $field): bool => $field->isFilterable)),
            'groupable' => array_values(array_filter($fields, fn (ReportFieldDefinition $field): bool => $field->isGroupable)),
            'aggregatable' => array_values(array_filter($fields, fn (ReportFieldDefinition $field): bool => $field->isAggregatable && in_array($field->fieldKey, $this->selected, true))),
            'operators' => self::OPERATORS,
            'aggregates' => self::AGGREGATES,
            'canConsolidate' => $resolver->has($user, 'core.school.view.group', PermissionScope::Own) && (bool) $this->school->tenant->is_group_reporting_enabled,
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function buildSpec(): ?ExecuteReportSpec
    {
        if ($this->entity === '' || ReportFieldRegistry::getEntity($this->entity) === null) {
            $this->addError('entity', __('Choose what to report on.'));

            return null;
        }

        if ($this->selected === []) {
            $this->addError('selected', __('Choose at least one field.'));

            return null;
        }

        $filters = [];

        foreach ($this->filters as $filter) {
            if ($filter['field'] === '') {
                continue;
            }

            $filters[] = [
                'field' => $filter['field'],
                'operator' => $filter['operator'],
                'value' => $this->parseValue($filter['operator'], $filter['value']),
                'group_id' => max(1, (int) $filter['group']),
            ];
        }

        $aggregations = array_values(array_filter(
            array_map(fn (array $aggregation): array => ['field' => $aggregation['field'], 'function' => $aggregation['function']], $this->aggregations),
            fn (array $aggregation): bool => $aggregation['field'] !== '',
        ));

        return new ExecuteReportSpec(
            schoolId: $this->school->id,
            primaryEntityKey: $this->entity,
            selectedFields: array_map(fn (string $field): array => ['entity' => $this->entity, 'field' => $field], array_values($this->selected)),
            filters: $filters,
            groupBy: array_values(array_filter($this->groupBy)),
            aggregations: $aggregations,
            consolidateSchoolIds: $this->consolidate ? $this->groupSchoolIds() : null,
        );
    }

    /**
     * The schools of this tenant the user is assigned to. The Action itself
     * refuses the request unless the user holds `core.school.view.group` and
     * the tenant allows group reporting.
     *
     * @return array<int, int>
     */
    private function groupSchoolIds(): array
    {
        return $this->user()->schools()->where('schools.tenant_id', $this->school->tenant_id)->pluck('schools.id')->map(fn ($id): int => (int) $id)->all();
    }

    private function parseValue(string $operator, string $raw): mixed
    {
        $raw = trim($raw);

        return match (true) {
            in_array($operator, ['is_null', 'is_not_null'], true) => null,
            in_array($operator, ['in', 'not_in'], true) => array_values(array_filter(array_map('trim', explode(',', $raw)), fn (string $item): bool => $item !== '')),
            is_numeric($raw) => str_contains($raw, '.') ? (float) $raw : (int) $raw,
            default => $raw,
        };
    }
}
