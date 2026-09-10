<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Boarding\Database\Factories\HostelFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\SchoolSection;
use Modules\People\Models\Staff;

/**
 * Book F BRD-01 §2/§4 ⭐/BR-BRD-01-001/002. `gender` is the hard,
 * no-override boarding constraint. `capacity` is recomputed from
 * active beds, never entered by hand.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $gender
 * @property int|null $section_id
 * @property int|null $house_id
 * @property int|null $housemaster_staff_id
 * @property int|null $matron_staff_id
 * @property int|null $deputy_staff_id
 * @property int $capacity
 * @property string|null $building
 * @property string|null $latitude
 * @property string|null $longitude
 * @property bool $has_sick_bay
 * @property bool $has_prep_room
 * @property string|null $notes
 * @property bool $is_active
 */
class Hostel extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HostelFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'gender', 'section_id', 'house_id', 'housemaster_staff_id',
        'matron_staff_id', 'deputy_staff_id', 'capacity', 'building', 'latitude', 'longitude',
        'has_sick_bay', 'has_prep_room', 'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'has_sick_bay' => 'boolean',
            'has_prep_room' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HostelFactory::new();
    }

    /**
     * @return BelongsTo<SchoolSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function housemaster(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'housemaster_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function matron(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'matron_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<HostelWing, $this>
     */
    public function wings(): HasMany
    {
        return $this->hasMany(HostelWing::class);
    }

    /**
     * @return HasMany<HostelRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(HostelRoom::class);
    }
}
