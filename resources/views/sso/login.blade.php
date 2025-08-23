<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSO Login</title>
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
        
        .login-container {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
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
    </style>
</head>
<body>
    <div class="login-container">
        <h1>SSO Login</h1>
        
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
        
        <form action="{{ route('login.post') }}" method="POST">
            @csrf
            
            @if(isset($client_id))
                <input type="hidden" name="client_id" value="{{ $client_id }}">
            @endif
            @if(isset($redirect_uri))
                <input type="hidden" name="redirect_uri" value="{{ $redirect_uri }}">
            @endif
            @if(isset($state))
                <input type="hidden" name="state" value="{{ $state }}">
            @endif
            
            <div class="form-group">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" required autofocus value="{{ old('email') }}">
            </div>
            
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember" value="1">
                    Angemeldet bleiben
                </label>
            </div>
            
            <button type="submit">Anmelden</button>
        </form>
    </div>
</body>
</html>