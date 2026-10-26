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
use App\Http\Controllers\CatalogAdminController;
use App\Http\Controllers\RodAdminController;
use App\Http\Controllers\PhaseOnePageController;
use App\Http\Controllers\PhaseThreePageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NegotiationController;
use App\Http\Controllers\OperatorAuditController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuspensionController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\AdminTimeControlController;
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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'create'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
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
    Route::patch('/preferences', [PreferenceController::class, 'update'])->middleware('throttle:60,1')->name('preferences.update');
    Route::get('/profile', [PhaseOnePageController::class, 'profile'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:60,1')->name('profile.update');
    Route::middleware('verified')->group(function (): void {
        Route::get('/inventory', [PhaseOnePageController::class, 'inventory'])->name('inventory');
        Route::get('/simulator', [PhaseOnePageController::class, 'simulator'])->name('simulator');
        Route::get('/my-listings', [PhaseOnePageController::class, 'listings'])->name('listings.manage');
        Route::get('/listings/create', [PhaseOnePageController::class, 'listingCreate'])->name('listings.create');
        Route::get('/listings/{listing}/edit', [PhaseOnePageController::class, 'listingEdit'])->name('listings.edit');
        Route::get('/recommendations/{listing}', [PhaseThreePageController::class, 'recommendations'])->name('recommendations.show');
        Route::get('/negotiations', [PhaseThreePageController::class, 'index'])->name('negotiations.index');
        Route::get('/negotiations/{negotiation}', [PhaseThreePageController::class, 'show'])->name('negotiations.show');
        Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
        Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:20,1')->name('reports.store');
        Route::middleware(['throttle:60,1', 'not-suspended'])->group(function (): void {
            Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
            Route::patch('/inventory/{entry}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::delete('/inventory/{entry}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
            Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
            Route::patch('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
            Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
            Route::post('/negotiations', [NegotiationController::class, 'store'])->name('negotiations.store');
            Route::post('/negotiations/{negotiation}/counter', [NegotiationController::class, 'counter'])->name('negotiations.counter');
            Route::post('/negotiations/{negotiation}/accept', [NegotiationController::class, 'accept'])->name('negotiations.accept');
            Route::post('/negotiations/{negotiation}/reject', [NegotiationController::class, 'reject'])->name('negotiations.reject');
            Route::post('/negotiations/{negotiation}/cancel', [NegotiationController::class, 'cancel'])->name('negotiations.cancel');
            Route::post('/negotiations/{negotiation}/server-choice', [DealController::class, 'proposeServer'])->name('negotiations.server-choice');
            Route::post('/negotiations/{negotiation}/server-accept', [DealController::class, 'acceptServer'])->name('negotiations.server-accept');
            Route::post('/negotiations/{negotiation}/final-accept', [DealController::class, 'finalAccept'])->name('negotiations.final-accept');
        });
        Route::middleware('operator')->prefix('operator')->name('operator.')->group(function (): void {
            Route::get('/audit', OperatorAuditController::class)->name('audit');
            Route::middleware('throttle:60,1')->group(function (): void {
                Route::patch('/reports/{report}', [ReportController::class, 'update'])->name('reports.update');
                Route::post('/suspensions', [SuspensionController::class, 'store'])->name('suspensions.store');
                Route::delete('/suspensions/{suspension}', [SuspensionController::class, 'destroy'])->name('suspensions.destroy');
            });
        });
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/', [AdminController::class, 'index'])->name('index');
            Route::get('/catalog-value-imports/template', [CatalogValueImportController::class, 'template'])->name('catalog-imports.template');
            Route::middleware('throttle:60,1')->group(function (): void {
                Route::patch('/settings/fair-threshold', [AdminController::class, 'threshold'])->name('threshold');
                Route::post('/catalog-value-imports', [CatalogValueImportController::class, 'store'])->name('catalog-imports.store');
                Route::post('/catalog-value-imports/{import}/publish', [CatalogValueImportController::class, 'publish'])->name('catalog-imports.publish');
                Route::post('/rods', [RodAdminController::class, 'store'])->name('rods.store');
                Route::patch('/rods/{rod}', [RodAdminController::class, 'update'])->name('rods.update');
                Route::patch('/catalog-items/{item}', [CatalogAdminController::class, 'update'])->name('catalog-items.update');
                Route::patch('/catalog-items/{item}/rod', [RodAdminController::class, 'assign'])->name('catalog-items.rod');
                Route::post('/catalog-items/{item}/thumbnail', [CatalogThumbnailController::class, 'update'])->name('catalog-items.thumbnail');
                Route::delete('/catalog-items/{item}/thumbnail', [CatalogThumbnailController::class, 'destroy'])->name('catalog-items.thumbnail.destroy');
            });
        });
        if (app()->environment(['local', 'development', 'staging', 'testing'])) {
            Route::middleware(['admin', 'throttle:60,1'])->prefix('admin/time-controls')->name('admin.time-controls.')->group(function (): void {
                Route::get('/', [AdminTimeControlController::class, 'index'])->name('index');
                Route::post('/pre-deals/{preDeal}/expire', [AdminTimeControlController::class, 'expirePreDeal'])->name('pre-deals.expire');
                Route::post('/deals/{deal}/expire-reveal', [AdminTimeControlController::class, 'expireReveal'])->name('deals.expire-reveal');
            });
        }
    });
});
