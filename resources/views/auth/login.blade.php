<!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FarmFort - Login</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <script>
        (function () {
            var theme = localStorage.getItem('farmflow-theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/farmfort.css') }}?v={{ @filemtime(public_path('css/farmfort.css')) }}">
</head>
<body class="login-page">
    <button type="button" class="theme-toggle login-theme-toggle" id="themeToggle" aria-label="Alternar tema" title="Alternar tema">
        <i class="bi bi-moon-stars"></i>
    </button>

    <main class="login-card">
        <div class="brand">
            <img src="{{ asset('assets/img/farmfort-logo.png') }}" alt="FarmFort" class="brand-icon">
            <div>
                <div class="brand-name">FarmFort</div>
                <div class="brand-sub">ERP Rural</div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-auto">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input class="form-control" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha</label>
                <input class="form-control" type="password" name="senha" autocomplete="current-password" required>
            </div>

            <button class="btn btn-login w-100" type="submit">
                <i class="bi bi-box-arrow-in-right"></i>
                Entrar
            </button>
        </form>

        <div class="login-credit">Sessão persistente até solicitar saída.</div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/farmfort.js') }}?v={{ @filemtime(public_path('js/farmfort.js')) }}"></script>
    <script>
        document.getElementById('themeToggle')?.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('farmflow-theme', next);
        });
    </script>
</body>
</html>
