<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\SponsorshipFactory;

/**
 * Book C PPL-03 §3. An organisation's funding programme for a number of learners.
 *
 * @property int $id
 * @property int $school_id
 * @property string $ulid
 * @property int $guardian_id
 * @property string $name
 * @property string $sponsorship_type
 * @property int|null $budget_minor
 * @property string|null $budget_currency
 * @property int $committed_minor
 * @property int $invoiced_minor
 * @property int $paid_minor
 * @property int|null $max_beneficiaries
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $contract_document_id
 * @property string $status
 * @property string|null $contact_person
 * @property string|null $reporting_frequency
 * @property int|null $created_by
 */
class Sponsorship extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SponsorshipFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'sponsorships';

    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = ['school_id', 'guardian_id', 'name', 'sponsorship_type', 'budget_minor', 'budget_currency', 'committed_minor', 'invoiced_minor', 'paid_minor', 'max_beneficiaries', 'starts_on', 'ends_on', 'contract_document_id', 'status', 'contact_person', 'reporting_frequency', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SponsorshipBeneficiary, $this>
     */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(SponsorshipBeneficiary::class);
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SponsorshipFactory::new();
    }
}
