<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{User, EmailOtp};
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Mail, Hash};

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Login with Password
     */
    public function loginWithPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)->where('status', 'active')->first();

        if (!$user) {
            return back()->withInput()->withErrors(['email' => 'No active account found with this email.']);
        }

        if (!$user->password) {
            return back()->withInput()->withErrors(['password' => 'Password not set. Please use OTP login or contact admin.']);
        }

        if (!Hash::check($request->password, $user->password)) {
            return back()->withInput()->withErrors(['password' => 'Incorrect password.']);
        }

        $user->update(['last_login_at' => now()]);
        Auth::login($user, $request->boolean('remember'));

        return $this->redirectAfterLogin($user);
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

        try {
            $otp = EmailOtp::generate($request->email);
            Mail::to($request->email)->send(new OtpMail($otp->otp, $user->name));
        } catch (\Exception $e) {
            \Log::error("OTP send failed: {$e->getMessage()}");
            return back()->withErrors(['email' => 'Failed to send OTP. Please try password login or contact admin.']);
        }

        return redirect()->route('auth.verify-otp', ['email' => $request->email])
            ->with('message', 'OTP sent to your email address.')
            ->with('debug_otp', $otp->otp); // For testing — remove in production

        // return redirect()->route('auth.verify-otp', ['email' => $request->email])
        //     ->with('message', 'OTP sent to your email address.');
    }

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
            'otp'   => 'required|string|size:6',
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

        return $this->redirectAfterLogin($user);
    }

    /**
     * Change Password (from profile)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'nullable|string',
            'password'         => 'required|string|min:6|confirmed',
        ]);

        $user = auth()->user();

        // If user has existing password, verify current
        if ($user->password && !Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Set Password (admin sets for a user)
     */
    public function setPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', "Password set for {$user->name}.");
    }


    // ─── 1. FORGOT PASSWORD (OTP-based recovery) ─────────────────────

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->where('status', 'active')->first();

        if (!$user) {
            return back()->withErrors(['email' => 'No active account found with this email.']);
        }

        try {
            $otp = EmailOtp::generate($request->email);
            Mail::to($request->email)->send(new OtpMail($otp->otp, $user->name, 'Password Reset'));
        } catch (\Exception $e) {
            \Log::error("Password reset OTP failed: {$e->getMessage()}");
            return back()->withErrors(['email' => 'Failed to send OTP. Please try again.']);
        }

        return redirect()->route('auth.reset-password', ['email' => $request->email])
            ->with('message', 'OTP sent to your email. Please enter it below with your new password.');
    }

    public function showResetPassword(Request $request)
    {
        return view('auth.reset-password', ['email' => $request->email]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'otp'      => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $otpRecord = EmailOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please request a new one.']);
        }

        $otpRecord->update(['used' => true]);

        $user = User::where('email', $request->email)->where('status', 'active')->first();
        if (!$user) {
            return redirect()->route('auth.login')->withErrors(['email' => 'Account not found.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return redirect()->route('auth.login')->with('message', 'Password reset successfully. You can now login with your new password.');
    }

    // ─── 2. CHANGE PASSWORD (logged-in user) ──────────────────────────

    public function showChangePassword()
    {
        return view('auth.change-password');
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
     * Redirect after login based on user type
     */
    private function redirectAfterLogin(User $user)
    {
        // Set first company as active
        $companyCodes = $user->company_codes ?? [];
        if (!empty($companyCodes)) {
            session(['active_company' => $companyCodes[0]]);
        }

        return match ($user->user_type) {
            'admin' => redirect()->route('admin.dashboard'),
            'external' => redirect()->route('vendor.dashboard'),
            'internal' => redirect()->route($this->getInternalRoute($user)),
            default => redirect()->route('admin.dashboard'),
        };
    }

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
