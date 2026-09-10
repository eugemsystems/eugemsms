<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Approvals;

/**
 * Book A CORE-07 §2/BR-CORE-07-001. `condition_rules` is an array of
 * `{field, operator, value}` triples, evaluated against the
 * approvable's `approvalPayload()` — every rule must match (AND). A
 * null/empty rule set always matches (an unconditional chain/step).
 */
final class ConditionRuleMatcher
{
    /**
     * @param  array<int, array{field: string, operator: string, value: mixed}>|null  $rules
     * @param  array<string, mixed>  $payload
     */
    public function matches(?array $rules, array $payload): bool
    {
        if ($rules === null || $rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            if (! $this->evaluate($rule, $payload)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{field: string, operator: string, value: mixed}  $rule
     * @param  array<string, mixed>  $payload
     */
    private function evaluate(array $rule, array $payload): bool
    {
        $actual = $payload[$rule['field']] ?? null;
        $expected = $rule['value'];

        return match ($rule['operator']) {
            '=', '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            default => false,
        };
    }
}
