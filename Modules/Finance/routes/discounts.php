<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\Discounts\AwardList;
use Modules\Finance\Livewire\Discounts\Budgets;
use Modules\Finance\Livewire\Discounts\ConditionReview;
use Modules\Finance\Livewire\Discounts\GrantAward;
use Modules\Finance\Livewire\Discounts\Schemes;
use Modules\Finance\Livewire\Discounts\SponsorAwards;
use Modules\Finance\Livewire\Reports\Discounts as DiscountsReport;
use Modules\Finance\Livewire\Scholarships\Applications;
use Modules\Finance\Livewire\Scholarships\Committee;

/**
 * Book K FIN-07 §5 — Scholarships, Bursaries & Discounts admin screens.
 * School-scoped, like every other Finance route group.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/finance')->name('finance.')->group(function (): void {
    Route::livewire('discounts/schemes', Schemes::class)->name('discounts.schemes');
    Route::livewire('discounts/budgets', Budgets::class)->name('discounts.budgets');
    Route::livewire('scholarships/applications', Applications::class)->name('scholarships.applications');
    Route::livewire('scholarships/committee', Committee::class)->name('scholarships.committee');
    Route::livewire('awards', AwardList::class)->name('awards.index');
    Route::livewire('awards/grant', GrantAward::class)->name('awards.grant');
    Route::livewire('awards/condition-review', ConditionReview::class)->name('awards.condition-review');
    Route::livewire('awards/sponsors', SponsorAwards::class)->name('awards.sponsors');
    Route::livewire('reports/discounts', DiscountsReport::class)->name('reports.discounts');
});
