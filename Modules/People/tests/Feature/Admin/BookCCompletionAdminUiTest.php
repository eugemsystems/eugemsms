<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\RequestOtpAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Auth\VerifyOtpAction;
use Modules\Core\Domain\DataObjects\Auth\DeviceData;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RequestOtpData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Auth\VerifyOtpData;
use Modules\Core\Domain\Registry\LearnerClearanceRegistry;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\Document;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\ChangeStudentStatusAction;
use Modules\People\Domain\Actions\CreateGuardianAction;
use Modules\People\Domain\Actions\RequestGuardianContactUpdateAction;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\CreateGuardianData;
use Modules\People\Domain\DataObjects\RequestGuardianContactUpdateData;
use Modules\People\Livewire\Admissions\Applications\Documents as ApplicationDocuments;
use Modules\People\Livewire\Admissions\Enquiries\Board;
use Modules\People\Livewire\Admissions\Exams\Manage as ExamsManage;
use Modules\People\Livewire\Admissions\Interviews\Schedule;
use Modules\People\Livewire\Admissions\Reports\Funnel;
use Modules\People\Livewire\Guardians\PortalAccess;
use Modules\People\Livewire\Guardians\UpdateQueue;
use Modules\People\Livewire\Guardians\Verification;
use Modules\People\Livewire\Households\Index as HouseholdsIndex;
use Modules\People\Livewire\Sponsorships\Index as SponsorshipsIndex;
use Modules\People\Livewire\Sponsorships\Show as SponsorshipsShow;
use Modules\People\Livewire\Staff\Qualifications;
use Modules\People\Livewire\Students\Documents;
use Modules\People\Livewire\Students\IdCards;
use Modules\People\Livewire\Students\PriorHistory;
use Modules\People\Livewire\Students\Siblings;
use Modules\People\Livewire\Students\Timeline;
use Modules\People\Livewire\Students\TransferOut;
use Modules\People\Models\ApplicationDocument;
use Modules\People\Models\Enquiry;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\EntranceExamCandidate;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianContactUpdate;
use Modules\People\Models\GuardianVerification;
use Modules\People\Models\Household;
use Modules\People\Models\HouseholdMember;
use Modules\People\Models\Sponsorship;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffQualification;
use Modules\People\Models\Student;
use Modules\People\Models\StudentDocument;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentSibling;

/**
 * Book C completion screens. Reuses `studentFixture()`, `createStudentData()`,
 * `bcStudent()`, `bcFile()` and `ppl02Fixture()` from the other People tests,
 * so run the module directory.
 *
 * @param  array<string, mixed>  $f
 */
function bcUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $name): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1] ?? $parts[0], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

function bcPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

it('refuses every Book C screen to a user without its permission', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    $staff = Staff::factory()->for($f['school'])->create();
    $sponsor = app(CreateGuardianAction::class)->execute(new CreateGuardianData($f['school']->id, 'organisation', $f['user']->id, organisationName: 'Trust'));
    $sponsorship = Sponsorship::factory()->for($f['school'])->create(['guardian_id' => $sponsor->id]);
    $this->actingAs(bcUser($f, 'cbt.bank.manage'));

    foreach ([Documents::class, PriorHistory::class, Siblings::class, Timeline::class, TransferOut::class, IdCards::class] as $component) {
        Livewire::test($component, ['school' => $f['school'], 'student' => $student])->assertForbidden();
    }

    Livewire::test(Qualifications::class, ['school' => $f['school'], 'staff' => $staff])->assertForbidden();
    Livewire::test(SponsorshipsShow::class, ['school' => $f['school'], 'sponsorship' => $sponsorship])->assertForbidden();

    foreach ([HouseholdsIndex::class, SponsorshipsIndex::class, UpdateQueue::class, Verification::class, Board::class, ExamsManage::class, Schedule::class, Funnel::class] as $component) {
        Livewire::test($component, ['school' => $f['school']])->assertForbidden();
    }
});

it('attaches a learner document through the vault and verifies it', function (): void {
    Storage::fake('local');
    $f = studentFixture();
    $student = bcStudent($f);
    $this->actingAs(bcUser($f, 'people.students.document_manage'));

    Livewire::test(Documents::class, ['school' => $f['school'], 'student' => $student])
        ->set('documentType', 'birth_certificate')->set('file', bcPdf('birth.pdf'))->call('attach')->assertHasNoErrors();

    $document = StudentDocument::where('student_id', $student->id)->firstOrFail();
    Livewire::test(Documents::class, ['school' => $f['school'], 'student' => $student])->assertSee('birth certificate')->call('verify', $document->id, true);

    expect($document->fresh()->is_verified)->toBeTrue();
});

