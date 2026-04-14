<!DOCTYPE html>
<html><head><style>body{font-family:Arial,sans-serif;background:#f1f5f9;padding:2rem;}.box{background:#fff;border-radius:12px;padding:2rem;max-width:460px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.06);}.otp{font-size:2rem;letter-spacing:.4em;font-weight:800;color:#1e3a5f;text-align:center;padding:1rem;background:#f8fafc;border-radius:8px;margin:1.5rem 0;}</style></head>
<body><div class="box">
    <h2 style="color:#0d1b2a;margin-bottom:.5rem;">Hello, {{ $userName }}</h2>
    <p style="color:#64748b;font-size:.9rem;">Your one-time password for Expo Bazaar SCM login:</p>
    <div class="otp">{{ $otp }}</div>
    <p style="color:#94a3b8;font-size:.8rem;">This OTP expires in 10 minutes. Do not share it with anyone.</p>
    <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;">
    <p style="color:#94a3b8;font-size:.7rem;">Expo Digital India Pvt Ltd</p>
</div></body></html>
