<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Comms\Livewire\Automation\Builder as AutomationBuilder;
use Modules\Comms\Livewire\Automation\ExecutionLog as AutomationExecutionLog;
use Modules\Comms\Livewire\Automation\Index as AutomationIndex;
use Modules\Comms\Livewire\Automation\ScanRuns as AutomationScanRuns;
use Modules\Comms\Livewire\Automation\Variants as AutomationVariants;
use Modules\Comms\Livewire\Calendar\View as CalendarView;
use Modules\Comms\Livewire\Complaints\Categories as ComplaintCategories;
use Modules\Comms\Livewire\Complaints\Queue as ComplaintsQueue;
use Modules\Comms\Livewire\Complaints\Show as ComplaintsShow;
use Modules\Comms\Livewire\Complaints\Submit as ComplaintsSubmit;
use Modules\Comms\Livewire\Consultations\Windows as ConsultationWindows;
use Modules\Comms\Livewire\Events\CheckIn as EventsCheckIn;
use Modules\Comms\Livewire\Events\Register as EventsRegister;
use Modules\Comms\Livewire\ExitInterviews\Index as ExitInterviewsIndex;
use Modules\Comms\Livewire\Meetings\AttendanceReview as MeetingAttendanceReview;
use Modules\Comms\Livewire\Meetings\Index as MeetingsIndex;
use Modules\Comms\Livewire\Meetings\Providers as MeetingProviders;
use Modules\Comms\Livewire\Meetings\Recordings as MeetingRecordings;
use Modules\Comms\Livewire\Messaging\Gateways\Index as GatewaysIndex;
use Modules\Comms\Livewire\Messaging\Gateways\Webhooks as GatewayWebhooks;
use Modules\Comms\Livewire\Messaging\Reports\Cost as CostReport;
use Modules\Comms\Livewire\Messaging\Reports\Reconciliation as ReconciliationReport;
use Modules\Comms\Livewire\Messaging\Sms\SenderIds;
use Modules\Comms\Livewire\Messaging\WhatsApp\Templates as WhatsAppTemplates;
use Modules\Comms\Livewire\Newsletters\Compose as NewslettersCompose;
use Modules\Comms\Livewire\Notices\Compose as NoticesCompose;
use Modules\Comms\Livewire\Notices\Index as NoticesIndex;
use Modules\Comms\Livewire\Portal\Admin\Widgets as PortalWidgets;
use Modules\Comms\Livewire\Surveys\Builder as SurveysBuilder;
use Modules\Comms\Livewire\Surveys\Results as SurveysResults;

/**
 * Book I admin screens, school-scoped like every other module's own
 * route group in this codebase.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/comms')->name('comms.')->group(function (): void {
    Route::livewire('gateways', GatewaysIndex::class)->name('gateways.index');
    Route::livewire('gateways/webhooks', GatewayWebhooks::class)->name('gateways.webhooks');
    Route::livewire('whatsapp/templates', WhatsAppTemplates::class)->name('whatsapp.templates');
    Route::livewire('sms/sender-ids', SenderIds::class)->name('sms.sender-ids');
    Route::livewire('reports/cost', CostReport::class)->name('reports.cost');
    Route::livewire('reports/reconciliation', ReconciliationReport::class)->name('reports.reconciliation');

    Route::prefix('automation')->name('automation.')->group(function (): void {
        Route::livewire('/', AutomationIndex::class)->name('index');
        Route::livewire('rules/create', AutomationBuilder::class)->name('create');
        Route::livewire('rules/{rule}', AutomationBuilder::class)->name('builder');
        Route::livewire('executions', AutomationExecutionLog::class)->name('executions');
        Route::livewire('scans', AutomationScanRuns::class)->name('scans');
        Route::livewire('variants', AutomationVariants::class)->name('variants');
    });

    Route::livewire('portal/widgets', PortalWidgets::class)->name('portal.widgets');

    Route::livewire('calendar', CalendarView::class)->name('calendar.view');
    Route::livewire('notices', NoticesIndex::class)->name('notices.index');
    Route::livewire('notices/compose', NoticesCompose::class)->name('notices.compose');
    Route::livewire('newsletters', NewslettersCompose::class)->name('newsletters.compose');
    Route::livewire('events/registrations', EventsRegister::class)->name('events.register');
    Route::livewire('events/check-in', EventsCheckIn::class)->name('events.checkin');

    Route::prefix('meetings')->name('meetings.')->group(function (): void {
        Route::livewire('/', MeetingsIndex::class)->name('index');
        Route::livewire('providers', MeetingProviders::class)->name('providers');
        Route::livewire('attendance', MeetingAttendanceReview::class)->name('attendance');
        Route::livewire('recordings', MeetingRecordings::class)->name('recordings');
        Route::livewire('consultations', ConsultationWindows::class)->name('consultations');
    });

    Route::livewire('surveys', SurveysBuilder::class)->name('surveys.builder');
    Route::livewire('surveys/results', SurveysResults::class)->name('surveys.results');

    Route::prefix('complaints')->name('complaints.')->group(function (): void {
        Route::livewire('/', ComplaintsQueue::class)->name('queue');
        Route::livewire('submit', ComplaintsSubmit::class)->name('submit');
        Route::livewire('categories', ComplaintCategories::class)->name('categories');
        Route::livewire('{complaint}', ComplaintsShow::class)->name('show');
    });

    Route::livewire('exit-interviews', ExitInterviewsIndex::class)->name('exit-interviews');
});