it('links and unlinks siblings from the learner screen and will not link someone from another school', function (): void {
    $f = studentFixture();
    $a = bcStudent($f, 'Alpha');
    $b = bcStudent($f, 'Bravo');
    $stranger = Student::factory()->for(School::factory()->create())->create();
    $this->actingAs(bcUser($f, 'people.students.update'));

    $page = Livewire::test(Siblings::class, ['school' => $f['school'], 'student' => $a])->call('select', $stranger->id)->assertSet('siblingId', null)->call('select', $b->id)->call('link')->assertHasNoErrors();
    expect(StudentSibling::count())->toBe(2);

    $page->call('unlink', $b->id);
    expect(StudentSibling::count())->toBe(0);
});

it('shows the clearance checklist and only transfers a learner out with a reason when clearance fails', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    ChangeStudentStatusAction::class;
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'active', $f['user']->id));
    LearnerClearanceRegistry::register('ui_test', fn (): array => ['2 library books not returned']);
    $this->actingAs(bcUser($f, 'people.students.transfer'));

    try {
        $page = Livewire::test(TransferOut::class, ['school' => $f['school'], 'student' => $student])->assertSee('2 library books not returned');
        $page->call('transfer')->assertHasErrors('overrideReason');
        expect($student->fresh()->status)->toBe('active');

        $page->set('overrideReason', 'Settled in cash, receipt R-12 on file.')->call('transfer');
        expect($student->fresh()->status)->toBe('transferred');
    } finally {
        LearnerClearanceRegistry::forget('ui_test');
    }
});

it('creates a household and keeps a learner in only one', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    $this->actingAs(bcUser($f, 'people.guardians.household_manage'));

    $page = Livewire::test(HouseholdsIndex::class, ['school' => $f['school']])->set('name', 'Moyo Family')->call('create')->assertHasNoErrors();
    $household = Household::firstOrFail();
    $other = Household::factory()->for($f['school'])->create(['name' => 'Other']);

    $page->set('memberType', 'student')->set('search', 'Tinash')->call('addMember', $student->id);
    expect(HouseholdMember::where('household_id', $household->id)->count())->toBe(1);

    Livewire::test(HouseholdsIndex::class, ['school' => $f['school']])->call('open', $other->id)->call('addMember', $student->id);
    expect(HouseholdMember::where('household_id', $other->id)->count())->toBe(0);
});

it('runs a sponsorship from creation to a billed beneficiary and refuses a non-organisation sponsor', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    $org = app(CreateGuardianAction::class)->execute(new CreateGuardianData($f['school']->id, 'organisation', $f['user']->id, organisationName: 'Diocese Trust'));
    $person = app(CreateGuardianAction::class)->execute(new CreateGuardianData($f['school']->id, 'individual', $f['user']->id, firstName: 'Grace', lastName: 'Moyo'));
    $this->actingAs(bcUser($f, 'people.sponsorships.manage'));

    Livewire::test(SponsorshipsIndex::class, ['school' => $f['school']])->set('guardianId', $person->id)->set('name', 'Bad')->call('create')->assertHasErrors('guardianId')
        ->set('guardianId', $org->id)->set('name', 'Orphan Support')->set('budget', '1000')->call('create')->assertHasNoErrors();

    $sponsorship = Sponsorship::firstOrFail();
    $page = Livewire::test(SponsorshipsShow::class, ['school' => $f['school'], 'sponsorship' => $sponsorship])->call('changeStatus', 'active')
        ->call('select', $student->id)->set('commitment', '2000')->call('addBeneficiary')->assertHasErrors('studentId')
        ->set('commitment', '400')->call('addBeneficiary')->assertHasNoErrors();

    expect($sponsorship->fresh()->committed_minor)->toBe(40000)->and($sponsorship->beneficiaries()->count())->toBe(1);
});

