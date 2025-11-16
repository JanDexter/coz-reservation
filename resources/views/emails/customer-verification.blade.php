<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
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
            background: linear-gradient(135deg, #2f4686 0%, #1e3a8a 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .button {
            display: inline-block;
            background: #2f4686;
            color: white !important;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background: #1e3a8a;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .info-box {
            background: #fff;
            border-left: 4px solid #2f4686;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to CO-Z Co-Workspace!</h1>
    </div>
    
    <div class="content">
        <h2>Hello {{ $customer->name }},</h2>
        
        <p>An account has been created for you at CO-Z Co-Workspace by our admin team. To activate your account and access our services, please verify your email address.</p>
        
        <div class="info-box">
            <strong>Account Details:</strong><br>
            Email: {{ $customer->email }}<br>
            Name: {{ $customer->name }}
        </div>
        
        <p>Click the button below to verify your email and activate your account:</p>
        
        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>
        </div>
        
        <p><small>This link will expire in 24 hours.</small></p>
        
        <p>Once your email is verified, you'll be able to:</p>
        <ul>
            <li>Access the customer portal</li>
            <li>View your reservations</li>
            <li>Book spaces and services</li>
            <li>Manage your account</li>
        </ul>
        
        <p>If you didn't expect this email or need assistance, please contact us at <a href="https://www.facebook.com/COZeeNarra" target="_blank">facebook.com/COZeeNarra</a>.</p>
        
        <p>Best regards,<br>
        <strong>CO-Z Co-Workspace Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} CO-Z Co-Workspace. All rights reserved.</p>
    </div>
</body>
</html>
