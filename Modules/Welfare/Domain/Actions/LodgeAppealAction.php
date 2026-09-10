<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Welfare\Domain\Events\AppealLodged;
use Modules\Welfare\Models\Appeal;
use Modules\Welfare\Models\Sanction;

/**
 * ACT-LodgeAppeal (Book G BRD-07 §2/BR-BRD-07-008). An appeal lodged
 * within the sanction type's appeal window suspends the sanction
 * (`status = 'appealed'`) when `behaviour.appeal_suspends_sanction` is
 * enabled — the spec's own §2 schema has no per-sanction-type
 * "continues during appeal" column to override this with, so this
 * pass applies the school-wide setting uniformly; documented here
 * rather than inventing an unlisted column.
 */
final class LodgeAppealAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $sanctionId, ?int $lodgedByGuardianId, bool $lodgedByStudent, string $grounds): Appeal
    {
        $sanction = Sanction::findOrFail($sanctionId);
        $sanctionType = $sanction->sanctionType;

        if (! $sanctionType->appealable) {
            throw new InvalidStateTransitionException(
                "Sanction #{$sanction->id}'s type is not appealable.",
                ['sanction_id' => $sanction->id],
            );
        }

        $windowEnds = $sanction->starts_on->copy()->addDays($sanctionType->appeal_window_days);

        if (Carbon::now()->toDateString() > $windowEnds->toDateString()) {
            throw new InvalidStateTransitionException(
                "The appeal window for sanction #{$sanction->id} closed on {$windowEnds->toDateString()}.",
                ['sanction_id' => $sanction->id, 'window_ends' => $windowEnds->toDateString()],
            );
        }

        return $this->transaction(function () use ($sanction, $lodgedByGuardianId, $lodgedByStudent, $grounds): Appeal {
            $appeal = Appeal::create([
                'school_id' => $sanction->school_id,
                'sanction_id' => $sanction->id,
                'lodged_by_guardian_id' => $lodgedByGuardianId,
                'lodged_by_student' => $lodgedByStudent,
                'grounds' => $grounds,
                'lodged_at' => Carbon::now(),
                'status' => 'lodged',
            ]);

            $scope = new ScopeChain(schoolId: $sanction->school_id);

            if ((bool) $this->settings->get('behaviour.appeal_suspends_sanction', $scope)) {
                $sanction->update(['status' => 'appealed']);
            }

            event(new AppealLodged($appeal));

            return $appeal;
        });
    }
}
