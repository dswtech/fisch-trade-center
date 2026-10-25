<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CatalogThumbnailController;
use App\Http\Controllers\CatalogValueImportController;
use App\Http\Controllers\RodAdminController;
use App\Http\Controllers\PhaseOnePageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/catalog', [PhaseOnePageController::class, 'catalog'])->name('catalog');
Route::get('/listings', DashboardController::class)->name('listings');
Route::get('/search', SearchController::class)->name('search');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'create'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', fn () => Inertia::render('verify-email'))->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill(); return to_route('dashboard')->with('success', 'Email verified.');
    })->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification(); return back()->with('success', 'Verification link sent.');
    })->middleware('throttle:6,1')->name('verification.send');
    Route::patch('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
    Route::get('/profile', [PhaseOnePageController::class, 'profile'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::middleware('verified')->group(function (): void {
        Route::get('/inventory', [PhaseOnePageController::class, 'inventory'])->name('inventory');
        Route::get('/simulator', [PhaseOnePageController::class, 'simulator'])->name('simulator');
        Route::get('/my-listings', [PhaseOnePageController::class, 'listings'])->name('listings.manage');
        Route::get('/listings/create', [PhaseOnePageController::class, 'listingCreate'])->name('listings.create');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::patch('/inventory/{entry}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::delete('/inventory/{entry}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
        Route::patch('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
        Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/', [AdminController::class, 'index'])->name('index');
            Route::patch('/settings/fair-threshold', [AdminController::class, 'threshold'])->name('threshold');
            Route::get('/catalog-value-imports/template', [CatalogValueImportController::class, 'template'])->name('catalog-imports.template');
            Route::post('/catalog-value-imports', [CatalogValueImportController::class, 'store'])->name('catalog-imports.store');
            Route::post('/catalog-value-imports/{import}/publish', [CatalogValueImportController::class, 'publish'])->name('catalog-imports.publish');
            Route::post('/rods', [RodAdminController::class, 'store'])->name('rods.store');
            Route::patch('/catalog-items/{item}/rod', [RodAdminController::class, 'assign'])->name('catalog-items.rod');
            Route::post('/catalog-items/{item}/thumbnail', [CatalogThumbnailController::class, 'update'])->name('catalog-items.thumbnail');
            Route::delete('/catalog-items/{item}/thumbnail', [CatalogThumbnailController::class, 'destroy'])->name('catalog-items.thumbnail.destroy');
        });
    });
});
