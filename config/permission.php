<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'acl_roles',
        'permissions' => 'acl_permissions',
        'model_has_permissions' => 'acl_model_has_permissions',
        'model_has_roles' => 'acl_model_has_roles',
        'role_has_permissions' => 'acl_role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'tenant_id',
    ],

    'register_permission_check_method' => true,

    'teams' => false,
    'teams_key' => 'team_id',

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],

    'permission' => [
        'use_passport_client_credentials' => false,
        'display_permission_in_exception' => false,
        'display_role_in_exception' => false,
    ],
];
