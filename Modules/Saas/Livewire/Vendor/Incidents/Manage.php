<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Incidents;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Saas\Domain\Actions\OpenIncidentAction;
use Modules\Saas\Domain\Actions\PostIncidentUpdateAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\DataObjects\OpenIncidentData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\IncidentStatusEntry;

/**
 * `Saas\Incidents\Manage` (Book J SAA-02 §4, vendor console). The content of
 * the public status page. Updates only ever append to an incident's timeline;
 * a resolved incident takes no more updates. A non-public incident never
 * appears on `/status`.
 */
#[Title('Incidents')]
#[Layout('saas::layouts.vendor')]
final class Manage extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $title = '';

    public string $components = '';

    public string $severity = 'minor';

    public string $message = '';

    public bool $isPublic = true;

    public ?int $updatingId = null;

    public string $updateStatus = 'identified';

    public string $updateMessage = '';

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function open(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate(['title' => ['required', 'string', 'max:200'], 'components' => ['required', 'string', 'max:500'], 'severity' => ['required', 'in:minor,major,critical'], 'message' => ['required', 'string', 'max:2000']]);

        $components = array_values(array_filter(array_map('trim', explode(',', $this->components))));

        try {
            $incident = app(OpenIncidentAction::class)->execute(new OpenIncidentData($this->title, $components, $this->severity, $this->message, $this->isPublic));
        } catch (InvalidArgumentException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'incident.opened', "Incident “{$incident->title}” opened", null, ['incident_id' => $incident->id, 'public' => $incident->is_public]);

        $this->reset('title', 'components', 'message');
        $this->toast(__('Incident opened.'));
    }

    public function beginUpdate(int $incidentId): void
    {
        $this->authorizeVendor();

        $this->updatingId = IncidentStatusEntry::query()->where('status', '!=', 'resolved')->findOrFail($incidentId)->id;
        $this->updateMessage = '';
        $this->resetErrorBag();
    }

    public function postUpdate(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $incident = IncidentStatusEntry::query()->findOrFail($this->updatingId);

        try {
            app(PostIncidentUpdateAction::class)->execute($incident->id, $this->updateStatus, $this->updateMessage);
        } catch (InvalidArgumentException $exception) {
            $this->addError('updateMessage', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'incident.updated', "Incident “{$incident->title}” → {$this->updateStatus}", null, ['incident_id' => $incident->id, 'status' => $this->updateStatus]);

        $this->updatingId = null;
        $this->toast(__('Update posted.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        return view('saas::vendor.incidents', ['incidents' => IncidentStatusEntry::query()->orderByDesc('id')->limit(50)->get()]);
    }
}
