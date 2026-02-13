<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ThemeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;

// Settings/admin-ish
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\Settings\SmtpController;
use App\Http\Controllers\Admin\Settings\ConfigurationController;

// Settings pages for service categories / vat types / product categories
use App\Http\Controllers\Admin\Settings\ServiceCategoryController;
use App\Http\Controllers\Admin\Settings\VatTypeController;
use App\Http\Controllers\Admin\Settings\ProductCategoryController as SettingsProductCategoryController;

// Staff + Clients
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\StaffController;

// Appointments
use App\Http\Controllers\AppointmentController;

// Services
use App\Http\Controllers\ServiceController;

// Products
use App\Http\Controllers\ProductController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::post('/theme/toggle', [ThemeController::class, 'toggle'])->name('theme.toggle');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/email/request', [ProfileController::class, 'requestEmailChange'])->name('profile.email.request');
    Route::get('/profile/email/confirm/{token}', [ProfileController::class, 'confirmEmailChange'])->name('profile.email.confirm');

    // 2FA
    Route::get('/profile/2fa', [TwoFactorController::class, 'show'])->name('profile.2fa.show');
    Route::post('/profile/2fa/enable', [TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('profile.2fa.confirm');
    Route::post('/profile/2fa/disable', [TwoFactorController::class, 'disable'])->name('profile.2fa.disable');
    Route::post('/profile/2fa/recovery/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])
        ->name('profile.2fa.recovery.regenerate');

    /*
     * MODULES (non-admin/settings)
     */

    // Clients
    Route::resource('clients', ClientController::class)->except(['show'])
        ->middleware('permission:client.manage');

    // Staff
    Route::resource('staff', StaffController::class)->except(['show'])
        ->parameters(['staff' => 'staffMember'])
        ->middleware('permission:staff.manage');

    // Appointments
    Route::prefix('appointments')->name('appointments.')->middleware('permission:appointment.manage')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('index');

        Route::get('/create', [AppointmentController::class, 'create'])->name('create');
        Route::get('/{appointment}/edit', [AppointmentController::class, 'edit'])->name('edit');

        Route::post('/', [AppointmentController::class, 'store'])->name('store');
        Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('update');
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->name('destroy');

        Route::get('/resources', [AppointmentController::class, 'resources'])->name('resources');
        Route::get('/events', [AppointmentController::class, 'events'])->name('events');
        Route::get('/services', [AppointmentController::class, 'servicesByCategory'])->name('services');
        Route::patch('/{appointment}/move', [AppointmentController::class, 'move'])->name('move');

        Route::get('/list', [AppointmentController::class, 'list'])->name('list');
        Route::get('/export', [AppointmentController::class, 'export'])->name('export');
    });

    // Services
    Route::prefix('services')->name('services.')->middleware('permission:services.manage')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->name('index');
        Route::get('/create', [ServiceController::class, 'create'])->name('create');
        Route::post('/', [ServiceController::class, 'store'])->name('store');
        Route::get('/{service}/edit', [ServiceController::class, 'edit'])->name('edit');
        Route::put('/{service}', [ServiceController::class, 'update'])->name('update');
        Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('destroy');

        Route::post('/import', [ServiceController::class, 'import'])->name('import');
        Route::get('/import/template', [ServiceController::class, 'downloadTemplate'])->name('import.template');
        Route::get('/template', [ServiceController::class, 'downloadTemplate'])->name('template');
    });

    /*
     * PRODUCTS (module page outside settings)
     */
    Route::middleware('permission:products.manage')->group(function () {
        Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
        Route::get('/products/template', [ProductController::class, 'template'])->name('products.template');
        Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');

        Route::resource('products', ProductController::class)->except(['show']);
    });

    /*
     * SETTINGS (admin-like)
     */
    Route::prefix('settings')->name('settings.')->middleware(['permission:admin.access'])->group(function () {

        Route::resource('users', UserController::class)->except(['show'])
            ->middleware('permission:user.manage');

        Route::get('/roles', [RoleController::class, 'index'])
            ->name('roles.index')->middleware('permission:role.manage');
        Route::post('/roles', [RoleController::class, 'store'])
            ->name('roles.store')->middleware('permission:role.manage');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->name('roles.update')->middleware('permission:role.manage');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->name('roles.destroy')->middleware('permission:role.manage');

        Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'sync'])
            ->name('roles.permissions.sync')->middleware('permission:role.manage');

        Route::get('/smtp', [SmtpController::class, 'edit'])
            ->name('smtp.edit')->middleware('permission:settings.smtp');
        Route::put('/smtp', [SmtpController::class, 'update'])
            ->name('smtp.update')->middleware('permission:settings.smtp');
        Route::post('/smtp/test', [SmtpController::class, 'test'])
            ->name('smtp.test')->middleware('permission:settings.smtp');

        Route::get('/configuration', [ConfigurationController::class, 'edit'])
            ->name('config.edit')->middleware('permission:settings.config');
        Route::put('/configuration/system', [ConfigurationController::class, 'updateSystem'])
            ->name('config.system.update')->middleware('permission:settings.config');

        Route::get('/audit', [AuditController::class, 'index'])
            ->name('audit.index')->middleware('permission:audit.view');

        Route::resource('service-categories', ServiceCategoryController::class)->except(['show'])
            ->middleware('permission:services.manage');

        Route::resource('vat-types', VatTypeController::class)->except(['show'])
            ->middleware('permission:services.manage');

        // Product Categories (under Settings)
        Route::middleware('permission:products.manage')->group(function () {
            Route::get('/product-categories/export', [SettingsProductCategoryController::class, 'export'])
                ->name('product-categories.export');

            Route::get('/product-categories/template', [SettingsProductCategoryController::class, 'template'])
                ->name('product-categories.template');

            Route::post('/product-categories/import', [SettingsProductCategoryController::class, 'import'])
                ->name('product-categories.import');

            Route::resource('product-categories', SettingsProductCategoryController::class)->except(['show']);
        });
    });
});

require __DIR__ . '/auth.php';
