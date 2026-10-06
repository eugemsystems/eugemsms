<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Modules\Academic\Models\TermResult;
use Modules\Comms\Models\Notice;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * The guardian-facing `/api/v1` slice: children, balances, invoices, published report cards
 * and notices. A parent sees only their own linked children, money comes back as `Money`, and
 * a field gated by a link permission is absent — not hidden — when the link lacks it.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User, child: Student, link: StudentGuardian}
 */
function guardianApiFixture(bool $mayViewBalance = true): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $user = User::factory()->create(['tenant_id' => $school->tenant_id]);
    $user->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);
    $guardian = Guardian::factory()->for($school)->create(['user_id' => $user->id]);
    $child = Student::factory()->for($school)->create();
    $link = StudentGuardian::factory()->create([
        'school_id' => $school->id, 'student_id' => $child->id, 'guardian_id' => $guardian->id,
        'may_view_full_balance' => $mayViewBalance,
    ]);

    return compact('school', 'year', 'term', 'user', 'child', 'link');
}

/**
 * @param  array<string, mixed>  $f
 * @param  array<string, mixed>  $attributes
 */
function guardianApiInvoice(array $f, Student $student, array $attributes = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::query()->create($attributes + [
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'invoice_number' => 'INV/'.fake()->unique()->numerify('######'), 'invoice_type' => 'term', 'student_id' => $student->id,
        'billed_party_type' => 'guardian', 'billed_party_id' => 1, 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
        'gross_minor' => 45000, 'net_minor' => 45000, 'paid_minor' => 10000, 'balance_minor' => 35000, 'currency' => 'USD', 'status' => 'issued',
    ]));
}

it('lists only the learners the guardian is currently linked to', function (): void {
    $f = guardianApiFixture();
    $stranger = Student::factory()->for($f['school'])->create();
    $ended = Student::factory()->for($f['school'])->create();
    StudentGuardian::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $ended->id, 'guardian_id' => $f['link']->guardian_id,
        'status' => 'inactive',
    ]);
    Sanctum::actingAs($f['user'], ['*']);

    $data = $this->getJson('/api/v1/guardians/me/children')->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($f['child']->ulid)
        ->and(collect($data)->pluck('id'))->not->toContain($stranger->ulid, $ended->ulid);
});

it('returns balances as Money objects, summed from non-voided invoices only', function (): void {
    $f = guardianApiFixture();
    guardianApiInvoice($f, $f['child']);
    guardianApiInvoice($f, $f['child'], ['net_minor' => 5000, 'gross_minor' => 5000, 'paid_minor' => 0, 'balance_minor' => 5000]);
    guardianApiInvoice($f, $f['child'], ['net_minor' => 99999, 'gross_minor' => 99999, 'paid_minor' => 0, 'balance_minor' => 99999, 'status' => 'voided']);
    Sanctum::actingAs($f['user'], ['*']);

    $row = $this->getJson('/api/v1/finance/balances')->assertOk()->json('data.0');

    expect($row['student_id'])->toBe($f['child']->ulid)
        ->and($row['balances'][0]['billed'])->toBe(['amount_minor' => 50000, 'currency' => 'USD', 'formatted' => $row['balances'][0]['billed']['formatted']])
        ->and($row['balances'][0]['outstanding']['amount_minor'])->toBe(40000)
        ->and($row['balances'][0]['outstanding']['formatted'])->toBeString();
});

it('leaves balance fields out entirely for a link without may_view_full_balance', function (): void {
    $f = guardianApiFixture(mayViewBalance: false);
    $invoice = guardianApiInvoice($f, $f['child']);
    Sanctum::actingAs($f['user'], ['*']);

    $balances = $this->getJson('/api/v1/finance/balances')->assertOk()->json('data.0');
    expect($balances)->not->toHaveKey('balances');

    $row = $this->getJson('/api/v1/finance/invoices?student='.$f['child']->ulid)->assertOk()->json('data.0');
    expect($row['id'])->toBe($invoice->ulid)->and($row)->toHaveKey('net')->and($row)->not->toHaveKey('balance')->and($row)->not->toHaveKey('paid');

    $detail = $this->getJson('/api/v1/finance/invoices/'.$invoice->ulid)->assertOk()->json('data');
    expect($detail)->not->toHaveKey('balance');
});

