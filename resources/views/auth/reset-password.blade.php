<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Expo Bazaar SCM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;}
        .card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);width:100%;max-width:400px;padding:2.5rem;}
        h2{font-size:1.4rem;font-weight:800;color:#0d1b2a;margin-bottom:.3rem;}
        p.sub{font-size:.82rem;color:#64748b;margin-bottom:1.5rem;line-height:1.5;}
        .form-group{margin-bottom:1.25rem;}
        label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.35rem;}
        input{width:100%;padding:.7rem .9rem;border:1.5px solid #d1d5db;border-radius:10px;font-size:.88rem;font-family:inherit;}
        input:focus{outline:none;border-color:#1e3a5f;box-shadow:0 0 0 3px rgba(30,58,95,.08);}
        .btn{width:100%;padding:.75rem;background:linear-gradient(135deg,#1e3a5f,#2d6a4f);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;}
        .btn:hover{opacity:.9;}
        .error{background:#fee2e2;color:#991b1b;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;border:1px solid #fecaca;}
        .info{background:#dbeafe;color:#1e40af;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;border:1px solid #bfdbfe;}
        .email-badge{background:#f1f5f9;padding:.4rem .75rem;border-radius:8px;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:1rem;display:inline-block;}
        .back-link{text-align:center;margin-top:1.25rem;}
        .back-link a{font-size:.8rem;color:#1e40af;text-decoration:none;}
    </style>
</head>
<body>
    <div class="card">
        <h2>Reset Password</h2>
        <p class="sub">Enter the OTP sent to your email and set a new password.</p>

        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        @if(session('message'))<div class="info">{{ session('message') }}</div>@endif

        <div class="email-badge">{{ $email }}</div>

        <form method="POST" action="{{ route('auth.reset-password.update') }}">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">

            <div class="form-group">
                <label>OTP Code</label>
                <input type="text" name="otp" maxlength="6" placeholder="6-digit OTP" required autofocus
                    style="text-align:center;font-size:1.2rem;letter-spacing:.5rem;font-weight:700;">
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" placeholder="Min 6 characters" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" placeholder="Confirm new password" required minlength="6">
            </div>
            <button type="submit" class="btn">Reset Password</button>
        </form>

        <div class="back-link">
            <a href="{{ route('auth.forgot-password') }}">Didn't receive OTP? Send again</a>
        </div>
    </div>
</body>
</html>
