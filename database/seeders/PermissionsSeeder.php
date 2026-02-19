<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $perms = [
            // Admin / Settings access
            ['key' => 'admin.access',            'name' => 'Access Admin/Settings',          'group' => 'Admin'],

            // Users / Roles
            ['key' => 'user.manage',             'name' => 'Manage Users',                   'group' => 'Settings'],
            ['key' => 'role.manage',             'name' => 'Manage Roles',                   'group' => 'Settings'],

            // Settings pages
            ['key' => 'settings.smtp',           'name' => 'Manage SMTP',                    'group' => 'Settings'],
            ['key' => 'settings.config',         'name' => 'Manage Configuration',          'group' => 'Settings'],
            ['key' => 'audit.view',              'name' => 'View Audit Log',                 'group' => 'Settings'],

            // Core modules
            ['key' => 'appointment.manage',      'name' => 'Manage Appointments',            'group' => 'Appointments'],
            ['key' => 'client.manage',           'name' => 'Manage Clients',                 'group' => 'Clients'],
            ['key' => 'staff.manage',            'name' => 'Manage Staff',                   'group' => 'Staff'],
            ['key' => 'services.manage',         'name' => 'Manage Services',                'group' => 'Services'],
            ['key' => 'products.manage',         'name' => 'Manage Products',                'group' => 'Products'],

            // ✅ Calendar View (read-only access)
            ['key' => 'calendar_view.view',      'name' => 'View Calendar',                  'group' => 'Appointments'],

            // ✅ Suppliers
            ['key' => 'suppliers.manage',        'name' => 'Manage Suppliers',               'group' => 'Suppliers'],

            // ✅ Bulk SMS (Send Now)
            ['key' => 'bulk_sms.send',           'name' => 'Send Bulk SMS (Send Now)',       'group' => 'Suppliers'],

            // Inventory
            ['key' => 'inventory.manage',        'name' => 'Manage Inventory',               'group' => 'Inventory'],

            // GDPR
            ['key' => 'gdpr.manage',             'name' => 'Manage GDPR Data Purge',         'group' => 'Settings'],

            // POS
            ['key' => 'cashier.manage',          'name' => 'Access POS / Cashier',           'group' => 'POS'],

            // Reports
            ['key' => 'reports.view',            'name' => 'View Operational Reports',       'group' => 'Reports'],
            ['key' => 'analytics.view',          'name' => 'View Analytics Dashboard',       'group' => 'Reports'],
            ['key' => 'reporting.view',          'name' => 'View BI / Financial Reporting',  'group' => 'Reports'],

            ['key' => 'staff_reports.view',      'name' => 'View Staff Performance',         'group' => 'Reports'],

            // Z Reports
            ['key' => 'zreports.manage',         'name' => 'Generate/Delete Z Reports',      'group' => 'Reports'],

            // SMS (settings/logs area)
            ['key' => 'sms.manage',              'name' => 'Manage SMS Settings & Logs',     'group' => 'Settings'],

            // Payment / Loyalty
            ['key' => 'payment_methods.manage',  'name' => 'Manage Payment Methods',         'group' => 'Settings'],
            ['key' => 'loyalty.manage',          'name' => 'Manage Loyalty & Rewards',       'group' => 'Settings'],
        ];

        foreach ($perms as $p) {
            DB::table('permissions')->updateOrInsert(
                ['permission_key' => $p['key']],
                [
                    'permission_name'  => $p['name'],
                    'permission_group' => $p['group'],
                    'updated_at'       => $now,
                    'created_at'       => $now,
                ]
            );
        }
    }
}
