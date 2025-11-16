<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification OTP</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2f4686;
        }
        .header img {
            max-width: 200px;
            height: auto;
            margin-bottom: 10px;
        }
        .header h1 {
            color: #2f4686;
            margin: 10px 0 0 0;
            font-size: 24px;
        }
        .otp-box {
            background: linear-gradient(135deg, #2f4686 0%, #3956a3 100%);
            color: white;
            font-size: 36px;
            font-weight: bold;
            text-align: center;
            padding: 30px;
            margin: 30px 0;
            border-radius: 8px;
            letter-spacing: 8px;
        }
        .message {
            font-size: 16px;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 14px;
            color: #666;
            text-align: center;
        }
        .highlight {
            color: #2f4686;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://cozcoworkspace.page/build/assets/logo-CALfEbup.png" alt="CO-Z Co-Workspace" />
            <h1>CO-Z Co-Workspace & Study Hub</h1>
            <p style="margin: 5px 0 0 0; color: #666;">Email Verification</p>
        </div>

        <div class="message">
            <p>Hello {{ $user->name }},</p>
            <p>Thank you for registering with CO-Z Co-Workspace! To complete your registration and activate your account, please use the following One-Time Password (OTP):</p>
        </div>

        <div class="otp-box">
            {{ $otp }}
        </div>

        <div class="message">
            <p>Enter this code on the verification page to activate your account.</p>
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>This OTP is valid for <span class="highlight">10 minutes</span></li>
                <li>Do not share this code with anyone</li>
                <li>If you didn't request this, please ignore this email</li>
            </ul>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply.</p>
            <p style="margin-top: 10px;">
                <strong>CO-Z Co-Workspace & Study Hub</strong><br>
                Your productive space awaits!
            </p>
        </div>
    </div>
</body>
</html>
