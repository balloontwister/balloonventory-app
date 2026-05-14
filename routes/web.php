<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\ListsController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdmin\CatalogBrandController;
use App\Http\Controllers\SuperAdmin\CatalogColorController;
use App\Http\Controllers\SuperAdmin\CatalogController;
use App\Http\Controllers\SuperAdmin\CatalogReferenceController;
use App\Http\Controllers\SuperAdmin\EmailTemplateController;
use App\Http\Controllers\SuperAdmin\SupportTicketController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupportController;
use App\Http\Middleware\RequireSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// ─── Locale ────────────────────────────────────────────────────────────────────
Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

// ─── Onboarding (auth required, no business required) ────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/onboarding/create-business', [BusinessController::class, 'create'])
        ->name('onboarding.create-business');

    Route::post('/onboarding/create-business', [BusinessController::class, 'store'])
        ->name('onboarding.store-business');
});

// ─── Business switcher ────────────────────────────────────────────────────────
Route::middleware('auth')->post('/business/{business}/switch', [BusinessController::class, 'switch'])
    ->name('business.switch');

// ─── Profile ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/support/contact', [SupportController::class, 'send'])
        ->middleware('throttle:3,60')
        ->name('support.contact');
});

// ─── Authenticated + verified + business-gated routes ────────────────────────
Route::middleware(['auth', 'verified', 'ensure.business'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/reorder', [ReorderController::class, 'index'])->name('reorder.index');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::get('/settings/businesses', [SettingsController::class, 'businesses'])->name('settings.businesses');
    Route::patch('/settings/businesses', [SettingsController::class, 'updateBusiness'])->name('settings.businesses.update');

    Route::get('/lists/create', [ListsController::class, 'create'])->name('lists.create');
    Route::get('/lists/{list}', [ListsController::class, 'show'])->name('lists.show');
    Route::get('/lists/{list}/edit', [ListsController::class, 'edit'])->name('lists.edit');
});

// ─── SuperAdmin ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', RequireSuperAdmin::class])->group(function () {
    Route::get('/super-admin', [SuperAdminController::class, 'dashboard'])->name('super-admin.dashboard');

    // ── Catalog ──────────────────────────────────────────────────────────────
    Route::get('/super-admin/catalog', fn () => redirect()->route('super-admin.catalog.skus'))->name('super-admin.catalog');
    Route::get('/super-admin/catalog/skus', [CatalogController::class, 'index'])->name('super-admin.catalog.skus');
    Route::get('/super-admin/catalog/skus/create', [CatalogController::class, 'create'])->name('super-admin.catalog.skus.create');
    Route::post('/super-admin/catalog/skus', [CatalogController::class, 'store'])->name('super-admin.catalog.skus.store');
    Route::get('/super-admin/catalog/skus/{sku}/edit', [CatalogController::class, 'edit'])->name('super-admin.catalog.skus.edit');
    Route::patch('/super-admin/catalog/skus/{sku}', [CatalogController::class, 'update'])->name('super-admin.catalog.skus.update');
    Route::delete('/super-admin/catalog/skus/{sku}', [CatalogController::class, 'destroy'])->name('super-admin.catalog.skus.destroy');

    Route::get('/super-admin/catalog/colors', [CatalogColorController::class, 'index'])->name('super-admin.catalog.colors');
    Route::post('/super-admin/catalog/colors', [CatalogColorController::class, 'store'])->name('super-admin.catalog.colors.store');
    Route::patch('/super-admin/catalog/colors/{color}', [CatalogColorController::class, 'update'])->name('super-admin.catalog.colors.update');
    Route::delete('/super-admin/catalog/colors/{color}', [CatalogColorController::class, 'destroy'])->name('super-admin.catalog.colors.destroy');

    Route::get('/super-admin/catalog/brands', [CatalogBrandController::class, 'index'])->name('super-admin.catalog.brands');
    Route::post('/super-admin/catalog/brands', [CatalogBrandController::class, 'store'])->name('super-admin.catalog.brands.store');
    Route::patch('/super-admin/catalog/brands/{brand}', [CatalogBrandController::class, 'update'])->name('super-admin.catalog.brands.update');

    Route::get('/super-admin/catalog/reference', [CatalogReferenceController::class, 'index'])->name('super-admin.catalog.reference');
    Route::post('/super-admin/catalog/reference/{table}', [CatalogReferenceController::class, 'store'])->name('super-admin.catalog.reference.store');
    Route::patch('/super-admin/catalog/reference/{table}/{item}', [CatalogReferenceController::class, 'update'])->name('super-admin.catalog.reference.update');
    Route::delete('/super-admin/catalog/reference/{table}/{item}', [CatalogReferenceController::class, 'destroy'])->name('super-admin.catalog.reference.destroy');

    // ── Email templates ───────────────────────────────────────────────────────
    Route::get('/super-admin/email-templates/{template}/edit', [EmailTemplateController::class, 'edit'])->name('super-admin.email-templates.edit');
    Route::patch('/super-admin/email-templates/{template}', [EmailTemplateController::class, 'update'])->name('super-admin.email-templates.update');
    Route::post('/super-admin/email-templates/{template}/preview', [EmailTemplateController::class, 'preview'])->name('super-admin.email-templates.preview');

    // ── Support tickets ───────────────────────────────────────────────────────
    Route::post('/super-admin/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('super-admin.tickets.reply');
    Route::patch('/super-admin/tickets/{ticket}/archive', [SupportTicketController::class, 'archive'])->name('super-admin.tickets.archive');
    Route::patch('/super-admin/tickets/{ticket}/unarchive', [SupportTicketController::class, 'unarchive'])->name('super-admin.tickets.unarchive');
    Route::delete('/super-admin/tickets/{ticket}', [SupportTicketController::class, 'destroy'])->name('super-admin.tickets.destroy');
});

require __DIR__.'/auth.php';
