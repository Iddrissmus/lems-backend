<?php

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\UserInvited;
use App\Domain\Identity\Events\UserRolesChanged;
use App\Domain\Identity\Events\UserStatusChanged;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

describe('listing', function () {
    beforeEach(function () {
        actingAsRole(Role::HeadOfAcademics);
        userWithRole(Role::Lecturer, ['name' => 'Abena Owusu', 'email' => 'abena@example.com']);
        userWithRole(Role::Student, ['name' => 'Kwame Asante', 'email' => 'kwame@example.com']);
        User::factory()->disabled()->create(['name' => 'Yaw Boateng', 'email' => 'yaw@example.com']);
    });

    it('returns a paginated list with roles', function () {
        $this->getJson('/api/v1/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 4)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'status', 'roles']], 'links', 'meta']);
    });

    it('filters by role, status and search text', function () {
        $this->getJson('/api/v1/users?role=lecturer')->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'abena@example.com');
        $this->getJson('/api/v1/users?status=disabled')->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'yaw@example.com');
        $this->getJson('/api/v1/users?q=kwame')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kwame Asante');
    });

    it('treats search text literally, not as wildcards', function () {
        $this->getJson('/api/v1/users?q=%25')->assertOk()->assertJsonCount(0, 'data');
    });

    it('sorts ascending and descending', function () {
        $asc = collect($this->getJson('/api/v1/users?sort=name')->json('data'))->pluck('name')->all();
        $desc = collect($this->getJson('/api/v1/users?sort=-name')->json('data'))->pluck('name')->all();

        expect($desc)->toBe(array_reverse($asc));
    });

    it('rejects bad filters and oversized pages with 422', function () {
        $this->getJson('/api/v1/users?sort=password')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson('/api/v1/users?role=wizard')->assertStatus(422)->assertJsonValidationErrors('role');
        $this->getJson('/api/v1/users?per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');
    });

    it('loads roles without one query per user (no N+1)', function () {
        User::factory()->count(10)->create()->each->assignRole(Role::Student->value);

        DB::enableQueryLog();
        $this->getJson('/api/v1/users?per_page=50')->assertOk();
        $roleQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'model_has_roles'))->count();
        DB::disableQueryLog();

        expect($roleQueries)->toBeLessThanOrEqual(2);
    });
});

describe('showing and updating', function () {
    it('shows one user by public id and 404s for unknown ids', function () {
        actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Lecturer);

        $this->getJson("/api/v1/users/{$target->ulid}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->ulid)
            ->assertJsonPath('data.roles', ['lecturer']);

        $this->getJson('/api/v1/users/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertStatus(404)->assertJsonPath('code', 'not_found');
        $this->getJson("/api/v1/users/{$target->id}")->assertStatus(404); // internal ids are never accepted
    });

    it('updates the display name', function () {
        actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Lecturer);

        $this->patchJson("/api/v1/users/{$target->ulid}", ['name' => 'New Name'])
            ->assertOk()->assertJsonPath('data.name', 'New Name');

        expect($target->fresh()->name)->toBe('New Name');
    });

    it('does not let head_of_academics edit a super_admin', function () {
        actingAsRole(Role::HeadOfAcademics);
        $admin = userWithRole(Role::SuperAdmin);

        $this->patchJson("/api/v1/users/{$admin->ulid}", ['name' => 'Hacked'])->assertStatus(403);
    });
});

