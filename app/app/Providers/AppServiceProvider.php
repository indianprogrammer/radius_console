<?php

namespace App\Providers;

use App\Services\ActivityLogger;
use App\Services\AuditObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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
        $this->registerActivityLogging();
    }

    /**
     * Wire up the Logs menu's data sources (SRD §5.0 #10, §9.8).
     *
     * Both halves hang off events rather than call sites: the audit trail must
     * cover every model change, and Login History has to record attempts that
     * never reach a controller (a rejected sign-in is handled entirely inside
     * the guard). `ActivityLogger` already swallows its own storage failures, so
     * a logging problem cannot break an action or a login.
     */
    private function registerActivityLogging(): void
    {
        AuditObserver::register();

        $logger = fn (): ActivityLogger => $this->app->make(ActivityLogger::class);

        Event::listen(Login::class, fn (Login $event) => $logger()->loginSucceeded($event->user, [
            'payload' => ['guard' => $event->guard, 'remember' => $event->remember],
        ]));

        // `Failed::$credentials` carries the submitted password — only the
        // identifier is passed on, and ActivityLogger redacts the rest anyway.
        // The tenant comes from the matched user when there is one; a login for
        // an unknown account falls back to the tenant the request resolved.
        Event::listen(Failed::class, fn (Failed $event) => $logger()->loginFailed(
            $this->identifierFrom($event->credentials),
            $event->user === null ? 'No matching account.' : 'Incorrect password.',
            [
                'tenant_id' => $event->user->tenant_id ?? null,
                'payload'   => ['guard' => $event->guard],
            ],
        ));

        // A lockout is a security signal, not a routine rejection, so it is
        // logged even though a Failed event fired alongside it.
        Event::listen(Lockout::class, fn () => $logger()->loginFailed(
            null,
            'Too many attempts — throttled.',
        ));

        Event::listen(Logout::class, fn (Logout $event) => $logger()->log('login', 'logout', [
            'tenant_id' => $event->user->tenant_id ?? null,
            'user_id' => $event->user?->getKey(),
            'actor'   => $event->user->name ?? $event->user->email ?? null,
            'message' => 'Signed out.',
        ]));
    }

    /**
     * The identifier a failed attempt used. Not hardcoded to `email`: the guard
     * may be configured to authenticate on a username, and a fail log with no
     * subject cannot be acted on.
     */
    private function identifierFrom(array $credentials): ?string
    {
        foreach (['username', 'email', 'name', 'phone'] as $key) {
            if (!empty($credentials[$key]) && is_string($credentials[$key])) {
                return $credentials[$key];
            }
        }

        return null;
    }
}
