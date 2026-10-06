<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\CapitalProject;
use Modules\Operations\Models\CapitalProjectMilestone;

/**
 * ACT-AddCapitalProjectMilestone (Book H2 OPS-02 §2). Adds the next milestone to a
 * project that is still being delivered. The sequence is allocated here; the target
 * date cannot precede the project's start, and the payment percentages of all its
 * milestones may not add up to more than 100.
 */
final class AddCapitalProjectMilestoneAction extends Action
{
    public function execute(int $capitalProjectId, string $name, CarbonInterface $targetDate, ?float $paymentPercent = null): CapitalProjectMilestone
    {
        return $this->transaction(function () use ($capitalProjectId, $name, $targetDate, $paymentPercent): CapitalProjectMilestone {
            $project = CapitalProject::query()->lockForUpdate()->findOrFail($capitalProjectId);

            if (! in_array($project->status, ['planning', 'approved', 'in_progress'], true)) {
                throw new InvalidStateTransitionException('Milestones can only be added to a project that has not been completed or cancelled.');
            }

            $errors = [];

            if ($targetDate->startOfDay()->lessThan($project->starts_on->copy()->startOfDay())) {
                $errors['targetDate'] = 'A milestone cannot be due before the project starts.';
            }

            if ($paymentPercent !== null && ($paymentPercent < 0 || $paymentPercent > 100)) {
                $errors['paymentPercent'] = 'A payment percentage is between 0 and 100.';
            } elseif ($paymentPercent !== null && (float) $project->milestones()->sum('payment_percent') + $paymentPercent > 100.0) {
                $errors['paymentPercent'] = 'The milestones\' payment percentages cannot add up to more than 100.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            return $project->milestones()->create([
                'school_id' => $project->school_id,
                'sequence' => (int) $project->milestones()->max('sequence') + 1,
                'name' => $name,
                'target_date' => $targetDate->toDateString(),
                'payment_percent' => $paymentPercent,
                'status' => 'pending',
            ]);
        });
    }
}
