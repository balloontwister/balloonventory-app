<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\ListsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupportController;
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
    Route::get('/settings/businesses', [SettingsController::class, 'businesses'])->name('settings.businesses');
    Route::patch('/settings/businesses', [SettingsController::class, 'updateBusiness'])->name('settings.businesses.update');

    Route::get('/lists/create', [ListsController::class, 'create'])->name('lists.create');
    Route::get('/lists/{list}', [ListsController::class, 'show'])->name('lists.show');
    Route::get('/lists/{list}/edit', [ListsController::class, 'edit'])->name('lists.edit');
});

// ─── SuperAdmin ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', App\Http\Middleware\RequireSuperAdmin::class])->group(function () {
    Route::get('/super-admin', [SuperAdminController::class, 'dashboard'])->name('super-admin.dashboard');
});

require __DIR__.'/auth.php';
