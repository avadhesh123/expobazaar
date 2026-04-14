<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Expo Bazaar SCM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;display:flex;background:#f1f5f9;}
        .login-left{flex:1;background:linear-gradient(135deg,#0d1b2a 0%,#1e3a5f 50%,#2d6a4f 100%);display:flex;flex-direction:column;justify-content:center;padding:4rem;color:#fff;position:relative;overflow:hidden;}
        .login-left::before{content:'';position:absolute;top:-50%;right:-30%;width:80%;height:200%;background:radial-gradient(circle,rgba(232,168,56,.08) 0%,transparent 60%);pointer-events:none;}
        .login-left h1{font-size:2.5rem;font-weight:800;margin-bottom:1rem;letter-spacing:-.03em;}
        .login-left h1 span{color:#e8a838;}
        .login-left p{font-size:1rem;color:#8899aa;line-height:1.7;max-width:420px;}
        .login-left .features{margin-top:2.5rem;display:flex;flex-direction:column;gap:.8rem;}
        .login-left .features .feat{display:flex;align-items:center;gap:.8rem;font-size:.85rem;color:#aab8c8;}
        .login-left .features .feat .icon{width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.06);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#e8a838;flex-shrink:0;}
        .login-right{width:480px;display:flex;align-items:center;justify-content:center;padding:3rem;}
        .login-box{width:100%;max-width:360px;}
        .login-box h2{font-size:1.5rem;font-weight:800;color:#0d1b2a;margin-bottom:.3rem;}
        .login-box p.sub{font-size:.85rem;color:#64748b;margin-bottom:2rem;}
        .login-box .form-group{margin-bottom:1.25rem;}
        .login-box label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.35rem;}
        .login-box input{width:100%;padding:.7rem .9rem;border:1.5px solid #d1d5db;border-radius:10px;font-size:.88rem;font-family:inherit;transition:border-color .2s;}
        .login-box input:focus{outline:none;border-color:#1e3a5f;box-shadow:0 0 0 3px rgba(30,58,95,.08);}
        .login-box .btn{width:100%;padding:.75rem;background:linear-gradient(135deg,#1e3a5f,#2d6a4f);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;transition:opacity .2s;font-family:inherit;}
        .login-box .btn:hover{opacity:.9;}
        .login-box .error{background:#fee2e2;color:#991b1b;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;border:1px solid #fecaca;}
        .login-box .info{background:#dbeafe;color:#1e40af;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;border:1px solid #bfdbfe;}
        @media(max-width:900px){.login-left{display:none;}.login-right{width:100%;}}
    </style>
</head>
<body>
    <div class="login-left">
        <h1>Expo<span>Bazaar</span></h1>
        <p>Complete Supply Chain Management for global trade operations across India, USA, and the Netherlands.</p>
        <div class="features">
            <div class="feat"><div class="icon">📦</div> End-to-end Sourcing & Consignment Management</div>
            <div class="feat"><div class="icon">🚢</div> Container Planning & Shipment Tracking</div>
            <div class="feat"><div class="icon">📊</div> Multi-platform Sales & Finance Reconciliation</div>
            <div class="feat"><div class="icon">🏪</div> Vendor Portal with Real-time Inventory</div>
            <div class="feat"><div class="icon">💰</div> Automated Payout & Warehouse Cost Allocation</div>
        </div>
    </div>
    <div class="login-right">
        <div class="login-box">
            <h2>Welcome back</h2>
            <p class="sub">Sign in with your email. We'll send you a one-time password.</p>

            @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
            @if(session('message'))<div class="info">{{ session('message') }}</div>@endif

            <form method="POST" action="{{ route('auth.request-otp') }}">
                @csrf
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" required autofocus>
                </div>
                <button type="submit" class="btn">Send OTP</button>
            </form>

            <p style="text-align:center;margin-top:1.5rem;font-size:.75rem;color:#94a3b8;">Expo Digital India Pvt Ltd &middot; Secure Login</p>
        </div>
    </div>
</body>
</html>
