<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Database\Factories\BorrowerCategoryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-10 §2/BR-ACA-10-002/003.
 *
 * @property int $id
 * @property int $school_id
 * @property string $category
 * @property int $max_concurrent_loans
 * @property int $loan_period_days
 * @property int $max_renewals
 */
class BorrowerCategory extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BorrowerCategoryFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'category', 'max_concurrent_loans', 'loan_period_days', 'max_renewals'];

    protected function casts(): array
    {
        return [
            'max_concurrent_loans' => 'integer',
            'loan_period_days' => 'integer',
            'max_renewals' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BorrowerCategoryFactory::new();
    }
}
