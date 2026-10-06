<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion admin</title>
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --text: #172033; --muted: #667085; --border: #e5e7eb; --shadow: 0 18px 45px rgba(15, 23, 42, 0.08); }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: radial-gradient(circle at top, #dbeafe 0%, #eef2ff 30%, #f8fafc 100%); font-family: 'Segoe UI', sans-serif; color: var(--text); padding: 24px; }
        .login-shell { width: min(100%, 440px); }
        .login-card { background: rgba(255,255,255,.82); backdrop-filter: blur(12px); border: 1px solid rgba(148,163,184,.2); border-radius: 24px; box-shadow: var(--shadow); padding: 32px; }
        .login-header { display: flex; align-items: center; gap: 18px; margin-bottom: 24px; }
        .brand-icon { width: 64px; height: 64px; border-radius: 16px; display: grid; place-items: center; background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%); color: white; font-weight: 700; font-size: 1.7rem; box-shadow: 0 18px 35px rgba(37,99,235,.25); }
        .eyebrow { margin: 0; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.72rem; color: var(--muted); }
        h1 { margin: 0; }
        .login-form { display: flex; flex-direction: column; gap: 18px; }
        .field-group { display: flex; flex-direction: column; gap: 8px; }
        .field-group label { font-size: 0.9rem; font-weight: 600; }
        .field-group input { width: 100%; border: 1px solid var(--border); border-radius: 12px; background: #f8fafc; padding: 12px 14px; font-size: 0.96rem; color: var(--text); }
        .field-group input:focus { outline: none; border-color: rgba(37,99,235,.7); box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
        .primary-btn { width: 100%; border: none; border-radius: 12px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 12px 18px; font-weight: 600; cursor: pointer; }
        .alert { margin: 0; padding: 12px 14px; border-radius: 12px; border: 1px solid transparent; background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    </style>
</head>
<body class="login-body">
    <div class="login-shell">
        <div class="login-card">
            <div class="login-header">
                <div class="brand-icon large">L</div>
                <div>
                    <p class="eyebrow">Administration</p>
                    <h1>Connexion</h1>
                </div>
            </div>

            <form action="{{ route('login.submit') }}" method="POST" class="login-form">
                @csrf

                <div class="field-group">
                    <label for="identifiant">Identifiant</label>
                    <input id="identifiant" type="text" name="identifiant" value="{{ old('identifiant') }}" placeholder="admin" required>
                </div>

                <div class="field-group">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input id="mot_de_passe" type="password" name="mot_de_passe" placeholder="••••••••" required>
                </div>

                @if ($errors->any())
                    <div class="alert error compact">{{ $errors->first() }}</div>
                @endif

                <button type="submit" class="primary-btn full">Se connecter</button>
            </form>
        </div>
    </div>
</body>
</html>
