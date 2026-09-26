<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\AttendancePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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
        $this->defineGates();

        // {teacher} only ever resolves guru accounts; an admin's id in the URL is a 404.
        Route::bind('teacher', fn (string $value): User => User::teachers()->findOrFail($value));
    }

    /**
     * Controller-level checks, used on top of the "admin"/"guru" route middleware.
     */
    private function defineGates(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('access-guru', fn (User $user): bool => $user->isGuru());
        Gate::define('manage-teacher', fn (User $user, User $teacher): bool => $user->isAdmin() && $teacher->isGuru());
        Gate::define('take-attendance', [AttendancePolicy::class, 'take']);
    }
}
