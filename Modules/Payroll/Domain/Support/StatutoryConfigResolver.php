<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Support\Currency;
use Modules\Payroll\Domain\Exceptions\UnconfirmedStatutoryConfigException;
use Modules\Payroll\Models\StatutoryConfiguration;

/**
 * Book H3 PPL-05 §0.1/§2 ⭐/BR-PPL-05-001/002/003. Resolves the single
 * `active` configuration row in force for a config type, on a given
 * pay date, for a given currency (when that config type is
 * currency-specific) — preferring a school-specific row over the
 * system default, and refusing to hand back anything that still
 * `requires_confirmation` and is unconfirmed (BR-PPL-05-002's payroll
 * block).
 */
final class StatutoryConfigResolver
{
    public function resolve(string $configType, int $schoolId, CarbonInterface $payDate, ?Currency $currency = null): StatutoryConfiguration
    {
        $config = $this->find($configType, $schoolId, $payDate, $currency);

        if ($config === null) {
            throw UnconfirmedStatutoryConfigException::notFound($configType, $schoolId, $payDate);
        }

        $this->assertConfirmed($config);

        return $config;
    }

    /**
     * Like `resolve()`, but returns `null` instead of throwing when no
     * row exists at all — for config types like `nec_dues`/`nssa_apwcs`
     * that a school may legitimately not have configured yet. A row
     * that DOES exist but is still unconfirmed still blocks, same as
     * `resolve()` — existing-but-unconfirmed is never treated as absent.
     */
    public function resolveOptional(string $configType, int $schoolId, CarbonInterface $payDate, ?Currency $currency = null): ?StatutoryConfiguration
    {
        $config = $this->find($configType, $schoolId, $payDate, $currency);

        if ($config === null) {
            return null;
        }

        $this->assertConfirmed($config);

        return $config;
    }

    private function find(string $configType, int $schoolId, CarbonInterface $payDate, ?Currency $currency): ?StatutoryConfiguration
    {
        // Deliberately not `where('status', 'active')` — BR-PPL-05-004:
        // superseding never alters a historical payslip's recomputation,
        // so a row already flipped to `superseded` by a later config
        // must remain resolvable for dates within ITS OWN effective
        // range. Only `draft` (never yet in force) is excluded.
        //
        // `whereDate(...)`, not a plain `where(...)` against
        // `$payDate->toDateString()`: a bug found during the Book H3
        // admin-UI pass — this column is written with a full
        // `Y-m-d 00:00:00` timestamp on at least one supported DB
        // driver (confirmed on SQLite; harmless on MySQL, which
        // truncates a DATE column itself), and a plain string
        // comparison against a bare `Y-m-d` value then fails
        // lexicographically (`'2026-11-01 00:00:00' <= '2026-11-01'`
        // is FALSE — the longer string sorts after its own prefix),
        // silently excluding every row whose `effective_from`/
        // `effective_to` equals the comparison date exactly.
        // `whereDate()` wraps both sides in the driver's own `DATE()`
        // function, which compares correctly regardless of a stored
        // time component.
        return StatutoryConfiguration::query()
            ->where('config_type', $configType)
            ->where('status', '!=', 'draft')
            ->whereDate('effective_from', '<=', $payDate->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $payDate->toDateString()))
            ->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', $currency?->value))
            ->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))
            ->orderByRaw('school_id IS NULL')
            ->orderByDesc('effective_from')
            ->first();
    }

    private function assertConfirmed(StatutoryConfiguration $config): void
    {
        if ($config->requires_confirmation && $config->confirmed_at === null) {
            throw UnconfirmedStatutoryConfigException::forConfig($config);
        }
    }
}
