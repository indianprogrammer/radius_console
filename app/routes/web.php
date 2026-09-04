<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BandwidthProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FranchiseController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\NasController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffGroupController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

Route::get('/', DashboardController::class)->name('dashboard');

Route::prefix('subscribers')->name('subscribers.')->group(function () {
    Route::get('/', [SubscriberController::class, 'index'])->name('index');
    Route::get('/create', [SubscriberController::class, 'create'])->name('create');
    Route::post('/', [SubscriberController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [SubscriberController::class, 'edit'])->name('edit');
    Route::put('/{id}', [SubscriberController::class, 'update'])->name('update');
    Route::delete('/{id}', [SubscriberController::class, 'destroy'])->name('destroy');
});

// Address lookup for the subscriber Installation Address map picker. Read-only
// JSON, served through our own host so the upstream geocoder gets the identifying
// User-Agent it requires and responses can be cached. See GeocodeController.
Route::prefix('geocode')->name('geocode.')->group(function () {
    Route::get('/search', [GeocodeController::class, 'search'])->name('search');
    Route::get('/reverse', [GeocodeController::class, 'reverse'])->name('reverse');
});

Route::prefix('nas')->name('nas.')->group(function () {
    Route::get('/', [NasController::class, 'index'])->name('index');
    Route::get('/create', [NasController::class, 'create'])->name('create');
    Route::post('/', [NasController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [NasController::class, 'edit'])->name('edit');
    Route::put('/{id}', [NasController::class, 'update'])->name('update');
    Route::delete('/{id}', [NasController::class, 'destroy'])->name('destroy');
});

// RADIUS-synced bandwidth profiles (Bandwidth Control).
Route::prefix('bandwidth-profiles')->name('bandwidth-profiles.')->group(function () {
    Route::get('/', [BandwidthProfileController::class, 'index'])->name('index');
    Route::get('/create', [BandwidthProfileController::class, 'create'])->name('create');
    Route::post('/', [BandwidthProfileController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [BandwidthProfileController::class, 'edit'])->name('edit');
    Route::put('/{id}', [BandwidthProfileController::class, 'update'])->name('update');
    Route::delete('/{id}', [BandwidthProfileController::class, 'destroy'])->name('destroy');
});

// Billing plans (financial details only; link to a bandwidth profile).
Route::prefix('plans')->name('plans.')->group(function () {
    Route::get('/', [PlanController::class, 'index'])->name('index');
    Route::get('/create', [PlanController::class, 'create'])->name('create');
    Route::post('/', [PlanController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [PlanController::class, 'edit'])->name('edit');
    Route::put('/{id}', [PlanController::class, 'update'])->name('update');
    Route::delete('/{id}', [PlanController::class, 'destroy'])->name('destroy');
});

// Tax rates (managed under Billing & Invoices).
Route::prefix('tax-rates')->name('tax-rates.')->group(function () {
    Route::get('/', [TaxRateController::class, 'index'])->name('index');
    Route::get('/create', [TaxRateController::class, 'create'])->name('create');
    Route::post('/', [TaxRateController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [TaxRateController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TaxRateController::class, 'update'])->name('update');
    Route::delete('/{id}', [TaxRateController::class, 'destroy'])->name('destroy');
});

// Products & Services (managed under Billing & Invoices).
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/autocomplete', [ProductController::class, 'autocomplete'])->name('autocomplete');
    Route::get('/create', [ProductController::class, 'create'])->name('create');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [ProductController::class, 'edit'])->name('edit');
    Route::put('/{id}', [ProductController::class, 'update'])->name('update');
    Route::delete('/{id}', [ProductController::class, 'destroy'])->name('destroy');
});

// Inventory (managed under Billing & Invoices).
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::get('/create', [InventoryController::class, 'create'])->name('create');
    Route::post('/', [InventoryController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [InventoryController::class, 'edit'])->name('edit');
    Route::put('/{id}', [InventoryController::class, 'update'])->name('update');
    Route::delete('/{id}', [InventoryController::class, 'destroy'])->name('destroy');
});

// Sales pipeline / leads (Sales). Stage changes, follow-ups and the
// quote hand-off are POST actions of their own so LeadService owns every
// transition and the `lead_activities` trail is always written.
Route::prefix('leads')->name('leads.')->group(function () {
    Route::get('/', [LeadController::class, 'index'])->name('index');
    // Declared BEFORE `/{id}`: that placeholder is unconstrained, so a literal
    // segment listed after it would be swallowed as an id.
    Route::get('/board', [LeadController::class, 'board'])->name('board');
    Route::get('/create', [LeadController::class, 'create'])->name('create');
    Route::post('/', [LeadController::class, 'store'])->name('store');
    Route::get('/{id}', [LeadController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [LeadController::class, 'edit'])->name('edit');
    Route::put('/{id}', [LeadController::class, 'update'])->name('update');
    Route::delete('/{id}', [LeadController::class, 'destroy'])->name('destroy');

    Route::post('/{id}/activity', [LeadController::class, 'activity'])->name('activity');
    Route::post('/{id}/follow-up', [LeadController::class, 'followUp'])->name('follow-up');
    Route::post('/{id}/stage', [LeadController::class, 'stage'])->name('stage');
    Route::post('/{id}/quote', [LeadController::class, 'quote'])->name('quote');
    Route::post('/{id}/win', [LeadController::class, 'win'])->name('win');
    Route::post('/{id}/lose', [LeadController::class, 'lose'])->name('lose');
});

// Invoices (managed under Billing & Invoices). Generated from a subscriber's
// billing items, so there is no free-form line-item editor — only generate,
// view and status/due-date maintenance.
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceController::class, 'index'])->name('index');
    Route::get('/create', [InvoiceController::class, 'create'])->name('create');
    Route::post('/', [InvoiceController::class, 'store'])->name('store');
    Route::get('/{id}', [InvoiceController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [InvoiceController::class, 'edit'])->name('edit');
    Route::put('/{id}', [InvoiceController::class, 'update'])->name('update');
    Route::delete('/{id}', [InvoiceController::class, 'destroy'])->name('destroy');
});

