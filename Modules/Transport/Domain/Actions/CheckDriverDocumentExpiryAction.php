<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\Events\DriverDocumentExpired;
use Modules\Transport\Models\Driver;

/**
 * ACT-CheckDriverDocumentExpiry (Book H2 OPS-01 §4/BR-OPS-01-004).
 * A driver whose licence, medical certificate or defensive driving
 * certificate has expired is moved to `expired_documents` — the same
 * state `ScheduleTripAction` refuses to assign.
 */
final class CheckDriverDocumentExpiryAction extends Action
{
    /**
     * @return Collection<int, Driver>
     */
    public function execute(int $schoolId): Collection
    {
        $drivers = Driver::where('school_id', $schoolId)
            ->where('status', 'active')
            ->get()
            ->filter(fn (Driver $driver): bool => $driver->hasExpiredDocuments());

        if ($drivers->isEmpty()) {
            return $drivers;
        }

        $this->transaction(function () use ($drivers): void {
            foreach ($drivers as $driver) {
                $documentType = match (true) {
                    $driver->licence_expires_on->isPast() => 'licence',
                    $driver->medical_expires_on?->isPast() === true => 'medical_certificate',
                    default => 'defensive_driving_cert',
                };

                $driver->update(['status' => 'expired_documents']);

                event(new DriverDocumentExpired($driver, $documentType));
            }
        });

        return $drivers;
    }
}