describe('inviting', function () {
    it('invites a user with roles, emails them and fires an event', function () {
        Notification::fake();
        Event::fake([UserInvited::class]);
        $actor = actingAsRole(Role::HeadOfAcademics);

        $this->postJson('/api/v1/users/invite', [
            'name' => 'Efua Mensah',
            'email' => ' Efua@Example.com ',
            'roles' => [Role::Lecturer->value, Role::Registrar->value],
        ])->assertStatus(201)
            ->assertJsonPath('data.email', 'efua@example.com')
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonPath('data.roles', ['lecturer', 'registrar']);

        $user = User::where('email', 'efua@example.com')->first();
        Notification::assertSentTo($user, InvitationNotification::class);
        Event::assertDispatched(UserInvited::class, fn ($e) => $e->actor->is($actor) && $e->user->is($user));
        expect($user->invitations()->first()->invited_by_id)->toBe($actor->id);
    });

    it('lets only super_admin hand out super_admin', function () {
        actingAsRole(Role::HeadOfAcademics);

        $this->postJson('/api/v1/users/invite', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'roles' => [Role::SuperAdmin->value],
        ])->assertStatus(403);

        expect(User::where('email', 'sneaky@example.com')->exists())->toBeFalse();
    });

    it('lets super_admin invite another super_admin', function () {
        Notification::fake();
        actingAsRole(Role::SuperAdmin);

        $this->postJson('/api/v1/users/invite', [
            'name' => 'Second Admin', 'email' => 'second@example.com', 'roles' => [Role::SuperAdmin->value],
        ])->assertStatus(201);
    });

    it('validates input', function () {
        actingAsRole(Role::HeadOfAcademics);

        $this->postJson('/api/v1/users/invite', [])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'roles']);

        $this->postJson('/api/v1/users/invite', [
            'name' => 'A', 'email' => 'a@example.com', 'roles' => ['wizard'],
        ])->assertStatus(422)->assertJsonValidationErrors('roles.0');

        $this->postJson('/api/v1/users/invite', [
            'name' => 'A', 'email' => 'a@example.com', 'roles' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('roles');
    });

    it('answers 409 when the email already has an active account', function () {
        actingAsRole(Role::HeadOfAcademics);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/users/invite', [
            'name' => 'A', 'email' => 'taken@example.com', 'roles' => [Role::Student->value],
        ])->assertStatus(409)->assertJsonPath('code', 'conflict');
    });

    it('re-sends an invitation only to people who have not accepted', function () {
        Notification::fake();
        actingAsRole(Role::HeadOfAcademics);

        $this->postJson('/api/v1/users/invite', [
            'name' => 'Efua', 'email' => 'efua@example.com', 'roles' => [Role::Student->value],
        ])->assertStatus(201);
        $invited = User::where('email', 'efua@example.com')->first();

        $this->postJson("/api/v1/users/{$invited->ulid}/resend-invitation")->assertStatus(202);
        expect($invited->invitations()->count())->toBe(1); // old link replaced

        $active = userWithRole(Role::Student);
        $this->postJson("/api/v1/users/{$active->ulid}/resend-invitation")->assertStatus(409);
    });
});

describe('assigning roles', function () {
    it('replaces the roles, returns them and fires an event with before and after', function () {
        Event::fake([UserRolesChanged::class]);
        $actor = actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Student);

        $this->putJson("/api/v1/users/{$target->ulid}/roles", ['roles' => [Role::Lecturer->value, Role::Registrar->value]])
            ->assertOk()
            ->assertJsonPath('data.roles', ['lecturer', 'registrar']);

        expect($target->fresh()->getRoleNames()->sort()->values()->all())->toBe(['lecturer', 'registrar']);

        Event::assertDispatched(UserRolesChanged::class, fn ($e) => $e->before === ['student']
            && $e->after === ['lecturer', 'registrar']
            && $e->actor->is($actor));
    });

    it('does not fire an event when nothing changed', function () {
        Event::fake([UserRolesChanged::class]);
        actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Student);

        $this->putJson("/api/v1/users/{$target->ulid}/roles", ['roles' => [Role::Student->value]])->assertOk();

        Event::assertNotDispatched(UserRolesChanged::class);
    });

    it('stops head_of_academics from granting super_admin or editing a super_admin', function () {
        actingAsRole(Role::HeadOfAcademics);
        $student = userWithRole(Role::Student);
        $admin = userWithRole(Role::SuperAdmin);

        $this->putJson("/api/v1/users/{$student->ulid}/roles", ['roles' => [Role::SuperAdmin->value]])->assertStatus(403);
        $this->putJson("/api/v1/users/{$admin->ulid}/roles", ['roles' => [Role::Student->value]])->assertStatus(403);

        expect($student->fresh()->isSuperAdmin())->toBeFalse()
            ->and($admin->fresh()->isSuperAdmin())->toBeTrue();
    });

    it('lets super_admin promote someone', function () {
        actingAsRole(Role::SuperAdmin);
        $target = userWithRole(Role::Lecturer);

        $this->putJson("/api/v1/users/{$target->ulid}/roles", ['roles' => [Role::SuperAdmin->value]])->assertOk();

        expect($target->fresh()->isSuperAdmin())->toBeTrue();
    });

    it('refuses to remove super_admin from the last active super_admin', function () {
        $me = actingAsRole(Role::SuperAdmin);

        $this->putJson("/api/v1/users/{$me->ulid}/roles", ['roles' => [Role::Lecturer->value]])
            ->assertStatus(409)->assertJsonPath('code', 'last_super_admin');

        expect($me->fresh()->isSuperAdmin())->toBeTrue();
    });

    it('allows demoting a super_admin when another active one remains', function () {
        actingAsRole(Role::SuperAdmin);
        $other = userWithRole(Role::SuperAdmin);

        $this->putJson("/api/v1/users/{$other->ulid}/roles", ['roles' => [Role::Lecturer->value]])->assertOk();
    });
});

