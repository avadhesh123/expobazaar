<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Expo Bazaar SCM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;display:flex;background:#f1f5f9;}
        .login-left{
            flex:1;
              text-align: center;
            /* background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#2d6a4f 100%); */
            background: linear-gradient(135deg,#0f172a,#1e293b,#2563eb);
            
            display:flex;flex-direction:column;justify-content:center;padding:4rem;color:#fff;position:relative;overflow:hidden;
        }
       
        .login-left::before{content:'';position:absolute;top:-50%;right:-30%;width:80%;height:200%;
        /* background:radial-gradient(circle,rgba(232,168,56,.08) 0%,transparent 60%); */        
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
            transform:rotate(25deg);
            pointer-events:none;
        }
        .login-left .logo-area{margin-bottom:2rem;}
        .login-left .logo-area img{
            /* width:225px; height:170px;  */
        border-radius: 5px;}
        /* .login-left p{font-size:1rem; line-height:1.7;max-width:420px;} */
        .login-left .features{margin-top:2.5rem;display:flex;flex-direction:column;gap:.8rem;}
        .login-left .features .feat{display:flex;align-items:center;gap:.8rem;font-size:.85rem;color:#aab8c8;}
        .login-left .features .feat .icon{width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.06);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#e8a838;flex-shrink:0;}
        .login-right{width:480px;display:flex;align-items:center;justify-content:center;padding:3rem;}
        .login-box{width:100%;max-width:360px;}
        .login-box .logo-mobile{display:none;text-align:center;margin-bottom:1.5rem;}
        .login-box .logo-mobile img{height:60px;}
        .login-box h2{font-size:1.5rem;font-weight:800;color:#0f172a;margin-bottom:.3rem;}
        .login-box p.sub{font-size:.85rem;color:#64748b;margin-bottom:2rem;}
        .login-box .form-group{margin-bottom:1.25rem;}
        .login-box label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.35rem;}
        .login-box input{width:100%;padding:.7rem .9rem;border:1.5px solid #d1d5db;border-radius:10px;font-size:.88rem;font-family:inherit;transition:border-color .2s;}
        .login-box input:focus{outline:none;border-color:#1e3a5f;box-shadow:0 0 0 3px rgba(30,58,95,.08);}
        .login-box .btn{width:100%;padding:.75rem;background:linear-gradient(135deg,#0f172a,#2563eb);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;transition:opacity .2s;font-family:inherit;}
        .login-box .btn:hover{opacity:.9;}
        .login-box .error{background:#fee2e2;color:#991b1b;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;border:1px solid #fecaca;}
        .login-box .info{background:#dbeafe;color:#1e40af;padding:.6rem .85rem;border-radius:8px;font-size:.8rem;margin-bottom:1rem;border:1px solid #bfdbfe;}
        h1{    font-size:32px;    font-weight:700;    margin-bottom:10px;}
        .highlight{    color:#93c5fd;}
        .tagline{    font-size:18px;    color:#dbeafe;    margin-bottom:30px;}  
        .description{
            font-size:16px;
            line-height:1.8;
            color:#e2e8f0;
            margin-bottom:35px;
        }
    @media(max-width:900px){.login-left{display:none;}.login-right{width:100%;}.login-box .logo-mobile{display:block;}}
    </style>
</head>
<body>
    <div class="login-left">
        <div class="logo-area">
            <!-- <img src="{{ asset('images/logo.png') }}" alt="ExpoBazaar"> -->
             <img src="//www.expobazaar.com/cdn/shop/files/OPTIMIZE_BACKUP_PRODUCT_eb-logo-mob.svg?v=1749987098&amp;width=400" alt="Expobazaar" width="200" height="77.47747747747748" loading="eager"  style="background: #fff; padding: 5px; border-radius: 5px;"sizes="200px" data-animate="fadein"> 

        </div>
        <h1>Welcome to <span class="highlight">EB Partner Home</span></h1>

        <p class="tagline">
            Your Complete Business Management Solution
        </p>
         <p class="description">
           A comprehensive ERP platform to simplify every facet of business, from sourcing and inventory to cataloging, logistics, sales, finance, and daily operations, all integrated seamlessly in one place. 
             
        </p>
        <!-- <div class="features">
            <div class="feat"><div class="icon">📦</div> End-to-end Sourcing & Consignment Management</div>
            <div class="feat"><div class="icon">🚢</div> Container Planning & Shipment Tracking</div>
            <div class="feat"><div class="icon">📊</div> Multi-platform Sales & Finance Reconciliation</div>
            <div class="feat"><div class="icon">🏪</div> Vendor Portal with Real-time Inventory</div>
            <div class="feat"><div class="icon">💰</div> Automated Payout & Warehouse Cost Allocation</div>
        </div> -->
    </div>
    <div class="login-right">
        <div class="login-box">
            <div class="logo-mobile"><img src="{{ asset('images/logo.jpeg') }}" alt="ExpoBazaar"></div>
            <h2>Welcome back</h2>
            <p class="sub">Sign in with your email. We'll send you a one-time password.</p>
            @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
            @if(session('message'))<div class="info">{{ session('message') }}</div>@endif
            <form method="POST" action="{{ route('auth.request-otp') }}">
                @csrf
                <div class="form-group"><label>Email Address</label><input type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" required autofocus></div>
                <button type="submit" class="btn">Send OTP</button>
            </form>
            <p style="text-align:center;margin-top:1.5rem;font-size:.75rem;color:#94a3b8;">Expo Digital India Pvt Ltd &middot; Secure Login</p>
        </div>
    </div>
</body>
</html>
