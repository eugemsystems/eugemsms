<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Compliance\Database\Factories\ConsentTypeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-004.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $lawful_basis
 * @property bool $is_withdrawable
 * @property bool $required_for_enrolment
 * @property string $applies_to
 * @property int|null $renewal_frequency_months
 * @property bool $is_active
 */
class ConsentType extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ConsentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'code', 'name', 'description', 'lawful_basis', 'is_withdrawable',
        'required_for_enrolment', 'applies_to', 'renewal_frequency_months', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_withdrawable' => 'boolean',
            'required_for_enrolment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConsentTypeFactory::new();
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class, 'consent_type_id');
    }
}
