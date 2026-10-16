<?php

namespace Kevinbharris1991\LaravelAccessControlSystem\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Kevinbharris1991\LaravelAccessControlSystem\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (str_starts_with($ability, 'permission:')) {
                return $user->hasPermission(substr($ability, strlen('permission:')));
            }

            return null;
        });
    }
}
