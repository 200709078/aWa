<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Kelebek') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/js/app.ts', 'resources/css/app.css'])
</head>
<body>
    @inertia
</body>
</html>
