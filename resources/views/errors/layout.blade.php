<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titre') · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #eef1f4; color: #0f172a; }
        main { max-width: 28rem; margin: 15vh auto; padding: 2rem 1.5rem; background: #fff; border-radius: 1rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; }
        p { color: #475569; }
        a { display: inline-block; margin-top: 1rem; min-height: 44px; line-height: 44px; padding: 0 1.25rem; border-radius: .5rem; background: #0e5e6f; color: #fff; font-weight: 600; text-decoration: none; }
        a:focus-visible { outline: 2px solid #0e5e6f; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <h1>@yield('titre')</h1>
        <p>@yield('message')</p>
        <a href="{{ url('/') }}">Retour au fil</a>
    </main>
</body>
</html>
