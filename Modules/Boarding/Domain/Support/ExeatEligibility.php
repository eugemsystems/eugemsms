<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Student;

/**
 * Works out the two facts `RequestExeatAction` needs to apply BR-BRD-03-006/007 — whether the
 * learner is suspended and whether their unpaid fees are over the school's threshold — so every
 * caller (the admin screen and the parent API) asks the same question the same way. Arrears only
 * count when the school has turned on `boarding.exeat_block_on_fee_arrears`, and only in the
 * school's base currency (the threshold is a single minor-unit figure).
 */
final class ExeatEligibility
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function isSuspended(Student $student): bool
    {
        return $student->status === 'suspended';
    }

    public function arrearsExceedThreshold(Student $student): bool
    {
        $scope = new ScopeChain(schoolId: $student->school_id);

        if (! (bool) $this->settings->get('boarding.exeat_block_on_fee_arrears', $scope)) {
            return false;
        }

        $currency = School::query()->whereKey($student->school_id)->value('base_currency');

        $outstanding = (int) Invoice::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', 'voided')
            ->where('currency', $currency)
            ->sum('balance_minor');

        return $outstanding > (int) $this->settings->get('boarding.exeat_arrears_threshold_minor', $scope);
    }
}
