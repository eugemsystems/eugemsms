<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\People\Domain\DataObjects\LiabilityLineInput;
use Modules\People\Domain\DataObjects\LiabilityShare;
use Modules\People\Domain\Exceptions\LiabilityShareMismatchException;
use Modules\People\Domain\Exceptions\NoFeeResponsibleGuardianException;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book C PPL-03 §4 ⭐. The algorithm `FIN-03` calls when issuing
 * invoices — it decides who receives a bill, so it must be exact
 * (BR-PPL-03-006/007).
 *
 * `percentage`-type rules are rounded independently against the
 * remaining amount at the moment each is evaluated — deliberately NOT
 * batched through one `Money::allocate()` call, because
 * BR-FIN-03-007's "any unallocated residue falls to the primary
 * fee-responsible guardian" only makes sense if percentage rules are
 * allowed to genuinely fall short of 100% (a school naming just one
 * 60% payer and letting the rest fall to the default guardian is
 * valid). When a set of percentages *is* complementary (60% + 40%),
 * per-rule rounding still reproduces both worked examples exactly
 * (§5's 720/480 split of ZWG 1,200, and AC-PPL-03-005's 60.01/40.00
 * split of USD 100.01) because each rule rounds against the same
 * shared `remaining` base rather than a shrinking one.
 * `full_component`/`fixed` rules are resolved first, in priority
 * order, since they make an unconditional claim the percentage rules
 * must divide only what's left of.
 */
final class LiabilityResolver
{
    /**
     * @param  Collection<int, LiabilityLineInput>  $lines
     * @return Collection<int, LiabilityShare>
     */
    public function resolve(Student $student, Collection $lines, CarbonInterface $at): Collection
    {
        $rules = FeeLiability::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at))
            ->orderBy('priority')
            ->get();

        $shares = collect();

        foreach ($lines as $line) {
            $remaining = $line->netMinor;

            $remaining = $this->resolvePass($rules->where('component_id', $line->componentId), $line, $remaining, $shares);

            if ($remaining > 0) {
                $remaining = $this->resolvePass($rules->whereNull('component_id'), $line, $remaining, $shares);
            }

            if ($remaining > 0) {
                $responsible = $this->defaultResponsible($student);
                $shares->push(new LiabilityShare($responsible, $line->lineId, $line->componentId, $remaining, $line->currency, null));
            }
        }

        $this->assertTotalsMatch($lines, $shares);

        return $shares;
    }

    /**
     * @param  Collection<int, FeeLiability>  $rulesInPass
     * @param  Collection<int, LiabilityShare>  $shares
     */
    private function resolvePass(Collection $rulesInPass, LiabilityLineInput $line, int $remaining, Collection $shares): int
    {
        $claims = $rulesInPass->whereIn('share_type', ['full_component', 'fixed']);
        $percentageRules = $rulesInPass->where('share_type', 'percentage');

        foreach ($claims as $rule) {
            if ($remaining <= 0) {
                break;
            }

            $share = $rule->share_type === 'full_component'
                ? $remaining
                : min($remaining, (int) $rule->share_amount_minor);

            if ($share <= 0) {
                continue;
            }

            $shares->push(new LiabilityShare($rule->guardian_id, $line->lineId, $line->componentId, $share, $line->currency, $rule->id));
            $remaining -= $share;
        }

        if ($remaining > 0 && $percentageRules->isNotEmpty()) {
            // Every percentage rule in this pass rounds against the same
            // fixed base — the remainder as it stood the moment the
            // percentage rules started being considered — never a
            // shrinking one, or a 50/30/20 split would compute the second
            // and third shares against the wrong (already-reduced) base.
            $percentageBase = $remaining;

            foreach ($percentageRules as $rule) {
                if ($remaining <= 0) {
                    break;
                }

                $fraction = bcdiv((string) $rule->share_percent, '100', 10);
                $share = min($remaining, Money::of($percentageBase, Currency::from($line->currency))->multiplyBy($fraction)->minor);

                if ($share <= 0) {
                    continue;
                }

                $shares->push(new LiabilityShare($rule->guardian_id, $line->lineId, $line->componentId, $share, $line->currency, $rule->id));
                $remaining -= $share;
            }
        }

        return $remaining;
    }

    /**
     * Public so `Modules\Finance\Domain\Actions\IssueInvoicesForAssignmentAction`
     * (Book K FIN-07) can attribute a fully-discounted (net-zero) fee
     * line — one `resolve()` itself never produces a share for, since
     * `$remaining` starts at 0 — to the same guardian a non-zero line
     * would have fallen through to.
     */
    public function defaultResponsible(Student $student): int
    {
        $guardianId = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('is_fee_responsible', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->value('guardian_id');

        if ($guardianId === null) {
            throw NoFeeResponsibleGuardianException::forStudent($student->id);
        }

        return $guardianId;
    }

    /**
     * @param  Collection<int, LiabilityLineInput>  $lines
     * @param  Collection<int, LiabilityShare>  $shares
     */
    private function assertTotalsMatch(Collection $lines, Collection $shares): void
    {
        $expected = $lines->groupBy('currency')->map(fn (Collection $group): int => $group->sum('netMinor'));
        $actual = $shares->groupBy('currency')->map(fn (Collection $group): int => $group->sum('shareMinor'));

        foreach ($expected as $currency => $expectedMinor) {
            $actualMinor = (int) ($actual[$currency] ?? 0);

            if ($actualMinor !== $expectedMinor) {
                throw LiabilityShareMismatchException::forCurrency((string) $currency, $expectedMinor, $actualMinor);
            }
        }
    }
}
