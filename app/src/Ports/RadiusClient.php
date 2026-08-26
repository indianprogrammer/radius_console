<?php

namespace App\Src\Ports;

/**
 * Port for the EXTERNAL RADIUS management server (single-tenant, JWT auth).
 * See SRD §4.2 (validated 2026-08-26 against http://127.0.0.1:8001/api/manual).
 *
 * The RADIUS core has NO tenant concept — isolation is this platform's job.
 * Therefore `username` values passed here MUST already be tenant-namespaced
 * (e.g. "acme_johndoe"); the adapter does not re-namespace. (SRD §4.1.1)
 */
interface RadiusClient
{
    /** Create a subscriber/user on the RADIUS server. Returns ['id'=>int,'username'=>string]. */
    public function createUser(array $payload): array;

    /** Update allowed fields (email, static_ip, status, plan_id, ...). */
    public function updateUser(int $id, array $payload): array;

    public function deleteUser(int $id): array;

    /** @return array list of users */
    public function listUsers(): array;

    public function getUser(int $id): ?array;

    /** Create a plan/profile. Returns ['id'=>int,'name'=>string]. */
    public function createPlan(array $payload): array;

    public function listPlans(): array;

    /** Register a NAS device. */
    public function createNas(array $payload): array;

    public function listNas(): array;

    /** Active sessions. */
    public function listSessions(): array;

    public function getSession(string $sessionId): ?array;

    /** PoD — disconnect a single session (Disconnect-Request). */
    public function disconnectSession(string $sessionId): array;

    /** CoA — change bandwidth for a session (MikroTik-Rate-Limit). */
    public function setSessionBandwidth(string $sessionId, int $uploadMbps, int $downloadMbps): array;

    /** Disconnect all sessions for a user. */
    public function disconnectUser(string $username): array;

    /** Accounting records (?limit,&offset). */
    public function listAccounting(int $limit = 100, int $offset = 0): array;

    /** Recent auth logs. */
    public function listAuthLogs(int $limit = 100): array;

    /** Health probe. */
    public function health(): array;
}
