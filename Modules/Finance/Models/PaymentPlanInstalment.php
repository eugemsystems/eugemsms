<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Finance\Database\Factories\PaymentPlanInstalmentFactory;

/**
 * Book B FIN-03 §2/BR-FIN-03-017. No `school_id` of its own — always
 * reached through its owning `PaymentPlan`, same reasoning as
 * `FeeStructureRule`/`CreditNoteLine`.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $instalment_number
 * @property Carbon $due_date
 * @property int $amount_minor
 * @property int $paid_minor
 * @property string $status
 */
class PaymentPlanInstalment extends Model
{
    /** @use HasFactory<PaymentPlanInstalmentFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'plan_id', 'instalment_number', 'due_date', 'amount_minor', 'paid_minor', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PaymentPlanInstalmentFactory::new();
    }

    /**
     * @return BelongsTo<PaymentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'plan_id');
    }
}
