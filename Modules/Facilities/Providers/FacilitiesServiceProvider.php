<?php

declare(strict_types=1);

namespace Modules\Facilities\Providers;

use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Facilities\Models\BookableResource;
use Modules\Facilities\Models\ResourceBooking;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H2 Domain F: Operations & Estates — `OPS-05` Facilities
 * Booking & External Hire, built after `OPS-03` per the book's own
 * order.
 *
 * Real cross-module wiring in this pass: `bookable_resources.venue_id`
 * (`ACA-03`) and `.vehicle_id` (`OPS-01`), both real FKs;
 * `CheckResourceAvailabilityAction` runs a genuine clash check against
 * `ACA-03`'s published timetable for a venue-linked resource — date →
 * cycle-day via `Modules\Academic\Domain\Support\CycleDayResolver`,
 * the same translation `CreateSubstitutionsForLeaveAction` already
 * uses, since no venue-occupancy-by-date/time lookup existed anywhere
 * in this codebase before this action; `resource_bookings.
 * setup_work_order_id`/`cleanup_work_order_id` are real `OPS-02` work
 * orders raised on confirmation (`ConfirmBookingAction`).
 *
 * Three deliberate, documented boundaries:
 *  - Hire deposits and their refund/damage-deduction
 *    (`RecordHireDepositAction`/`AssessDamageAndRefundDepositAction`)
 *    post their own journal directly rather than routing through
 *    `Modules\Finance`'s `CreateReceiptAction` (`FIN-04`) — that
 *    action treats a `studentId`-less receipt as unidentified-deposit
 *    suspense, not a known-hirer deposit liability.
 *  - `resource_bookings.invoice_id` stays a real but unpopulated FK
 *    into `FIN-03`'s `invoices` — that table's `student_id` column is
 *    `NOT NULL`, so an external hirer with no learner record has no
 *    path through `FIN-03`'s own invoicing at all in this pass.
 *  - `BR-OPS-05-004`'s fiscalised hire invoice is a `FIN-13`
 *    (Book H3) forward reference — confirmed by the book's own
 *    Appendix A as "Book H3", not a deferral judgement call made
 *    here.
 */
class FacilitiesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Facilities';

    protected string $nameLower = 'facilities';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
    }

    /**
     * Book H2 OPS-05 has no settings table of its own in the spec —
     * these are the ones its own business rules imply a school needs
     * to configure.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['facilities.require_deposit_for_external_hire', 'bool', '1', 'Whether an external hire must have a deposit on file before it can be confirmed (locked true, BR-OPS-05-003).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'OPS',
                'group_key' => explode('.', $key)[0],
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => 'toggle',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A Part 1.11's tenancy isolation test generator.
     */
    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(BookableResource::class, fn (School $school): BookableResource => BookableResource::factory()->for($school)->create());

        TenantModelRegistry::register(ResourceBooking::class, fn (School $school): ResourceBooking => ResourceBooking::factory()->for($school)->create());
    }
}
