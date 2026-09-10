<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\LearnerIssuedItemFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book F BRD-05 §2/BR-BRD-05-001/002/003/004.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $student_id
 * @property int $issuable_item_id
 * @property string|null $tag_reference
 * @property int $quantity
 * @property string $condition_at_issue
 * @property Carbon $issued_on
 * @property int $issued_by
 * @property Carbon|null $returned_on
 * @property string|null $condition_at_return
 * @property int|null $received_by
 * @property string $status
 * @property int|null $charge_minor
 * @property int|null $ad_hoc_charge_id
 * @property string|null $notes
 */
class LearnerIssuedItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerIssuedItemFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'student_id', 'issuable_item_id', 'tag_reference', 'quantity',
        'condition_at_issue', 'issued_on', 'issued_by', 'returned_on', 'condition_at_return',
        'received_by', 'status', 'charge_minor', 'ad_hoc_charge_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'returned_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerIssuedItemFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<IssuableItem, $this>
     */
    public function issuableItem(): BelongsTo
    {
        return $this->belongsTo(IssuableItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