// Payments / receipts (managed under Billing & Invoices).
Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->name('index');
    Route::get('/create', [PaymentController::class, 'create'])->name('create');
    Route::post('/', [PaymentController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [PaymentController::class, 'edit'])->name('edit');
    Route::put('/{id}', [PaymentController::class, 'update'])->name('update');
    Route::delete('/{id}', [PaymentController::class, 'destroy'])->name('destroy');
});

// Ledger (managed under Billing & Invoices). Read-only statement derived from
// invoices (debit) + payments (credit); no write routes by design.
Route::prefix('ledger')->name('ledger.')->group(function () {
    Route::get('/', [LedgerController::class, 'index'])->name('index');
});

// Logs (SRD §5.0 #10). Every page is `audit_log` filtered by `{channel}`, so
// one route serves all nine menu entries. GET only — the trail is append-only
// (SRD §9.8) and `App\Services\ActivityLogger` is its sole writer.
// `radius` and `live-sessions` are read-through proxies over the external
// RADIUS API and MUST be declared before `/{channel}`, which would otherwise
// swallow them as channel names and 404.
Route::prefix('logs')->name('logs.')->group(function () {
    Route::get('/', [LogController::class, 'index'])->name('index');
    Route::get('/radius', [LogController::class, 'radius'])->name('radius');
    Route::get('/live-sessions', [LogController::class, 'liveSessions'])->name('live-sessions');
    Route::get('/{channel}', [LogController::class, 'channel'])->name('channel');
});

// Franchises / LCOs (managed under Franchise Management, SRD §5.4).
Route::prefix('franchises')->name('franchises.')->group(function () {
    Route::get('/', [FranchiseController::class, 'index'])->name('index');
    Route::get('/create', [FranchiseController::class, 'create'])->name('create');
    Route::post('/', [FranchiseController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [FranchiseController::class, 'edit'])->name('edit');
    Route::put('/{id}', [FranchiseController::class, 'update'])->name('update');
    Route::delete('/{id}', [FranchiseController::class, 'destroy'])->name('destroy');
});

// Staff / employee master (Staff & HR). `/staff/groups` is declared BEFORE
// `/staff/{id}` would ever be reachable as a separate prefix, so there is no
// collision — teams live under their own `staff-groups` prefix.
Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::get('/create', [StaffController::class, 'create'])->name('create');
    Route::post('/', [StaffController::class, 'store'])->name('store');
    Route::get('/{id}', [StaffController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [StaffController::class, 'edit'])->name('edit');
    Route::put('/{id}', [StaffController::class, 'update'])->name('update');
    Route::delete('/{id}', [StaffController::class, 'destroy'])->name('destroy');
});

