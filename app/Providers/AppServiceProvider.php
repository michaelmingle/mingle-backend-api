<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\PushNotifier;
use App\Models\Connection;
use App\Models\Event;
use App\Models\Report;
use App\Models\User;
use App\Policies\ConnectionPolicy;
use App\Policies\EventPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
use App\Services\Payments\ManualPaymentGateway;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\NullPushNotifier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private array $policies = [
        Connection::class => ConnectionPolicy::class,
        Event::class => EventPolicy::class,
        Report::class => ReportPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        // FCM is only bound once it's enabled AND fully configured; anything
        // short of that -- disabled, no project id, no credentials -- keeps the
        // no-op default so half-configuring this can never throw mid-request.
        $this->app->bind(PushNotifier::class, function ($app) {
            $push = config('mingle.push');

            $configured = ($push['enabled'] ?? false)
                && filled($push['project_id'] ?? null)
                && (filled($push['credentials_json'] ?? null) || filled($push['credentials_path'] ?? null));

            return $configured ? $app->make(FcmPushNotifier::class) : $app->make(NullPushNotifier::class);
        });

        $this->app->bind(PaymentGateway::class, ManualPaymentGateway::class);
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Gate used by the /api/admin routes.
        Gate::define('access-admin', fn (User $user) => $user->isAdmin());
    }
}
