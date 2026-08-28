<?php

namespace App\Console\Commands;

use App\Models\Nas as NasModel;
use App\Src\Ports\NasRepository;
use App\Src\Ports\RadiusClient;
use Illuminate\Console\Command;

/**
 * Reconciliation guardrail: the live system of record is the external RADIUS
 * server. CRUD is fail-closed (every change is pushed to RADIUS first), but
 * this command additionally re-pushes any local NAS whose payload differs from
 * RADIUS — closing the rare window where a local write passed but a later
 * change drifted (e.g. manual DB edit, restore). SRD §4.1 / §4.2.
 */
final class ReconcileNas extends Command
{
    protected $signature = 'nas:reconcile {--tenant= : reconcile only this tenant id}';
    protected $description = 'Re-push local NAS records to RADIUS so they can never drift out of sync';

    public function handle(NasRepository $nas, RadiusClient $radius): int
    {
        $tenant = $this->option('tenant');
        $ids = $tenant ? [$tenant] : NasModel::distinct()->pluck('tenant_id')->all();

        $pushed = 0;
        $failed = 0;
        foreach ($ids as $tid) {
            foreach ($nas->listByTenant((string) $tid) as $local) {
                // Skip records with no RADIUS id (e.g. seeds created pre-migration).
                if ($local->radiusNasId === null) {
                    continue;
                }
                $payload = [
                    'nas_ip' => $local->nasIp,
                    'shared_secret' => $local->sharedSecret,
                    'nas_identifier' => $local->nasIdentifier ?? $local->nasIp,
                    'type' => $local->type ?? null,
                    'api_enabled' => $local->apiEnabled ? 1 : 0,
                    'api_host' => $local->apiHost ?? null,
                    'api_port' => $local->apiPort ?? null,
                    'api_username' => $local->apiUsername ?? null,
                    'api_password' => $local->apiPassword ?? null,
                    'description' => $local->description ?? null,
                ];
                try {
                    $radius->updateNas($local->radiusNasId, $payload);
                    $pushed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("NAS #{$local->id} (radius {$local->radiusNasId}) drift push failed: {$e->getMessage()}");
                }
            }
        }

        $this->info("Reconciled: pushed=$pushed failed=$failed");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
