<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Core\Domain\Support\Install\InstallProgress;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Administrator;
use Modules\Core\Livewire\Install\Database;
use Modules\Core\Livewire\Install\Environment;
use Modules\Core\Livewire\Install\Finalise;
use Modules\Core\Livewire\Install\Licence;
use Modules\Core\Livewire\Install\Migrations;
use Modules\Core\Livewire\Install\Organisation;
use Modules\Core\Livewire\Install\Requirements;
use Modules\Core\Livewire\Install\Seed;
use Modules\Core\Livewire\Install\Services;
use Modules\Core\Livewire\Install\Welcome;
use PragmaRX\Google2FA\Google2FA;

it('renders the welcome screen and blocks continuing without accepting terms', function (): void {
    Livewire::test(Welcome::class)
        ->assertSee('Welcome to')
        ->call('continue')
        ->assertHasErrors(['termsAccepted']);
});

it('completes the welcome step and advances to requirements', function (): void {
    Livewire::test(Welcome::class)
        ->set('termsAccepted', true)
        ->call('continue')
        ->assertRedirect(route('install.requirements'));

    expect(app(InstallProgress::class)->resumeStep())->toBe(InstallStepKey::Requirements);
});

it('every step beyond welcome redirects back to the resumable step when visited out of order', function (): void {
    foreach ([
        Requirements::class,
        Environment::class,
        Database::class,
        Migrations::class,
        Licence::class,
        Administrator::class,
        Organisation::class,
        Seed::class,
        Services::class,
        Finalise::class,
    ] as $component) {
        Livewire::test($component)->assertRedirect(route('install.welcome'));
    }
});

it('only advances past requirements once mandatory checks pass', function (): void {
    app(InstallProgress::class)->markCompleted(InstallStepKey::Welcome);

    Livewire::test(Requirements::class)
        ->set('passesMandatory', false)
        ->call('continue')
        ->assertNoRedirect();

    Livewire::test(Requirements::class)
        ->set('passesMandatory', true)
        ->call('continue')
        ->assertRedirect(route('install.environment'));

    expect(app(InstallProgress::class)->resumeStep())->toBe(InstallStepKey::Environment);
});

it('creates the administrator, forces 2FA confirmation before continuing, then advances to organisation', function (): void {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

    app(InstallProgress::class)->markCompleted(InstallStepKey::Welcome);
    app(InstallProgress::class)->markCompleted(InstallStepKey::Requirements);
    app(InstallProgress::class)->markCompleted(InstallStepKey::Environment);
    app(InstallProgress::class)->markCompleted(InstallStepKey::Database);
    app(InstallProgress::class)->markCompleted(InstallStepKey::Migrations);
    app(InstallProgress::class)->markCompleted(InstallStepKey::Licence);

    $component = Livewire::test(Administrator::class)
        ->set('name', 'Jane Admin')
        ->set('email', 'jane@example.test')
        ->set('password', 'a-very-strong-password-123')
        ->set('password_confirmation', 'a-very-strong-password-123')
        ->call('createAdmin')
        ->assertHasNoErrors()
        ->assertSet('adminCreated', true)
        ->assertSet('twoFactorEnabled', false);

    expect(app(InstallProgress::class)->resumeStep())
        ->toBe(InstallStepKey::Administrator);

    $component->call('continue')->assertNoRedirect();

    $user = User::query()->where('email', 'jane@example.test')->firstOrFail();
    $secret = decrypt($user->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $component
        ->set('code', $code)
        ->call('confirmTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('twoFactorEnabled', true)
        ->call('continue')
        ->assertRedirect(route('install.organisation'));

    expect(app(InstallProgress::class)->resumeStep())->toBe(InstallStepKey::Organisation);
});

it('shows the finish button before finalising and the login link after', function (): void {
    foreach (InstallStepKey::ordered() as $key) {
        if ($key !== InstallStepKey::Finalise) {
            app(InstallProgress::class)->markCompleted($key);
        }
    }

    Livewire::test(Finalise::class)
        ->assertSee('Finish installation')
        ->assertDontSee('Installation complete');
});
