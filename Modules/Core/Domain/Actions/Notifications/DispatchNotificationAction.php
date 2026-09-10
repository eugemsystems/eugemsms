<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Events\Notifications\NotificationBudgetCapReached;
use Modules\Core\Domain\Events\Notifications\NotificationDispatched;
use Modules\Core\Domain\Events\Notifications\NotificationSuppressed;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Registry\NotificationChannelDriverRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;
use Modules\Core\Domain\Support\Notifications\NotificationRenderer;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationBudget;
use Modules\Core\Models\NotificationOptOut;
use Modules\Core\Models\NotificationPreference;
use Modules\Core\Models\NotificationTemplate;

/**
 * ACT-DispatchNotification (Book A CORE-09 §3 ⭐/§4). The pipe every
 * other module dispatches an intent to — see the resolution order in
 * Book A CORE-09 §3: opt-out, preference, address validity, quiet
 * hours, dedupe, budget cap, then send with channel fallback. Urgent
 * notifications skip quiet hours and budget caps only (BR-CORE-09-005)
 * — opt-out and preference checks still apply.
 */
final class DispatchNotificationAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly NotificationRenderer $renderer,
    ) {}

    public function execute(DispatchNotificationData $data): Notification
    {
        $definition = NotificationKeyRegistry::get($data->notificationKey);
        $channel = $data->channel ?? $definition->defaultChannels[0];
        $urgent = $data->urgent ?? $definition->isUrgent;
        $scope = new ScopeChain(schoolId: $data->schoolId);

        return $this->transaction(function () use ($data, $definition, $channel, $urgent, $scope): Notification {
            $address = $this->normaliseAddress($channel, $data->addresses[$channel] ?? null);

            if ($address === null) {
                return $this->suppressed($data, $channel, $data->addresses[$channel] ?? '', 'invalid_address', 'The recipient address is not valid for this channel.');
            }

            if (! $definition->isTransactional && $this->isOptedOut($data->schoolId, $address, $channel)) {
                return $this->suppressed($data, $channel, $address, 'opted_out', 'This recipient has opted out on this channel.');
            }

            if ($data->recipientId !== null && $this->preferenceDisabled($data->schoolId, $data->recipientId, $data->notificationKey, $channel)) {
                return $this->suppressed($data, $channel, $address, 'preference_disabled', 'This recipient has disabled this notification on this channel.');
            }

            $dedupeKey = $this->buildDedupeKey($data);

            if ($dedupeKey !== null && $this->isDuplicate($data->schoolId, $dedupeKey, $data->dedupeWindowMinutes)) {
                return $this->suppressed($data, $channel, $address, 'duplicate', 'An identical notification was already sent in the dedupe window.');
            }

            if (! $urgent && $this->quietHoursActive($data->schoolId, $scope)) {
                return $this->scheduled($data, $channel, $address, $dedupeKey, $this->nextPermittedWindow($data->schoolId, $scope));
            }

            $budget = $this->findBudget($data->schoolId, $channel);

            if (! $urgent && $budget !== null && $budget->is_hard_stop && $budget->hasReachedCap()) {
                event(new NotificationBudgetCapReached($data->schoolId, $channel));

                return $this->suppressed($data, $channel, $address, 'budget_cap_reached', 'The monthly budget cap for this channel has been reached.');
            }

            $template = $this->resolveTemplate($data->schoolId, $data->notificationKey, $channel, $data->locale);
            $body = $this->renderer->render($template->body, $data->context);
            $subject = $template->subject !== null ? $this->renderer->render($template->subject, $data->context) : null;

            $notification = Notification::create([
                'school_id' => $data->schoolId,
                'notification_key' => $data->notificationKey,
                'recipient_type' => $data->recipientType,
                'recipient_id' => $data->recipientId,
                'recipient_address' => $address,
                'channel' => $channel,
                'subject' => $subject,
                'body' => $body,
                'context' => $data->context,
                'related_type' => $data->relatedType,
                'related_id' => $data->relatedId,
                'status' => 'sending',
                'attempt_count' => 0,
                'dedupe_key' => $dedupeKey,
                'created_at' => Carbon::now(),
            ]);

            $this->sendWithFallback($notification, $definition->defaultChannels, $channel, $data->addresses, $subject, $body);

            if ($notification->status === 'sent' && $notification->cost_minor !== null) {
                $this->accrueBudget($data->schoolId, $notification->channel, $notification->cost_minor);
            }

            if ($channel !== 'in_app') {
                $this->mirrorToInbox($notification);
            }

            event(new NotificationDispatched($notification));

            return $notification;
        });
    }

    /**
     * @param  array<int, string>  $defaultChannels
     * @param  array<string, string>  $addresses
     */
    private function sendWithFallback(Notification $notification, array $defaultChannels, string $primaryChannel, array $addresses, ?string $subject, string $body): void
    {
        $attempts = array_values(array_unique([$primaryChannel, ...$defaultChannels]));

        foreach ($attempts as $attemptChannel) {
            $address = $this->normaliseAddress($attemptChannel, $addresses[$attemptChannel] ?? null);

            if ($address === null) {
                continue;
            }

            $driver = NotificationChannelDriverRegistry::forChannel($attemptChannel);
            $result = $driver->send($address, $subject, $body);

            $notification->forceFill([
                'channel' => $attemptChannel,
                'recipient_address' => $address,
                'attempt_count' => $notification->attempt_count + 1,
                'provider' => $attemptChannel,
                'provider_message_id' => $result->providerMessageId,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'cost_minor' => $result->costMinor,
                'cost_currency' => $result->costMinor !== null ? 'USD' : null,
            ]);

            if ($result->succeeded) {
                $notification->forceFill(['status' => 'sent', 'sent_at' => Carbon::now()])->save();

                return;
            }

            $notification->save();
        }

        $notification->forceFill(['status' => 'failed', 'failed_at' => Carbon::now()])->save();
    }

    private function mirrorToInbox(Notification $original): void
    {
        Notification::create([
            'school_id' => $original->school_id,
            'notification_key' => $original->notification_key,
            'recipient_type' => $original->recipient_type,
            'recipient_id' => $original->recipient_id,
            'recipient_address' => (string) $original->recipient_id,
            'channel' => 'in_app',
            'subject' => $original->subject,
            'body' => $original->body,
            'context' => $original->context,
            'related_type' => $original->related_type,
            'related_id' => $original->related_id,
            'status' => 'delivered',
            'attempt_count' => 1,
            'created_at' => Carbon::now(),
        ]);
    }

    private function normaliseAddress(string $channel, ?string $address): ?string
    {
        if ($address === null || $address === '') {
            return null;
        }

        return match ($channel) {
            'sms', 'whatsapp' => PhoneNormalizer::toE164($address),
            'email' => str_contains($address, '@') ? $address : null,
            default => $address,
        };
    }

    private function isOptedOut(int $schoolId, string $address, string $channel): bool
    {
        return NotificationOptOut::where('school_id', $schoolId)
            ->where('address', $address)
            ->where('channel', $channel)
            ->exists();
    }

    private function preferenceDisabled(int $schoolId, int $recipientId, string $key, string $channel): bool
    {
        $specific = NotificationPreference::where('school_id', $schoolId)
            ->where('user_id', $recipientId)
            ->where('notification_key', $key)
            ->where('channel', $channel)
            ->first();

        if ($specific !== null) {
            return ! $specific->is_enabled;
        }

        $global = NotificationPreference::where('school_id', $schoolId)
            ->where('user_id', $recipientId)
            ->whereNull('notification_key')
            ->where('channel', $channel)
            ->first();

        return $global !== null && ! $global->is_enabled;
    }

    private function buildDedupeKey(DispatchNotificationData $data): ?string
    {
        if ($data->relatedType === null || $data->relatedId === null) {
            return null;
        }

        return md5("{$data->recipientType}:{$data->recipientId}:{$data->notificationKey}:{$data->relatedType}:{$data->relatedId}");
    }

    private function isDuplicate(int $schoolId, string $dedupeKey, int $windowMinutes): bool
    {
        return Notification::where('school_id', $schoolId)
            ->where('dedupe_key', $dedupeKey)
            ->where('created_at', '>=', Carbon::now()->subMinutes($windowMinutes))
            ->exists();
    }

    private function quietHoursActive(int $schoolId, ScopeChain $scope): bool
    {
        $start = (string) $this->settings->get('notifications.quiet_hours_start', $scope);
        $end = (string) $this->settings->get('notifications.quiet_hours_end', $scope);

        if ($start === '' || $end === '') {
            return false;
        }

        $now = Carbon::now()->format('H:i');

        return $start <= $end ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);
    }

    private function nextPermittedWindow(int $schoolId, ScopeChain $scope): Carbon
    {
        $end = (string) $this->settings->get('notifications.quiet_hours_end', $scope);
        [$hour, $minute] = array_map('intval', explode(':', $end !== '' ? $end : '06:00'));

        $next = Carbon::today()->setTime($hour, $minute);

        return $next->isFuture() ? $next : $next->addDay();
    }

    private function findBudget(int $schoolId, string $channel): ?NotificationBudget
    {
        return NotificationBudget::where('school_id', $schoolId)
            ->where('period_month', Carbon::now()->format('Y-m'))
            ->where('channel', $channel)
            ->first();
    }

    private function accrueBudget(int $schoolId, string $channel, int $costMinor): void
    {
        $budget = $this->findBudget($schoolId, $channel);
        $budget?->increment('spent_minor', $costMinor);
    }

    private function resolveTemplate(int $schoolId, string $key, string $channel, string $locale): NotificationTemplate
    {
        $template = NotificationTemplate::where('key', $key)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderByRaw('school_id IS NULL')
            ->where(fn ($query) => $query->where('school_id', $schoolId)->orWhereNull('school_id'))
            ->first();

        if ($template === null) {
            throw new class("No active template for [{$key}/{$channel}/{$locale}].") extends DomainException
            {
                public function errorCode(): string
                {
                    return 'NOTIFICATION_TEMPLATE_NOT_FOUND';
                }
            };
        }

        return $template;
    }

    private function suppressed(DispatchNotificationData $data, string $channel, string $address, string $reason, string $message, ?string $dedupeKey = null): Notification
    {
        $notification = Notification::create([
            'school_id' => $data->schoolId,
            'notification_key' => $data->notificationKey,
            'recipient_type' => $data->recipientType,
            'recipient_id' => $data->recipientId,
            'recipient_address' => $address,
            'channel' => $channel,
            'body' => '',
            'context' => $data->context,
            'related_type' => $data->relatedType,
            'related_id' => $data->relatedId,
            'status' => 'suppressed',
            'error_code' => $reason,
            'error_message' => $message,
            'dedupe_key' => $dedupeKey,
            'created_at' => Carbon::now(),
        ]);

        event(new NotificationSuppressed($notification, $reason));

        return $notification;
    }

    private function scheduled(DispatchNotificationData $data, string $channel, string $address, ?string $dedupeKey, Carbon $for): Notification
    {
        return Notification::create([
            'school_id' => $data->schoolId,
            'notification_key' => $data->notificationKey,
            'recipient_type' => $data->recipientType,
            'recipient_id' => $data->recipientId,
            'recipient_address' => $address,
            'channel' => $channel,
            'body' => '',
            'context' => $data->context,
            'related_type' => $data->relatedType,
            'related_id' => $data->relatedId,
            'status' => 'queued',
            'scheduled_for' => $for,
            'dedupe_key' => $dedupeKey,
            'created_at' => Carbon::now(),
        ]);
    }
}
