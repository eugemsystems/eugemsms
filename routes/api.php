<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Http\Controllers\Api\V1\CurriculumController;
use Modules\Academic\Http\Controllers\Api\V1\GuardianLmsController;
use Modules\Academic\Http\Controllers\Api\V1\LearnerSelfController;
use Modules\Academic\Http\Controllers\Api\V1\ReportCardsController;
use Modules\Academic\Http\Controllers\Api\V1\StudentAcademicsController;
use Modules\Academic\Http\Controllers\Api\V1\SubjectSelectionsController;
use Modules\Academic\Http\Controllers\Api\V1\TeacherAttendanceController;
use Modules\Academic\Http\Controllers\Api\V1\TeacherMarksController;
use Modules\Boarding\Http\Controllers\Api\V1\GuardianExeatsController;
use Modules\Comms\Http\Controllers\Api\V1\DevicesController;
use Modules\Comms\Http\Controllers\Api\V1\InboxController;
use Modules\Comms\Http\Controllers\Api\V1\NoticesController;
use Modules\Core\Http\Controllers\Api\V1\AuthController;
use Modules\Core\Http\Controllers\Api\V1\LookupsController;
use Modules\Core\Http\Controllers\Api\V1\MeController;
use Modules\Finance\Http\Controllers\Api\V1\GatewayWebhookController;
use Modules\Finance\Http\Controllers\Api\V1\GuardianFinanceController;
use Modules\Finance\Http\Controllers\Api\V1\GuardianPaymentsController;
use Modules\Intelligence\Http\Controllers\Api\V1\HardwareController;
use Modules\Intelligence\Http\Controllers\Api\V1\OpenApiController;
use Modules\Intelligence\Http\Controllers\Api\V1\ReportsController;
use Modules\People\Http\Controllers\Api\V1\GuardianChildrenController;
use Modules\People\Http\Controllers\Api\V1\GuardianDocumentsController;
use Modules\People\Http\Controllers\Api\V1\GuardianProfileController;
use Modules\People\Http\Controllers\Api\V1\LearnerProfileController;
use Modules\People\Http\Controllers\Api\V1\Public\PublicApplicationsController;
use Modules\People\Http\Controllers\Api\V1\StaffController;
use Modules\People\Http\Controllers\Api\V1\StaffSelfServiceController;
use Modules\People\Http\Controllers\Api\V1\StudentsController;

/*
|--------------------------------------------------------------------------
| /api/v1 (Volume 1 §9)
|--------------------------------------------------------------------------
| Bearer-token (Sanctum) REST API for the parent, teacher and student apps. Public routes are
| the sign-in family only, rate limited per IP; everything else runs through the `serp.api`
| stack (tenant, subscription, token, school, session). A token's abilities are a ceiling on
| the person's role (BR-CORE-05-008), declared per route with `serp.token-ability`. Money is
| always a `{amount_minor, currency, formatted}` object. Financial mutations will sit behind
| `serp.idempotent`, which requires an `Idempotency-Key` header.
*/

// Payment gateway result callbacks: public, authenticated by the gateway driver itself.
Route::post('webhooks/payments/{driver}', [GatewayWebhookController::class, 'receive'])
    ->middleware(['serp.resolve-tenant', 'throttle:300,1']);

// Machine-readable spec (Book J INT-04, BR-INT-04-010): public, generated from the live routes.
Route::get('openapi.json', OpenApiController::class)->middleware('throttle:60,1')->name('api.openapi');

// Hardware devices authenticate with their OWN api_clients key, never a user token (INT-04 §3).
Route::prefix('hardware')->middleware(['serp.api-client'])->group(function (): void {
    Route::post('scan', [HardwareController::class, 'scan']);
    Route::post('{ulid}/heartbeat', [HardwareController::class, 'heartbeat']);
});

// Third-party reporting access (Book J INT-01 §5): an integration client acts as its own
// `created_by` user, so a key can only run/export what the person who issued it could see.
Route::prefix('reports')->middleware(['serp.api-client:reports:read'])->group(function (): void {
    Route::get('entities', [ReportsController::class, 'entities']);
    Route::post('{ulid}/run', [ReportsController::class, 'run']);
    Route::get('{ulid}/export', [ReportsController::class, 'export']);
});

