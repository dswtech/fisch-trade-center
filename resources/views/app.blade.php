<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="dark light">
    <script>(()=>{const t=localStorage.getItem('ftc-theme')||'dark';document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.dataset.theme=t})()</script>
    <title>Fisch Trade Center</title>
    @inertiaHead
    @if (app()->environment('local'))
        <script type="module" src="http://localhost:5173/resources/js/app.tsx"></script>
    @else
        <link rel="stylesheet" href="/build/assets/app.css"><script type="module" src="/build/assets/app.js"></script>
    @endif
</head>
<body>@inertia</body>
</html>
