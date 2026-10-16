# laravel-access-control-system

A Laravel 13 / PHP 8.3+ foundation for camp operations access control. Users
belong to one or more groups; groups grant explicit, granular permissions.
No frontend toolchain or third-party authorization package is required.

## Get started

Requires Composer, PHP 8.3+ with PDO SQLite (or a configured MySQL/PostgreSQL
connection), and the standard Laravel PHP extensions.

```sh
composer setup
php artisan access:create-user "Camp Owner" owner@example.test
composer dev
```

`composer setup` installs locked dependencies, creates a local `.env`, generates
the application key, and migrates/seeds SQLite. For another database, configure
`.env` before running setup. Setup is for local development; provision production
secrets separately, set `APP_DEBUG=false`, use HTTPS, and run
`php artisan migrate --seed --seeder="Kevinbharris1991\LaravelAccessControlSystem\Database\Seeders\DatabaseSeeder" --force`
during deployment. Serve only the `public`
directory, never the repository root.

### Upgrading from Laravel 12

Use PHP 8.3 or newer. The project now requires `laravel/framework ^13.17`
and Laravel 13-compatible Tinker 3. Existing checkouts should run
`composer install` to use the updated lockfile.

If another project's root `composer.json` requires this repository as
`dev-main` alongside `laravel/framework ^13.17`, refresh both packages with:

```sh
composer update kevinbharris1991/laravel-access-control-system laravel/framework --with-all-dependencies
```

The `-W` option permits locked dependency changes, but cannot reconcile
incompatible constraints such as `^12.60` and `^13.17`; the package requirement
must support Laravel 13 first.

### Composer class-name collisions

Application classes now use `Kevinbharris1991\LaravelAccessControlSystem\`,
with factories and seeders under its `Database\Factories` and `Database\Seeders`
namespaces. This avoids exporting a second application's `App\` or `Database\`
classes when this repository is installed as a Composer dependency.

In an existing consuming application, update the installed revision and rebuild
the autoloader:

```sh
composer update kevinbharris1991/laravel-access-control-system --with-all-dependencies
composer dump-autoload --optimize
```

Update references to this project's former `App\` and `Database\` classes to the
new namespaces; do not rename the consuming application's own classes.
When seeding this project's tables directly, specify its seeder explicitly:

```sh
php artisan db:seed --class="Kevinbharris1991\LaravelAccessControlSystem\Database\Seeders\DatabaseSeeder"
```

This repository remains an application scaffold, not an auto-configuring Laravel
package: installing it as a dependency does not register its provider, routes,
configuration, or migrations in the consuming application.

The user command prompts for a password (at least 12 characters) and confirmation,
without placing it in shell history. Seeders **never create accounts or default
passwords**. Create the first account through this trusted command before exposing
any future registration endpoint.

The first successfully committed Eloquent account receives **Executive Director**
(the platform's Director group).
Every later user receives **Guest**, including factory and quiet creation.
Insertion, default membership, and a persistent bootstrap marker are transactional.
The marker serializes concurrent creation on row-locking databases and is not
reset when accounts are deleted or seeders rerun. SQLite acquires its write lock
before reading the marker. If database contention exceeds the connection's lock
timeout, retry the entire creation transaction. Do not reset the
marker, use raw/bulk user inserts (which bypass Eloquent), or bypass this model
when implementing registration/imports. Migrations and seeders must run before
creating users; missing default groups fail closed with instructions to reseed.

### Existing databases and registration integrations

Deploy with account creation paused, back up the database, then run the normal
`migrate --seed` deployment command above, **not `migrate:fresh`**. The
`2026_10_16_000001_initialize_access_control_bootstrap` migration consumes
bootstrap if users already exist. It does not assign, reset, or demote any
existing membership, and rolling it back does not reopen bootstrap. A runtime
check under the same bootstrap lock also treats existing users as established
ownership if the marker is still false. Even if all those users are later
deleted, new accounts remain Guests. If no Director is currently assigned,
an operator must review ownership and explicitly grant the appropriate group
through trusted administration; the next public signup is never promoted.

This checkout's supported account-creation entry point is `access:create-user`,
backed by `Kevinbharris1991\LaravelAccessControlSystem\Models\User`. It has no
SecureAccess dependency, Fortify action, or public registration endpoint.
The reported `2024_01_01_000006_create_secure_access_acl_tables` migration is
not present here; do not copy it here or create a parallel Role system.
Installing this scaffold in a SecureAccess host does not automatically connect
the host's user model, registration flow, or ACL tables. That integration must
be reviewed against the host's actual schema and creation paths separately.

Future registration code using this model should validate and explicitly select
only `name`, `email`, and `password`; the model also allowlists these fields for
mass assignment and hashes passwords. Never fill relationship or privilege fields
from public input. Assignment runs only on insertion, so subsequent profile saves
preserve authorized membership changes. Existing authentication and verification
behavior is unchanged.

## Try the protected endpoints

Open `/` for the landing page. The following read-only endpoints accept HTTP
Basic authentication using an account's email/password (HTTPS only outside local
development). They share a limit of 10 requests/minute per IP, applied before
authentication. For example, this prompts for the password:

```sh
curl --user owner@example.test --header 'Accept: application/json' \
    http://localhost:8000/account/access
