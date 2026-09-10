<?php

declare(strict_types=1);

namespace Modules\Facilities\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Facilities\Database\Factories\BookableResourceFactory;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Transport\Models\Vehicle;

/**
 * Book H2 OPS-05 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $venue_id
 * @property int|null $vehicle_id
 * @property string $code
 * @property string $name
 * @property string $resource_type
 * @property int|null $capacity
 * @property bool $is_externally_hireable
 * @property int|null $hire_rate_minor
 * @property string|null $hire_rate_unit
 * @property string|null $hire_currency
 * @property int|null $deposit_minor
 * @property int $requires_setup_minutes
 * @property int $requires_cleaning_minutes
 * @property int $booking_lead_time_hours
 * @property int $cost_centre_id
 * @property int|null $income_account_id
 * @property bool $is_active
 */
class BookableResource extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BookableResourceFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'venue_id', 'vehicle_id', 'code', 'name', 'resource_type', 'capacity',
        'is_externally_hireable', 'hire_rate_minor', 'hire_rate_unit', 'hire_currency', 'deposit_minor',
        'requires_setup_minutes', 'requires_cleaning_minutes', 'booking_lead_time_hours', 'cost_centre_id',
        'income_account_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_externally_hireable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BookableResourceFactory::new();
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    /**
     * @return HasMany<ResourceBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(ResourceBooking::class, 'resource_id');
    }
}
