<!DOCTYPE html>
@inject('themeBootScript', 'App\Http\Theme\ThemeBootScript')
<html lang="fr" @class(['dark' => ($page['props']['theme'] ?? null) === \App\Http\Theme\ThemeChoice::Dark->value])>

<head>
    <meta charset="utf-8">
    <script>{!! $themeBootScript->source() !!}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="max-image-preview:large">
    <meta name="content-language" content="fr">

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileColor" content="#0f172a">
    <meta name="theme-color" content="#0b0f1a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f7f4ec" media="(prefers-color-scheme: light)">

    <link rel="preload" href="{{ Vite::asset('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('node_modules/@fontsource/cinzel/files/cinzel-latin-700-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('node_modules/@fontsource/cinzel/files/cinzel-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

    @production
    <script defer src="https://umami.wowplanet.fr/script.js" data-website-id="be8977fd-a0fe-4a4c-867d-d75f83101232"></script>
    @endproduction

    <script src="https://wow.zamimg.com/js/tooltips.js"></script>

    @vite(['resources/css/app.css', 'resources/js/inertia.js'])
    @inertiaHead

    {{-- JSON-LD (données structurées) rendu côté serveur depuis les props de la page. --}}
    @if (! empty($page['props']['meta']['jsonLd']))
    <script type="application/ld+json">{!! $page['props']['meta']['jsonLd'] !!}</script>
    @endif
</head>

<body class="antialiased">
    @inertia
</body>

</html>
