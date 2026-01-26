<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="icon" type="image/png" href="/favicon_1/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon_1/favicon.svg" />
    <link rel="shortcut icon" href="/favicon_1/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon_1/apple-touch-icon.png" />
    <link rel="manifest" href="/favicon_1/site.webmanifest" />

</head>
    <body>
        @yield('content')
    </body>
</html>
