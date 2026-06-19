<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BinController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\ListsController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdmin\AdminUserController;
use App\Http\Controllers\SuperAdmin\BackupController;
use App\Http\Controllers\SuperAdmin\BarcodeAuditController;
use App\Http\Controllers\SuperAdmin\CatalogBrandController;
use App\Http\Controllers\SuperAdmin\CatalogColorController;
use App\Http\Controllers\SuperAdmin\CatalogController;
use App\Http\Controllers\SuperAdmin\CatalogReferenceController;
use App\Http\Controllers\SuperAdmin\EmailTemplateController;
use App\Http\Controllers\SuperAdmin\SkuFeedbackController;
use App\Http\Controllers\SuperAdmin\SupportTicketController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupportController;
use App\Http\Middleware\RequireAdminAccess;
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
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
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

    // ─── Onboarding wizard (business exists; auto-shown after creation, re-runnable) ──
    Route::get('/onboarding/wizard', [OnboardingController::class, 'show'])->name('onboarding.wizard');
    Route::post('/onboarding/wizard', [OnboardingController::class, 'complete'])->name('onboarding.wizard.complete');
    Route::post('/onboarding/wizard/skip', [OnboardingController::class, 'skip'])->name('onboarding.wizard.skip');
    Route::post('/onboarding/samples/clear', [OnboardingController::class, 'clearSamples'])->name('onboarding.samples.clear');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/skus/{sku}', [InventoryController::class, 'show'])->name('inventory.sku.show');
    Route::post('/inventory/skus', [InventoryController::class, 'store'])->name('inventory.sku.store');
    Route::delete('/inventory/skus/{sku}', [InventoryController::class, 'destroy'])->name('inventory.sku.destroy');
    Route::post('/inventory/skus/{sku}/transfer', [InventoryController::class, 'transfer'])->name('inventory.sku.transfer');
    Route::post('/inventory/skus/{sku}/adjust', [InventoryController::class, 'adjust'])->name('inventory.sku.adjust');
    Route::delete('/inventory/skus/{sku}/bins/{bin}', [InventoryController::class, 'removeStockBin'])->name('inventory.sku.bin.remove');
    Route::patch('/inventory/skus/{sku}/override', [InventoryController::class, 'updateOverride'])->name('inventory.override.update');
    Route::post('/inventory/skus/{sku}/lists', [InventoryController::class, 'addToList'])->name('inventory.sku.add-to-list');
    Route::post('/inventory/skus/{sku}/feedback', [InventoryController::class, 'submitFeedback'])
        ->middleware('throttle:5,60')
        ->name('inventory.sku.feedback');
    Route::post('/favorites/{sku}', [InventoryController::class, 'addFavorite'])->name('favorites.add');
    Route::post('/favorites/{sku}/remove', [InventoryController::class, 'removeFavorite'])->name('favorites.remove');

    // ── Bins & locations (the "By Bin" view + management) ─────────────────────
    Route::get('/inventory/bins', [BinController::class, 'index'])->name('inventory.bins.index');
    Route::get('/inventory/bins/{bin}/contents', [BinController::class, 'contents'])->name('inventory.bins.contents');
    Route::post('/inventory/bins', [BinController::class, 'store'])->name('inventory.bins.store');
    Route::patch('/inventory/bins/{bin}', [BinController::class, 'update'])->name('inventory.bins.update');
    Route::delete('/inventory/bins/{bin}', [BinController::class, 'destroy'])->name('inventory.bins.destroy');
    Route::post('/inventory/locations', [LocationController::class, 'store'])->name('inventory.locations.store');
    Route::patch('/inventory/locations/{location}', [LocationController::class, 'update'])->name('inventory.locations.update');
    Route::delete('/inventory/locations/{location}', [LocationController::class, 'destroy'])->name('inventory.locations.destroy');
    Route::get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::post('/scan/lookup', [ScanController::class, 'lookup'])->name('scan.lookup');
    Route::get('/scan/search-skus', [ScanController::class, 'searchSkus'])->name('scan.search-skus');
    Route::post('/scan/link-barcode', [ScanController::class, 'linkBarcode'])->name('scan.link-barcode');
    Route::post('/scan/check-in', [ScanController::class, 'checkIn'])->name('scan.check-in');
    Route::post('/scan/check-out', [ScanController::class, 'checkOut'])->name('scan.check-out');
    Route::post('/scan/undo/{stockMovement}', [ScanController::class, 'undo'])->name('scan.undo');
    Route::get('/reorder', [ReorderController::class, 'index'])->name('reorder.index');

    Route::get('/account', [AccountController::class, 'index'])->name('account.index');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::get('/settings/businesses', [SettingsController::class, 'businesses'])->name('settings.businesses');
    Route::patch('/settings/businesses', [SettingsController::class, 'updateBusiness'])->name('settings.businesses.update');
    Route::post('/settings/businesses/logo', [SettingsController::class, 'updateBusinessLogo'])->name('settings.businesses.logo.update');

    Route::get('/lists/create', [ListsController::class, 'create'])->name('lists.create');
    Route::get('/lists/{list}', [ListsController::class, 'show'])->name('lists.show');
    Route::get('/lists/{list}/edit', [ListsController::class, 'edit'])->name('lists.edit');
});

