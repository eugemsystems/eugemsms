<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Closure;

/**
 * Book I COM-02 §4/BR-COM-02-001. `eventClass` is a real, already-fired
 * domain event's FQCN — `CreateAutomationRuleAction` refuses to save a
 * rule whose `event_name` isn't registered here (AC-COM-02-003's own
 * "field not exposed" rule, applied to events instead of scan
 * entities). `extractor` turns the fired event object into the flat
 * field map `ConditionEvaluator` and the notification dispatch both need.
 */
final readonly class AutomationEventDefinition
{
    /**
     * @param  array<int, string>  $allowedFields
     * @param  Closure(object): AutomationScanRecord  $extractor
     */
    public function __construct(
        public string $eventName,
        public string $eventClass,
        public array $allowedFields,
        public Closure $extractor,
    ) {}
}
