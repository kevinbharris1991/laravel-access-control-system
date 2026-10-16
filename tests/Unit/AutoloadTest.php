<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    public function test_production_autoloading_does_not_export_host_application_namespaces(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $prefix = 'Kevinbharris1991\\LaravelAccessControlSystem\\';

        foreach (array_keys($composer['autoload']['psr-4']) as $namespace) {
            $this->assertStringStartsWith($prefix, $namespace);
        }

        $loader = require $root.'/vendor/autoload.php';

        foreach ([
            'Database\\Seeders\\DatabaseSeeder' => 'database/seeders/DatabaseSeeder.php',
            'Database\\Factories\\UserFactory' => 'database/factories/UserFactory.php',
            'Http\\Controllers\\Controller' => 'app/Http/Controllers/Controller.php',
            'Models\\User' => 'app/Models/User.php',
            'Providers\\AppServiceProvider' => 'app/Providers/AppServiceProvider.php',
        ] as $class => $path) {
            $file = $loader->findFile($prefix.$class);

            $this->assertNotFalse($file);
            $this->assertSame(realpath($root.'/'.$path), realpath($file));
        }
    }
}
