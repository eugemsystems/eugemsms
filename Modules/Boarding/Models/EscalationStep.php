<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\EscalationStepFactory;
use Modules\People\Models\Staff;

/**
 * Book F BRD-02 §2/§3 ⭐/BR-BRD-02-009/010. `requires_action_record`
 * is "acknowledgement is not resolution" made literal.
 *
 * @property int $id
 * @property int $profile_id
 * @property int $step_number
 * @property int $delay_minutes
 * @property int|null $notify_role_id
 * @property int|null $notify_staff_id
 * @property bool $notify_guardians
 * @property array<int, string> $channels
 * @property bool $requires_acknowledgement
 * @property bool $requires_action_record
 * @property string $message_template_key
 */
class EscalationStep extends Model
{
    /** @use HasFactory<EscalationStepFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'profile_id', 'step_number', 'delay_minutes', 'notify_role_id', 'notify_staff_id',
        'notify_guardians', 'channels', 'requires_acknowledgement', 'requires_action_record',
        'message_template_key',
    ];

    protected function casts(): array
    {
        return [
            'notify_guardians' => 'boolean',
            'channels' => 'array',
            'requires_acknowledgement' => 'boolean',
            'requires_action_record' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EscalationStepFactory::new();
    }

    /**
     * @return BelongsTo<EscalationProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(EscalationProfile::class, 'profile_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function notifyStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'notify_staff_id');
    }
}
