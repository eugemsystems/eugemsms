<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Stores\Database\Factories\BudgetFactory;

/**
 * Book H1 FIN-11 §2/BR-FIN-11-002 — versioned; a revision creates a
 * new row rather than mutating this one.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $name
 * @property string $budget_type
 * @property string $period_basis
 * @property string $currency
 * @property int $version
 * @property string $status
 * @property int $total_income_minor
 * @property int $total_expense_minor
 * @property int $surplus_minor
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int $prepared_by
 */
class Budget extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'name', 'budget_type', 'period_basis', 'currency', 'version',
        'status', 'total_income_minor', 'total_expense_minor', 'surplus_minor', 'approval_request_id',
        'approved_by', 'approved_at', 'board_approved_on', 'prepared_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'board_approved_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BudgetFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