```

| Endpoint | Required permission |
| --- | --- |
| `/account/access` | Any authenticated account; shows only its own grants |
| `/admin/access` | Both `groups.read` and `permissions.read`; group/permission catalog |
| `/examples/billing` | `billing.read` |
| `/examples/clinical` | `health-profiles.read` |
| `/examples/reservations` | `own-reservations.read` |

Unauthorized requests receive 401; authenticated accounts missing a permission
receive 403 **before the route handler runs**. Example routes return feature
names, not records. There is no registration, login UI, or web-based access
editor yet. HTTP Basic is a minimal scaffold; replace it with your preferred
Laravel authentication flow as the application grows.

## Default groups

`AccessControlSeeder` contains the complete resource/action permission matrix.
Grants distinguish create/read/update/delete, approval, export, assignment, and
other operations instead of a blanket "admin" permission.

| Group slug | Group | Representative grant |
| --- | --- | --- |
| `executive-director` | Executive Director | `camper-summaries.read`, `budgets.update`, `public-content.delete` |
| `administrative-directors` | Administrative Directors | `staff-users.create`, `group-memberships.update` |
| `medical-nursing-staff` | Medical & Nursing Staff | `medications.delete`, `health-profiles.update` |
| `behavioral-support-services` | Behavioral Support Services | `support-plans.update`, `behavior-relevant-health.read` |
| `accessibility-adaptive-services` | Accessibility & Adaptive Services | `adaptive-equipment.update`, `facility-audits.create` |
| `program-directors` | Program Directors | `master-schedule.update`, `safety-alerts.read` |
| `counselors-dsps` | Counselors & DSP's | `assigned-camper-profiles.read`, `incident-flags.create` |
| `facilities-cabin-maintenance` | Facilities & Cabin Maintenance | `work-orders.assign`, `safety-inspections.update` |
| `housekeeping` | Housekeeping | `sanitation-roster.update`, `supply-requests.create` |
| `catering-dietary-support` | Catering & Dietary Support | `dietary-dashboard.read`, `menus.update` |
| `finance-accounting` | Finance & Accounting | `billing.delete`, `payroll.approve` |
| `guest` | Guest | `own-reservations.create`, `own-health-disclosures.update` |

Executive Director is **not a superuser**: its grants follow the specification
and do not include unrestricted clinical records or staff/group administration.
Administrative Directors receive the access-management grants. An operator can
assign that group through Tinker when needed.

Seeders are repeatable and additive: they preserve custom groups, names, and
grants. They do not revoke removed/renamed defaults. Make intentional grant
revocations explicitly through relationships or a reviewed data migration.

## Extending authorization

The reserved `permission:` Gate namespace delegates to the user's groups. Unknown
permissions and users with no grants are denied. Other Gate/policy abilities
continue to behave normally. Permissions are queried live, so membership/grant
revocations take effect on the next check without a cache reset.

Use Laravel's built-in `can` middleware **alongside authentication**:

```php
Route::get('/billing', [BillingController::class, 'index'])
    ->middleware(['auth.basic', 'can:permission:billing.read']);

Gate::authorize('permission:billing.update');
$user->can('permission:billing.read');
```

Multiple `can` middleware entries require all listed grants. Do not use
`Gate::before` to introduce an unrestricted executive bypass.

Use trusted `php artisan tinker` for initial administration:

```php
use Kevinbharris1991\LaravelAccessControlSystem\Models\Group;
use Kevinbharris1991\LaravelAccessControlSystem\Models\Permission;
use Kevinbharris1991\LaravelAccessControlSystem\Models\User;

$user = User::where('email', 'owner@example.test')->firstOrFail();
$admin = Group::where('slug', 'administrative-directors')->firstOrFail();
$user->groups()->syncWithoutDetaching([$admin->id]);

$group = Group::create(['slug' => 'volunteers', 'name' => 'Volunteers']);
$permission = Permission::firstOrCreate(
    ['slug' => 'volunteer-roster.read'],
    ['name' => 'Read volunteer roster'],
);
$group->permissions()->syncWithoutDetaching([$permission->id]);
$user->groups()->syncWithoutDetaching([$group->id]);
```

`attach`/`syncWithoutDetaching` add memberships; `sync` replaces them; `detach`
revokes them. Never accept group IDs or permission grants from public registration
input. Any future web editor needs its own permission checks, validation, CSRF
protection, auditing, and explicit rules against unwanted privilege escalation.
Keep the `executive-director` and `guest` groups available for default assignment.

**Route authorization is not record authorization.** Before implementing real
camper or booking endpoints, add policies/query scopes that enforce ownership
for `own-*` permissions and camper assignment for `assigned-*` permissions.
Separate aggregate reports, safety alerts, dietary views, and behavior-relevant
health projections from full clinical records; a route grant alone must never
expose all fields or all campers. This scaffold stores no camper/clinical data
and does not claim regulatory compliance.

## Verification

After local setup:

```sh
php artisan test --filter=AccessControlTest
php artisan test --filter=BootstrapConcurrencyTest
composer test
vendor/bin/pint --test
composer dump-autoload --optimize --strict-psr --strict-ambiguous
composer audit
```

Tests cover all default groups, permission denial, middleware short-circuiting,
bootstrap assignment/rollback, reseeding, extensibility, and live revocation.
The concurrency test uses two PHP processes and a temporary SQLite database;
it skips when subprocesses or PDO SQLite are unavailable. MySQL/PostgreSQL
row-lock behavior still needs validation on those database servers.
The `User::performInsert` integration keeps membership atomic even when model
events are disabled; retain these regression tests when upgrading Laravel.
