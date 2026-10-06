<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Http\Controllers\Api\V1\ReportCardsController;
use Modules\Academic\Http\Controllers\Api\V1\StudentAcademicsController;
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
use Modules\People\Http\Controllers\Api\V1\GuardianChildrenController;

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

Route::prefix('auth')->middleware(['serp.resolve-tenant', 'throttle:20,1'])->group(function (): void {
    Route::post('otp/request', [AuthController::class, 'requestOtp']);
    Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['serp.api', 'throttle:120,1'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('me', [MeController::class, 'show']);
    Route::get('me/schools', [MeController::class, 'schools']);
    Route::get('me/session', [MeController::class, 'currentSession']);

    Route::get('guardians/me/children', [GuardianChildrenController::class, 'index'])->middleware('serp.token-ability:children.read');
    Route::get('students/{student}/report-cards', [ReportCardsController::class, 'index'])->middleware('serp.token-ability:results.read');

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

    Route::get('teacher/classes', [TeacherAttendanceController::class, 'classes'])->middleware('serp.token-ability:attendance.mark');
    Route::get('teacher/classes/{class}/attendance', [TeacherAttendanceController::class, 'register'])->middleware('serp.token-ability:attendance.mark');
    Route::post('teacher/classes/{class}/attendance', [TeacherAttendanceController::class, 'mark'])->middleware('serp.token-ability:attendance.mark');

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
});
