<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Settings — the tenant-level preference screens (SRD §5.0 #2 "Settings").
 *
 * One page per section, driven entirely by `Setting::SCHEMA`; adding a
 * preference needs no change here. Only the keys actually posted are saved, so
 * a section form can never blank out another section's values.
 *
 * The "Company Profile" section is special: name / domain / logo / default
 * theme already live on the `tenants` row (read by ResolveTenant and the
 * layout), so they are written there rather than duplicated into `settings`.
 */
final class SettingController extends Controller
{
    public function index()
    {
        return redirect()->route('settings.section', 'profile');
    }

    /**
     * Render one section. `profile` is the tenant row; everything else comes
     * from the schema.
     */
    public function section(string $section)
    {
        abort_unless($section === 'profile' || isset(Setting::SCHEMA[$section]), 404);

        return view('settings.section', [
            'section'  => $section,
            'sections' => $this->sectionTabs(),
            'schema'   => Setting::SCHEMA[$section] ?? null,
            'values'   => Setting::all_for(tenant_id()),
            // NOT named `tenant` — the layout reads the shared domain tenant
            // under that name and shadowing it would break theming.
            'tenantModel' => $this->tenant(),
        ]);
    }

    public function update(Request $request, string $section)
    {
        abort_unless($section === 'profile' || isset(Setting::SCHEMA[$section]), 404);

        if ($section === 'profile') {
            return $this->updateProfile($request);
        }

        $fields = Setting::SCHEMA[$section]['fields'];

        // Every control posts as `settings[<safe key>]`. Two reasons:
        //  1. PHP rewrites `.` to `_` in top-level POST keys, so a raw
        //     "billing.invoice_prefix" input would never arrive intact;
        //     dots inside brackets are only mangled outside them, and we avoid
        //     them entirely by swapping "." for "__" (see Setting::safeKey()).
        //  2. Grouping under one array keeps unrelated request input out of the
        //     save loop.
        $rules = [];
        $attributes = [];
        foreach ($fields as $key => $def) {
            $field = 'settings.' . Setting::safeKey($key);
            $rules[$field] = $def['rules'] ?? 'nullable|string|max:500';
            $attributes[$field] = $def['label'];
        }

        $request->validate($rules, [], $attributes);

        $posted = (array) $request->input('settings', []);

        foreach ($fields as $key => $def) {
            $safe = Setting::safeKey($key);

            // An unchecked toggle is absent from the request — store "0" rather
            // than skipping it, otherwise it would fall back to the schema
            // default (often "1") and appear to re-enable itself.
            if (($def['type'] ?? 'text') === 'toggle') {
                $on = filter_var($posted[$safe] ?? '0', FILTER_VALIDATE_BOOLEAN);
                Setting::put($key, $on ? '1' : '0', tenant_id());
                continue;
            }

            if (!array_key_exists($safe, $posted)) {
                continue;
            }

            Setting::put($key, (string) ($posted[$safe] ?? ''), tenant_id());
        }

        return redirect()->route('settings.section', $section)
            ->with('status', Setting::SCHEMA[$section]['label'] . ' settings saved.');
    }

    /** Company Profile writes straight to the `tenants` row. */
    private function updateProfile(Request $request)
    {
        $tenant = $this->tenant();

        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'logo_url'      => 'nullable|url|max:500',
            'theme_default' => 'required|in:light,dark',
        ]);

        // `domain` and `slug` are deliberately NOT editable here: the host is
        // how ResolveTenant finds the tenant and the slug namespaces RADIUS
        // usernames, so changing either would orphan live sessions.
        $tenant->update($data);

        return redirect()->route('settings.section', 'profile')
            ->with('status', 'Company profile saved.');
    }

    /**
     * Tab list: Company Profile first, then every schema section.
     *
     * `standalone` sections are deliberately excluded — they have their own
     * menu entry elsewhere (RADIUS API sits under Radius Control) and would
     * otherwise appear twice.
     */
    private function sectionTabs(): array
    {
        $tabs = ['profile' => 'Company Profile'];
        foreach (Setting::SCHEMA as $key => $def) {
            if (!empty($def['standalone'])) {
                continue;
            }
            $tabs[$key] = $def['label'];
        }
        return $tabs;
    }

    /**
     * The resolved tenant as an Eloquent model.
     *
     * ResolveTenant shares a domain object (App\Src\Domain\Tenant), which has
     * no update(); re-read the row so the profile form can save.
     */
    private function tenant(): Tenant
    {
        return Tenant::findOrFail(tenant_id());
    }
}
