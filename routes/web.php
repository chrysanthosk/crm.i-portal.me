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

// ✅ NEW: Settings pages for service categories / vat types
use App\Http\Controllers\Admin\Settings\ServiceCategoryController;
use App\Http\Controllers\Admin\Settings\VatTypeController;

// Staff + Clients
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\StaffController;

// Appointments
use App\Http\Controllers\AppointmentController;

// Services
use App\Http\Controllers\ServiceController;

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
        Route::patch('/{appointment}/move', [AppointmentController::class, 'move'])->name('move');

        Route::get('/list', [AppointmentController::class, 'list'])->name('list');
        Route::get('/export', [AppointmentController::class, 'export'])->name('export');
    });

    // ✅ Services (permission: services.manage)
    Route::prefix('services')->name('services.')->middleware('permission:services.manage')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->name('index');
        Route::get('/create', [ServiceController::class, 'create'])->name('create');
        Route::post('/', [ServiceController::class, 'store'])->name('store');
        Route::get('/{service}/edit', [ServiceController::class, 'edit'])->name('edit');
        Route::put('/{service}', [ServiceController::class, 'update'])->name('update');
        Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('destroy');

        Route::post('/import', [ServiceController::class, 'import'])->name('import');

        // ✅ Blade expects services.import.template
        Route::get('/import/template', [ServiceController::class, 'downloadTemplate'])->name('import.template');

        // optional: also keep the shorter name if you want
        Route::get('/template', [ServiceController::class, 'downloadTemplate'])->name('template');
    });

    /*
     * SETTINGS (admin-like)
     */
    Route::prefix('settings')->name('settings.')->middleware(['permission:admin.access'])->group(function () {

        // Users
        Route::resource('users', UserController::class)->except(['show'])->middleware('permission:user.manage');

        // Roles + permissions
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:role.manage');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:role.manage');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:role.manage');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:role.manage');
        Route::post('roles/{role}/permissions', [RolePermissionController::class, 'sync'])
            ->name('roles.permissions.sync')->middleware('permission:role.manage');

        // SMTP
        Route::get('smtp', [SmtpController::class, 'edit'])->name('smtp.edit')->middleware('permission:settings.smtp');
        Route::put('smtp', [SmtpController::class, 'update'])->name('smtp.update')->middleware('permission:settings.smtp');
        Route::post('smtp/test', [SmtpController::class, 'test'])->name('smtp.test')->middleware('permission:settings.smtp');

        // Configuration
        Route::get('configuration', [ConfigurationController::class, 'edit'])->name('config.edit')->middleware('permission:settings.config');
        Route::put('configuration/system', [ConfigurationController::class, 'updateSystem'])->name('config.system.update')->middleware('permission:settings.config');

        // Audit log
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index')->middleware('permission:audit.view');

        // ✅ NEW: Service Categories + VAT Types settings pages
        Route::resource('service-categories', ServiceCategoryController::class)->except(['show'])
            ->middleware('permission:services.manage');

        Route::resource('vat-types', VatTypeController::class)->except(['show'])
            ->middleware('permission:services.manage');
    });
});

require __DIR__ . '/auth.php';
