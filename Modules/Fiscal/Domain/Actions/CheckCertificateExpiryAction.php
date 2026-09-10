<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Fiscal\Domain\Events\CertificateExpiring;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * ACT-CheckCertificateExpiry (Book H3 FIN-13 §6/BR-FIN-13-014). A
 * scheduled scan, mirroring this codebase's own established
 * due/overdue-scan pattern (`Modules\Payroll`'s
 * `CheckStatutoryReturnDeadlinesAction`, `Modules\Security`'s
 * `CheckOverdueKeysAction`). Fires `CertificateExpiring` once per
 * device per configured alert day (60, 30, 7 by default).
 */
final class CheckCertificateExpiryAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, FiscalDevice>
     */
    public function execute(int $schoolId): Collection
    {
        $alertDays = (array) $this->settings->get('fiscal.certificate_alert_days', new ScopeChain(schoolId: $schoolId));

        $devices = FiscalDevice::where('school_id', $schoolId)
            ->whereNotNull('certificate_expires_at')
            ->where('is_active', true)
            ->get();

        $flagged = new Collection;

        foreach ($devices as $device) {
            $daysUntilExpiry = (int) Carbon::now()->startOfDay()->diffInDays($device->certificate_expires_at->copy()->startOfDay(), false);

            if (in_array($daysUntilExpiry, $alertDays, true)) {
                $flagged->push($device);
                event(new CertificateExpiring($device, $daysUntilExpiry));
            }
        }

        return $flagged;
    }
}
