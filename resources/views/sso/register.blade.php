<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSO Registrierung</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-container {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #2d3748;
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a5568;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #5a67d8;
        }

        .error {
            background: #fed7d7;
            color: #742a2a;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .success {
            background: #c6f6d5;
            color: #22543d;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0 1.5rem;
            color: #a0aec0;
            font-size: 0.875rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            padding: 0 1rem;
        }

        .social-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            color: #2d3748;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .social-btn:hover {
            border-color: #cbd5e0;
            background: #f7fafc;
        }

        .social-btn svg {
            width: 20px;
            height: 20px;
        }

        .alternative-login {
            margin-top: 1.5rem;
            text-align: center;
        }

        .alternative-login a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .alternative-login a:hover {
            text-decoration: underline;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .strength-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s;
        }

        .strength-weak { background: #f56565; width: 25%; }
        .strength-fair { background: #ed8936; width: 50%; }
        .strength-good { background: #38a169; width: 75%; }
        .strength-strong { background: #38a169; width: 100%; }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>SSO Registrierung</h1>

        @if(session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <!-- Social Registration Buttons -->
        @php
            use App\Http\Controllers\SocialLoginController;
            $socialProviders = SocialLoginController::getAvailableProviders();
        @endphp

        @if(count($socialProviders) > 0)
            <div class="social-buttons">
                @foreach($socialProviders as $provider)
                    <a href="{{ route('sso.social', $provider['name']) }}" class="social-btn">
                        <i class="{{ $provider['icon'] }}"></i>
                        <span>Mit {{ $provider['display_name'] }} registrieren</span>
                    </a>
                @endforeach
            </div>

            <div class="divider">
                <span>oder mit E-Mail</span>
            </div>
        @endif

        <form action="{{ route('sso.register.post') }}" method="POST">
            @csrf

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Vorname</label>
                    <input type="text" id="first_name" name="first_name" required autofocus value="{{ old('first_name') }}">
                </div>

                <div class="form-group">
                    <label for="last_name">Nachname</label>
                    <input type="text" id="last_name" name="last_name" required value="{{ old('last_name') }}">
                </div>
            </div>

            <div class="form-group">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" required value="{{ old('email') }}">
            </div>

            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div id="strength-fill" class="strength-fill"></div>
                    </div>
                    <span id="strength-text">Passwort eingeben...</span>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Passwort bestätigen</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="terms" value="1" required>
                    Ich akzeptiere die <a href="#" target="_blank">Nutzungsbedingungen</a> und <a href="#" target="_blank">Datenschutzerklärung</a>
                </label>
            </div>

            <button type="submit">Account erstellen</button>
        </form>

        <!-- Alternative Login Methods -->
        <div class="alternative-login">
            <a href="{{ route('sso.login') }}">Bereits registriert? Anmelden</a>
        </div>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthFill = document.getElementById('strength-fill');
        const strengthText = document.getElementById('strength-text');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);

            strengthFill.className = 'strength-fill ' + strength.class;
            strengthText.textContent = strength.text;
        });

        function checkPasswordStrength(password) {
            if (password.length === 0) {
                return { class: '', text: 'Passwort eingeben...' };
            }

            let score = 0;

            // Length check
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;

            // Character variety
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;

            if (score < 2) {
                return { class: 'strength-weak', text: 'Schwach - Mindestens 8 Zeichen mit Groß-/Kleinbuchstaben' };
            } else if (score < 4) {
                return { class: 'strength-fair', text: 'Mittel - Fügen Sie Zahlen und Sonderzeichen hinzu' };
            } else if (score < 6) {
                return { class: 'strength-good', text: 'Gut - Sicheres Passwort' };
            } else {
                return { class: 'strength-strong', text: 'Sehr stark - Ausgezeichnetes Passwort' };
            }
        }
    </script>
</body>
</html>