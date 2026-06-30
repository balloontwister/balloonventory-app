<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BinController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\ListsController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdmin\AdminBusinessController;
use App\Http\Controllers\SuperAdmin\AdminUserController;
use App\Http\Controllers\SuperAdmin\AdminUserEmailController;
use App\Http\Controllers\SuperAdmin\BackupController;
use App\Http\Controllers\SuperAdmin\BarcodeAuditController;
use App\Http\Controllers\SuperAdmin\CatalogBrandController;
use App\Http\Controllers\SuperAdmin\CatalogColorController;
use App\Http\Controllers\SuperAdmin\CatalogController;
use App\Http\Controllers\SuperAdmin\CatalogReferenceController;
use App\Http\Controllers\SuperAdmin\ComingSoonController;
use App\Http\Controllers\SuperAdmin\DistributorController;
use App\Http\Controllers\SuperAdmin\DistributorProposalController;
use App\Http\Controllers\SuperAdmin\EmailTemplateController;
use App\Http\Controllers\SuperAdmin\LoginLogController;
use App\Http\Controllers\SuperAdmin\MagicLoginLinkController;
use App\Http\Controllers\SuperAdmin\SkuFeedbackController;
use App\Http\Controllers\SuperAdmin\SupportTicketController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupportController;
use App\Http\Middleware\RequireAdminAccess;
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

