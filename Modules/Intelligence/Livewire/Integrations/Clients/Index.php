<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Integrations\Clients;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\IssueApiClientAction;
use Modules\Intelligence\Domain\Actions\RevokeApiClientAction;
use Modules\Intelligence\Domain\Actions\RotateApiClientKeyAction;
use Modules\Intelligence\Domain\Registry\HardwareScanRouteRegistry;
use Modules\Intelligence\Models\ApiClient;

/**
 * `Intelligence\Integrations\Clients\Index` (Book J INT-04 §5,
 * `integration.manage` ⚠). Third-party API keys, scoped to an explicit
 * ability allow-list at issuance (BR-INT-04-001). The plaintext key is
 * shown once and never stored (BR-INT-04-002): it lives only in
 * `$revealedKey` until dismissed. Hardware devices have their own
 * screen and credentials — only `integration` clients appear here, and
 * every action re-checks that against the database.
 */
#[Title('API clients')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $name = '';

    public string $contactEmail = '';

    /** @var array<int, string> */
    public array $abilities = ['usage:read'];

    public int $rateLimit = 60;

    public string $ipAllowlist = '';

    public ?string $revealedKey = null;

    public ?string $revealedFor = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('integration.manage');
    }

    public function issue(): void
    {
        $this->authorizePermission('integration.manage');
        $this->resetErrorBag();

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'contactEmail' => ['nullable', 'email', 'max:150'],
        ]);

        $allowlist = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->ipAllowlist) ?: [])));

        try {
            $issued = app(IssueApiClientAction::class)->execute(
                schoolId: $this->school->id,
                name: $this->name,
                clientType: 'integration',
                scopedAbilities: array_values($this->abilities),
                contactEmail: $this->contactEmail === '' ? null : $this->contactEmail,
                ipAllowlist: $allowlist === [] ? null : $allowlist,
                rateLimitPerMinute: $this->rateLimit,
                createdByUserId: (int) auth()->id(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->revealedKey = $issued['plaintextKey'];
        $this->revealedFor = $issued['client']->name;
        $this->reset('name', 'contactEmail', 'ipAllowlist');
        $this->abilities = ['usage:read'];
        $this->rateLimit = 60;
    }

    public function rotate(int $clientId): void
    {
        $this->authorizePermission('integration.manage');

        $client = $this->integrationClient($clientId);

        try {
            $rotated = app(RotateApiClientKeyAction::class)->execute($client->id);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->revealedKey = $rotated['plaintextKey'];
        $this->revealedFor = $rotated['client']->name;
    }

    public function revoke(int $clientId): void
    {
        $this->authorizePermission('integration.manage');

        $client = $this->integrationClient($clientId);

        app(RevokeApiClientAction::class)->execute($client->id, (int) auth()->id());

        $this->toast(__('Client revoked; its webhooks are switched off.'));
    }

    public function dismissKey(): void
    {
        $this->revealedKey = null;
        $this->revealedFor = null;
    }

    private function integrationClient(int $clientId): ApiClient
    {
        return ApiClient::where('school_id', $this->school->id)->where('client_type', 'integration')->findOrFail($clientId);
    }

    public function render(): View
    {
        return view('intelligence::integrations.clients', [
            'clients' => ApiClient::where('school_id', $this->school->id)->where('client_type', 'integration')->orderByDesc('id')->limit(100)->get(),
            'allowedAbilities' => ['usage:read', ...HardwareScanRouteRegistry::abilities()],
        ]);
    }
}
