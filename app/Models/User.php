<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'user_type', 'department', 'company_codes',
        'status', 'phone', 'avatar', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'company_codes' => 'array',
    ];

    // Relationships
    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    public function roles()
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles');
    }

    public function permissions()
    {
        return $this->morphToMany(Permission::class, 'model', 'model_has_permissions');
    }

    // Scopes
    public function scopeAdmins($query)
    {
        return $query->where('user_type', 'admin');
    }

    public function scopeInternal($query)
    {
        return $query->where('user_type', 'internal');
    }

    public function scopeExternal($query)
    {
        return $query->where('user_type', 'external');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByCompanyCode($query, string $code)
    {
        return $query->whereJsonContains('company_codes', $code);
    }

    // Helpers
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function isVendor(): bool
    {
        return $this->user_type === 'external';
    }

    public function isInternal(): bool
    {
        return $this->user_type === 'internal';
    }

    public function hasCompanyAccess(string $code): bool
    {
        return $this->isAdmin() || in_array($code, $this->company_codes ?? []);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) return true;
        return $this->permissions()->where('name', $permission)->exists()
            || $this->roles()->whereHas('permissions', fn($q) => $q->where('name', $permission))->exists();
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
