<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Database\Factories\SchoolCurrencyFactory;

/**
 * Book B FIN-06 §2. Which currencies a school transacts in.
 *
 * @property int $id
 * @property int $school_id
 * @property string $currency
 * @property bool $is_base
 * @property bool $is_accepted_for_payment
 * @property int $rounding_increment_minor
 * @property bool $is_active
 */
class SchoolCurrency extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchoolCurrencyFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'currency', 'is_base', 'is_accepted_for_payment', 'rounding_increment_minor', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_accepted_for_payment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchoolCurrencyFactory::new();
    }
}
