<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Messaging\WhatsApp;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\RecordWhatsAppTemplateReviewAction;
use Modules\Comms\Domain\Actions\RegisterWhatsAppBusinessAccountAction;
use Modules\Comms\Domain\Actions\SubmitWhatsAppTemplateAction;
use Modules\Comms\Domain\Actions\UpdateWhatsAppQualityRatingAction;
use Modules\Comms\Domain\DataObjects\RegisterWhatsAppBusinessAccountData;
use Modules\Comms\Domain\DataObjects\SubmitWhatsAppTemplateData;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\WhatsApp\Templates` (Book I COM-01 §6 ⭐, `comms.template.manage`).
 * Folds the WhatsApp Business account (register, record Meta's quality
 * rating) onto the same screen as its templates — a template cannot
 * exist without one, and BR-COM-01-007's red-rating pause is the first
 * thing an administrator needs to see next to them. Template category
 * is chosen at submission only; there is no edit path (BR-COM-01-006).
 * Meta's approve/reject decision is recorded here, never invented.
 */
#[Title('WhatsApp templates')]
#[Layout('layouts.app')]
final class Templates extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** Meta's body limit; validated live per the spec's "placeholder validation". */
    public const int BODY_LIMIT = 1024;

    public ?int $gatewayId = null;

    public string $wabaId = '';

    public string $displayPhoneNumber = '';

    public string $displayName = '';

    public ?int $templateWabaId = null;

    public string $metaTemplateName = '';

    public string $category = 'utility';

    public string $language = 'en';

    public string $bodyText = '';

    public string $footerText = '';

    public string $notificationKey = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('comms.template.manage');
    }

    public function registerAccount(): void
    {
        $this->authorizePermission('comms.template.manage');

        $this->validate([
            'gatewayId' => ['required', 'integer'],
            'wabaId' => ['required', 'string', 'max:60'],
            'displayPhoneNumber' => ['required', 'string', 'max:30'],
            'displayName' => ['required', 'string', 'max:120'],
        ]);

        $gateway = MessageGateway::where('school_id', $this->school->id)->where('channel', 'whatsapp')->findOrFail($this->gatewayId);

        app(RegisterWhatsAppBusinessAccountAction::class)->execute(new RegisterWhatsAppBusinessAccountData(
            schoolId: $this->school->id,
            gatewayId: $gateway->id,
            wabaId: $this->wabaId,
            displayPhoneNumber: $this->displayPhoneNumber,
            displayName: $this->displayName,
        ));

        $this->reset(['gatewayId', 'wabaId', 'displayPhoneNumber', 'displayName']);
        $this->toast(__('WhatsApp Business account registered.'));
    }

    public function recordQuality(int $accountId, string $rating): void
    {
        $this->authorizePermission('comms.template.manage');

        if (! in_array($rating, ['green', 'yellow', 'red'], true)) {
            return;
        }

        $account = WhatsAppBusinessAccount::where('school_id', $this->school->id)->findOrFail($accountId);
        app(UpdateWhatsAppQualityRatingAction::class)->execute($account->id, $rating);

        $this->toast(__('Quality rating recorded.'));
    }

    public function submitTemplate(): void
    {
        $this->authorizePermission('comms.template.manage');

        $this->validate([
            'templateWabaId' => ['required', 'integer'],
            'metaTemplateName' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', 'in:utility,marketing,authentication'],
            'language' => ['required', 'string', 'max:10'],
            'bodyText' => ['required', 'string', 'max:'.self::BODY_LIMIT, 'regex:/^(?!.*\{\{\s*[^0-9\s}]).*$/s'],
            'footerText' => ['nullable', 'string', 'max:120'],
            'notificationKey' => ['nullable', 'string', 'max:80'],
        ], [
            'metaTemplateName.regex' => __('Use lowercase letters, digits and underscores only.'),
            'bodyText.regex' => __('Placeholders must be numbered, like {{1}}.'),
        ]);

        $account = WhatsAppBusinessAccount::where('school_id', $this->school->id)->findOrFail($this->templateWabaId);

        app(SubmitWhatsAppTemplateAction::class)->execute(new SubmitWhatsAppTemplateData(
            schoolId: $this->school->id,
            wabaId: $account->id,
            metaTemplateName: $this->metaTemplateName,
            category: $this->category,
            language: $this->language,
            bodyText: $this->bodyText,
            notificationKey: $this->notificationKey !== '' ? $this->notificationKey : null,
            footerText: $this->footerText !== '' ? $this->footerText : null,
        ));

        $this->reset(['metaTemplateName', 'bodyText', 'footerText', 'notificationKey']);
        $this->toast(__('Template submitted for Meta review.'));
    }

    public function recordReview(int $templateId, string $decision, ?string $metaTemplateId = null, ?string $rejectionReason = null): void
    {
        $this->authorizePermission('comms.template.manage');

        $template = WhatsAppTemplate::where('school_id', $this->school->id)->where('review_status', 'pending')->findOrFail($templateId);

        app(RecordWhatsAppTemplateReviewAction::class)->execute(
            $template->id,
            $decision,
            $metaTemplateId !== '' ? $metaTemplateId : null,
            $rejectionReason !== '' ? $rejectionReason : null,
        );

        $this->toast(__('Meta review decision recorded.'));
    }

    public function render(): View
    {
        return view('comms::whatsapp.templates', [
            'whatsAppGateways' => MessageGateway::where('school_id', $this->school->id)->where('channel', 'whatsapp')->orderBy('name')->get(['id', 'name']),
            'accounts' => WhatsAppBusinessAccount::where('school_id', $this->school->id)->orderBy('display_name')->get(),
            'templates' => WhatsAppTemplate::where('school_id', $this->school->id)->orderByDesc('id')->limit(100)->get(),
            'bodyLength' => mb_strlen($this->bodyText),
        ]);
    }
}
