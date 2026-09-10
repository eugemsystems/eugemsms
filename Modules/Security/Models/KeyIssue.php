<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Staff;
use Modules\Security\Database\Factories\KeyIssueFactory;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-006/007.
 *
 * @property int $id
 * @property int $school_id
 * @property int $key_id
 * @property int|null $issued_to_staff_id
 * @property int|null $issued_to_contractor_id
 * @property Carbon $issued_at
 * @property int $issued_by
 * @property Carbon|null $due_back_on
 * @property Carbon|null $returned_at
 * @property int|null $received_by
 * @property string $status
 */
class KeyIssue extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<KeyIssueFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'key_id', 'issued_to_staff_id', 'issued_to_contractor_id', 'issued_at', 'issued_by',
        'due_back_on', 'returned_at', 'received_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_back_on' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return KeyIssueFactory::new();
    }

    /**
     * @return BelongsTo<KeyAndCard, $this>
     */
    public function key(): BelongsTo
    {
        return $this->belongsTo(KeyAndCard::class, 'key_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function issuedToStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'issued_to_staff_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function issuedToContractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'issued_to_contractor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
