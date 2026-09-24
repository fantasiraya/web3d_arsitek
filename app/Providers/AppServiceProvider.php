<?php

namespace App\Providers;

use App\Actions\Auth\MatchInvitedClientEmailAction;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SystemSettingRepository::class, fn () => new SystemSettingRepository);

        // Custom login response to redirect super admin to admin panel
        $this->app->singleton(
            LoginResponse::class,
            \App\Http\Responses\LoginResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Eager load roles for all User queries to ensure Spatie Permission works
        \App\Domains\Auth\Models\User::retrieved(function ($user) {
            $user->loadMissing('roles');
        });

        Event::listen(Login::class, function (Login $event) {
            app(MatchInvitedClientEmailAction::class)->execute($event->user); // @phpstan-ignore-line
        });

        Event::listen(Registered::class, function (Registered $event) {
            app(MatchInvitedClientEmailAction::class)->execute($event->user); // @phpstan-ignore-line
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
