<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TIL SSO Server - Single Sign-On Service</title>
    
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
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4rem;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }
        
        .nav {
            display: flex;
            gap: 2rem;
        }
        
        .nav a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .nav a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4rem 0;
        }
        
        .hero-content {
            max-width: 800px;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
        }
        
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 1rem 2rem;
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            border-radius: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s;
            font-size: 1.1rem;
        }
        
        .btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
        }
        
        .btn-primary:hover {
            background: rgba(255,255,255,0.9);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 4rem;
        }
        
        .feature {
            background: rgba(255,255,255,0.1);
            padding: 2rem;
            border-radius: 1rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .feature-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .feature h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .feature p {
            opacity: 0.9;
            line-height: 1.5;
        }
        
        .footer {
            text-align: center;
            padding: 2rem 0;
            border-top: 1px solid rgba(255,255,255,0.1);
            opacity: 0.7;
            margin-top: 4rem;
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">🔐 TIL SSO Server</div>
            <nav class="nav">
                @auth
                    <a href="{{ route('admin') }}">Dashboard</a>
                    <form action="{{ route('sso.logout') }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" style="background: none; border: none; color: rgba(255,255,255,0.9); cursor: pointer;">Logout</button>
                    </form>
                @else
                    <a href="{{ route('sso.login') }}">Login</a>
                    <a href="{{ route('sso.register') }}">Registrieren</a>
                @endauth
            </nav>
        </header>
        
        <main class="hero">
            <div class="hero-content">
                <h1>Single Sign-On</h1>
                <p>Sicherer, zentraler Authentifizierungsservice für alle TIL42-Anwendungen. Ein Login für alle Dienste - einfach, sicher und benutzerfreundlich.</p>
                
                <div class="buttons">
                    @auth
                        <a href="{{ route('admin') }}" class="btn btn-primary">
                            📊 Admin Dashboard
                        </a>
                        <a href="#" class="btn">
                            👤 Mein Profil
                        </a>
                    @else
                        <a href="{{ route('sso.login') }}" class="btn btn-primary">
                            🚀 Jetzt anmelden
                        </a>
                        <a href="{{ route('sso.register') }}" class="btn">
                            ➕ Account erstellen
                        </a>
                        <a href="{{ route('sso.magic') }}" class="btn">
                            ✨ Magic Link
                        </a>
                    @endauth
                </div>
                
                <div class="features">
                    <div class="feature">
                        <div class="feature-icon">🔒</div>
                        <h3>Sicherheit</h3>
                        <p>Multi-Faktor-Authentifizierung, verschlüsselte Übertragung und sichere Token-Verwaltung für maximalen Schutz.</p>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">⚡</div>
                        <h3>Einfachheit</h3>
                        <p>Ein Login für alle TIL42-Dienste. Magic Links für passwortlose Anmeldung und Social Login für noch mehr Komfort.</p>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">🔄</div>
                        <h3>Integration</h3>
                        <p>OAuth 2.0 und OpenID Connect kompatibel. Nahtlose Integration in bestehende Anwendungen und Services.</p>
                    </div>
                </div>
            </div>
        </main>
        
        <footer class="footer">
            <p>&copy; {{ date('Y') }} TIL42 SSO Server - Secure Single Sign-On Service</p>
        </footer>
    </div>
</body>
</html>