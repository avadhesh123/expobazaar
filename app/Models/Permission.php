<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name', 'guard_name', 'module', 'display_name'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }
}
