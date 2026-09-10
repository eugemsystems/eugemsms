<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\LinkAccountAction;
use Modules\Core\Domain\DataObjects\Auth\LinkAccountData;
use Modules\Core\Models\School;
use Modules\Core\Models\UserAccountLink;

it('links a user to a learner record', function (): void {
    $school = School::factory()->create();
    $parent = User::factory()->create();

    $link = app(LinkAccountAction::class)->execute(new LinkAccountData($school->id, $parent->id, 'student', 501));

    expect($link->is_active)->toBeTrue();
    $this->assertDatabaseHas('user_account_links', [
        'school_id' => $school->id,
        'user_id' => $parent->id,
        'linked_type' => 'student',
        'linked_id' => 501,
    ]);
});

it('is idempotent when the same link already exists', function (): void {
    $school = School::factory()->create();
    $parent = User::factory()->create();

    app(LinkAccountAction::class)->execute(new LinkAccountData($school->id, $parent->id, 'student', 501));
    app(LinkAccountAction::class)->execute(new LinkAccountData($school->id, $parent->id, 'student', 501));

    expect(UserAccountLink::withoutGlobalScopes()->where('school_id', $school->id)->count())->toBe(1);
});
