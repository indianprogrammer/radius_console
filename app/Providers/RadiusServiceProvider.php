<?php

namespace App\Providers;

use App\Src\Adapters\Persistence\EloquentBandwidthProfileRepository;
use App\Src\Adapters\Persistence\EloquentNasRepository;
use App\Src\Adapters\Persistence\EloquentPlanRepository;
use App\Src\Adapters\Persistence\EloquentSubscriberRepository;
use App\Src\Adapters\Persistence\EloquentTenantRepository;
use App\Src\Adapters\Radius\HttpRadiusAdapter;
use App\Src\Ports\BandwidthProfileRepository;
use App\Src\Ports\NasRepository;
use App\Src\Ports\PlanRepository;
use App\Src\Ports\RadiusClient;
use App\Src\Ports\SubscriberRepository;
use App\Src\Ports\TenantRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Composition root (Clean/Hexagonal). Wires outer-edge adapters into the
 * application's port interfaces. No other layer references concrete adapters.
 */
final class RadiusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RadiusClient::class, fn() => new HttpRadiusAdapter());

        $this->app->bind(TenantRepository::class, EloquentTenantRepository::class);
        $this->app->bind(SubscriberRepository::class, EloquentSubscriberRepository::class);
        $this->app->bind(PlanRepository::class, EloquentPlanRepository::class);
        $this->app->bind(BandwidthProfileRepository::class, EloquentBandwidthProfileRepository::class);
        $this->app->bind(NasRepository::class, EloquentNasRepository::class);
    }

    public function boot(): void
    {
        // ensures config is published/loaded
        $this->mergeConfigFrom(__DIR__ . '/../config/radius.php', 'radius');
    }
}
