<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Password Change</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #2f4686 0%, #3956a3 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            background: #2f4686;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background: #3956a3;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-radius: 0 0 8px 8px;
        }
        .alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="https://cozcoworkspace.page/build/assets/logo-CALfEbup.png" alt="CO-Z Co-Workspace" style="max-width: 180px; height: auto; margin-bottom: 10px;" />
        <h1 style="margin: 0;">Password Change Request</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">CO-Z Co-Workspace & Study Hub</p>
    </div>
    
    <div class="content">
        <h2>Hello, {{ $user->name }}!</h2>
        
        <p>We received a request to change your password for your CO-Z account.</p>
        
        <p>To proceed with changing your password, please click the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Change My Password</a>
        </div>
        
        <div class="alert">
            <strong>⏰ Important:</strong> This link will expire in 1 hour for security reasons.
        </div>
        
        <p><strong>If you didn't request this password change</strong>, you can safely ignore this email. Your password will remain unchanged.</p>
        
        <p>For security reasons, if you need help, please contact our support team directly.</p>
        
        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
        
        <p style="font-size: 12px; color: #666;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $verificationUrl }}" style="color: #2f4686; word-break: break-all;">{{ $verificationUrl }}</a>
        </p>
    </div>
    
    <div class="footer">
        <p><strong>CO-Z Co-Workspace & Study Hub</strong></p>
        <p>This is an automated email. Please do not reply to this message.</p>
        <p>&copy; {{ date('Y') }} CO-Z Co-Workspace. All rights reserved.</p>
    </div>
</body>
</html>