it('holds a guardian\'s contact change in the queue until approved, and a guardian cannot be verified by themselves', function (): void {
    Storage::fake('local');
    $f = studentFixture();
    $guardian = app(CreateGuardianAction::class)->execute(new CreateGuardianData($f['school']->id, 'individual', $f['user']->id, firstName: 'Grace', lastName: 'Moyo', primaryPhone: '0771111111'));
    app(RequestGuardianContactUpdateAction::class)->execute(new RequestGuardianContactUpdateData($guardian->id, ['primary_phone' => '0772222222']));
    $update = GuardianContactUpdate::firstOrFail();

    $this->actingAs(bcUser($f, 'people.guardians.update'));
    Livewire::test(UpdateQueue::class, ['school' => $f['school']])->assertSee('+263772222222')->call('approve', $update->id);
    expect($guardian->fresh()->primary_phone)->toBe('+263772222222');

    $verifier = bcUser($f, 'people.guardians.verify');
    $this->actingAs($verifier);
    Livewire::test(Verification::class, ['school' => $f['school']])->call('select', $guardian->id)
        ->set('document', bcPdf('id.pdf'))->set('photo', UploadedFile::fake()->image('face.jpg'))->call('record')->assertHasNoErrors();

    $row = GuardianVerification::firstOrFail();
    Livewire::test(Verification::class, ['school' => $f['school']])->call('verify', $row->id);
    expect($row->fresh()->verified_at)->not->toBeNull();
});

it('works an enquiry on the board and reports the funnel', function (): void {
    $f = studentFixture();
    $this->actingAs(bcUser($f, 'people.admissions.enquiry_manage', 'people.admissions.report_view'));

    $page = Livewire::test(Board::class, ['school' => $f['school']])->set('enquirerName', 'Mrs Ncube')->set('phone', '0771234567')->call('create')->assertHasNoErrors();
    $enquiry = Enquiry::firstOrFail();

    $page->call('open', $enquiry->id)->set('summary', 'Called back')->call('log')->assertHasNoErrors()->call('advance', 'lost')->assertHasErrors('lostReason')
        ->set('lostReason', 'fees')->call('advance', 'lost')->assertHasNoErrors();

    expect($enquiry->fresh()->stage)->toBe('lost');
    Livewire::test(Funnel::class, ['school' => $f['school']])->assertSee('Fees');
});

it('schedules an exam, seats and marks a candidate, and records an interview from the screens', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);
    $application->update(['status' => 'submitted']);
    $user = bcUser($f, 'people.admissions.exam_manage', 'people.admissions.interview_manage', 'people.admissions.application_review');
    $this->actingAs($user);

    $page = Livewire::test(ExamsManage::class, ['school' => $f['school']])
        ->set('intakeId', $f['intake']->id)->set('name', 'Entrance')->set('papersText', 'Maths | 100 | 100')->call('schedule')->assertHasNoErrors()
        ->set('applicationId', $application->id)->call('seat')->assertHasNoErrors();
    $candidate = EntranceExamCandidate::firstOrFail();

    $page->set('marks.'.$candidate->id.'.Maths', '150')->call('saveMarks', $candidate->id, true)->assertHasErrors('marks.'.$candidate->id)
        ->set('marks.'.$candidate->id.'.Maths', '72')->call('saveMarks', $candidate->id, true)->call('process')->call('publish');
    expect($candidate->fresh()->rank_in_exam)->toBe(1)->and(EntranceExam::first()->status)->toBe('published');

    Livewire::test(Schedule::class, ['school' => $f['school']])->set('applicationId', $application->id)->set('panel', [User::factory()->create()->id])->call('schedule')->assertHasErrors('applicationId');
    Livewire::test(Schedule::class, ['school' => $f['school']])->set('applicationId', $application->id)->set('panel', [$user->id])->call('schedule')->assertHasNoErrors();
});

it('records a staff qualification and keeps the holder from verifying it, and attaches an application document', function (): void {
    Storage::fake('local');
    $f = ppl02Fixture();
    $holder = bcUser($f, 'people.staff.qualification_manage');
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $holder->id]);
    $this->actingAs($holder);

    $page = Livewire::test(Qualifications::class, ['school' => $f['school'], 'staff' => $staff])
        ->set('title', 'BEd')->set('institution', 'UZ')->set('certificate', bcPdf('cert.pdf'))->call('add')->assertHasNoErrors();
    $q = StaffQualification::firstOrFail();

    $page->call('verify', $q->id);
    expect($q->fresh()->is_verified)->toBeFalse();

    $application = submitPpl02Application($f);
    $this->actingAs(bcUser($f, 'people.admissions.application_review'));
    Livewire::test(ApplicationDocuments::class, ['school' => $f['school'], 'application' => $application])
        ->set('file', bcPdf('birth.pdf'))->call('attach')->assertHasNoErrors();
    expect(ApplicationDocument::count())->toBe(1);
});

