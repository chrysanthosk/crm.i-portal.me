<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ThemeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\Settings\SmtpController;
use App\Http\Controllers\Admin\Settings\ConfigurationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Theme toggle (works for guests too; stores theme in session, and for logged-in users in DB)
Route::post('/theme/toggle', [ThemeController::class, 'toggle'])->name('theme.toggle');

Route::middleware(['auth', 'verified', 'theme'])->group(function () {

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

    // Admin area
    Route::prefix('admin')->name('admin.')->middleware(['permission:admin.access'])->group(function () {

        // Users
        Route::resource('users', UserController::class)->except(['show'])->middleware('permission:user.manage');

        // Roles + permissions
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:role.manage');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:role.manage');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:role.manage');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:role.manage');
        Route::post('roles/{role}/permissions', [RolePermissionController::class, 'sync'])
            ->name('roles.permissions.sync')->middleware('permission:role.manage');

        // Settings (admin-only)
        Route::get('settings/smtp', [SmtpController::class, 'edit'])->name('settings.smtp.edit')->middleware('permission:settings.smtp');
        Route::put('settings/smtp', [SmtpController::class, 'update'])->name('settings.smtp.update')->middleware('permission:settings.smtp');
        Route::post('settings/smtp/test', [SmtpController::class, 'test'])->name('settings.smtp.test')->middleware('permission:settings.smtp');

        Route::get('settings/configuration', [ConfigurationController::class, 'edit'])->name('settings.config.edit')->middleware('permission:settings.config');
        Route::put('settings/configuration/system', [ConfigurationController::class, 'updateSystem'])
            ->name('settings.config.system.update')->middleware('permission:settings.config');

        // Audit log (admin-only)
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index')->middleware('permission:audit.view');
    });
});

require __DIR__.'/auth.php';
