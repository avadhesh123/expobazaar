@extends('layouts.app')
@section('title', 'Change Password')
@section('page-title', 'Change Password')

@section('content')
<div style="max-width:500px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-lock" style="margin-right:.5rem;color:#1e3a5f;"></i> Change Password</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('auth.change-password.update') }}">
                @csrf

                @if(auth()->user()->password)
                <div class="form-group">
                    <label>Current Password <span style="color:#dc2626;">*</span></label>
                    <input type="password" name="current_password" required placeholder="Enter current password">
                    @error('current_password')<div style="color:#dc2626;font-size:.72rem;margin-top:.2rem;">{{ $message }}</div>@enderror
                </div>
                @else
                <div style="padding:.6rem .85rem;background:#dbeafe;border:1px solid #bfdbfe;border-radius:8px;font-size:.78rem;color:#1e40af;margin-bottom:1rem;">
                    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
                    No password set yet. You're currently using OTP login. Set a password below for faster access.
                </div>
                @endif

                <div class="form-group">
                    <label>New Password <span style="color:#dc2626;">*</span></label>
                    <input type="password" name="password" required minlength="6" placeholder="Min 6 characters">
                    @error('password')<div style="color:#dc2626;font-size:.72rem;margin-top:.2rem;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>Confirm New Password <span style="color:#dc2626;">*</span></label>
                    <input type="password" name="password_confirmation" required minlength="6" placeholder="Confirm new password">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save" style="margin-right:.3rem;"></i>
                    {{ auth()->user()->password ? 'Update Password' : 'Set Password' }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
