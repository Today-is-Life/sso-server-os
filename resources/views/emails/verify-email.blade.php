<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail-Adresse bestätigen</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #374151;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9fafb;
        }
        .email-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .content {
            padding: 40px 30px;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .button:hover {
            background: #5a67d8;
        }
        .footer {
            background: #f7fafc;
            padding: 20px 30px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .warning {
            background: #fef3cd;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #92400e;
        }
        .code {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 10px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 16px;
            letter-spacing: 2px;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">🔐 SSO Server</div>
            <h1>E-Mail-Adresse bestätigen</h1>
        </div>

        <div class="content">
            <p><strong>Hallo {{ $user->name }},</strong></p>

            <p>vielen Dank für Ihre Registrierung beim SSO Server. Um Ihr Konto zu aktivieren, müssen Sie Ihre E-Mail-Adresse bestätigen.</p>

            <p>Klicken Sie auf den folgenden Link, um Ihre E-Mail-Adresse zu verifizieren:</p>

            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="button">
                    E-Mail-Adresse bestätigen
                </a>
            </div>

            <p>Oder kopieren Sie diesen Link in Ihren Browser:</p>
            <div class="code">{{ $verificationUrl }}</div>

            <div class="warning">
                <strong>Wichtiger Hinweis:</strong><br>
                Dieser Link ist nur für <strong>24 Stunden</strong> gültig. Falls Sie diese E-Mail nicht angefordert haben, können Sie sie ignorieren.
            </div>

            <p>Falls Sie Probleme haben, kontaktieren Sie uns unter:
                <a href="mailto:support@todayislife.de">support@todayislife.de</a>
            </p>
        </div>

        <div class="footer">
            <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese E-Mail.</p>
            <p>&copy; {{ date('Y') }} Today is Life GmbH, Hamburg</p>
        </div>
    </div>
</body>
</html>