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
        $ownerRole = Role::firstOrCreate(
            ['role_key' => 'owner'],
            ['role_name' => 'Owner', 'role_desc' => 'Clinic owner with full business access']
        );

        $adminRole = Role::firstOrCreate(
            ['role_key' => 'admin'],
            ['role_name' => 'Admin', 'role_desc' => 'Administrative full access']
        );

        $receptionRole = Role::firstOrCreate(
            ['role_key' => 'reception'],
            ['role_name' => 'Reception', 'role_desc' => 'Reception/front-desk operations']
        );

        $userRole = Role::firstOrCreate(
            ['role_key' => 'user'],
            ['role_name' => 'User', 'role_desc' => 'Standard user']
        );

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

        $adminRole->permissions()->syncWithoutDetaching(\App\Models\Permission::pluck('id')->all());
        $ownerRole->permissions()->syncWithoutDetaching(\App\Models\Permission::pluck('id')->all());
        $receptionRole->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('permission_key', [
                'appointment.manage',
                'calendar_view.view',
                'client.manage',
                'cashier.manage',
                'services.manage',
                'inventory.manage',
                'suppliers.manage',
                'reports.view',
                'zreports.manage',
                'bulk_sms.send',
            ])->pluck('id')->all()
        );

        $adminEmail    = env('SEED_ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('SEED_ADMIN_PASSWORD', 'ChangeMe123!!');

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'name' => 'Admin User',
                'role' => 'owner',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
                'theme' => 'light',
            ]
        );

        SmtpSetting::firstOrCreate([], ['enabled' => false]);

        Setting::firstOrCreate(['key' => 'system'], ['value' => [
            'header_name' => config('app.name'),
            'footer_name' => config('app.name'),
        ]]);
    }
}
