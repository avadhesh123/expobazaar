<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    //protected $fillable = ['name', 'guard_name', 'module', 'display_name'];
    protected $fillable = ['name', 'guard_name', 'module', 'entity', 'action', 'display_name', 'is_system'];

    // public function users()
    // {
    //     return $this->belongsToMany(User::class, 'model_has_permissions', 'permission_id', 'model_id')
    //         ->where('model_type', User::class);
    // }
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }
}
