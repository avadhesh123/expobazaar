<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Expo Bazaar SCM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}body{font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0d1b2a,#1e3a5f);}
        .box{background:#fff;border-radius:16px;padding:2.5rem;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.2);}
        .box .logo{text-align:center;margin-bottom:1.5rem;}
        .box .logo img{width:225px; height:170px;}
        .box h2{font-size:1.4rem;font-weight:800;color:#0d1b2a;margin-bottom:.3rem;}.box p{font-size:.82rem;color:#64748b;margin-bottom:1.5rem;}
        label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.35rem;}
        input{width:100%;padding:.7rem .9rem;border:1.5px solid #d1d5db;border-radius:10px;font-size:1.2rem;letter-spacing:.5em;text-align:center;font-family:inherit;}
        input:focus{outline:none;border-color:#1e3a5f;box-shadow:0 0 0 3px rgba(30,58,95,.08);}
        .btn{width:100%;padding:.75rem;background:linear-gradient(135deg,#1e3a5f,#2d6a4f);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;margin-top:1rem;font-family:inherit;}
        .error{background:#fee2e2;color:#991b1b;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;}
        .back{display:block;text-align:center;margin-top:1rem;font-size:.8rem;color:#64748b;text-decoration:none;}
    </style>
</head>
<body>
    <div class="box">
        <div class="logo"><img src="{{ asset('images/logo.jpeg') }}" alt="ExpoBazaar"></div>
        <h2>Enter OTP</h2>
        <p>We've sent a 6-digit code to <strong>{{ $email }}</strong></p>
        @if(session('debug_otp'))
        <div style="background:#dcfce7;color:#166534;padding:.65rem .85rem;border-radius:8px;font-size:.82rem;margin-bottom:1rem;border:1px solid #bbf7d0;">
            <div style="font-size:.65rem;font-weight:600;color:#64748b;text-transform:uppercase;margin-bottom:.2rem;">🧪 Testing Mode — OTP</div>
            <div style="font-size:1.6rem;font-weight:800;letter-spacing:.4em;text-align:center;color:#166534;font-family:monospace;">{{ session('debug_otp') }}</div>
        </div>
        @endif
        @if(session('message'))<div style="background:#dbeafe;color:#1e40af;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('auth.verify-otp.submit') }}">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <div style="margin-bottom:1rem;"><label>One-Time Password</label><input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" required autofocus placeholder="000000"></div>
            <button type="submit" class="btn">Verify & Sign In</button>
        </form>
        <a href="{{ route('auth.login') }}" class="back">&larr; Back to login</a>
    </div>
</body>
</html>
