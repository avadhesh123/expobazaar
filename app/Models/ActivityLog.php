<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'module', 'subject_type', 'subject_id',
        'old_values', 'new_values', 'description', 'ip_address',
    ];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function subject() { return $this->morphTo(); }

    public static function log(string $action, string $module, Model $subject, ?array $old = null, ?array $new = null, ?string $description = null): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}
