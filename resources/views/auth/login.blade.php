<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion admin</title>
    <style>
        :root { --primary: #1b5942; --primary-dark: #123b2e; --text: #20342b; --muted: #718078; --border: #e1e8e1; --shadow: 0 8px 28px rgba(18, 59, 46, 0.08); }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f2f5f1; font-family: "Aptos", "Segoe UI", sans-serif; color: var(--text); padding: 24px; }
        .login-shell { width: min(100%, 440px); }
        .login-card { background: #fff; border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow); padding: 30px; }
        .login-header { display: flex; align-items: center; gap: 18px; margin-bottom: 24px; }
        .brand-icon { width: 64px; height: 64px; border-radius: 10px; display: grid; place-items: center; background: #d8e9db; color: var(--primary-dark); font-family: Georgia, serif; font-weight: 700; font-size: 1.7rem; }
        .eyebrow { margin: 0; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.72rem; color: var(--muted); }
        h1 { margin: 0; }
        .login-form { display: flex; flex-direction: column; gap: 18px; }
        .field-group { display: flex; flex-direction: column; gap: 8px; }
        .field-group label { font-size: 0.9rem; font-weight: 600; }
        .field-group input { width: 100%; border: 1px solid var(--border); border-radius: 6px; background: #fff; padding: 10px 12px; font: inherit; color: var(--text); }
        .field-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27,89,66,.12); }
        .primary-btn { width: 100%; border: none; border-radius: 6px; background: var(--primary-dark); color: white; padding: 10px 18px; font-weight: 600; cursor: pointer; }
        .alert { margin: 0; padding: 12px 14px; border-radius: 12px; border: 1px solid transparent; background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .password-input-wrap { position: relative; display: flex; align-items: center; }
        .password-input-wrap input { padding-right: 48px; }
        .password-toggle { position: absolute; right: 8px; display: grid; width: 34px; height: 34px; place-items: center; padding: 6px; border: 0; border-radius: 6px; background: transparent; color: var(--primary); cursor: pointer; }
        .password-toggle:hover { background: #e5eee7; }
        .password-toggle:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        .password-toggle svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }
    </style>
</head>
<body class="login-body">
    <div class="login-shell">
        <div class="login-card">
            <div class="login-header">
                <div class="brand-icon large">e</div>
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
                    <span class="password-input-wrap">
                        <input id="mot_de_passe" type="password" name="mot_de_passe" placeholder="••••••••" required>
                        <button class="password-toggle" type="button" data-toggle-password="mot_de_passe" aria-label="Afficher le mot de passe" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </span>
                </div>

                @if ($errors->any())
                    <div class="alert error compact">{{ $errors->first() }}</div>
                @endif

                <button type="submit" class="primary-btn full">Se connecter</button>
            </form>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(visible));
                button.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            });
        });
    </script>
</body>
</html>
