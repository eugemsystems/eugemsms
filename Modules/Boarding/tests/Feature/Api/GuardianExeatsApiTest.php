<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * A parent asking for an exeat through the mobile API: only for their own boarder, only if they may
 * authorise exeats, through the same rules the staff screen applies.
 *
 * @return array{school: School, user: User, child: Student, type: ExeatType, link: StudentGuardian}
 */
function exeatApiFixture(bool $mayAuthorise = true): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'exeat', pattern: 'EX/{SEQ:4}'));

    $user = User::factory()->create(['tenant_id' => $school->tenant_id]);
    $user->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);
    $guardian = Guardian::factory()->for($school)->create(['user_id' => $user->id]);
    $child = Student::factory()->for($school)->create(['residency' => 'BOARDER']);
    $link = StudentGuardian::factory()->create(['school_id' => $school->id, 'student_id' => $child->id, 'guardian_id' => $guardian->id, 'may_authorise_exeat' => $mayAuthorise]);
    $type = ExeatType::factory()->create(['school_id' => $school->id, 'min_notice_hours' => 0, 'allowed_per_term' => 5, 'blocks_on_fee_arrears' => true]);

    return compact('school', 'user', 'child', 'type', 'link');
}

/**
 * @param  array<string, mixed>  $f
 * @return array<string, mixed>
 */
function exeatApiBody(array $f, array $over = []): array
{
    return $over + [
        'exeat_type_id' => $f['type']->id, 'reason' => 'Family wedding', 'departs_at' => now()->addDays(2)->toIso8601String(),
        'returns_by' => now()->addDays(3)->toIso8601String(), 'destination_address' => '12 Samora Machel Ave', 'destination_province' => 'Harare',
        'contact_phone' => '0771234567', 'collection_method' => 'guardian_collects',
    ];
}

it('lists exeat types and requests an exeat for the guardian\'s own boarder', function (): void {
    $f = exeatApiFixture();
    Sanctum::actingAs($f['user'], ['*']);

    expect($this->getJson('/api/v1/exeat-types')->assertOk()->json('data.0.code'))->toBe($f['type']->code);

    $created = $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-1'])->assertCreated();
    expect($created->json('data.status'))->toBe('pending')->and($created->json('data'))->not->toHaveKey('verification_code');

    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-1'])->assertStatus(201);
    expect(Exeat::count())->toBe(1);

    expect($this->getJson('/api/v1/students/'.$f['child']->ulid.'/exeats')->assertOk()->json('data'))->toHaveCount(1);

    Exeat::query()->update(['status' => 'approved', 'verification_code' => 'AB12CD']);
    expect($this->getJson('/api/v1/students/'.$f['child']->ulid.'/exeats')->json('data.0.verification_code'))->toBe('AB12CD');
});

it('refuses a guardian who may not authorise exeats, a stranger\'s child, and a day scholar', function (): void {
    $f = exeatApiFixture(mayAuthorise: false);
    Sanctum::actingAs($f['user'], ['*']);
    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-2'])->assertForbidden();

    $other = Student::factory()->for($f['school'])->create(['residency' => 'BOARDER']);
    $this->postJson('/api/v1/students/'.$other->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-3'])->assertNotFound();
    $this->getJson('/api/v1/students/'.$other->ulid.'/exeats')->assertNotFound();

    $f['link']->update(['may_authorise_exeat' => true]);
    DB::table('students')->where('id', $f['child']->id)->update(['residency' => 'DAY']);
    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-4'])->assertStatus(422);
    expect(Exeat::count())->toBe(0);
});

it('needs the parent\'s own one-off authorisation for a collector who is not a registered guardian', function (): void {
    $f = exeatApiFixture();
    Sanctum::actingAs($f['user'], ['*']);
    $body = exeatApiBody($f, ['collection_method' => 'other_person_collects', 'collecting_person_name' => 'Uncle Tendai', 'collecting_person_id_no' => '63-123456A63']);

    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', $body, ['Idempotency-Key' => 'e-5'])->assertCreated();
    expect(Exeat::first()->collecting_person_name)->toBe('Uncle Tendai')->and(Exeat::first()->one_off_authorisation_by)->not->toBeNull();

    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f, ['collection_method' => 'other_person_collects']), ['Idempotency-Key' => 'e-6'])->assertStatus(422);
});

it('blocks an exeat for a suspended learner and for fee arrears over the school\'s threshold', function (): void {
    $f = exeatApiFixture();
    Sanctum::actingAs($f['user'], ['*']);

    DB::table('students')->where('id', $f['child']->id)->update(['status' => 'suspended']);
    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-7'])->assertStatus(422)->assertJsonPath('error.code', 'EXEAT_BLOCKED');

    DB::table('students')->where('id', $f['child']->id)->update(['status' => 'enrolled']);
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('boarding.exeat_block_on_fee_arrears', SettingScope::School, $f['school']->id, true));
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('boarding.exeat_arrears_threshold_minor', SettingScope::School, $f['school']->id, 10000));
    Invoice::factory()->create(['school_id' => $f['school']->id, 'student_id' => $f['child']->id, 'currency' => 'USD', 'net_minor' => 50000, 'balance_minor' => 50000, 'status' => 'issued']);

    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-8'])->assertStatus(422)->assertJsonPath('error.code', 'EXEAT_BLOCKED');
    expect(Exeat::count())->toBe(0);

    Invoice::query()->update(['balance_minor' => 5000]);
    $this->postJson('/api/v1/students/'.$f['child']->ulid.'/exeats', exeatApiBody($f), ['Idempotency-Key' => 'e-9'])->assertCreated();
});
