<?php

namespace App\Src\Domain;

final class Tenant
{
    public const THEME_LIGHT = 'clean_enterprise';
    public const THEME_DARK = 'dark_ops';

    public function __construct(
        public ?int $id,
        public string $name,
        public string $domain,        // e.g. acme.platform.com
        public string $slug,          // e.g. acme (used to namespace RADIUS usernames)
        public string $themeDefault = self::THEME_LIGHT,
        public ?string $logoUrl = null,
        public string $status = 'active',
    ) {}
}
