<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Kevinbharris1991\LaravelAccessControlSystem\Database\Factories\UserFactory;
use Kevinbharris1991\LaravelAccessControlSystem\Database\Seeders\DatabaseSeeder;
use Kevinbharris1991\LaravelAccessControlSystem\Models\Group;
use Kevinbharris1991\LaravelAccessControlSystem\Models\Permission;
use Kevinbharris1991\LaravelAccessControlSystem\Models\User;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_namespaced_application_and_factory_are_resolved_explicitly(): void
    {
        $this->assertSame('Kevinbharris1991\\LaravelAccessControlSystem\\', $this->app->getNamespace());
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertInstanceOf(UserFactory::class, User::factory());
        $this->assertInstanceOf(User::class, UserFactory::new()->make());

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])->assertSuccessful();
    }

    public function test_seeding_is_repeatable_and_preserves_custom_grants_without_creating_users(): void
    {
        $permission = Permission::create(['slug' => 'custom.read', 'name' => 'Custom read']);
        $group = Group::where('slug', Group::GUEST)->firstOrFail();
        $group->permissions()->attach($permission);
        $counts = [Group::count(), Permission::count(), DB::table('group_permission')->count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, Group::count());
        $this->assertSame($counts, [Group::count(), Permission::count(), DB::table('group_permission')->count()]);
        $this->assertSame(0, User::count());
        $this->assertTrue($group->permissions()->where('slug', 'custom.read')->exists());
    }

    public function test_first_user_is_executive_director_and_later_users_are_guests(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertSame([Group::EXECUTIVE_DIRECTOR], $first->groups()->pluck('slug')->all());
        $this->assertSame([Group::GUEST], $second->groups()->pluck('slug')->all());
        $this->assertTrue(Hash::check('password', $first->password));
        $this->assertSame([Group::GUEST], User::factory()->createQuietly()->groups()->pluck('slug')->all());
    }

    public function test_deleting_all_users_and_reseeding_does_not_reopen_executive_bootstrap(): void
    {
        User::factory()->create()->delete();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([Group::GUEST], User::factory()->create()->groups()->pluck('slug')->all());
    }

    public function test_missing_default_group_rolls_back_user_creation_and_bootstrap_state(): void
    {
        Group::where('slug', Group::EXECUTIVE_DIRECTOR)->delete();

        try {
            User::factory()->create();
            $this->fail('An unseeded installation must not create an ungrouped user.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('php artisan db:seed', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseHas('access_control_state', ['id' => 1, 'first_user_assigned' => false]);
        }

        $this->seed(DatabaseSeeder::class);
        $this->assertSame([Group::EXECUTIVE_DIRECTOR], User::factory()->create()->groups()->pluck('slug')->all());
    }

    public function test_cancelled_user_insert_does_not_consume_first_user_assignment(): void
    {
        User::creating(fn () => false);

        try {
            $this->assertFalse(User::factory()->make()->save());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseHas('access_control_state', ['id' => 1, 'first_user_assigned' => false]);
        } finally {
            User::flushEventListeners();
        }
    }

    public function test_new_groups_and_permissions_work_without_code_changes_and_revocations_are_immediate(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['slug' => 'volunteers', 'name' => 'Volunteers']);
        $permission = Permission::create(['slug' => 'volunteer-roster.read', 'name' => 'Read volunteer roster']);
        $group->permissions()->attach($permission);
        $user->groups()->attach($group);
        $user->load('groups.permissions');

        $this->assertTrue($user->can('permission:volunteer-roster.read'));
        $this->assertTrue($user->can('permission:budgets.read'));
        $this->assertFalse($user->can('permission:does-not-exist'));

        $group->permissions()->detach($permission);
        $this->assertFalse($user->can('permission:volunteer-roster.read'));
        $group->permissions()->attach($permission);
        $user->groups()->detach($group);
        $this->assertFalse($user->can('permission:volunteer-roster.read'));
        $user->groups()->detach();
        $this->assertFalse($user->can('permission:budgets.read'));
    }

    public function test_permission_gate_does_not_override_other_application_gates(): void
    {
        $user = User::factory()->create();
        Gate::define('ordinary-ability', fn (User $user) => true);

        $this->assertTrue($user->can('ordinary-ability'));
        $this->assertFalse($user->can('undefined-ability'));
    }

    #[DataProvider('groupPermissions')]
    public function test_default_groups_have_scoped_grants_and_deny_unrelated_access(
        string $group, string $allowed, string $denied
    ): void {
        $user = User::factory()->create();
        $user->groups()->sync(Group::where('slug', $group)->firstOrFail());

        $this->assertTrue($user->can("permission:$allowed"));
        $this->assertFalse($user->can("permission:$denied"));
    }

    public static function groupPermissions(): array
    {
        return [
            ['executive-director', 'camper-summaries.read', 'health-profiles.read'],
            ['administrative-directors', 'group-memberships.update', 'health-profiles.read'],
            ['medical-nursing-staff', 'medications.delete', 'public-content.update'],
            ['behavioral-support-services', 'behavior-relevant-health.read', 'health-profiles.read'],
            ['accessibility-adaptive-services', 'adaptive-equipment.update', 'billing.read'],
            ['program-directors', 'safety-alerts.read', 'health-profiles.read'],
            ['counselors-dsps', 'assigned-camper-profiles.read', 'master-schedule.update'],
            ['facilities-cabin-maintenance', 'work-orders.assign', 'health-profiles.read'],
            ['housekeeping', 'sanitation-roster.update', 'assigned-camper-profiles.read'],
            ['catering-dietary-support', 'dietary-dashboard.read', 'behavior-relevant-health.read'],
            ['finance-accounting', 'billing.delete', 'health-profiles.read'],
            ['guest', 'own-health-disclosures.update', 'health-profiles.read'],
        ];
    }

    public function test_routes_reject_unauthenticated_requests(): void
    {
        foreach (['/account/access', '/admin/access', '/examples/billing', '/examples/clinical', '/examples/reservations'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_http_basic_authentication_checks_actual_credentials(): void
    {
        $user = User::factory()->create();

        $this->withBasicAuth($user->email, 'password')
            ->getJson('/account/access')
            ->assertOk()
            ->assertJsonPath('groups', ['executive-director']);
    }

    public function test_http_basic_authentication_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->withBasicAuth($user->email, 'incorrect')
            ->getJson('/account/access')->assertUnauthorized();
    }

    public function test_authentication_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->getJson('/account/access')->assertUnauthorized();
        }

        $this->getJson('/account/access')->assertStatus(429);
    }

    public function test_permission_middleware_stops_execution_before_the_handler_runs(): void
    {
        $executed = false;
        Route::get('/protected-test', function () use (&$executed) {
            $executed = true;

            return response()->noContent();
        })->middleware(['auth.basic', 'can:permission:health-profiles.read']);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/protected-test')->assertForbidden();
        $this->assertFalse($executed);

        $user->groups()->attach(Group::where('slug', 'medical-nursing-staff')->firstOrFail());
        $this->getJson('/protected-test')->assertNoContent();
        $this->assertTrue($executed);
    }

    public function test_protected_examples_and_catalog_respect_memberships(): void
    {
        User::factory()->create();
        $guest = User::factory()->create();

        $this->actingAs($guest)->getJson('/account/access')->assertOk()->assertJsonPath('groups', ['guest']);
        $this->getJson('/examples/reservations')->assertOk();
        $this->getJson('/examples/clinical')->assertForbidden();
        $this->getJson('/examples/billing')->assertForbidden();
        $this->getJson('/admin/access')->assertForbidden();

        $guest->groups()->sync(Group::where('slug', 'finance-accounting')->firstOrFail());
        $this->getJson('/examples/billing')->assertOk()->assertJsonPath('feature', 'billing');
        $this->getJson('/examples/clinical')->assertForbidden();

        $guest->groups()->sync(Group::where('slug', 'administrative-directors')->firstOrFail());
        $this->getJson('/admin/access')->assertOk()->assertJsonCount(12, 'groups');
    }

    public function test_catalog_requires_both_permissions(): void
    {
        $user = User::factory()->create();
        $group = Group::where('slug', Group::GUEST)->firstOrFail();
        $group->permissions()->attach(Permission::where('slug', 'groups.read')->firstOrFail());
        $user->groups()->sync($group);

        $this->actingAs($user)->getJson('/admin/access')->assertForbidden();
    }

    public function test_user_command_validates_input_and_assigns_membership(): void
    {
        $password = fake()->password(16, 20);
        $this->artisan('access:create-user', ['name' => 'Camp Owner', 'email' => 'owner@example.test'])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('Created owner@example.test in Executive Director.')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame([Group::EXECUTIVE_DIRECTOR], $user->groups()->pluck('slug')->all());

        $this->artisan('access:create-user', ['name' => 'Duplicate', 'email' => 'owner@example.test'])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_command_rejects_invalid_email_and_unconfirmed_short_password(): void
    {
        $this->artisan('access:create-user', ['name' => 'Invalid', 'email' => 'not-an-email'])
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
