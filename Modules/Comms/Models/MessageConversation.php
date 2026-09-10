<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\MessageConversationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-01 §2 ⭐/BR-COM-01-003/004.
 *
 * @property int $id
 * @property int $school_id
 * @property int $waba_id
 * @property string $contact_phone
 * @property Carbon|null $last_inbound_at
 * @property Carbon|null $session_expires_at
 * @property string|null $conversation_category
 * @property int|null $opened_by_template_id
 */
class MessageConversation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MessageConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'waba_id', 'contact_phone', 'last_inbound_at', 'session_expires_at',
        'conversation_category', 'opened_by_template_id',
    ];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'session_expires_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MessageConversationFactory::new();
    }

    /**
     * @return BelongsTo<WhatsAppBusinessAccount, $this>
     */
    public function whatsAppBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBusinessAccount::class, 'waba_id');
    }

    /**
     * BR-COM-01-003: inside the 24-hour session window, free-form
     * text is allowed.
     */
    public function isSessionOpen(): bool
    {
        return $this->session_expires_at !== null && $this->session_expires_at->isFuture();
    }
}