it('paginates invoices and caps the page size at 100', function (): void {
    $f = guardianApiFixture();
    foreach (range(1, 3) as $i) {
        guardianApiInvoice($f, $f['child']);
    }
    Sanctum::actingAs($f['user'], ['*']);

    $response = $this->getJson('/api/v1/finance/invoices?student='.$f['child']->ulid.'&per_page=2')->assertOk();
    expect($response->json('data'))->toHaveCount(2)->and($response->json('meta.pagination.total'))->toBe(3);

    expect($this->getJson('/api/v1/finance/invoices?student='.$f['child']->ulid.'&per_page=500')->json('meta.pagination.per_page'))->toBe(100);
});

it('answers 404 for another family\'s learner or invoice and 422 when no learner is named', function (): void {
    $f = guardianApiFixture();
    $other = Student::factory()->for($f['school'])->create();
    $foreign = guardianApiInvoice($f, $other);
    Sanctum::actingAs($f['user'], ['*']);

    $this->getJson('/api/v1/finance/invoices?student='.$other->ulid)->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    $this->getJson('/api/v1/finance/invoices/'.$foreign->ulid)->assertStatus(404);
    $this->getJson('/api/v1/finance/invoices')->assertStatus(422);
    $this->getJson('/api/v1/students/'.$other->ulid.'/report-cards')->assertStatus(404);
});

it('shows published report cards with marks and a withheld one with no marks and no reason', function (): void {
    $f = guardianApiFixture();
    $class = SchoolClass::factory()->for($f['school'])->create();
    $term2 = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create(['number' => 2]);

    foreach ([[$f['term'], 'published', '72.50', null], [$term2, 'withheld', '65.00', 'Fees outstanding USD 350']] as [$term, $status, $average, $reason]) {
        TermResult::unguarded(fn () => TermResult::query()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $term->id, 'student_id' => $f['child']->id,
            'class_id' => $class->id, 'status' => $status, 'average_percent' => $average, 'withheld_reason' => $reason, 'published_at' => $status === 'published' ? now() : null,
            'head_comment' => 'Well done', 'subjects_taken' => 8, 'report_version' => 1,
        ]));
    }
    TermResult::unguarded(fn () => TermResult::query()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create(['number' => 3])->id,
        'student_id' => $f['child']->id, 'class_id' => $class->id, 'status' => 'computed', 'average_percent' => '50.00', 'subjects_taken' => 8, 'report_version' => 1,
    ]));
    Sanctum::actingAs($f['user'], ['*']);

    $cards = collect($this->getJson('/api/v1/students/'.$f['child']->ulid.'/report-cards')->assertOk()->json('data'))->keyBy('status');

    expect($cards)->toHaveCount(2)
        ->and((float) $cards['published']['average_percent'])->toBe(72.5)
        ->and($cards['published']['head_comment'])->toBe('Well done')
        ->and($cards['withheld'])->not->toHaveKey('average_percent')
        ->and(json_encode($cards['withheld']))->not->toContain('Fees outstanding');
});

it('shows a guardian only the live notices aimed at them', function (): void {
    $f = guardianApiFixture();
    $mk = fn (array $attributes) => Notice::factory()->create($attributes + ['school_id' => $f['school']->id, 'posted_by' => $f['user']->id]);
    $mk(['title' => 'School-wide']);
    $mk(['title' => 'Our level', 'audience_scope' => 'level', 'audience_scope_id' => $f['child']->grade_level_id]);
    $mk(['title' => 'Other level', 'audience_scope' => 'level', 'audience_scope_id' => $f['child']->grade_level_id + 999]);
    $mk(['title' => 'Staff only', 'audience_scope' => 'staff']);
    $mk(['title' => 'Expired', 'expires_at' => now()->subDay()]);
    $mk(['title' => 'Scheduled', 'status' => 'scheduled', 'publish_at' => now()->addDay()]);
    Sanctum::actingAs($f['user'], ['*']);

    $titles = collect($this->getJson('/api/v1/communications/notices')->assertOk()->json('data'))->pluck('title');

    expect($titles->all())->toEqualCanonicalizing(['School-wide', 'Our level']);
});

it('reads a named term through X-Academic-Year-Id / X-Term-Id and refuses one that is not the school\'s', function (): void {
    $f = guardianApiFixture();
    $other = School::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    $foreignYear = AcademicYear::factory()->for($other)->create();
    $pastTerm = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create(['number' => 2]);
    Sanctum::actingAs($f['user'], ['*']);

    expect($this->getJson('/api/v1/me/session', ['X-Term-Id' => (string) $pastTerm->id])->assertOk()->json('data.session.term.id'))->toBe($pastTerm->id);

    $this->getJson('/api/v1/me/session', ['X-Academic-Year-Id' => (string) $foreignYear->id])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_SESSION_CONTEXT');
    $this->getJson('/api/v1/me/session', ['X-Term-Id' => '999999'])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_SESSION_CONTEXT');
});

