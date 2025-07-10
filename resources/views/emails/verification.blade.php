<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2d3748;">Hello, {{ $merchant->business_name }},</h1>
        <h2 style="color: #2d3748;">Verify Your Email Address</h2>
        <p>Thank you for signing up with Chitlink! Please use the verification code below to verify your email address:</p>

        <div style="background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
            <h1 style="color: #4299e1; letter-spacing: 5px; margin: 0;">{{ $code }}</h1>
        </div>

        <p>This code will expire in 15 minutes.</p>

        @if (!empty($hasUppercase))
            <p style="color: #e53e3e; font-weight: bold;">Note: Your code contains uppercase letter(s).</p>
        @endif

        <p>If you didn't create an account with Chitlink, please ignore this email.</p>

        <p style="margin-top: 30px; font-size: 14px; color: #718096;">
            Best regards,<br>
            The Chitlink Team
        </p>
    </div>
</body>
</html>
