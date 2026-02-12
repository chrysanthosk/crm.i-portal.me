<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'permission_key',
        'permission_name',
        'permission_group',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }
}
