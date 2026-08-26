<?php

if (!function_exists('tenant_id')) {
    /**
     * Returns the resolved tenant id (set by ResolveTenant middleware).
     * SRD §3.1 — every tenant-scoped query is keyed on this value.
     */
    function tenant_id(): string
    {
        return optional(view()->shared('tenant'))->id ?? '0';
    }
}