Route::prefix('auth')->middleware(['serp.resolve-tenant', 'throttle:20,1'])->group(function (): void {
    Route::post('otp/request', [AuthController::class, 'requestOtp']);
    Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// Book C PPL-02 §6/BR-PPL-02-001: unauthenticated, rate-limited admissions REST surface, distinct
// from the existing /apply/{slug} Blade form. See PublicApplicationsController's own docblock for
// what "CAPTCHA-protected" and "signed upload" mean here in the absence of a real CAPTCHA service
// or a signed-URL-for-anonymous-POST mechanism anywhere else in this codebase.
Route::prefix('public')->middleware('throttle:10,1')->group(function (): void {
    Route::get('intakes', [PublicApplicationsController::class, 'intakes']);
    Route::post('applications', [PublicApplicationsController::class, 'store']);
    Route::get('applications/track', [PublicApplicationsController::class, 'track']);
    Route::post('applications/{ulid}/documents', [PublicApplicationsController::class, 'storeDocument']);
    Route::post('enquiries', [PublicApplicationsController::class, 'storeEnquiry']);
});

Route::middleware(['serp.api', 'throttle:120,1'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('me', [MeController::class, 'show']);
    Route::get('me/schools', [MeController::class, 'schools']);
    Route::get('me/session', [MeController::class, 'currentSession']);

    Route::get('guardians/me/children', [GuardianChildrenController::class, 'index'])->middleware('serp.token-ability:children.read');
    Route::get('students/{student}/report-cards', [ReportCardsController::class, 'index'])->middleware('serp.token-ability:results.read');

    Route::get('students/{student}/documents', [GuardianDocumentsController::class, 'index'])->middleware('serp.token-ability:documents.read');
    Route::get('students/{student}/documents/{document}/download', [GuardianDocumentsController::class, 'download'])->middleware('serp.token-ability:documents.read');

    Route::get('students/{student}/lms/courses', [GuardianLmsController::class, 'courses'])->middleware('serp.token-ability:homework.read');
    Route::get('students/{student}/homework', [GuardianLmsController::class, 'homework'])->middleware('serp.token-ability:homework.read');

    Route::get('finance/balances', [GuardianFinanceController::class, 'balances'])->middleware('serp.token-ability:fees.read');
    Route::get('finance/invoices', [GuardianFinanceController::class, 'invoices'])->middleware('serp.token-ability:fees.read');
    Route::get('finance/invoices/{invoice}', [GuardianFinanceController::class, 'showInvoice'])->middleware('serp.token-ability:fees.read');
    Route::get('finance/payment-methods', [GuardianPaymentsController::class, 'methods'])->middleware('serp.token-ability:fees.read');
    Route::post('finance/payments', [GuardianPaymentsController::class, 'store'])->middleware(['serp.token-ability:fees.pay', 'serp.idempotent']);
    Route::get('finance/payments/{payment}', [GuardianPaymentsController::class, 'show'])->middleware('serp.token-ability:fees.pay');

    Route::post('me/devices', [DevicesController::class, 'store']);
    Route::delete('me/devices/{device}', [DevicesController::class, 'destroy']);

    Route::get('lookups/terms', [LookupsController::class, 'terms']);
    Route::get('lookups/grade-levels', [LookupsController::class, 'gradeLevels']);

    Route::get('students/{student}/attendance', [StudentAcademicsController::class, 'attendance'])->middleware('serp.token-ability:attendance.read');
    Route::get('students/{student}/timetable', [StudentAcademicsController::class, 'timetable'])->middleware('serp.token-ability:timetable.read');
    Route::get('students/{student}/subjects', [StudentAcademicsController::class, 'subjects'])->middleware('serp.token-ability:subjects.read');
    Route::get('students/{student}/subject-history', [StudentAcademicsController::class, 'subjectHistory'])->middleware('serp.token-ability:subjects.read');
    Route::get('students/{student}/performance-trend', [StudentAcademicsController::class, 'performanceTrend'])->middleware('serp.token-ability:performance.read');

    // Book D ACA-01 §6: read-only catalogue reference data for a mobile subject-selection flow.
    Route::get('academic/frameworks', [CurriculumController::class, 'frameworks'])->middleware('serp.token-ability:catalogue.read');
    Route::get('academic/subjects', [CurriculumController::class, 'subjects'])->middleware('serp.token-ability:catalogue.read');
    Route::get('academic/subject-groups', [CurriculumController::class, 'subjectGroups'])->middleware('serp.token-ability:catalogue.read');
    Route::get('academic/offerings', [CurriculumController::class, 'offerings'])->middleware('serp.token-ability:catalogue.read');
    Route::get('academic/selection-rules', [CurriculumController::class, 'selectionRules'])->middleware('serp.token-ability:catalogue.read');
    Route::post('academic/selection-rules/validate', [CurriculumController::class, 'validateSelection'])->middleware('serp.token-ability:catalogue.read');

    // Book D ACA-02 §6/§7: the guardian/learner-facing half of subject selection
    // `Academic\Selection\Form`'s own docblock names as unbuilt. School approval and allocation
    // stay staff-only (`Selection\Approvals`) -- no API route for either.
    Route::post('academic/selections', [SubjectSelectionsController::class, 'store'])->middleware(['serp.token-ability:selections.submit', 'serp.idempotent']);
    Route::post('academic/selections/preview-fee', [SubjectSelectionsController::class, 'previewFee'])->middleware('serp.token-ability:selections.read');
    Route::get('academic/selections/{selection}', [SubjectSelectionsController::class, 'show'])->middleware('serp.token-ability:selections.read');
    Route::post('academic/selections/{selection}/approve', [SubjectSelectionsController::class, 'approve'])->middleware(['serp.token-ability:selections.approve', 'serp.idempotent']);

    Route::get('teacher/classes', [TeacherAttendanceController::class, 'classes'])->middleware('serp.token-ability:attendance.mark');
    Route::get('teacher/classes/{class}/attendance', [TeacherAttendanceController::class, 'register'])->middleware('serp.token-ability:attendance.mark');
    Route::post('teacher/classes/{class}/attendance', [TeacherAttendanceController::class, 'mark'])->middleware('serp.token-ability:attendance.mark');
    Route::post('attendance/sync', [TeacherAttendanceController::class, 'sync'])->middleware('serp.token-ability:attendance.mark');

    Route::get('teacher/assessments', [TeacherMarksController::class, 'index'])->middleware('serp.token-ability:results.enter');
    Route::get('teacher/assessments/{assessment}', [TeacherMarksController::class, 'show'])->middleware('serp.token-ability:results.enter');
    Route::post('teacher/assessments/{assessment}/marks', [TeacherMarksController::class, 'save'])->middleware(['serp.token-ability:results.enter', 'serp.idempotent']);
    Route::post('teacher/assessments/{assessment}/submit', [TeacherMarksController::class, 'submit'])->middleware(['serp.token-ability:results.enter', 'serp.idempotent']);

    Route::get('exeat-types', [GuardianExeatsController::class, 'types'])->middleware('serp.token-ability:exeats.read');
    Route::get('students/{student}/exeats', [GuardianExeatsController::class, 'index'])->middleware('serp.token-ability:exeats.read');
    Route::post('students/{student}/exeats', [GuardianExeatsController::class, 'store'])->middleware(['serp.token-ability:exeats.request', 'serp.idempotent']);

    Route::get('communications/inbox', [InboxController::class, 'index'])->middleware('serp.token-ability:notices.read');
    Route::post('communications/inbox/{notification}/read', [InboxController::class, 'read'])->middleware('serp.token-ability:notices.read');
    Route::get('communications/notices', [NoticesController::class, 'index'])->middleware('serp.token-ability:notices.read');

    // Book C PPL-01 §9/PPL-03 §8: staff-scoped student directory, read-only (no Action exists for
    // any of these queries, only for the writes that created the rows -- see StudentsController's
    // own docblock).
    Route::get('students', [StudentsController::class, 'index'])->middleware('serp.token-ability:students.read');
    Route::get('students/{student}', [StudentsController::class, 'show'])->middleware('serp.token-ability:students.read');
    Route::get('students/{student}/timeline', [StudentsController::class, 'timeline'])->middleware('serp.token-ability:students.read');
    Route::get('students/{student}/enrolments', [StudentsController::class, 'enrolments'])->middleware('serp.token-ability:students.read');
    Route::get('students/{student}/guardians', [StudentsController::class, 'guardians'])->middleware('serp.token-ability:students.read');
    Route::get('students/{student}/collection-authorised', [StudentsController::class, 'collectionAuthorised'])->middleware('serp.token-ability:collection.read');

    // Book C PPL-01 §9: a learner token's own reduced profile.
    Route::get('me/profile', [LearnerProfileController::class, 'show'])->middleware('serp.token-ability:profile.read');

    // Book D ACA-02 §7/ACA-04 §6/ACA-05 §7: the same "me" ergonomic shortcut as /me/profile, for a
    // learner token that would otherwise need to already know its own ulid to call the
    // students/{student}/* equivalents above. Teaching-groups/roll (ACA-02 §7) is deliberately not
    // built -- no mobile consumer for "my teaching groups" exists in this pass beyond what
    // teacher/assessments and teacher/classes already expose for marking.
    Route::get('me/subjects', [LearnerSelfController::class, 'subjects'])->middleware('serp.token-ability:subjects.read');
    Route::get('me/attendance', [LearnerSelfController::class, 'attendance'])->middleware('serp.token-ability:attendance.read');
    Route::get('me/results', [LearnerSelfController::class, 'results'])->middleware('serp.token-ability:results.read');

    // Book C PPL-03 §8: guardian self-service beyond the existing finance/children slice.
    Route::patch('me/contact-details', [GuardianProfileController::class, 'updateContactDetails'])->middleware('serp.token-ability:contact.manage');
    Route::get('me/notification-preferences', [GuardianProfileController::class, 'notificationPreferences'])->middleware('serp.token-ability:notification_preferences.manage');
    Route::put('me/notification-preferences', [GuardianProfileController::class, 'updateNotificationPreferences'])->middleware('serp.token-ability:notification_preferences.manage');
    Route::get('me/liabilities', [GuardianFinanceController::class, 'liabilities'])->middleware('serp.token-ability:fees.read');
    Route::get('me/statement', [GuardianFinanceController::class, 'statement'])->middleware('serp.token-ability:fees.read');

    // Book C PPL-04 §6: staff self-service. /me/timetable is deliberately not here -- the spec
    // itself delegates it to ACA-03, which has no /api/v1 surface of its own yet.
    Route::get('me/staff-profile', [StaffSelfServiceController::class, 'profile'])->middleware('serp.token-ability:staff.self');
    Route::get('me/allocations', [StaffSelfServiceController::class, 'allocations'])->middleware('serp.token-ability:staff.self');
    Route::get('me/workload', [StaffSelfServiceController::class, 'workload'])->middleware('serp.token-ability:staff.self');
    Route::get('me/duties', [StaffSelfServiceController::class, 'duties'])->middleware('serp.token-ability:staff.self');
    Route::post('me/duties/{assignment}/swap-request', [StaffSelfServiceController::class, 'requestDutySwap'])->middleware(['serp.token-ability:staff.self', 'serp.idempotent']);
    Route::get('me/leave/balances', [StaffSelfServiceController::class, 'leaveBalances'])->middleware('serp.token-ability:staff.self');
    Route::get('me/leave/requests', [StaffSelfServiceController::class, 'leaveRequests'])->middleware('serp.token-ability:staff.self');
    Route::post('me/leave/requests', [StaffSelfServiceController::class, 'storeLeaveRequest'])->middleware(['serp.token-ability:staff.self', 'serp.idempotent']);
    Route::delete('me/leave/requests/{leaveRequest}', [StaffSelfServiceController::class, 'cancelLeaveRequest'])->middleware('serp.token-ability:staff.self');

    Route::get('staff', [StaffController::class, 'index'])->middleware('serp.token-ability:staff.read');
    Route::get('staff/{staff}', [StaffController::class, 'show'])->middleware('serp.token-ability:staff.read');
});
