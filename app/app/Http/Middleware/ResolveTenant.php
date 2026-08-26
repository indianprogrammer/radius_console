<?php

namespace App\Http\Middleware;

use App\Src\Ports\TenantRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the Host header, loads it, sets the RLS context
 * for PostgreSQL (`SET app.current_tenant`), and shares it with the views
 * for theming. Per SRD §3.1 this is the mandatory isolation guardrail
 * (RLS is defense-in-depth).
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantRepository $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $tenant = $this->tenants->findByDomain($host)
            ?? $this->tenants->findByDomain(rtrim($host, '.'));

        abort_if($tenant === null, 404, 'Unknown tenant for host: ' . $host);

        // PostgreSQL RLS context (no-op on SQLite during local boot).
        if (config('database.default') === 'pgsql') {
            \DB::statement("SET app.current_tenant = ?", [$tenant->id]);
        }

        // Share for Blade theming (logo/theme/name).
        view()->share('tenant', $tenant);

        return $next($request);
    }
}
