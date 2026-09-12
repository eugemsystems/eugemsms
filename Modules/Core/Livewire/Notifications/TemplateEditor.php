<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Notifications;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Core\Notifications\TemplateEditor` (Book A CORE-09 §5,
 * `core.notification.manage_templates`) — create only: the domain
 * layer has `CreateNotificationTemplateAction` but no update action
 * (a template's channel/locale/key form its uniqueness, so "editing"
 * one would mean either mutating a live template in place — with no
 * versioning safety net the way CORE-06's document templates have — or
 * a new Action this module doesn't have yet). "Character/segment
 * counter for SMS" and "live preview" are simplified to a live SMS
 * segment count (GSM-7, 160/153 chars per segment) and the registered
 * variable palette — no rendered preview against sample data, the same
 * simplification made for CORE-06's document template editor.
 *
 * `NotificationKeyRegistry` currently has zero registered keys: no
 * other module has shipped its own registration yet, which the spec
 * frames as their responsibility, not CORE-09's ("this module is the
 * pipe; those modules are the taps").
 */
#[Title('New notification template')]
#[Layout('layouts.app')]
final class TemplateEditor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $key = '';

    public string $channel = 'sms';

    public string $locale = 'en_ZW';

    public string $subject = '';

    public string $body = '';

    public string $providerTemplateId = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.notification.manage_templates');
    }

    public function smsSegmentCount(): int
    {
        $length = mb_strlen($this->body);

        if ($length === 0) {
            return 0;
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    public function save(): void
    {
        $this->validate([
            'key' => ['required', 'string', Rule::in(array_keys(NotificationKeyRegistry::all()))],
            'channel' => ['required', Rule::in(['sms', 'whatsapp', 'email', 'push', 'in_app'])],
            'locale' => ['required', 'string', 'max:10'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'providerTemplateId' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
                schoolId: $this->school->id,
                key: $this->key,
                channel: $this->channel,
                body: $this->body,
                locale: $this->locale,
                subject: $this->subject !== '' ? $this->subject : null,
                providerTemplateId: $this->providerTemplateId !== '' ? $this->providerTemplateId : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Notification template created.'));

        $this->redirectRoute('notifications.templates', ['school' => $this->school], navigate: true);
    }

    public function render(): View
    {
        return view('core::notifications.template-editor', [
            'availableKeys' => array_keys(NotificationKeyRegistry::all()),
            'availableVariables' => $this->key !== '' && NotificationKeyRegistry::has($this->key)
                ? NotificationKeyRegistry::get($this->key)->variables
                : [],
        ]);
    }
}