// ─── Invitation magic link (auth optional — logs in the invitee) ──────────────
Route::get('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->name('invitations.accept');

// ─── Admin magic login link (public landing — click-to-confirm, then logs in) ──
Route::get('/magic-login/{token}', [MagicLoginLinkController::class, 'show'])
    ->name('magic-login.show');
Route::post('/magic-login/{token}', [MagicLoginLinkController::class, 'consume'])
    ->name('magic-login.consume');

// ─── End impersonation (any authenticated user — the target may not be admin) ──
Route::middleware('auth')->post('/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->name('impersonate.stop');

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
Route::middleware(['auth', 'verified', 'ensure.business', 'ensure.business.active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/nudges/dismiss', [DashboardController::class, 'dismissNudge'])->name('dashboard.nudges.dismiss');

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
    Route::get('/inventory/storage', [BinController::class, 'manage'])->name('inventory.storage');
    Route::post('/inventory/bins/auto-number', [BinController::class, 'autoNumber'])->name('inventory.bins.auto-number');
    Route::post('/inventory/bins/reorder', [BinController::class, 'reorder'])->name('inventory.bins.reorder');
    Route::get('/inventory/bins/{bin}', [BinController::class, 'show'])->name('inventory.bins.show');
    Route::get('/inventory/bins-contents', [BinController::class, 'bulkContents'])->name('inventory.bins.bulk-contents');
    Route::get('/inventory/bins/{bin}/contents', [BinController::class, 'contents'])->name('inventory.bins.contents');
    Route::get('/inventory/bins/{bin}/search-items', [BinController::class, 'searchItems'])->name('inventory.bins.search-items');
    Route::post('/inventory/bins/{bin}/items', [InventoryController::class, 'addItemToBin'])->name('inventory.bins.add-item');
    Route::post('/inventory/bins', [BinController::class, 'store'])->name('inventory.bins.store');
    Route::patch('/inventory/bins/{bin}', [BinController::class, 'update'])->name('inventory.bins.update');
    Route::delete('/inventory/bins/{bin}', [BinController::class, 'destroy'])->name('inventory.bins.destroy');
    Route::post('/inventory/locations', [LocationController::class, 'store'])->name('inventory.locations.store');
    Route::patch('/inventory/locations/{location}', [LocationController::class, 'update'])->name('inventory.locations.update');
    Route::delete('/inventory/locations/{location}', [LocationController::class, 'destroy'])->name('inventory.locations.destroy');
    // The "By List" tab on the Inventory page.
    Route::get('/inventory/lists', [ListsController::class, 'inventoryView'])->name('inventory.lists.index');
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
    Route::post('/settings/distributors', [SettingsController::class, 'updateDistributors'])->name('settings.distributors.update');

    // ─── Membership management (invite, role change, remove, revoke invite) ──────
    Route::post('/memberships/invite', [MembershipController::class, 'invite'])->name('memberships.invite');
    Route::patch('/memberships/{membership}/role', [MembershipController::class, 'updateRole'])->name('memberships.update-role');
    Route::delete('/memberships/{membership}', [MembershipController::class, 'destroy'])->name('memberships.destroy');
    Route::delete('/memberships/{membership}/leave', [MembershipController::class, 'leave'])->name('memberships.leave');
    Route::delete('/memberships/invitations/{invitation}/revoke', [MembershipController::class, 'revokeInvite'])->name('memberships.invitations.revoke');

    // ─── Invitation in-app paths (accept/decline/acknowledge from dashboard) ─────
    Route::post('/invitations/accept-in-app', [InvitationController::class, 'acceptInApp'])->name('invitations.accept-in-app');
    Route::post('/invitations/decline', [InvitationController::class, 'decline'])->name('invitations.decline');

    // ─── Notifications (unified notice feed) ─────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // ── Lists & Jobs hub ──────────────────────────────────────────────────────
    Route::get('/lists', [ListsController::class, 'index'])->name('lists.index');
    Route::post('/lists', [ListsController::class, 'store'])->name('lists.store');
    Route::get('/lists/create', [ListsController::class, 'create'])->name('lists.create');
    Route::get('/lists/{list}', [ListsController::class, 'show'])->name('lists.show');
    Route::get('/lists/{list}/edit', [ListsController::class, 'edit'])->name('lists.edit');
    Route::patch('/lists/{list}', [ListsController::class, 'update'])->name('lists.update');
    Route::delete('/lists/{list}', [ListsController::class, 'destroy'])->name('lists.destroy');
    Route::post('/lists/{list}/items', [ListsController::class, 'itemsStore'])->name('lists.items.store');
    Route::patch('/lists/{list}/items/{item}', [ListsController::class, 'itemsUpdate'])->name('lists.items.update');
    Route::delete('/lists/{list}/items/{item}', [ListsController::class, 'itemsDestroy'])->name('lists.items.destroy');
});

// ─── SuperAdmin ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', RequireAdminAccess::class])->group(function () {
    Route::get('/admin', [SuperAdminController::class, 'dashboard'])->name('admin.dashboard');

    // ── Users ────────────────────────────────────────────────────────────────
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    // Registered before the /{user} wildcard so "search" isn't captured as an id.
    Route::get('/admin/users/search', [AdminUserEmailController::class, 'search'])->name('admin.users.search');
    Route::post('/admin/user-emails', [AdminUserEmailController::class, 'store'])->name('admin.user-emails.store');
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::post('/admin/users/{user}/site-admin', [AdminUserController::class, 'promote'])->name('admin.users.promote');
    Route::delete('/admin/users/{user}/site-admin', [AdminUserController::class, 'demote'])->name('admin.users.demote');
    Route::post('/admin/users/{user}/freeze', [AdminUserController::class, 'freeze'])->name('admin.users.freeze');
    Route::delete('/admin/users/{user}/freeze', [AdminUserController::class, 'thaw'])->name('admin.users.thaw');
    Route::post('/admin/users/{user}/password-reset', [AdminUserController::class, 'sendPasswordReset'])->name('admin.users.password-reset');
    Route::post('/admin/users/{user}/password', [AdminUserController::class, 'setPassword'])->name('admin.users.set-password');
    Route::post('/admin/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('admin.users.impersonate');
    Route::post('/admin/users/{user}/magic-login', [MagicLoginLinkController::class, 'store'])->name('admin.users.magic-login');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

    // ── Businesses ───────────────────────────────────────────────────────────
    Route::get('/admin/businesses', [AdminBusinessController::class, 'index'])->name('admin.businesses.index');
    // Static segment registered before the {business} wildcard.
    Route::post('/admin/businesses/stop-view', [AdminBusinessController::class, 'stopViewAs'])->name('admin.businesses.stop-view');
    Route::get('/admin/businesses/{business}', [AdminBusinessController::class, 'show'])->name('admin.businesses.show');
    Route::post('/admin/businesses/{business}/view-as', [AdminBusinessController::class, 'viewAs'])->name('admin.businesses.view-as');
    Route::post('/admin/businesses/{business}/suspend', [AdminBusinessController::class, 'suspend'])->name('admin.businesses.suspend');
    Route::delete('/admin/businesses/{business}/suspend', [AdminBusinessController::class, 'thaw'])->name('admin.businesses.thaw');
    Route::delete('/admin/businesses/{business}', [AdminBusinessController::class, 'destroy'])->name('admin.businesses.destroy');

    // ── Catalog ──────────────────────────────────────────────────────────────
    Route::get('/admin/catalog', fn () => redirect()->route('admin.catalog.skus'))->name('admin.catalog');
    Route::get('/admin/catalog/skus', [CatalogController::class, 'index'])->name('admin.catalog.skus');
    Route::get('/admin/catalog/skus/create', [CatalogController::class, 'create'])->name('admin.catalog.skus.create');
    Route::post('/admin/catalog/skus', [CatalogController::class, 'store'])->name('admin.catalog.skus.store');
    Route::get('/admin/catalog/skus/{sku}', [CatalogController::class, 'show'])->name('admin.catalog.skus.show');
    Route::get('/admin/catalog/skus/{sku}/edit', [CatalogController::class, 'edit'])->name('admin.catalog.skus.edit');
    Route::patch('/admin/catalog/skus/{sku}', [CatalogController::class, 'update'])->name('admin.catalog.skus.update');
    Route::delete('/admin/catalog/skus/{sku}', [CatalogController::class, 'destroy'])->name('admin.catalog.skus.destroy');

    Route::get('/admin/catalog/colors', [CatalogColorController::class, 'index'])->name('admin.catalog.colors');
    Route::get('/admin/catalog/colors/{color}', [CatalogColorController::class, 'show'])->name('admin.catalog.colors.show');
    Route::get('/admin/catalog/colors/{color}/edit', [CatalogColorController::class, 'edit'])->name('admin.catalog.colors.edit');
    Route::post('/admin/catalog/colors', [CatalogColorController::class, 'store'])->name('admin.catalog.colors.store');
    Route::patch('/admin/catalog/colors/{color}', [CatalogColorController::class, 'update'])->name('admin.catalog.colors.update');
    Route::delete('/admin/catalog/colors/{color}', [CatalogColorController::class, 'destroy'])->name('admin.catalog.colors.destroy');

    Route::get('/admin/catalog/brands', [CatalogBrandController::class, 'index'])->name('admin.catalog.brands');
    Route::get('/admin/catalog/brands/{brand}', [CatalogBrandController::class, 'show'])->name('admin.catalog.brands.show');
    Route::get('/admin/catalog/brands/{brand}/edit', [CatalogBrandController::class, 'edit'])->name('admin.catalog.brands.edit');
    Route::post('/admin/catalog/brands', [CatalogBrandController::class, 'store'])->name('admin.catalog.brands.store');
    Route::patch('/admin/catalog/brands/{brand}', [CatalogBrandController::class, 'update'])->name('admin.catalog.brands.update');
    Route::post('/admin/catalog/brands/{brand}/gs1-prefixes', [CatalogBrandController::class, 'storeGs1Prefix'])->name('admin.catalog.brands.gs1-prefixes.store');
    Route::delete('/admin/catalog/brands/{brand}/gs1-prefixes/{prefix}', [CatalogBrandController::class, 'destroyGs1Prefix'])->name('admin.catalog.brands.gs1-prefixes.destroy');

    Route::get('/admin/catalog/reference', [CatalogReferenceController::class, 'index'])->name('admin.catalog.reference');
    Route::post('/admin/catalog/reference/{table}', [CatalogReferenceController::class, 'store'])->name('admin.catalog.reference.store');
    Route::patch('/admin/catalog/reference/{table}/{item}', [CatalogReferenceController::class, 'update'])->name('admin.catalog.reference.update');
    Route::delete('/admin/catalog/reference/{table}/{item}', [CatalogReferenceController::class, 'destroy'])->name('admin.catalog.reference.destroy');

    // ── Distributors ───────────────────────────────────────────────────────
    Route::get('/admin/distributors', [DistributorController::class, 'index'])->name('admin.distributors.index');
    Route::get('/admin/distributors/create', [DistributorController::class, 'create'])->name('admin.distributors.create');
    Route::post('/admin/distributors', [DistributorController::class, 'store'])->name('admin.distributors.store');

    // Proposal review queue — registered BEFORE the {distributor} wildcard so
    // "proposals" isn't captured as a distributor slug.
    Route::get('/admin/distributors/proposals', [DistributorProposalController::class, 'index'])->name('admin.distributors.proposals.index');
    Route::post('/admin/distributors/proposals/{proposal}/approve', [DistributorProposalController::class, 'approve'])->name('admin.distributors.proposals.approve');
    Route::post('/admin/distributors/proposals/{proposal}/reject', [DistributorProposalController::class, 'reject'])->name('admin.distributors.proposals.reject');
    Route::post('/admin/distributors/proposals/{proposal}/map-to-existing', [DistributorProposalController::class, 'mapToExisting'])->name('admin.distributors.proposals.map-to-existing');
    Route::patch('/admin/distributors/proposals/{proposal}', [DistributorProposalController::class, 'update'])->name('admin.distributors.proposals.update');

    Route::post('/admin/distributors/{distributor}/sync', [DistributorController::class, 'sync'])->name('admin.distributors.sync');
    Route::post('/admin/distributors/{distributor}/probe', [DistributorController::class, 'probe'])->name('admin.distributors.probe');
    Route::get('/admin/distributors/{distributor}', [DistributorController::class, 'show'])->name('admin.distributors.show');
    Route::get('/admin/distributors/{distributor}/edit', [DistributorController::class, 'edit'])->name('admin.distributors.edit');
    Route::patch('/admin/distributors/{distributor}', [DistributorController::class, 'update'])->name('admin.distributors.update');
    Route::delete('/admin/distributors/{distributor}', [DistributorController::class, 'destroy'])->name('admin.distributors.destroy');

    // ── Email templates ───────────────────────────────────────────────────────
    Route::get('/admin/email-templates', [EmailTemplateController::class, 'index'])->name('admin.email-templates.index');
    Route::get('/admin/email-templates/{template}/edit', [EmailTemplateController::class, 'edit'])->name('admin.email-templates.edit');
    Route::patch('/admin/email-templates/{template}', [EmailTemplateController::class, 'update'])->name('admin.email-templates.update');
    Route::post('/admin/email-templates/{template}/preview', [EmailTemplateController::class, 'preview'])->name('admin.email-templates.preview');

    // ── Support tickets ───────────────────────────────────────────────────────
    Route::get('/admin/tickets', [SupportTicketController::class, 'index'])->name('admin.tickets.index');
    Route::post('/admin/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('admin.tickets.reply');
    Route::patch('/admin/tickets/{ticket}/archive', [SupportTicketController::class, 'archive'])->name('admin.tickets.archive');
    Route::patch('/admin/tickets/{ticket}/unarchive', [SupportTicketController::class, 'unarchive'])->name('admin.tickets.unarchive');
    Route::delete('/admin/tickets/{ticket}', [SupportTicketController::class, 'destroy'])->name('admin.tickets.destroy');

    // ── Barcode link audit log ────────────────────────────────────────────────
    Route::get('/admin/barcode-audits', [BarcodeAuditController::class, 'index'])->name('admin.barcode-audits.index');
    Route::post('/admin/barcode-audits/{audit}/revert', [BarcodeAuditController::class, 'revert'])->name('admin.barcode-audits.revert');

    // ── Login log (global sign-in history) ─────────────────────────────────────
    Route::get('/admin/login-log', [LoginLogController::class, 'index'])->name('admin.login-log.index');

    // ── Item feedback (user-reported catalog discrepancies) ────────────────────
    Route::get('/admin/feedback', [SkuFeedbackController::class, 'index'])->name('admin.feedback.index');
    Route::patch('/admin/feedback/{feedback}/status', [SkuFeedbackController::class, 'updateStatus'])->name('admin.feedback.update-status');
    Route::post('/admin/feedback/{feedback}/reply', [SkuFeedbackController::class, 'reply'])->name('admin.feedback.reply');

    // ── Super-Admin-only areas (backups + future billing) ─────────────────────
    Route::middleware(RequireSuperAdmin::class)->group(function () {
        // Database backups
        Route::get('/admin/backups', [BackupController::class, 'index'])->name('admin.backups.index');
        Route::post('/admin/backups', [BackupController::class, 'store'])->name('admin.backups.store');
        Route::get('/admin/backups/{filename}/download', [BackupController::class, 'download'])->name('admin.backups.download');
        Route::patch('/admin/backups/{filename}', [BackupController::class, 'rename'])->name('admin.backups.rename');
        Route::delete('/admin/backups/{filename}', [BackupController::class, 'destroy'])->name('admin.backups.destroy');

        // Future-growth stubs (scaffolded "coming soon" pages).
        Route::get('/admin/subscriptions', ComingSoonController::class)->defaults('area', 'subscriptions')->name('admin.subscriptions.index');
        Route::get('/admin/payments', ComingSoonController::class)->defaults('area', 'payments')->name('admin.payments.index');
        Route::get('/admin/affiliates', ComingSoonController::class)->defaults('area', 'affiliates')->name('admin.affiliates.index');
    });
});

require __DIR__.'/auth.php';
