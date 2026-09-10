<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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

/**
 * Book A CORE-01 §5/§6. Unauthenticated, standalone — every route in this
 * group 404s once storage/installed.lock exists (BR-CORE-01-001).
 */
Route::middleware(['serp.not-installed'])->prefix('install')->group(function (): void {
    Route::get('/', fn () => redirect()->route('install.welcome'))->name('install');
    Route::livewire('welcome', Welcome::class)->name('install.welcome');
    Route::livewire('requirements', Requirements::class)->name('install.requirements');
    Route::livewire('environment', Environment::class)->name('install.environment');
    Route::livewire('database', Database::class)->name('install.database');
    Route::livewire('migrations', Migrations::class)->name('install.migrations');
    Route::livewire('licence', Licence::class)->name('install.licence');
    Route::livewire('administrator', Administrator::class)->name('install.administrator');
    Route::livewire('organisation', Organisation::class)->name('install.organisation');
    Route::livewire('seed', Seed::class)->name('install.seed');
    Route::livewire('services', Services::class)->name('install.services');
    Route::livewire('finalise', Finalise::class)->name('install.finalise');
});