// Staff groups / teams — targets for bulk ticket assignment.
Route::prefix('staff-groups')->name('staff-groups.')->group(function () {
    Route::get('/', [StaffGroupController::class, 'index'])->name('index');
    Route::get('/create', [StaffGroupController::class, 'create'])->name('create');
    Route::post('/', [StaffGroupController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [StaffGroupController::class, 'edit'])->name('edit');
    Route::put('/{id}', [StaffGroupController::class, 'update'])->name('update');
    Route::delete('/{id}', [StaffGroupController::class, 'destroy'])->name('destroy');
});

// Attendance register (Staff & HR). The register saves a whole day at once.
Route::prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/', [AttendanceController::class, 'index'])->name('index');
    Route::post('/', [AttendanceController::class, 'bulkStore'])->name('bulk-store');
    Route::get('/staff/{staff}', [AttendanceController::class, 'sheet'])->name('sheet');
    Route::delete('/{id}', [AttendanceController::class, 'destroy'])->name('destroy');
});

// Payroll / payslips (Staff & HR). Always computed from attendance by
// PayrollService — there is no free-form earnings editor by design.
Route::prefix('payroll')->name('payroll.')->group(function () {
    Route::get('/', [PayrollController::class, 'index'])->name('index');
    Route::get('/create', [PayrollController::class, 'create'])->name('create');
    Route::post('/', [PayrollController::class, 'store'])->name('store');
    Route::get('/{id}', [PayrollController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [PayrollController::class, 'edit'])->name('edit');
    Route::put('/{id}', [PayrollController::class, 'update'])->name('update');
    Route::delete('/{id}', [PayrollController::class, 'destroy'])->name('destroy');
});

// Tickets / helpdesk. Assignment has its own endpoints so the audit trail in
// `ticket_events` is written by TicketAssigner on every change.
Route::prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', [TicketController::class, 'index'])->name('index');
    Route::get('/create', [TicketController::class, 'create'])->name('create');
    Route::post('/', [TicketController::class, 'store'])->name('store');
    Route::get('/{id}', [TicketController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [TicketController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TicketController::class, 'update'])->name('update');
    Route::delete('/{id}', [TicketController::class, 'destroy'])->name('destroy');

    // Assign to one / many staff or a team; reassign to a new owner.
    Route::post('/{id}/assign', [TicketController::class, 'assign'])->name('assign');
    Route::post('/{id}/reassign', [TicketController::class, 'reassign'])->name('reassign');
    Route::post('/{id}/comment', [TicketController::class, 'comment'])->name('comment');
    Route::delete('/{ticket}/assignees/{staff}', [TicketController::class, 'removeAssignee'])
        ->name('assignees.destroy');
});

// Quotations & Proforma Invoices (Billing & Invoices). Both documents are the
// same entity with a different `type`, so ONE named route group is defined and
// the type is bound as the first parameter — hence the two url aliases below.
// Neither is a receivable until `convert` produces a real invoice.
Route::prefix('{type}')->name('quotes.')->whereIn('type', ['quotation', 'proforma'])->group(function () {
    Route::get('/', [QuoteController::class, 'index'])->name('index');
    Route::get('/create', [QuoteController::class, 'create'])->name('create');
    Route::post('/', [QuoteController::class, 'store'])->name('store');
    Route::get('/{id}', [QuoteController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [QuoteController::class, 'edit'])->name('edit');
    Route::put('/{id}', [QuoteController::class, 'update'])->name('update');
    Route::delete('/{id}', [QuoteController::class, 'destroy'])->name('destroy');

    // The only path that creates a receivable from a pre-sale document.
    Route::post('/{id}/convert', [QuoteController::class, 'convert'])->name('convert');
});

// Tenant settings. One page per section; `{section}` is validated against
// Setting::SCHEMA in the controller, so new sections need no route change.
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('index');
    Route::get('/{section}', [SettingController::class, 'section'])->name('section');
    Route::put('/{section}', [SettingController::class, 'update'])->name('update');
});

// Per-user theme preference persistence (best-effort, SRD §3.2).
Route::post('/profile/theme', function (\Illuminate\Http\Request $request) {
    $theme = $request->input('theme');
    if (auth()->check() && in_array($theme, ['light', 'dark'])) {
        auth()->user()->update(['theme_pref' => $theme]);
    }
    return response()->noContent();
});
