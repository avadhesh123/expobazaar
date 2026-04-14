<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{User, EmailOtp};
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Mail};

class AuthController extends Controller
{
    /**
     * Show login page
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Request OTP
     */
    public function requestOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->where('status', 'active')->first();

        if (!$user) {
            return back()->withErrors(['email' => 'No active account found with this email address.']);
        }

        $otp = EmailOtp::generate($request->email);

        // Send OTP via email
        try {
            Mail::to($request->email)->send(new OtpMail($otp->otp, $user->name));
        } catch (\Exception $e) {
            // Mail might fail in dev — continue anyway
        }

        return redirect()->route('auth.verify-otp', ['email' => $request->email])
            ->with('message', 'OTP sent to your email address.')
            ->with('debug_otp', $otp->otp); // For testing — remove in production
    }

    /**
     * Show OTP verification page
     */
    public function showVerifyOtp(Request $request)
    {
        return view('auth.verify-otp', ['email' => $request->email]);
    }

    /**
     * Verify OTP and login
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $otpRecord = EmailOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        $otpRecord->update(['used' => true]);

        $user = User::where('email', $request->email)->where('status', 'active')->first();

        if (!$user) {
            return redirect()->route('auth.login')->withErrors(['email' => 'Account not found or inactive.']);
        }

        $user->update([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ]);

        Auth::login($user, true);

        // Redirect based on user type
        return match ($user->user_type) {
            'admin' => redirect()->route('admin.dashboard'),
            'external' => redirect()->route('vendor.dashboard'),
            'internal' => redirect()->route($this->getInternalRoute($user)),
            default => redirect()->route('admin.dashboard'),
        };
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.login');
    }

    /**
     * Get redirect route for internal users based on department
     */
    private function getInternalRoute(User $user): string
    {
        return match ($user->department) {
            'sourcing' => 'sourcing.dashboard',
            'logistics' => 'logistics.dashboard',
            'cataloguing' => 'cataloguing.dashboard',
            'sales' => 'sales.dashboard',
            'finance' => 'finance.dashboard',
            'hod' => 'hod.dashboard',
            default => 'admin.dashboard',
        };
    }
}
