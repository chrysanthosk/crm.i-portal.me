<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ThemeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;

// ✅ Calendar View
use App\Http\Controllers\CalendarViewController;

// ✅ Bulk SMS
use App\Http\Controllers\BulkSmsController;

// ✅ Clients is NOT under Admin anymore
use App\Http\Controllers\ClientController;

// ✅ Inventory
use App\Http\Controllers\InventoryController;

// Staff stays as you had it
use App\Http\Controllers\Admin\StaffController;

// ✅ Suppliers
use App\Http\Controllers\SupplierController;

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

// SMS Settings + Logs
use App\Http\Controllers\Admin\Settings\SmsSettingsController;
use App\Http\Controllers\Admin\Settings\SmsLogsController;

// Payment Methods + Loyalty (Settings)
use App\Http\Controllers\Admin\Settings\PaymentMethodController;
use App\Http\Controllers\Admin\Settings\LoyaltyController;

// Appointments
use App\Http\Controllers\AppointmentController;

// Services
use App\Http\Controllers\ServiceController;

// Products
use App\Http\Controllers\ProductController;

// POS
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosSalesController;

// REPORTS
use App\Http\Controllers\Reports\ReportsController;

// FINANCIAL
use App\Http\Controllers\Reports\FinanceController;

// GDPR
use App\Http\Controllers\Admin\Settings\GdprController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::post('/theme/toggle', [ThemeController::class, 'toggle'])->name('theme.toggle');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::view('/dashboard', 'dashboard')->name('dashboard');

    /*
     * ✅ Calendar View (read-only calendar for users who can view, full control for appointment.manage)
     */
    Route::get('/calendar-view', [CalendarViewController::class, 'index'])
        ->name('calendar_view.index');

    Route::get('/calendar-view/today-rows', [CalendarViewController::class, 'todayRows'])
        ->name('calendar_view.today_rows');

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

    // ✅ Clients + Import + Export
    Route::middleware('permission:client.manage')->group(function () {
        Route::get('/clients/export', [ClientController::class, 'export'])->name('clients.export');
        Route::get('/clients/import/template', [ClientController::class, 'downloadTemplate'])->name('clients.import.template');
        Route::post('/clients/import', [ClientController::class, 'import'])->name('clients.import');
        Route::resource('clients', ClientController::class)->except(['show']);
    });

    // ✅ Inventory
    Route::middleware('permission:inventory.manage')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/save', [InventoryController::class, 'save'])->name('inventory.save');
    });

    // Staff
    Route::resource('staff', StaffController::class)->except(['show'])
        ->parameters(['staff' => 'staffMember'])
        ->middleware('permission:staff.manage');

    // ✅ Suppliers (Manage + Import + Export + Template)
    Route::middleware('permission:suppliers.manage')->group(function () {
        Route::get('/suppliers/export', [SupplierController::class, 'export'])->name('suppliers.export');
        Route::get('/suppliers/template', [SupplierController::class, 'template'])->name('suppliers.template');
        Route::post('/suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');
        Route::resource('suppliers', SupplierController::class)->except(['show', 'create', 'edit']);
    });

    /*
     * ✅ Bulk SMS (Send Now)
     * Permission key is bulk_sms.send (as per your seeder)
     */
    Route::middleware('permission:bulk_sms.send')->group(function () {
        Route::get('/bulk-sms', [BulkSmsController::class, 'index'])->name('bulk_sms.index');
        Route::post('/bulk-sms/send', [BulkSmsController::class, 'send'])->name('bulk_sms.send');
    });

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

    // Services + Import + Export
    Route::prefix('services')->name('services.')->middleware('permission:services.manage')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->name('index');
        Route::get('/export', [ServiceController::class, 'export'])->name('export');

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
     * POS (non-admin)
     */
    Route::middleware('permission:cashier.manage')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');

        Route::get('/pos/sales', [PosSalesController::class, 'index'])->name('pos.sales.index');
        Route::post('/pos/sales/{sale}/void', [PosSalesController::class, 'void'])->name('pos.sales.void');
        Route::delete('/pos/sales/{sale}', [PosSalesController::class, 'destroy'])->name('pos.sales.destroy');
    });

    /*
     * REPORTS
     */
    Route::prefix('reports')->name('reports.')->group(function () {

        Route::get('/', [ReportsController::class, 'index'])
            ->name('index')
            ->middleware('permission:reports.view');

        Route::get('/analytics', [ReportsController::class, 'analytics'])
            ->name('analytics')
            ->middleware('permission:analytics.view');

        Route::get('/bi', [ReportsController::class, 'bi'])
            ->name('bi')
            ->middleware('permission:reporting.view');

        Route::get('/data', [ReportsController::class, 'data'])
            ->name('data')
            ->middleware('permission:reporting.view');

        // ✅ Staff Performance module under Reports dropdown
        Route::get('/staff-performance', [ReportsController::class, 'staffPerformance'])
            ->name('staff_performance')
            ->middleware('permission:staff_reports.view');

        Route::post('/zreport/generate', [ReportsController::class, 'zReportGenerate'])
            ->name('zreport.generate')
            ->middleware('permission:zreports.manage');

        Route::get('/zreport/{id}', [ReportsController::class, 'zReportPrint'])
            ->name('zreport.print')
            ->middleware('permission:reports.view');

        Route::delete('/zreport/{id}', [ReportsController::class, 'zReportDelete'])
            ->name('zreport.delete')
            ->middleware('permission:zreports.manage');

        Route::get('/analytics/pdf', function (\Illuminate\Http\Request $request) {
            return app(ReportsController::class)->pdf('analytics', $request);
        })->name('analytics.pdf')->middleware('permission:analytics.view');

        Route::get('/bi/pdf', function (\Illuminate\Http\Request $request) {
            return app(ReportsController::class)->pdf('bi', $request);
        })->name('bi.pdf')->middleware('permission:reporting.view');

        Route::get('/pdf/{report}', [ReportsController::class, 'pdf'])
            ->name('pdf')
            ->middleware('permission:reports.view');

        Route::prefix('financial')->name('financial.')->middleware('permission:reporting.view')->group(function () {
            Route::get('/income', [FinanceController::class, 'income'])->name('income');
            Route::post('/income/save', [FinanceController::class, 'incomeSave'])->name('income.save');
            Route::post('/income/import', [FinanceController::class, 'incomeImport'])->name('income.import');

            Route::get('/expenses', [FinanceController::class, 'expenses'])->name('expenses');
            Route::post('/expenses/save', [FinanceController::class, 'expensesSave'])->name('expenses.save');
            Route::post('/expenses/import', [FinanceController::class, 'expensesImport'])->name('expenses.import');
        });
    });

    /*
     * SETTINGS (admin-like)
     */
    Route::prefix('settings')->name('settings.')->middleware(['permission:admin.access'])->group(function () {

        Route::resource('users', UserController::class)->except(['show'])
            ->middleware('permission:user.manage');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:role.manage');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:role.manage');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:role.manage');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:role.manage');

        Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'sync'])
            ->name('roles.permissions.sync')->middleware('permission:role.manage');

        Route::get('/smtp', [SmtpController::class, 'edit'])->name('smtp.edit')->middleware('permission:settings.smtp');
        Route::put('/smtp', [SmtpController::class, 'update'])->name('smtp.update')->middleware('permission:settings.smtp');
        Route::post('/smtp/test', [SmtpController::class, 'test'])->name('smtp.test')->middleware('permission:settings.smtp');

        Route::get('/configuration', [ConfigurationController::class, 'edit'])->name('config.edit')->middleware('permission:settings.config');

        Route::put('/configuration/system', [ConfigurationController::class, 'updateSystem'])->name('config.system.update')->middleware('permission:settings.config');
        Route::put('/configuration/company', [ConfigurationController::class, 'updateCompany'])->name('config.company.update')->middleware('permission:settings.config');
        Route::put('/configuration/sms', [ConfigurationController::class, 'updateSms'])->name('config.sms.update')->middleware('permission:settings.config');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index')->middleware('permission:audit.view');

        Route::resource('service-categories', ServiceCategoryController::class)->except(['show'])->middleware('permission:services.manage');
        Route::resource('vat-types', VatTypeController::class)->except(['show'])->middleware('permission:services.manage');

        Route::middleware('permission:products.manage')->group(function () {
            Route::get('/product-categories/export', [SettingsProductCategoryController::class, 'export'])->name('product-categories.export');
            Route::get('/product-categories/template', [SettingsProductCategoryController::class, 'template'])->name('product-categories.template');
            Route::post('/product-categories/import', [SettingsProductCategoryController::class, 'import'])->name('product-categories.import');
            Route::resource('product-categories', SettingsProductCategoryController::class)->except(['show']);
        });

        Route::middleware('permission:payment_methods.manage')->group(function () {
            Route::resource('payment-methods', PaymentMethodController::class)->except(['show']);
        });

        Route::middleware('permission:loyalty.manage')->group(function () {
            Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
            Route::post('/loyalty/tiers', [LoyaltyController::class, 'saveTier'])->name('loyalty.tiers.save');
            Route::delete('/loyalty/tiers/{tier}', [LoyaltyController::class, 'deleteTier'])->name('loyalty.tiers.delete');
            Route::post('/loyalty/adjust', [LoyaltyController::class, 'adjust'])->name('loyalty.adjust');
            Route::post('/loyalty/settings', [LoyaltyController::class, 'saveSettings'])->name('loyalty.settings.save');
        });

        Route::middleware('permission:sms.manage')->group(function () {
            Route::get('/sms', [SmsSettingsController::class, 'edit'])->name('sms.edit');
            Route::get('/sms/logs', [SmsLogsController::class, 'index'])->name('sms.logs');

            Route::post('/sms/providers/save', [SmsSettingsController::class, 'saveProvider'])->name('sms.providers.save');
            Route::delete('/sms/providers/{provider}', [SmsSettingsController::class, 'deleteProvider'])->name('sms.providers.delete');

            Route::post('/sms/providers/{provider}/toggle', [SmsSettingsController::class, 'toggleProviderActive'])->name('sms.providers.toggle');
            Route::post('/sms/providers/priority', [SmsSettingsController::class, 'updatePriority'])->name('sms.providers.priority');
            Route::get('/sms/providers/{provider}/settings', [SmsSettingsController::class, 'fetchSettings'])->name('sms.providers.settings');

            Route::post('/sms/settings/save', [SmsSettingsController::class, 'saveSettings'])->name('sms.settings.save');
            Route::post('/sms/test', [SmsSettingsController::class, 'sendTestSms'])->name('sms.test');
        });

        /*
         * GDPR (Data Purge)
         */
        Route::get('/gdpr', [GdprController::class, 'index'])->name('gdpr.index')->middleware('permission:gdpr.manage');
        Route::post('/gdpr/clients/{client}/purge', [GdprController::class, 'purgeClient'])->name('gdpr.clients.purge')->middleware('permission:gdpr.manage');
    });
});

require __DIR__ . '/auth.php';
