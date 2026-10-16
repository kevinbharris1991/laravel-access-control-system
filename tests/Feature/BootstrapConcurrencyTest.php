<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Kevinbharris1991\LaravelAccessControlSystem\Database\Seeders\DatabaseSeeder;
use Kevinbharris1991\LaravelAccessControlSystem\Models\Group;
use Kevinbharris1991\LaravelAccessControlSystem\Models\User;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BootstrapConcurrencyTest extends TestCase
{
    public function test_concurrent_sqlite_creations_serialize_bootstrap(): void
    {
        if (! function_exists('proc_open') || ! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Requires subprocesses and PDO SQLite.');
        }

        $database = tempnam(sys_get_temp_dir(), 'access-bootstrap-');
        $processes = [];
        config(['database.connections.bootstrap_test' => array_merge(
            config('database.connections.sqlite'),
            ['database' => $database, 'url' => null, 'busy_timeout' => 10000],
        )]);
        DB::setDefaultConnection('bootstrap_test');

        try {
            $this->artisan('migrate', ['--database' => 'bootstrap_test', '--force' => true])->assertSuccessful();
            $this->seed(DatabaseSeeder::class);

            DB::beginTransaction();
            DB::table('access_control_state')->where('id', 1)->update(['id' => 1]);

            $code = <<<'PHP'
                require $argv[1].'/vendor/autoload.php';
                $app = require $argv[1].'/bootstrap/app.php';
                $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                config([
                    'database.default' => 'sqlite',
                    'database.connections.sqlite.database' => $argv[2],
                    'database.connections.sqlite.url' => null,
                    'database.connections.sqlite.busy_timeout' => 10000,
                ]);
                \Illuminate\Support\Facades\DB::purge('sqlite');
                echo "READY\n";
                $user = \Kevinbharris1991\LaravelAccessControlSystem\Models\User::create([
                    'name' => 'Concurrent signup',
                    'email' => $argv[3],
                    'password' => bin2hex(random_bytes(16)),
                ]);
                echo $user->groups()->sole()->slug;
                PHP;

            foreach (['one@example.test', 'two@example.test'] as $email) {
                $process = new Process([PHP_BINARY, '-r', $code, base_path(), $database, $email], base_path());
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }

            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')));
                $this->assertTrue($process->isRunning(), $process->getErrorOutput());
            }

            DB::commit();

            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            }

            $this->assertSame(2, User::count());
            $this->assertSame(1, User::whereHas('groups', fn ($query) => $query->where('slug', Group::EXECUTIVE_DIRECTOR))->count());
            $this->assertSame(1, User::whereHas('groups', fn ($query) => $query->where('slug', Group::GUEST))->count());
            $this->assertDatabaseCount('group_user', 2);
            $this->assertDatabaseHas('access_control_state', ['id' => 1, 'first_user_assigned' => true]);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
            DB::purge('bootstrap_test');
            unlink($database);
        }
    }
}
