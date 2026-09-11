<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\DiscountApplication;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * Book K FIN-07 §3 ⭐ — the interface `FIN-02`'s billing pipeline calls
 * (BR-FIN-07-001 ⭐: there is no other code path that reduces a fee
 * line). `AwardDiscountResolver` is the only implementation.
 */
interface DiscountResolver
{
    /**
     * @return Collection<int, DiscountApplication>
     */
    public function discountsFor(Student $student, FeeComponent $component, Money $grossAmount, Term $term): Collection;
}