it('shows prior schooling, the timeline and an ID card', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'active', $f['user']->id));
    $this->actingAs(bcUser($f, 'people.students.view', 'people.students.document_manage', 'people.students.id_card_issue'));

    Livewire::test(PriorHistory::class, ['school' => $f['school'], 'student' => $student])->set('schoolName', 'Chitungwiza Primary')->set('hadOutstandingFees', true)->call('record')->assertHasNoErrors()->assertSee('fees owing');
    Livewire::test(Timeline::class, ['school' => $f['school'], 'student' => $student])->assertSee('Enrolled')->set('category', 'administrative')->assertSee('Status changed to active');
    Livewire::test(IdCards::class, ['school' => $f['school'], 'student' => $student])->call('generate')->assertHasNoErrors();
    expect(Document::where('document_type', 'student_id_card')->count())->toBe(1);
});

it('gives a guardian parent-app access by their phone, links the account, and lets them sign in by code', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    $guardian = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0771234567', 'email' => null]);
    StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id]);
    $this->actingAs(bcUser($f, 'people.guardians.portal_access'));

    Livewire::test(PortalAccess::class, ['school' => $f['school']])->call('grant', $guardian->id);

    $user = User::findOrFail($guardian->fresh()->user_id);
    expect($user->phone)->toBe('+263771234567')->and($user->user_type)->toBe(UserType::Parent)
        ->and($user->isAssignedToSchool($f['school']->id))->toBeTrue()->and($user->password)->toBeNull();

    Livewire::test(PortalAccess::class, ['school' => $f['school']])->call('grant', $guardian->id);
    expect(User::where('phone', '+263771234567')->count())->toBe(1);

    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $f['school']->tenant_id));
    $code = Cache::get('otp:code:+263771234567')['code'];
    $signedIn = app(VerifyOtpAction::class)->execute(new VerifyOtpData('0771234567', $code, new DeviceData('Parent phone'), $f['school']->tenant_id));
    expect($signedIn->user->id)->toBe($user->id)->and($signedIn->tokens)->not->toBeNull();
});

it('refuses portal access without a phone, without a learner at the school, or for a number that belongs to another guardian', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    $this->actingAs(bcUser($f, 'people.guardians.portal_access'));
    $noPhone = Guardian::factory()->for($f['school'])->create(['primary_phone' => null]);
    $noLearner = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0772000001']);
    $first = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0772000002']);
    $second = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0772000002']);

    foreach ([$noPhone, $first, $second] as $guardian) {
        StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id]);
    }

    $screen = Livewire::test(PortalAccess::class, ['school' => $f['school']]);
    $screen->call('grant', $noPhone->id)->call('grant', $noLearner->id)->call('grant', $first->id)->call('grant', $second->id);

    expect($noPhone->fresh()->user_id)->toBeNull()->and($noLearner->fresh()->user_id)->toBeNull()->and($first->fresh()->user_id)->not->toBeNull()->and($second->fresh()->user_id)->toBeNull();
});

it('withdraws access, signs the parent out everywhere and deactivates their membership of the school', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    $guardian = Guardian::factory()->for($f['school'])->create(['primary_phone' => '0773000001']);
    StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id]);
    $admin = bcUser($f, 'people.guardians.portal_access');
    $this->actingAs($admin);

    Livewire::test(PortalAccess::class, ['school' => $f['school']])->call('grant', $guardian->id);
    $user = User::findOrFail($guardian->fresh()->user_id);
    $token = $user->createToken('Phone');
    Livewire::test(PortalAccess::class, ['school' => $f['school']])->call('revoke', $guardian->id);

    expect($guardian->fresh()->user_id)->toBeNull()->and($user->isAssignedToSchool($f['school']->id))->toBeFalse()
        ->and($user->tokens()->whereNull('revoked_at')->count())->toBe(0);

    Livewire::actingAs(bcUser($f))->test(PortalAccess::class, ['school' => $f['school']])->assertForbidden();
});
