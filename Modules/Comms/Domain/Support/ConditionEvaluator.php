<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Comms\Models\RuleCondition;

/**
 * Book I COM-02 §3/BR-COM-02-004. Condition groups combine by AND
 * WITHIN a group and OR ACROSS groups — fixed semantics, not
 * configurable per rule; `rule_conditions.group_logic` is reserved
 * for a future pass, not read here.
 */
final class ConditionEvaluator
{
    /**
     * @param  Collection<int, RuleCondition>  $conditions
     * @param  array<string, mixed>  $fields
     */
    public function evaluate(Collection $conditions, array $fields): bool
    {
        if ($conditions->isEmpty()) {
            return true;
        }

        return $conditions->groupBy('group_id')->contains(
            fn (Collection $group): bool => $group->every(fn (RuleCondition $condition): bool => $this->evaluateOne($condition, $fields))
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function evaluateOne(RuleCondition $condition, array $fields): bool
    {
        $actual = $fields[$condition->field] ?? null;
        $expected = $condition->value;

        return match ($condition->operator) {
            'eq' => $this->looseEquals($actual, $expected),
            'neq' => ! $this->looseEquals($actual, $expected),
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'is_null' => $actual === null,
            default => false,
        };
    }

    /**
     * Type-aware equality: numeric-to-numeric compares as numbers,
     * everything else compares as strings — avoids PHP's own loose
     * `==` surprises (e.g. two differently-typed falsy values).
     */
    private function looseEquals(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        if (is_bool($actual) || is_bool($expected)) {
            return (bool) $actual === (bool) $expected;
        }

        return (string) $actual === (string) $expected;
    }
}
