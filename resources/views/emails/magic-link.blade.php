<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Link Anmeldung</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .content {
            padding: 40px 30px;
        }
        .button {
            display: inline-block;
            background: #10b981;
            color: white;
            text-decoration: none;
            padding: 15px 35px;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background: #059669;
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
            font-size: 14px;
            word-break: break-all;
            text-align: center;
            margin: 20px 0;
        }
        .security-info {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #1d4ed8;
        }
        .metadata {
            font-size: 12px;
            color: #6b7280;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">✨ Magic Link</div>
            <h1>Passwortfreie Anmeldung</h1>
        </div>

        <div class="content">
            <p><strong>Hallo {{ $user->name }},</strong></p>

            <p>Sie haben eine passwortfreie Anmeldung für Ihr SSO-Konto angefordert. Klicken Sie auf den folgenden Link, um sich automatisch anzumelden:</p>

            <div style="text-align: center;">
                <a href="{{ $magicUrl }}" class="button">
                    🔗 Jetzt anmelden
                </a>
            </div>

            <p>Oder kopieren Sie diesen Link in Ihren Browser:</p>
            <div class="code">{{ $magicUrl }}</div>

            <div class="security-info">
                <strong>🔒 Sicherheitshinweise:</strong><br>
                • Dieser Link ist nur <strong>10 Minuten</strong> gültig<br>
                • Er kann nur <strong>einmal</strong> verwendet werden<br>
                • Nach der Nutzung wird er automatisch deaktiviert
            </div>

            <div class="warning">
                <strong>Wichtiger Hinweis:</strong><br>
                Falls Sie diese Anmeldung nicht angefordert haben, ignorieren Sie diese E-Mail. Ihr Konto bleibt sicher.
            </div>

            <div class="metadata">
                <strong>Anmelde-Details:</strong><br>
                • IP-Adresse: {{ $ipAddress }}<br>
                • Zeitpunkt: {{ $timestamp }}<br>
                • Browser: {{ $userAgent }}
            </div>

            <p>Bei Fragen kontaktieren Sie uns unter:
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