// ─── SuperAdmin ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', RequireAdminAccess::class])->group(function () {
    Route::get('/super-admin', [SuperAdminController::class, 'dashboard'])->name('super-admin.dashboard');

    // ── Users ────────────────────────────────────────────────────────────────
    Route::get('/super-admin/users', [AdminUserController::class, 'index'])->name('super-admin.users.index');
    Route::post('/super-admin/users/{user}/site-admin', [AdminUserController::class, 'promote'])->name('super-admin.users.promote');
    Route::delete('/super-admin/users/{user}/site-admin', [AdminUserController::class, 'demote'])->name('super-admin.users.demote');

    // ── Catalog ──────────────────────────────────────────────────────────────
    Route::get('/super-admin/catalog', fn () => redirect()->route('super-admin.catalog.skus'))->name('super-admin.catalog');
    Route::get('/super-admin/catalog/skus', [CatalogController::class, 'index'])->name('super-admin.catalog.skus');
    Route::get('/super-admin/catalog/skus/create', [CatalogController::class, 'create'])->name('super-admin.catalog.skus.create');
    Route::post('/super-admin/catalog/skus', [CatalogController::class, 'store'])->name('super-admin.catalog.skus.store');
    Route::get('/super-admin/catalog/skus/{sku}', [CatalogController::class, 'show'])->name('super-admin.catalog.skus.show');
    Route::get('/super-admin/catalog/skus/{sku}/edit', [CatalogController::class, 'edit'])->name('super-admin.catalog.skus.edit');
    Route::patch('/super-admin/catalog/skus/{sku}', [CatalogController::class, 'update'])->name('super-admin.catalog.skus.update');
    Route::delete('/super-admin/catalog/skus/{sku}', [CatalogController::class, 'destroy'])->name('super-admin.catalog.skus.destroy');

    Route::get('/super-admin/catalog/colors', [CatalogColorController::class, 'index'])->name('super-admin.catalog.colors');
    Route::get('/super-admin/catalog/colors/{color}', [CatalogColorController::class, 'show'])->name('super-admin.catalog.colors.show');
    Route::get('/super-admin/catalog/colors/{color}/edit', [CatalogColorController::class, 'edit'])->name('super-admin.catalog.colors.edit');
    Route::post('/super-admin/catalog/colors', [CatalogColorController::class, 'store'])->name('super-admin.catalog.colors.store');
    Route::patch('/super-admin/catalog/colors/{color}', [CatalogColorController::class, 'update'])->name('super-admin.catalog.colors.update');
    Route::delete('/super-admin/catalog/colors/{color}', [CatalogColorController::class, 'destroy'])->name('super-admin.catalog.colors.destroy');

    Route::get('/super-admin/catalog/brands', [CatalogBrandController::class, 'index'])->name('super-admin.catalog.brands');
    Route::get('/super-admin/catalog/brands/{brand}', [CatalogBrandController::class, 'show'])->name('super-admin.catalog.brands.show');
    Route::get('/super-admin/catalog/brands/{brand}/edit', [CatalogBrandController::class, 'edit'])->name('super-admin.catalog.brands.edit');
    Route::post('/super-admin/catalog/brands', [CatalogBrandController::class, 'store'])->name('super-admin.catalog.brands.store');
    Route::patch('/super-admin/catalog/brands/{brand}', [CatalogBrandController::class, 'update'])->name('super-admin.catalog.brands.update');
    Route::post('/super-admin/catalog/brands/{brand}/gs1-prefixes', [CatalogBrandController::class, 'storeGs1Prefix'])->name('super-admin.catalog.brands.gs1-prefixes.store');
    Route::delete('/super-admin/catalog/brands/{brand}/gs1-prefixes/{prefix}', [CatalogBrandController::class, 'destroyGs1Prefix'])->name('super-admin.catalog.brands.gs1-prefixes.destroy');

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

    // ── Barcode link audit log ────────────────────────────────────────────────
    Route::get('/super-admin/barcode-audits', [BarcodeAuditController::class, 'index'])->name('super-admin.barcode-audits.index');
    Route::post('/super-admin/barcode-audits/{audit}/revert', [BarcodeAuditController::class, 'revert'])->name('super-admin.barcode-audits.revert');

    // ── Item feedback (user-reported catalog discrepancies) ────────────────────
    Route::get('/super-admin/feedback', [SkuFeedbackController::class, 'index'])->name('super-admin.feedback.index');
    Route::patch('/super-admin/feedback/{feedback}/status', [SkuFeedbackController::class, 'updateStatus'])->name('super-admin.feedback.update-status');
    Route::post('/super-admin/feedback/{feedback}/reply', [SkuFeedbackController::class, 'reply'])->name('super-admin.feedback.reply');

    // ── Database backups ──────────────────────────────────────────────────────
    Route::get('/super-admin/backups', [BackupController::class, 'index'])->name('super-admin.backups.index');
    Route::post('/super-admin/backups', [BackupController::class, 'store'])->name('super-admin.backups.store');
    Route::get('/super-admin/backups/{filename}/download', [BackupController::class, 'download'])->name('super-admin.backups.download');
    Route::patch('/super-admin/backups/{filename}', [BackupController::class, 'rename'])->name('super-admin.backups.rename');
    Route::delete('/super-admin/backups/{filename}', [BackupController::class, 'destroy'])->name('super-admin.backups.destroy');
});

require __DIR__.'/auth.php';
