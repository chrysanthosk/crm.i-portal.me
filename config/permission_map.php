<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Permission mapping based on route name prefixes
     |--------------------------------------------------------------------------
     | For each prefix, we define:
     | - key: permission_key to use in DB
     | - name: human label
     | - group: grouping name in the Roles UI
     |
     | Example route name: "settings.vat-types.index"
     | Prefix to match: "settings.vat-types."
     */

    [
        'prefix' => 'appointments.',
        'key'    => 'appointment.manage',
        'name'   => 'Manage Appointments',
        'group'  => 'Appointments',
    ],

    [
        'prefix' => 'clients.',
        'key'    => 'client.manage',
        'name'   => 'Manage Clients',
        'group'  => 'Clients',
    ],

    [
        'prefix' => 'staff.',
        'key'    => 'staff.manage',
        'name'   => 'Manage Staff',
        'group'  => 'Staff',
    ],

    [
        'prefix' => 'services.',
        'key'    => 'services.manage',
        'name'   => 'Manage Services',
        'group'  => 'Services',
    ],

    // Settings (admin access)
    [
        'prefix' => 'settings.users.',
        'key'    => 'user.manage',
        'name'   => 'Manage Users',
        'group'  => 'Settings',
    ],

    [
        'prefix' => 'settings.roles.',
        'key'    => 'role.manage',
        'name'   => 'Manage Roles',
        'group'  => 'Settings',
    ],

    [
        'prefix' => 'settings.smtp.',
        'key'    => 'settings.smtp',
        'name'   => 'SMTP Settings',
        'group'  => 'Settings',
    ],

    [
        'prefix' => 'settings.config.',
        'key'    => 'settings.config',
        'name'   => 'System Configuration',
        'group'  => 'Settings',
    ],

    [
        'prefix' => 'settings.audit.',
        'key'    => 'audit.view',
        'name'   => 'View Audit Log',
        'group'  => 'Settings',
    ],

    // NEW pages you created:
    [
        'prefix' => 'settings.service-categories.',
        'key'    => 'services.manage',
        'name'   => 'Service Categories',
        'group'  => 'Settings',
    ],

    [
        'prefix' => 'settings.vat-types.',
        'key'    => 'services.manage',
        'name'   => 'VAT Types',
        'group'  => 'Settings',
    ],
];
