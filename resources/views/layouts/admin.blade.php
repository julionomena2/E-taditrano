<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin')</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --panel: #ffffff;
            --panel-soft: #eef4ff;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --accent: #10b981;
            --text: #172033;
            --muted: #667085;
            --border: #e5e7eb;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); color: var(--text); }
        .app-shell { display: flex; min-height: 100vh; align-items: stretch; }
        .sidebar {
            width: 260px;
            min-width: 260px;
            height: 100vh;
            position: sticky;
            top: 0;
            align-self: flex-start;
            background: rgba(15, 23, 42, 0.94);
            color: white;
            padding: 24px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .brand-box { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
        .brand-icon { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%); color: white; font-weight: 700; font-size: 1.3rem; box-shadow: 0 18px 35px rgba(37, 99, 235, 0.25); }
        .brand-box h1 { font-size: 1.1rem; margin: 0; }
        .eyebrow { margin: 0; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.72rem; color: var(--muted); }
        .nav-menu { display: flex; flex-direction: column; gap: 8px; }
        .nav-link { text-decoration: none; color: rgba(255,255,255,0.82); background: transparent; padding: 12px 14px; border-radius: 12px; transition: background .2s ease, color .2s ease; }
        .nav-link:hover, .nav-link.active { background: rgba(148,163,184,.12); color: white; }
        .logout-form { margin-top: 16px; }
        .logout-btn, .primary-btn, .secondary-btn, .mini-btn { border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: transform .2s ease, opacity .2s ease; }
        .logout-btn { width: 100%; padding: 12px 14px; background: rgba(239,68,68,.12); color: #fecaca; }
        .main-panel { flex: 1; padding: 32px 36px 48px; min-width: 0; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .user-pill { background: rgba(255,255,255,.85); border: 1px solid rgba(148,163,184,.2); padding: 10px 14px; border-radius: 999px; font-weight: 600; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 18px; margin-bottom: 28px; }
        .stat-card { background: var(--panel); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 24px rgba(15,23,42,.04); padding: 20px; display: flex; flex-direction: column; gap: 8px; }
        .stat-card.accent { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); }
        .stat-card span { color: var(--muted); }
        .stat-card strong { font-size: 2rem; }
        .two-col-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .panel { background: rgba(255,255,255,.8); border: 1px solid var(--border); border-radius: 20px; box-shadow: 0 18px 35px rgba(15,23,42,.04); padding: 24px; }
        .form-panel { margin-bottom: 24px; }
        .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .small-link { color: var(--primary); text-decoration: none; font-weight: 600; }
        .list-stack { display: flex; flex-direction: column; gap: 12px; }
        .mini-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
        .mini-item strong { display: block; }
        .mini-item small { color: var(--muted); }
        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { text-align: left; padding: 14px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: 0.8rem; letter-spacing: 0.04em; text-transform: uppercase; color: var(--muted); }
        .actions-cell { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .mini-btn { padding: 8px 12px; text-decoration: none; font-size: 0.78rem; display: inline-flex; align-items: center; justify-content: center; }
        .mini-btn.edit { background: #e0f2fe; color: #075985; }
        .mini-btn.delete { background: #fee2e2; color: var(--danger-dark); }
        .field-group { display: flex; flex-direction: column; gap: 8px; }
        .field-group label { font-size: 0.9rem; font-weight: 600; }
        .field-group input, .field-group textarea, .field-group select { width: 100%; border: 1px solid var(--border); border-radius: 12px; background: #f8fafc; padding: 12px 14px; font-size: 0.96rem; color: var(--text); transition: border-color .2s ease, box-shadow .2s ease; }
        .field-group input:focus, .field-group textarea:focus, .field-group select:focus { outline: none; border-color: rgba(37,99,235,.7); box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
        .field-group textarea { min-height: 110px; resize: vertical; }
        .resource-form { display: flex; flex-direction: column; gap: 18px; }
        .establishment-map { width: 100%; min-height: 360px; border: 1px solid var(--border); border-radius: 12px; background: #eef2f7; }
        .map-help { color: var(--muted); font-size: .9rem; }
        .map-toggle { align-self: flex-start; }
        .coordinates-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; }
        .form-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .primary-btn { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 12px 18px; }
        .secondary-btn { padding: 12px 16px; background: #eef2ff; color: var(--primary-dark); text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .alert { margin-bottom: 20px; padding: 12px 14px; border-radius: 12px; border: 1px solid transparent; }
        .alert.success { background: #ecfdf5; color: #166534; border-color: #a7f3d0; }
        .alert.error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .empty-text { color: var(--muted); text-align: center; padding: 20px; }
        @media (max-width: 980px) { .app-shell { flex-direction: column; } .sidebar { width: 100%; } .main-panel { padding: 22px 18px 44px; } }
    </style>
    @stack('styles')
</head>
<body class="admin-body">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand-box">
                <div class="brand-icon">e-</div>
                <div>
                    
                    <h1>taditrano</h1>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span>Accueil</span>
                </a>
                <a href="{{ route('admin.utilisateurs') }}" class="nav-link {{ request()->routeIs('admin.utilisateurs') ? 'active' : '' }}">
                    <span>Utilisateurs</span>
                </a>
                <a href="{{ route('admin.villes') }}" class="nav-link {{ request()->routeIs('admin.villes') ? 'active' : '' }}">
                    <span>Villes</span>
                </a>
                <a href="{{ route('admin.quartiers') }}" class="nav-link {{ request()->routeIs('admin.quartiers') ? 'active' : '' }}">
                    <span>Quartiers</span>
                </a>
                <a href="{{ route('admin.logements') }}" class="nav-link {{ request()->routeIs('admin.logements') ? 'active' : '' }}">
                    <span>Logements</span>
                </a>
                <a href="{{ route('admin.etablissements') }}" class="nav-link {{ request()->routeIs('admin.etablissements') ? 'active' : '' }}">
                    <span>Établissements</span>
                </a>
            </nav>

            <form action="{{ route('logout') }}" method="POST" class="logout-form">
                @csrf
                <button type="submit" class="logout-btn">Déconnexion</button>
            </form>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Tableau de bord</p>
                    <h2>@yield('page-title', 'Administration')</h2>
                </div>
                <div class="user-pill">{{ session('admin_name', 'Admin') }}</div>
            </header>

            @if (session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
