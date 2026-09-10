<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\RegisterSchoolCurrencyData;
use Modules\Finance\Domain\Exceptions\BaseCurrencyImmutableException;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\SchoolCurrency;

/**
 * ACT-RegisterSchoolCurrency (Book B FIN-06 §2/BR-FIN-06-001). A school
 * has exactly one base currency, immutable once any journal exists —
 * changing it after the fact would silently reprice every historical
 * balance.
 */
final class RegisterSchoolCurrencyAction extends Action
{
    public function execute(RegisterSchoolCurrencyData $data): SchoolCurrency
    {
        if ($data->isBase) {
            $currentBase = SchoolCurrency::withoutGlobalScopes()
                ->where('school_id', $data->schoolId)
                ->where('is_base', true)
                ->first();

            $changingBase = $currentBase !== null && $currentBase->currency !== $data->currency;

            if ($changingBase && Journal::withoutGlobalScopes()->where('school_id', $data->schoolId)->exists()) {
                throw BaseCurrencyImmutableException::forSchool($data->schoolId);
            }
        }

        return $this->transaction(function () use ($data): SchoolCurrency {
            if ($data->isBase) {
                SchoolCurrency::withoutGlobalScopes()
                    ->where('school_id', $data->schoolId)
                    ->where('is_base', true)
                    ->update(['is_base' => false]);
            }

            return SchoolCurrency::updateOrCreate(
                ['school_id' => $data->schoolId, 'currency' => $data->currency],
                [
                    'is_base' => $data->isBase,
                    'is_accepted_for_payment' => $data->isAcceptedForPayment,
                    'rounding_increment_minor' => $data->roundingIncrementMinor,
                    'is_active' => true,
                ],
            );
        });
    }
}
