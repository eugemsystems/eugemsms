<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\People\Database\Factories\ApplicationGuardianFactory;

/**
 * Book C PPL-02 §2/§3 — captured at application, converted with it.
 *
 * @property int $id
 * @property int $application_id
 * @property string $relationship
 * @property string|null $title
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $organisation_name
 * @property string|null $primary_phone
 * @property string|null $email
 * @property bool $is_primary_contact
 * @property bool $is_fee_responsible
 * @property int|null $existing_guardian_id
 */
class ApplicationGuardian extends Model
{
    /** @use HasFactory<ApplicationGuardianFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'application_id', 'relationship', 'title', 'first_name', 'last_name',
        'organisation_name', 'primary_phone', 'email', 'is_primary_contact',
        'is_fee_responsible', 'existing_guardian_id',
    ];

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'is_fee_responsible' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApplicationGuardianFactory::new();
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function existingGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'existing_guardian_id');
    }
}
