<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Book J INT-01 §3 ⭐/BR-INT-01-001. Applies a `custom_reports.filters`
 * condition tree directly onto the REAL query builder — never
 * interpolated raw SQL. AND within a `group_id`, OR across groups,
 * the same semantic `Modules\Comms\Domain\Support\ConditionEvaluator`
 * (COM-02) already established, reused here per the spec's own note
 * that this module's filter shape is deliberately identical.
 */
final class ReportConditionBuilder
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<int, array{field: string, operator: string, value: mixed, group_id?: int}>  $filters
     */
    public function apply(Builder $query, array $filters): void
    {
        if ($filters === []) {
            return;
        }

        $groups = [];

        foreach ($filters as $filter) {
            $groups[$filter['group_id'] ?? 0][] = $filter;
        }

        $query->where(function (Builder $outer) use ($groups): void {
            foreach ($groups as $groupFilters) {
                $outer->orWhere(function (Builder $inner) use ($groupFilters): void {
                    foreach ($groupFilters as $filter) {
                        $this->applyOne($inner, $filter);
                    }
                });
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array{field: string, operator: string, value: mixed}  $filter
     */
    private function applyOne(Builder $query, array $filter): void
    {
        $column = $filter['field'];
        $value = $filter['value'];

        match ($filter['operator']) {
            'eq' => $query->where($column, '=', $value),
            'ne' => $query->where($column, '!=', $value),
            'gt' => $query->where($column, '>', $value),
            'gte' => $query->where($column, '>=', $value),
            'lt' => $query->where($column, '<', $value),
            'lte' => $query->where($column, '<=', $value),
            'in' => $query->whereIn($column, (array) $value),
            'not_in' => $query->whereNotIn($column, (array) $value),
            'contains' => $query->where($column, 'like', '%'.$value.'%'),
            'is_null' => $query->whereNull($column),
            'is_not_null' => $query->whereNotNull($column),
            default => throw new InvalidArgumentException("Unsupported report filter operator '{$filter['operator']}'."),
        };
    }
}
