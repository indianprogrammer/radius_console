<?php

namespace App\Src\Adapters\Radius;

use App\Src\Ports\RadiusClient;
use Illuminate\Support\Facades\Http;

/**
 * Concrete adapter for the EXTERNAL RADIUS management server.
 * Validated against live /api/manual (SRD §4.2, 2026-08-26).
 *
 * Responsibilities:
 *  - Obtain + cache a Bearer JWT via POST /api/auth/login (12h validity).
 *  - Auto-refresh on 401.
 *  - Retry-with-backoff (SRD §4.1 resilience).
 *  - NEVER re-namespace usernames (caller passes tenant-namespaced value).
 */
final class HttpRadiusAdapter implements RadiusClient
{
    private ?string $token = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private ?string $baseUrl = null,
        private ?string $apiUser = null,
        private ?string $apiPass = null,
        private int $timeoutSec = 5,
        private int $retries = 3,
        private int $retryDelayMs = 300,
    ) {
        $this->baseUrl = $baseUrl ?? config('radius.base_url', 'http://127.0.0.1:8001/api');
        $this->apiUser = $apiUser ?? config('radius.username', 'manoj');
        $this->apiPass = $apiPass ?? config('radius.password', 'test1');
    }

    // ---- Auth -------------------------------------------------------------
    private function token(): string
    {
        if ($this->token && time() < $this->tokenExpiresAt - 60) {
            return $this->token;
        }
        $resp = Http::timeout($this->timeoutSec)
            ->post($this->baseUrl . '/auth/login', [
                'username' => $this->apiUser,
                'password' => $this->apiPass,
            ]);
        if (!$resp->successful()) {
            throw new \RuntimeException('RADIUS auth failed: ' . $resp->body());
        }
        $body = $resp->json();
        $this->token = $body['token'] ?? throw new \RuntimeException('No token in RADIUS auth response');
        // Default 12h if expires_in missing; parse "12h" if present.
        $ttl = 12 * 3600;
        if (isset($body['expires_in'])) {
            $ttl = ctype_digit($body['expires_in']) ? (int) $body['expires_in']
                : ($this->parseDuration($body['expires_in']) ?? $ttl);
        }
        $this->tokenExpiresAt = time() + $ttl;
        return $this->token;
    }

    private function parseDuration(string $d): ?int
    {
        if (preg_match('/^(\d+)\s*h$/', $d, $m)) {
            return (int) $m[1] * 3600;
        }
        return null;
    }

    // ---- HTTP helper with retry + 401 refresh ----------------------------
    private function call(string $method, string $path, array $body = []): array
    {
        $attempt = 0;
        $lastErr = null;
        while ($attempt < $this->retries) {
            $attempt++;
            $req = Http::timeout($this->timeoutSec)
                ->withToken($this->token())
                ->acceptJson();
            $resp = match (strtoupper($method)) {
                'GET' => $req->get($this->baseUrl . $path),
                'DELETE' => $req->delete($this->baseUrl . $path),
                default => $req->withBody(json_encode($body), 'application/json')->$method($this->baseUrl . $path),
            };
            if ($resp->status() === 401 && $attempt < $this->retries) {
                $this->token = null; // force re-auth
                continue;
            }
            if ($resp->successful()) {
                return $resp->json() ?? [];
            }
            $lastErr = $resp->status() . ' ' . $resp->body();
            if ($resp->serverError()) {
                usleep($this->retryDelayMs * 1000 * $attempt);
                continue;
            }
            throw new \RuntimeException("RADIUS $method $path failed: $lastErr");
        }
        throw new \RuntimeException("RADIUS $method $path failed after retries: $lastErr");
    }

    // ---- RadiusClient implementation -------------------------------------
    public function createUser(array $payload): array { return $this->call('POST', '/users', $payload); }
    public function updateUser(int $id, array $payload): array { return $this->call('PUT', "/users/$id", $payload); }
    public function deleteUser(int $id): array { return $this->call('DELETE', "/users/$id"); }
    public function listUsers(): array { return $this->call('GET', '/users'); }
    public function getUser(int $id): ?array { return $this->call('GET', "/users/$id"); }
    public function createPlan(array $payload): array { return $this->call('POST', '/plans', $payload); }
    public function listPlans(): array { return $this->call('GET', '/plans'); }
    public function getPlan(int $id): ?array { return $this->call('GET', "/plans/$id"); }
    public function updatePlan(int $id, array $payload): array { return $this->call('PUT', "/plans/$id", $payload); }
    public function deletePlan(int $id): array { return $this->call('DELETE', "/plans/$id"); }
    public function createNas(array $payload): array { return $this->call('POST', '/nas', $payload); }
    public function updateNas(int $id, array $payload): array { return $this->call('PUT', "/nas/$id", $payload); }
    public function deleteNas(int $id): array { return $this->call('DELETE', "/nas/$id"); }
    public function getNas(int $id): ?array { return $this->call('GET', "/nas/$id"); }
    public function listNas(): array { return $this->call('GET', '/nas'); }
    public function listSessions(): array { return $this->call('GET', '/sessions'); }
    public function getSession(string $sessionId): ?array { return $this->call('GET', "/sessions/$sessionId"); }
    public function disconnectSession(string $sessionId): array { return $this->call('POST', "/sessions/$sessionId/disconnect"); }
    public function setSessionBandwidth(string $sessionId, int $uploadMbps, int $downloadMbps): array
    {
        return $this->call('POST', "/sessions/$sessionId/bandwidth", [
            'upload_mbps' => $uploadMbps,
            'download_mbps' => $downloadMbps,
        ]);
    }
    public function disconnectUser(string $username): array { return $this->call('POST', "/sessions/disconnect/user/$username"); }
    public function listAccounting(int $limit = 100, int $offset = 0): array { return $this->call('GET', "/accounting?limit=$limit&offset=$offset"); }
    public function listAuthLogs(int $limit = 100): array { return $this->call('GET', "/auth-logs?limit=$limit"); }
    public function health(): array { return $this->call('GET', '/health'); }
}