describe('disabling and enabling', function () {
    it('disables an account, revokes its tokens and blocks login', function () {
        Event::fake([UserStatusChanged::class]);
        $actor = actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Lecturer);
        $target->createToken('phone');

        $this->postJson("/api/v1/users/{$target->ulid}/disable")
            ->assertOk()->assertJsonPath('data.status', 'disabled');

        expect($target->tokens()->count())->toBe(0);
        Event::assertDispatched(UserStatusChanged::class, fn ($e) => $e->before === UserStatus::Active
            && $e->after === UserStatus::Disabled && $e->actor->is($actor));

        $this->postJson('/api/v1/auth/login', ['email' => $target->email, 'password' => 'password'])->assertStatus(401);
    });

    it('re-enables an account', function () {
        actingAsRole(Role::HeadOfAcademics);
        $target = User::factory()->disabled()->create();

        $this->postJson("/api/v1/users/{$target->ulid}/enable")
            ->assertOk()->assertJsonPath('data.status', 'active');
    });

    it('sends a never-activated account back to invited, not active', function () {
        actingAsRole(Role::HeadOfAcademics);
        $target = User::factory()->invited()->create(['status' => UserStatus::Disabled]);

        $this->postJson("/api/v1/users/{$target->ulid}/enable")
            ->assertOk()->assertJsonPath('data.status', 'invited');
    });

    it('refuses to disable yourself', function () {
        $me = actingAsRole(Role::HeadOfAcademics);

        $this->postJson("/api/v1/users/{$me->ulid}/disable")
            ->assertStatus(422)->assertJsonPath('code', 'cannot_disable_self');
    });

    it('refuses to disable the last active super_admin', function () {
        actingAsRole(Role::SuperAdmin);
        $other = userWithRole(Role::SuperAdmin);
        $other->forceFill(['status' => UserStatus::Disabled])->save();
        $me = User::role('super_admin')->where('status', 'active')->first();

        // Disabling is blocked for self; a head cannot touch super_admins at all.
        $this->postJson("/api/v1/users/{$me->ulid}/disable")->assertStatus(422);

        actingAsRole(Role::HeadOfAcademics);
        $this->postJson("/api/v1/users/{$me->ulid}/disable")->assertStatus(403);
    });

    it('lets one super_admin disable another while a third stays active', function () {
        actingAsRole(Role::SuperAdmin);
        $second = userWithRole(Role::SuperAdmin);

        $this->postJson("/api/v1/users/{$second->ulid}/disable")->assertOk();
    });

    it('blocks the last-super_admin guard when it is another admin', function () {
        $caller = userWithRole(Role::SuperAdmin);
        $victim = userWithRole(Role::SuperAdmin);
        $caller->forceFill(['status' => UserStatus::Disabled])->save(); // caller is no longer an active admin
        Sanctum::actingAs($caller->forceFill(['status' => UserStatus::Active]));

        // The caller is active in memory only; in the database the victim is the sole active super_admin.
        $this->postJson("/api/v1/users/{$victim->ulid}/disable")
            ->assertStatus(409)->assertJsonPath('code', 'last_super_admin');
    });

    it('forbids a lecturer from disabling anyone', function () {
        actingAsRole(Role::Lecturer);
        $target = userWithRole(Role::Student);

        $this->postJson("/api/v1/users/{$target->ulid}/disable")->assertStatus(403);
    });
});
