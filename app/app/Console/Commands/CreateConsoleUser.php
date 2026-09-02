<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Provision an account for any supported console role. */
final class CreateConsoleUser extends Command
{
    protected $signature = 'console:user
        {email : Login email}
        {--username= : Short login username; derived from email when omitted}
        {--name= : Display name}
        {--role=staff : superadmin, admin, franchise, staff or subscriber}
        {--tenant=127.0.0.1 : Tenant domain}
        {--password= : Password; prompted when omitted}
        {--force : Reset an existing account instead of refusing}';

    protected $description = 'Create or reset a tenant-scoped console user';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = (string) $this->option('role');
        $tenant = Tenant::where('domain', $this->option('tenant'))->first();

        if (!$tenant) {
            $this->error('Tenant not found: ' . $this->option('tenant'));
            return self::FAILURE;
        }

        if (!array_key_exists($role, User::ROLES)) {
            $this->error('Unknown role. Use: ' . implode(', ', array_keys(User::ROLES)));
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user && !$this->option('force')) {
            $this->error('A user with that email already exists. Use --force to reset it.');
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 characters)'));
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        $user ??= new User();
        $username = strtolower(trim((string) ($this->option('username') ?: (strstr($email, '@', true) ?: $email))));
        $user->forceFill([
            'tenant_id' => $tenant->id,
            'name' => $this->option('name') ?: ucwords(str_replace(['.', '_', '-'], ' ', strstr($email, '@', true) ?: $email)),
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Reset') . " {$role} account {$email} for {$tenant->domain}.");
        return self::SUCCESS;
    }
}
