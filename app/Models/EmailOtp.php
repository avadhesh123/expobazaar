<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    protected $fillable = ['email', 'otp', 'expires_at', 'used'];
    protected $casts = ['expires_at' => 'datetime', 'used' => 'boolean'];

    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    public static function generate(string $email): self
    {
        // Invalidate old OTPs
        self::where('email', $email)->where('used', false)->update(['used' => true]);

        return self::create([
            'email' => $email,
            'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
