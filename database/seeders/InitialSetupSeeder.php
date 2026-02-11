<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SmtpSetting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $adminRole = Role::firstOrCreate(
            ['role_key' => 'admin'],
            ['role_name' => 'Admin', 'role_desc' => 'Full access']
        );

        $userRole = Role::firstOrCreate(
            ['role_key' => 'user'],
            ['role_name' => 'User', 'role_desc' => 'Standard user']
        );

        // Core permissions (grow this list as you add new modules)
        $perms = [
            ['permission_key' => 'admin.access', 'permission_group' => 'admin', 'permission_desc' => 'Access admin area'],
            ['permission_key' => 'user.manage', 'permission_group' => 'admin', 'permission_desc' => 'Manage users'],
            ['permission_key' => 'role.manage', 'permission_group' => 'admin', 'permission_desc' => 'Manage roles & permissions'],
            ['permission_key' => 'settings.smtp', 'permission_group' => 'settings', 'permission_desc' => 'Manage SMTP settings'],
            ['permission_key' => 'settings.config', 'permission_group' => 'settings', 'permission_desc' => 'Manage configuration settings'],
            ['permission_key' => 'audit.view', 'permission_group' => 'settings', 'permission_desc' => 'View audit log'],
        ];

        $permIds = [];
        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['permission_key' => $p['permission_key']], $p);
            $permIds[] = $perm->id;
        }

        // Admin role gets all current permissions (though code also shortcuts role=admin)
        $adminRole->permissions()->syncWithoutDetaching($permIds);

        // User role gets just admin.access? NO - keep admin area restricted.
        // If later you want a “backoffice user” role, you can grant admin.access without admin role.

        // Default admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'name' => 'Admin User',
                'role' => 'admin',
                'password' => Hash::make('ChangeMe123!!'),
                'email_verified_at' => now(),
                'theme' => 'light',
            ]
        );

        // SMTP row
        SmtpSetting::firstOrCreate([], ['enabled' => false]);

        // System settings
        Setting::firstOrCreate(['key' => 'system'], ['value' => [
            'header_name' => config('app.name'),
            'footer_name' => config('app.name'),
        ]]);
    }
}