it('serves lookups with a cache header, a timetable and attendance for a linked learner only', function (): void {
    $f = guardianApiFixture();
    $stranger = Student::factory()->for($f['school'])->create();
    Sanctum::actingAs($f['user'], ['*']);

    $terms = $this->getJson('/api/v1/lookups/terms')->assertOk();
    expect($terms->json('data.0.terms'))->not->toBeEmpty()->and($terms->headers->get('Cache-Control'))->toContain('max-age=300');
    $this->getJson('/api/v1/lookups/grade-levels')->assertOk();

    $att = $this->getJson('/api/v1/students/'.$f['child']->ulid.'/attendance')->assertOk();
    expect($att->json('data'))->toHaveKeys(['term_id', 'summaries', 'recent']);
    expect($this->getJson('/api/v1/students/'.$f['child']->ulid.'/timetable')->assertOk()->json('data.slots'))->toBe([]);

    $this->getJson('/api/v1/students/'.$stranger->ulid.'/attendance')->assertStatus(404);
    $this->getJson('/api/v1/students/'.$stranger->ulid.'/timetable')->assertStatus(404);
});

it('registers a push device in place, lists the inbox with an unread count and lets only the owner act', function (): void {
    $f = guardianApiFixture();
    $guardianId = Guardian::query()->where('user_id', $f['user']->id)->value('id');
    $mk = fn (string $subject, ?DateTimeInterface $readAt = null) => Notification::unguarded(fn () => Notification::query()->create([
        'school_id' => $f['school']->id, 'notification_key' => 'academic.report_card_published', 'recipient_type' => 'guardian', 'recipient_id' => $guardianId,
        'recipient_address' => 'in-app', 'channel' => 'in_app', 'subject' => $subject, 'body' => 'Body', 'status' => 'delivered', 'read_at' => $readAt, 'created_at' => now(),
    ]));
    $unread = $mk('Report ready');
    $mk('Old', now());
    Sanctum::actingAs($f['user'], ['*']);

    $device = ['device_id' => 'dev-9', 'platform' => 'android', 'push_token' => 'tok-1'];
    $id = $this->postJson('/api/v1/me/devices', $device)->assertStatus(201)->json('data.id');
    expect($this->postJson('/api/v1/me/devices', ['push_token' => 'tok-2'] + $device)->json('data.id'))->toBe($id);
    $this->postJson('/api/v1/me/devices', ['device_id' => 'x', 'platform' => 'blackberry'])->assertStatus(422);

    $inbox = $this->getJson('/api/v1/communications/inbox')->assertOk();
    expect($inbox->json('meta.unread_count'))->toBe(1)->and($inbox->json('data'))->toHaveCount(2);

    $stranger = User::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    $stranger->schools()->attach($f['school'], ['status' => 'active', 'is_primary' => true]);
    Sanctum::actingAs($stranger, ['*']);
    $this->postJson('/api/v1/communications/inbox/'.$unread->ulid.'/read')->assertStatus(404);
    $this->deleteJson('/api/v1/me/devices/'.$id)->assertStatus(404);

    Sanctum::actingAs($f['user'], ['*']);
    $this->postJson('/api/v1/communications/inbox/'.$unread->ulid.'/read')->assertOk();
    expect($this->getJson('/api/v1/communications/inbox')->json('meta.unread_count'))->toBe(0);
    $this->deleteJson('/api/v1/me/devices/'.$id)->assertOk();
});

it('serves a learner their own report cards and attendance, never a fee balance, and never lists them as someone\'s child', function (): void {
    $f = guardianApiFixture();
    $learnerUser = User::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    $learnerUser->schools()->attach($f['school'], ['status' => 'active', 'is_primary' => true]);
    $own = Student::factory()->for($f['school'])->create(['user_id' => $learnerUser->id, 'status' => 'active']);
    guardianApiInvoice($f, $own);
    Sanctum::actingAs($learnerUser, ['*']);

    $this->getJson('/api/v1/students/'.$own->ulid.'/attendance')->assertOk();
    $this->getJson('/api/v1/students/'.$own->ulid.'/report-cards')->assertOk();
    $this->getJson('/api/v1/students/'.$f['child']->ulid.'/attendance')->assertStatus(404);
    expect($this->getJson('/api/v1/guardians/me/children')->json('data'))->toBe([]);

    $balances = $this->getJson('/api/v1/finance/balances')->assertOk()->json('data.0');
    expect($balances['student_id'])->toBe($own->ulid)->and($balances)->not->toHaveKey('balances');
});
