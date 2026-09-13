<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Database\Factories\PaymentPlanFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-03 §2/BR-FIN-03-017. An instalment schedule against a
 * party's (guardian's) total balance, not one invoice.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property string $party_type
 * @property int $party_id
 * @property int $total_minor
 * @property string $currency
 * @property int $instalment_count
 * @property string $status
 * @property int|null $agreement_document_id
 * @property int|null $approved_by
 * @property int $breach_count
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read Collection<int, PaymentPlanInstalment> $instalments
 */
class PaymentPlan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PaymentPlanFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'party_type', 'party_id', 'total_minor', 'currency',
        'instalment_count', 'status', 'agreement_document_id', 'approved_by',
        'breach_count', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'breach_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PaymentPlanFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PaymentPlanInstalment, $this>
     */
    public function instalments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstalment::class, 'plan_id');
    }
}
